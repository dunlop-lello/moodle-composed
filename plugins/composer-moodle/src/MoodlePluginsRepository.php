<?php

namespace DunlopLello\Composer\Moodle;

use Composer\Cache;
use Composer\Composer;
use Composer\Config;
use Composer\Downloader\TransportException;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\Loader\InvalidPackageException;
use Composer\Package\Loader\ValidatingArrayLoader;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Repository\ArrayRepository;
use Composer\Repository\ConfigurableRepositoryInterface;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\VcsRepository;
use Composer\Script\Event;

class MoodlePluginsRepository extends ArrayRepository implements ConfigurableRepositoryInterface
{
    protected $repoConfig;
    protected $composer;
    protected $io;
    protected $plugins;
    protected $cache;
    protected $eventDispatcher;
    protected $degradedMode;

    public function __construct($repoConfig, Composer $composer, IOInterface $io)
    {
        $this->repoConfig = $repoConfig;
        $this->composer = $composer;
        $this->io = $io;
        $this->config = $composer->getConfig();
        $this->cache = new Cache($io, $this->config->get('cache-dir').'/moodle');
        $this->rfs = Factory::createRemoteFilesystem($this->io, $this->config, $this->repoConfig['options']);
        parent::__construct();
    }

    protected function fetchFile($filename, $cacheKey = null, $sha256 = null, $storeLastModifiedTime = false)
    {
        if (null === $cacheKey) {
            $cacheKey = $filename;
        }

        // url-encode $ signs in URLs as bad proxies choke on them
        if (($pos = strpos($filename, '$')) && preg_match('{^https?://.*}i', $filename)) {
            $filename = substr($filename, 0, $pos) . '%24' . substr($filename, $pos + 1);
        }

        $retries = 3;
        while ($retries--) {
            try {
                $preFileDownloadEvent = new PreFileDownloadEvent(PluginEvents::PRE_FILE_DOWNLOAD, $this->rfs, $filename);
                if ($this->eventDispatcher) {
                    $this->eventDispatcher->dispatch($preFileDownloadEvent->getName(), $preFileDownloadEvent);
                }

                $hostname = parse_url($filename, PHP_URL_HOST) ?: $filename;
                $rfs = $preFileDownloadEvent->getRemoteFilesystem();
                $data = $rfs->getContents($hostname, $filename, false);
                if ($sha256 && $sha256 !== hash('sha256', $json)) {
                    if ($retries) {
                        usleep(100000);

                        continue;
                    }

                    // TODO use scarier wording once we know for sure it doesn't do false positives anymore
                    throw new RepositorySecurityException('The contents of '.$filename.' do not match its signature. This should indicate a man-in-the-middle attack. Try running composer again and report this if you think it is a mistake.');
                }

                if ($cacheKey) {
                    if ($storeLastModifiedTime) {
                        $lastModifiedDate = $rfs->findHeaderValue($rfs->getLastHeaders(), 'last-modified');
                        if ($lastModifiedDate) {
                        }
                    }
                    $this->cache->write($cacheKey, $data);
                }

                break;
            } catch (\Exception $e) {
                if ($e instanceof TransportException && $e->getStatusCode() === 404) {
                    throw $e;
                }

                if ($retries) {
                    usleep(100000);
                    continue;
                }

                if ($e instanceof RepositorySecurityException) {
                    throw $e;
                }

                if ($cacheKey && ($contents = $this->cache->read($cacheKey))) {
                    if (!$this->degradedMode) {
                        $this->io->writeError('<warning>'.$e->getMessage().'</warning>');
                        $this->io->writeError('<warning>'.$filename.' could not be fully loaded, package information was loaded from the local cache and may be out of date</warning>');
                    }
                    $this->degradedMode = true;
                    $data = $this->cache->read($cacheKey);
                    break;
                }

                throw $e;
            }
        }
        return $data;
    }


