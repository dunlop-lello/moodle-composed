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
use Composer\Script\Event;

class MoodlePluginsRepository extends ArrayRepository
{
    protected $io;
    protected $composer;
    protected $plugins;

    public function __construct(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
        parent::__construct();
    }

    protected function initialize()
    {
        parent::initialize();
        $url = "https://download.moodle.org/api/1.3/pluglist.php";
        $plugin_data = SELF::loadPluginList($this->io, $this->composer->getConfig(), $url);
        $loader = new ValidatingArrayLoader(new ArrayLoader(null, true), false);
        foreach ($plugin_data->plugins as $plugin)
        {
            $this->io->overwrite("Importing ".$plugin->component, false);
            foreach ($plugin->versions as $version)
            {
                foreach ($version->supportedmoodles as $supportedmoodle)
                {
                    $package_data = array();
                    $package_data['name'] = "moodle/moodle_".$supportedmoodle->release."_".$plugin->component;
                    $package_data['version'] = $version->version;
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
        $this->io->write("");
    }

    protected static function cacheFileName(Config $config, $url)
    {
        return "moodle-plugins.json";
    }

    protected static function loadPluginList(IOInterface $io, Config $config, $url)
    {
        $cache = SELF::cacheFileName($config, $url);
        if (!file_exists($cache))
        {
            SELF::doUpdatePluginList($io, $config, $url);
        }
        return json_decode(file_get_contents($cache));
    }

    protected static function doUpdatePluginList(IOInterface $io, Config $config, $url)
    {
        $io->write("Updating plugin list '".$url."'... ", false);
        $plugin_json = file_get_contents($url);
        if (!empty($plugin_json) && json_decode($plugin_json))
        {
            file_put_contents(SELF::cacheFileName($config, $url), $plugin_json);
        }
        $io->write("done");
    }

    public static function updatePluginList(Event $event)
    {
        $url = "https://download.moodle.org/api/1.3/pluglist.php";
        SELF::doUpdatePluginList($event->getIO(), $event->getComposer()->getConfig(), $url);
    }
}
