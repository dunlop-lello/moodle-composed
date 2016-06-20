<?php

namespace DunlopLello\Composer\Moodle;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Script\Event;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    protected static $repository;
    protected static $installer;
    protected static $pluginInstaller;

    public function activate(Composer $composer, IOInterface $io)
    {
        $config = $composer->getConfig()->get('moodle');
        $config = array_replace_recursive(array(
            "docroot" => "docroot",
            "plugins" => array(
                "moodle" => "https://download.moodle.org/api/1.3/pluglist.php"
            ),
            "source" => array(
                "url" => "https://github.com/moodle/moodle.git",
            ),
            "options" => array(),
        ), is_null($config)?array():$config);
        if (SELF::$repository == null)
        {
            SELF::$repository = new MoodlePluginsRepository($config, $composer, $io);
            $composer->getRepositoryManager()->addRepository(SELF::$repository);
        }
        if (SELF::$installer == null)
        {
            SELF::$installer = new MoodleInstaller($config, $composer, $io);
            $composer->getInstallationManager()->addInstaller(SELF::$installer);
        }
        if (SELF::$pluginInstaller == null)
        {
            SELF::$pluginInstaller = new MoodlePluginsInstaller($config, $composer, $io);
            $composer->getInstallationManager()->addInstaller(SELF::$pluginInstaller);
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
        );
    }
}
