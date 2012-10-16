<?php

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

$version = isset($_ENV["PLUGIN_VERSION"]) ? $_ENV["PLUGIN_VERSION"] : "1.4.0";

$plugin['code_file']    = realpath('glz_custom_fields_code.php');
$plugin['help_file']    = realpath('glz_custom_fields_help.html');
$plugin['version']      = $version;
$plugin['description']  = "Unlimited, super special custom fields.";
$plugin['author']       = "Gerhard Lazu";
$plugin['author_uri']   = "http://gerhardlazu.com";
$plugin['contributors'] = "Randy Levine, Sam Weiss, Luca Botti, Manfre, Vladimir Siljkovic, Julian Reisenberger, Steve Dickinson, Stef Dawson, Jean-Pol Dupont";
$plugin['compatibility'] = "4.5.1";

// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment spam evaluators or URL redirectors
// would probably want to run earlier (1...4) to prepare the environment for everything else that follows.
// Orders 6...9 should be considered for plugins which would work late. This order is user-overrideable.
$plugin['order'] = '9';

// Plugin 'type' defines where the plugin is loaded
// http://forum.textpattern.com/viewtopic.php?id=38434
// -----------------------------------------------------------------------
// | plugin type | public | admin (synchronous) | library | admin (ajax) |
// -----------------------------------------------------------------------
// | 0           | yes    |                     |         |              |
// | 1           | yes    | yes                 |         |              |
// | 2           |        |                     | yes     |              |
// | 3           |        | yes                 |         |              |
// | 4           |        | yes                 |         | yes          |
// | 5           | yes    | yes                 |         | yes          |
// -----------------------------------------------------------------------
$plugin['type'] = '5';

// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML. Not recommended.
$plugin['allow_html_help'] = 1;

// Plugin 'flags' signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use.
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = PLUGIN_HAS_PREFS | PLUGIN_LIFECYCLE_NOTIFY;

if (!defined('txpinterface'))
  include_once('.txp_plugin_template/zem_tpl.php');

?>
