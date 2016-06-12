<?php

namespace DunlopLello\MoodleComposed;

use Composer\Script\Event;
use Composer\Package\PackageInterface;

class Commands {
    protected static function resolvePackage($repositoryManager, $packageName)
    {
        $repository = $repositoryManager->getLocalRepository();
        foreach ($repository->getPackages() as $package)
        {
            if (
                $package->getName() == $packageName
             && $package->getSourceType() == "git"
            )
            {
                return $package;
            }
        }
        return null;
    }

    public static function composify(Event $event)
    {
        $args = $event->getArguments();
        $repositoryManager = $event->getComposer()->getRepositoryManager();
        $package = null;
        switch (count($args))
        {
            case 1:
                $package = self::resolvePackage($repositoryManager, $args[0]);
                break;
            default:
                break;
        }

        // Show help if something was bad with the arguments.
        $showHelp = !($package instanceof PackageInterface);
        if ($showHelp)
        {
            echo "Usage: composer ".$event->getName()." <package>".PHP_EOL;
            return;
        }
        var_dump($package->getSourceUrl());
        var_dump($package->getUpstreamUrl());
        $repository = $repositoryManager->createRepository(
            $package->getSourceType(),
            array(
                'url' => $package->getSourceUrl(),
            )
        );
        var_dump(get_class($repository->getDriver()));
    }
}
