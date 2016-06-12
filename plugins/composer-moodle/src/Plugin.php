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

    public function activate(Composer $composer, IOInterface $io)
    {
        if (SELF::$repository == null)
        {
            $config = $composer->getConfig()->get('moodle');
            SELF::$repository = new MoodlePluginsRepository($config, $composer, $io);
            $composer->getRepositoryManager()->addRepository(SELF::$repository);
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            'pre-update-cmd' => 'preUpdateCommand',
        );
    }

    public static function cacheFileName()
    {
        return "moodle-plugins.json";
    }

    public static function preUpdateCommand(Event $event)
    {
        $plugin = new Plugin();
        $plugin->activate($event->getComposer(), $event->getIO());
        $repositoryManager = $event->getComposer()->getRepositoryManager();
        $moodlePlugins = new \stdClass();
        foreach ($repositoryManager->getRepositories() as $repository)
        {
            if ($repository instanceof MoodlePluginsRepository)
            {
                $config = $repository->getRepoConfig();
                foreach ($config['plugins'] as $prefix => $url)
                {
                    $event->getIO()->write("Reading plugin list '".$prefix."' from '".$url."'... ", false);
                    $moodlePlugins->$prefix = json_decode(file_get_contents($url))->plugins;
                    if ($moodlePlugins->$prefix == null)
                    {
                        $event->getIO()->write("failed");
                        return;
                    }
                    $event->getIO()->write("done");
                }
            }
        }
        file_put_contents(SELF::cacheFileName(), json_encode($moodlePlugins));
    }
}
