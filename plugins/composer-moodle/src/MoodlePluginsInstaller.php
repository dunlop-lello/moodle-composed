<?php

namespace DunlopLello\Composer\Moodle;

use Composer\Composer;
use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;

        define('MOODLE_INTERNAL', true);
        define('IGNORE_COMPONENT_CACHE', true);
        define('CLI_SCRIPT', true);

class MoodlePluginsInstaller extends LibraryInstaller
{
    protected $installerConfig;

    public function __construct($installerConfig, Composer $composer, IOInterface $io)
    {
        $this->installerConfig = $installerConfig;
        parent::__construct($io, $composer);
    }

    /**
     * Decides if the installer supports the given type
     *
     * @param  string $packageType
     * @return bool
     */
    public function supports($packageType)
    {
        print_r(PHP_EOL.__FILE__.":".__LINE__." ".$packageType.PHP_EOL);
        switch ($packageType)
        {
        case "moodle-plugin":
            return true;
        }
        return false;
    }

    /**
     * Returns the installation path of a package
     *
     * @param  PackageInterface $package
     * @return string           path
     */
    public function getInstallPath(PackageInterface $package)
    {
        switch ($package->getType())
        {
        case "moodle-plugin":
            return $this->getPluginPath($package->getName());
            break;
        }
    }

    public function getPluginPath($packageName)
    {
        $parts = array();
        if (preg_match("/^([^\/]+)\/moodle-([a-z]+)_(.+)$/", $packageName, $parts))
        {
            $vendor = $parts[1];
            $type = $parts[2];
            $plugin = $parts[3];

            $pluginTypes = $this->getPluginTypes();
            $path = $pluginTypes[$type].'/'.$plugin;
            return $path;
        }
        else
        {
            throw new Exception("Bad package name $packageName; expected <vendor>/moodle-<frankenstyle>");
        }
    }

    protected function getPluginTypes()
    {
        global $CFG;

        /** Used by library scripts to check they are being called by Moodle */
        $CFG = new \stdClass();
        $CFG->dirroot = realpath("./".$this->installerConfig['docroot']);
        $CFG->libdir = $CFG->dirroot.'/lib';
        $CFG->admin = $CFG->dirroot.'/admin';

        require_once($CFG->libdir.'/classes/component.php');
        require_once($CFG->libdir.'/classes/text.php');
        require_once($CFG->libdir.'/classes/string_manager.php');
        require_once($CFG->libdir.'/classes/string_manager_install.php');
        require_once($CFG->libdir.'/classes/string_manager_standard.php');
        require_once($CFG->libdir.'/installlib.php');
        require_once($CFG->libdir.'/clilib.php');
        require_once($CFG->libdir.'/setuplib.php');
        require_once($CFG->libdir.'/weblib.php');
        require_once($CFG->libdir.'/dmllib.php');
        require_once($CFG->libdir.'/moodlelib.php');
        require_once($CFG->libdir.'/deprecatedlib.php');
        require_once($CFG->libdir.'/adminlib.php');
        require_once($CFG->libdir.'/componentlib.class.php');
        require_once($CFG->dirroot.'/cache/lib.php');

        // Register our classloader, in theory somebody might want to replace it to load other hacked core classes.
        // Required because the database checks below lead to session interaction which is going to lead us to requiring autoloaded classes.
        if (defined('COMPONENT_CLASSLOADER')) {
            spl_autoload_register(COMPONENT_CLASSLOADER);
        } else {
            spl_autoload_register('core_component::classloader');
        }

        $plugins = \core_plugin_manager::instance()->get_plugin_types();

        return $plugins;
    }
}
