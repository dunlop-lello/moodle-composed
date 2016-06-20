<?php

namespace DunlopLello\Composer\Moodle;

use Composer\Composer;
use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;

class MoodleInstaller extends LibraryInstaller
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
        case "moodle-core":
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
        case "moodle-core":
            return $this->installerConfig['docroot'];
            break;
        }
    }
}
