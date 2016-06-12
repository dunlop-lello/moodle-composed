<?php

namespace DunlopLello\Composer\Moodle;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\Loader\InvalidPackageException;
use Composer\Package\Loader\ValidatingArrayLoader;
use Composer\Package\PackageInterface;
use Composer\Repository\ArrayRepository;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\ConfigurableRepositoryInterface;
use Composer\Script\Event;

class MoodlePluginsRepository extends ArrayRepository implements ConfigurableRepositoryInterface
{
    protected $config;
    protected $composer;
    protected $io;
    protected $plugins;

    public function __construct($config, Composer $composer, IOInterface $io)
    {
        $this->config = array_merge(array(
            "docroot" => "docroot",
            "plugins" => array(
                "moodle" => "https://download.moodle.org/api/1.3/pluglist.php"
            ),
            "source" => array(
                "type" => "vcs",
                "url" => "https://github.com/moodle/moodle.git",
                "reference" => "master"
            ),
        ), is_null($config)?array():$config);
        $this->composer = $composer;
        $this->io = $io;
        parent::__construct();
    }

    protected function initialize()
    {
        parent::initialize();
        $plugin_data = SELF::loadPluginList($this->io, $this->composer->getConfig());
        $loader = new ValidatingArrayLoader(new ArrayLoader(null, true), false);
        foreach ($plugin_data as $prefix => $plugins)
        {
            foreach ($plugins as $plugin)
            {
                if (!isset($plugin->component))
                {
                    $plugin->component = 'other_'.str_replace(' ', '_', strtolower($plugin->name));
                }
                $this->io->overwrite("Importing ".$plugin->component, false);
                foreach ($plugin->versions as $version)
                {
                    foreach ($version->supportedmoodles as $supportedmoodle)
                    {
                        $package_data = array();
                        $package_data['name'] = $prefix."/moodle-".$plugin->component;
                        $package_data['version'] = $supportedmoodle->release.".".$version->version;
                        try
                        {
                            $this->packages[] = $loader->load($package_data);
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
        return $this->config;
    }

    protected static function loadPluginList(IOInterface $io, Config $config)
    {
        $cache = Plugin::cacheFileName();
        if (!file_exists($cache))
        {
            SELF::doUpdatePluginList($io, $config);
        }
        return json_decode(file_get_contents($cache));
    }

    protected static function doUpdatePluginList(IOInterface $io, RepositoryInterface $config)
    {
        $io->write("Updating plugin list '".$url."'... ", false);
        $plugin_json = file_get_contents($url);
        if (!empty($plugin_json) && json_decode($plugin_json))
        {
            file_put_contents(SELF::cacheFileName(), $plugin_json);
        }
        $io->write("done");
    }

    public static function updatePluginList(Event $event)
    {
        SELF::doUpdatePluginList($event->getIO(), $event->getComposer()->getRepositories());
    }
}
