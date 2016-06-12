<?php

namespace DunlopLello\MoodleComposed;

use Composer\Script\Event;

class Hooks {
    const DOCROOT=__DIR__."/../vendor/moodle/moodle";
    const VENDORSDIR=__DIR__."/../vendor";

    protected static function isPluginDir($dir)
    {
        return file_exists("$dir/version.php");
    }

    protected static function plugin($dir)
    {
        $plugin = new stdClass();
        include("$dir/version.php");
        $parts = explode("_", $plugin->component, 2);
        return array($parts[1] => $parts[0]);
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
            if ($dir == SELF::DOCROOT)
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
        $vendors = SELF::vendorPlugins();
        foreach ($vendors as $vendordir => $plugins)
        {
            foreach ($plugins as $plugin => $vendorplugindir)
            {
                if (!is_link(SELF::DOCROOT.'/'.$plugin))
                {
                    symlink($vendorplugindir, SELF::DOCROOT.'/'.$plugin);
                }
            }
        }
    }

    public static function postUpdate(Event $event)
    {
    }
}
