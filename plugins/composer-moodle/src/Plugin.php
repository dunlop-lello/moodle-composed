<?php

namespace DunlopLello\Composer\Moodle;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Script\Event;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {
        $repository = new MoodlePluginsRepository($composer, $io);
        $composer->getRepositoryManager()->addRepository($repository);
    }

    public static function getSubscribedEvents()
    {
        return array(
            'pre-update-cmd' => 'preUpdateCommand',
        );
    }

    public static function preUpdateCommand(Event $event)
    {
        MoodlePluginsRepository::updatePluginList($event);
    }
}