    protected function initialize()
    {
        parent::initialize();
        $plugin_data = SELF::loadPluginList($this->io, $this->composer->getConfig());
        $loader = new ValidatingArrayLoader(new ArrayLoader(null, true), false);

        // Create moodle package.
        $repository = new VcsRepository($this->repoConfig['source'], $this->io, $this->composer->getConfig());
        try {
            $driver = $repository->getDriver();
        }
        catch (TransportException $e)
        {
            if ($e->getStatusCode() == 404)
            {
                $driver = null;
            }
            else
            {
                throw $e;
            }
        }
        if (!is_null($driver))
        {
            foreach (array_merge($driver->getTags(), $driver->getBranches()) as $hash => $comment)
            {
                $package_data = array();
                $package_data['type'] = 'moodle-core';
                $package_data['name'] = 'moodle/moodle';
                $package_data['version'] = $hash;
                $package_data['source'] = array(
                    'type' => (isset($this->repoConfig['source']['type'])?$this->repoConfig['source']['type']:'vcs'),
                    'url' => $this->repoConfig['source']['url'],
                    'reference' => $hash,
                );
                try
                {
                    $package = $loader->load($package_data);
                    $package->setRepository($this);
                    $this->packages[] = $package;
                }
                catch (InvalidPackageException $ex)
                {
                    $this->io->write($ex->getMessage(), true, IOInterface::VERBOSE);
                }
            }
        }
        foreach ($plugin_data as $prefix => $plugins)
        {
            foreach ($plugins as $plugin)
            {
                if (!isset($plugin->component))
                {
                    //$plugin->component = 'other_'.str_replace(' ', '_', strtolower($plugin->name));
                    continue;
                }
                $this->io->overwrite("Importing ".$plugin->component, false);

                // First build the list of source versions.
                $vcsrepositories = array();
                foreach ($plugin->versions as $version)
                {
                    if ($version->vcsrepositoryurl != null)
                    {
                        if (preg_match("/^[^:]*:\/\/github.com\//", $version->vcsrepositoryurl))
                        {
                            if (!preg_match("/^([^:]*:\/\/github.com)\/([^\/]+)\/([^\/]+)(|\/)$/", $version->vcsrepositoryurl))
                            {
                                $version->vcssystem = "bad";
                            }
                        }
                        $vcsrepositories[$version->vcsrepositoryurl] = is_null($version->vcssystem)?"vcs":$version->vcssystem;
                    }
                }
                foreach ($vcsrepositories as $repo => $type)
                {
                    if ($type == "bad")
                    {
                        $this->io->writeError("Skipping bad repo $repo");
                        continue;
                    }

                    /*
                    $repoConfig = array(
                        //"type" => $type,
                        "url" => $repo,
                    );
                    $repository = new VcsRepository($repoConfig, $this->io, $this->composer->getConfig());
                    try {
                        $driver = $repository->getDriver();
                    }
                    catch (TransportException $e)
                    {
                        if ($e->getStatusCode() == 404)
                        {
                            $driver = null;
                        }
                        else
                        {
                            throw $e;
                        }
                    }
                    if (is_null($driver))
                    {
                        continue;
                    }
                    foreach (array_merge($driver->getTags(), $driver->getBranches()) as $hash => $comment)
                    {
                        $package_data = array();
                        $package_data['name'] = $prefix."/moodle-".$plugin->component;
                        $package_data['version'] = $hash;
                        $package_data['source'] = array(
                            'type' => 'vcs',
                            'url' => $repo,
                            'reference' => $hash,
                        );
                    }
                     */
                }

                // Now build the list of dist versions.
                foreach ($plugin->versions as $version)
                {
                    foreach ($version->supportedmoodles as $supportedmoodle)
                    {
                        $package_data = array();
                        $package_data['type'] = 'moodle-plugin';
                        $package_data['name'] = $prefix."/moodle-".$plugin->component;
                        $package_data['version'] = $supportedmoodle->release.".".$version->version;
                        $package_data['dist'] = array(
                            'type' => 'zip',
                            'url' => $version->downloadurl,
                        );

                        try
                        {
                            $package = $loader->load($package_data);
                            $package->setRepository($this);
                            $this->packages[] = $package;
                        }
                        catch (InvalidPackageException $ex)
                        {
                            $this->io->write($ex->getMessage(), true, IOInterface::VERBOSE);
                        }
                    }
                }
            }
        }
        $this->io->write("");
    }

    public function getRepoConfig()
    {
        return $this->repoConfig;
    }

    protected function loadPluginList()
    {
        $moodlePlugins = new \stdClass();
        foreach ($this->repoConfig['plugins'] as $prefix => $url)
        {
            $json = $this->fetchFile($url);
            $moodlePlugins->$prefix = json_decode($json)->plugins;
        }
        return $moodlePlugins;
    }
}
