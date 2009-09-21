<?php

// -------------------------------------------------------------
// messages that will be available throughout this plugin
function glz_custom_fields_gTxt($get, $atts = array()) {
  $lang = array(
    'no_name'           => 'Ooops! <strong>custom set</strong> must have a name',
    'deleted'           => '<strong>{custom_set_name}</strong> was deleted',
    'reset'             => '<strong>{custom_set_name}</strong> was reset',
    'created'           => '<strong>{custom_set_name}</strong> was created',
    'updated'           => '<strong>{custom_set_name}</strong> was updated',
    'exists'            => 'Ooops! <strong>{custom_set_name}</strong> already exists',
    'doesnt_exist'      => 'Ooops! <strong>{custom_set_name}</strong> is not set',
    'field_problems'    => 'Ooops! <strong>{custom_set_name}</strong> has some problems. <a href="?event=glz_custom_fields">Go fix it</a>.',
    'custom_set'        => 'Text Input', # custom sets in TXP 4.2.0 are by type custom_set by default...
    'text_input'        => 'Text Input',
    'select'            => 'Select',
    'multi-select'      => 'Multi-Select',
    'textarea'          => 'Textarea',
    'checkbox'          => 'Checkbox',
    'radio'             => 'Radio',
    'date-picker'       => 'Date Picker',
    'type_not_supported'=> 'Type not supported',
    'no_do'             => 'Ooops! No action specified for method, abort.',
    'not_specified'     => 'Ooops! {what} is not specified',
    'searchby_not_set'  => '<strong>searcby</strong> cannot be left blank',
    'jquery_missing'    => 'Upgrade TXP to at least 4.0.5 or put <strong>jquery.js</strong> in your /textpattern folder. <a href="http://jquery.com" title="jQuery website">jQuery website</a>',
    'check_path'        => 'Make sure all your paths are correct. Check <strong>config.php</strong> and the Admin tab (mainly Advanced).',
    'no_articles_found' => 'No articles with custom fields have been found.',
    'migration_success' => 'Migrating custom fields was successful',
    'migration_skip'    => '<strong>custom_fields</strong> table already has data in it, migration skipped.',
    'search_section_created'  => '<strong>search</strong> section has been created',
    'custom_sets_all_input'   => 'All custom sets have been set back to input'
  );

  $out = ( strstr($lang[$get], "Ooops!") ) ? // Ooops! would appear 0 in the string...
      "<span class=\"red\">{$lang[$get]}</span>" :
      $lang[$get];

  return strtr($out, $atts);
}

?>

