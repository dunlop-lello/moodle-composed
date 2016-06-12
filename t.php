<?php

// make sure PHP errors are displayed - helps with diagnosing of problems
@error_reporting(E_ALL);
@ini_set('display_errors', '1');

/** Used by library scripts to check they are being called by Moodle */
define('MOODLE_INTERNAL', true);
define('IGNORE_COMPONENT_CACHE', true);
define('CLI_SCRIPT', true);

$CFG = new stdClass();
$CFG->dirroot = 'docroot';
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

$plugins = core_plugin_manager::instance()->get_plugin_types();
$plugins = array_flip($plugins);
krsort($plugins);
print_r($plugins);

