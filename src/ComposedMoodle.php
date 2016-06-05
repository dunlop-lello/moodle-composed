<?php

namespace DunlopLello;

use Composer\Script\Event;
use Composer\Installer\PackageEvent;

class ComposedMoodle {
    const DOCROOT=__DIR__."/../docroot";
    const VENDORSDIR=__DIR__."/../vendor";

    protected static function isPluginDir($dir)
    {
        return file_exists("$dir/version.php");
    }

    protected static function scanPlugins($vendordir, $subdir = "")
    {
        $result = array();
        if (!empty($subdir) && SELF::isPluginDir("$vendordir/$subdir"))
        {
            $result[$subdir] = "$vendordir/$subdir";
        } else {
            $dh = opendir("$vendordir/$subdir");
            while (false !== ($entry = readdir($dh)))
            {
                if (substr($entry, 0, 1) == ".")
                {
                    continue;
                }
                if (!is_dir("$vendordir/$subdir/$entry"))
                {
                    continue;
                }
                if (!empty($subdir)) {
                    $entry = "$subdir/$entry";
                }
                $result += SELF::scanPlugins($vendordir, $entry);
            }
            closedir($dh);
        }
        return $result;
    }

    protected static function vendorPlugins()
    {
        $plugins = array();
        $dirs = glob(SELF::VENDORSDIR."/*/*");
        foreach ($dirs as $dir)
        {
            if (!is_dir($dir))
            {
                continue;
            }
            if ($dir == SELF::VENDORSDIR."/moodle/moodle")
            {
                continue;
            }
            $plugins[$dir] = SELF::scanPlugins($dir);
        }
        return $plugins;
    }

    protected static function scanForVendorLinks($dir)
    {
        $result = array();
        if (!is_dir($dir)) {
            return $result;
        }
        $dh = opendir($dir);
        while (false !== ($entry = readdir($dh)))
        {
            if (substr($entry, 0, 1) == ".")
            {
                continue;
            }
            $entry = "$dir/$entry";
            if (false === is_link($entry))
            {
                if (is_dir($entry)) {
                    $result += SELF::scanForVendorLinks($entry);
                }
                continue;
            }
            $target = readlink($entry);
            if (substr($target, 0, strlen(SELF::VENDORSDIR)) == SELF::VENDORSDIR)
            {
                $result[$entry] = $target;
            }
        }
        closedir($dh);
        return $result;
    }

    public static function preInstall(Event $event)
    {
        SELF::preUpdate($event);
    }

    public static function preUpdate(Event $event)
    {
        $links = SELF::scanForVendorLinks(SELF::DOCROOT);
        foreach ($links as $link => $target)
        {
            unlink($link);
        }

        $vendors = SELF::vendorPlugins();
        foreach ($vendors as $vendordir => $plugins)
        {
            if (is_link($vendordir.'/config.php'))
            {
                unlink($vendordir.'/config.php');
            }
        }
    }

    public static function postInstall(Event $event)
    {
        SELF::postUpdate($event);
    }

    public static function postUpdate(Event $event)
    {
        $vendors = SELF::vendorPlugins();
        foreach ($vendors as $vendordir => $plugins)
        {
            symlink(SELF::DOCROOT.'/config.php', $vendordir.'/config.php');
            foreach ($plugins as $plugin => $vendorplugindir)
            {
                if (symlink($vendorplugindir, SELF::DOCROOT.'/'.$plugin) === false)
                {
                    echo "symlink('$vendorplugindir', '".SELF::DOCROOT."/$plugin') failed.".PHP_EOL;
                }
            }
        }
    }
}
