<?php

// Either copy classTextile.php to your plugin directory, or uncomment the following
// line and edit it to give the location where classTextile.php can be found
#ini_set('include_path', ini_get('include_path') . ':/full/path/to/textile');

exit(compile_plugin());

// -----------------------------------------------------

function strip_php_tags($input) {
  return preg_replace("/\<\?php|\?\>/", "", $input);
}

function fetch_external_file($matches) {
  return strip_php_tags(file_get_contents($matches[1]));
}

function build_file($file) {
  $out = strip_php_tags(file_get_contents($file));

  if (empty($out))
    exit("Make sure $file exists");

  // let's load the content of external files into our plugin file
  $regex = "/require_once\(\'(.+)\'\)\;/";
  $out = preg_replace_callback($regex, "fetch_external_file", $out);

  return rtrim($out);
}

function compile_plugin() {
  global $plugin;

  if (!isset($plugin['name']))
		$plugin['name'] = basename($_SERVER['SCRIPT_FILENAME'], '.php');

  $plugin['help'] = build_file($plugin['help_file']);
  $plugin['code'] = build_file($plugin['code_file']);

  // textpattern will textile it, and encode html
  $plugin['help_raw'] = $plugin['help'];

  // only do the Textile thing if help not already in HTML
  if ($plugin['allow_html_help'] == 0) {
    // This is for bc; and for help that needs to use
    @include('classTextile.php');
    if (class_exists('Textile')) {
      $textile = new Textile();
      $plugin['help'] = $textile->TextileThis($plugin['help']);
    }
  }

  $plugin_information = array("\n# {$plugin['name']} v{$plugin['version']}", "# {$plugin['description']}", "#");
  $plugin_information[] = "# {$plugin['author']}";
  $plugin_information[] = "# {$plugin['author_uri']}";
  // Optional values, not all plugins will have them
  if ($plugin['contributors'] || $plugin['compatibility'])
    $plugin_information[] = "#";
  if ($plugin['contributors'])
    $plugin_information[] = "# Contributors: {$plugin['contributors']}";
  if ($plugin['compatibility'])
    $plugin_information[] = "# Minimum requirements: Textpattern {$plugin['compatibility']}";

  $plugin_information = join($plugin_information, "\n");

  $plugin['code'] = $plugin_information.$plugin['code'];

  $plugin['md5'] = md5($plugin['code']);

  $header = <<<EOF
$plugin_information

# ......................................................................
# This is a plugin for Textpattern - http://textpattern.com/
# To install: textpattern > admin > plugins
# Paste the following text into the 'Install plugin' box:
# ......................................................................
EOF;

  $body = trim(chunk_split(base64_encode(gzencode(serialize($plugin))), 72));

  // to produce a copy of the plugin for distribution, load this file in a browser.
  header('Content-type: text/plain');

  return $header."\n\n".$body;
}

?>

