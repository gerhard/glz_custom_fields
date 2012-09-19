<?php

// Including helper files. If we can't have classes, we will use includes
require_once('lib/gTxt.php');
require_once('lib/db.php');
require_once('lib/helpers.php');
require_once('lib/callbacks.php');

global $event;

// globals, expensive operations mostly
before_glz_custom_fields();

if (@txpinterface == "admin") {

  // INSTALL ROUTINES
  // checks if all tables exist and everything is setup properly
  add_privs('glz_custom_fields_install', "1");
  register_callback("glz_custom_fields_install", "plugin_lifecycle.glz_custom_fields", "installed");

  // we'll be doing this only on the pages that we care about, not everywhere
  if ( in_array($event, array("article", "prefs", "glz_custom_fields", "plugin_prefs.glz_custom_fields")) ) {
    // we need some stylesheets & JS
    add_privs('glz_custom_fields_css_js', "1,2,3,4,5,6");
    register_callback('glz_custom_fields_css_js', "admin_side", 'head_end');

    // we need to make sure that all custom field values will be converted to strings first - think checkboxes & multi-selects etc.
    if ( (gps("step") == "edit") || (gps("step") == "create") ) {
      add_privs('glz_custom_fields_before_save', "1,2,3,4,5,6");
      register_callback('glz_custom_fields_before_save', "article", '', 1);
    }
  }

  // Custom Fields tab under Extensions
  add_privs('glz_custom_fields', "1,2");
  register_tab("extensions", 'glz_custom_fields', "Custom Fields");
  register_callback('glz_custom_fields', "glz_custom_fields");

  // plugin preferences
  add_privs('plugin_prefs.glz_custom_fields', "1,2");
  register_callback('glz_custom_fields_preferences', 'plugin_prefs.glz_custom_fields');


  // YES, finally the default custom fields are replaced by the new, pimped ones : )
  add_privs('glz_custom_fields_replace', "1,2,3,4,5,6");
  register_callback('glz_custom_fields_replace', 'article_ui', 'custom_fields');
  // YES, now we have textarea custom fields as well ; )
  register_callback('glz_custom_fields_replace', 'article_ui', 'body');
}

// -------------------------------------------------------------
// everything is happening in this function... generates the content for Extensions > Custom Fields
function glz_custom_fields() {
  global $event, $all_custom_sets, $glz_notice, $prefs;

  // we have $_POST, let's see if there is any CRUD
  if ( $_POST ) {
    $incoming = stripPost();
    // DEBUG
    // die(dmp($incoming));
    extract($incoming);

    // create an empty $value if it's not set in the $_POST
    if ( !isset($value) )
      $value = '';

    // we are deleting a new custom field
    if ( gps('delete') ) {
      glz_custom_fields_MySQL("delete", $custom_set, PFX."txp_prefs");
      glz_custom_fields_MySQL("delete", $custom_set, PFX."txp_lang");
      glz_custom_fields_MySQL("delete", $custom_set, PFX."custom_fields");

      glz_custom_fields_MySQL("delete", glz_custom_number($custom_set), PFX."textpattern");

      $glz_notice[] = glz_custom_fields_gTxt("deleted", array('{custom_set_name}' => $custom_set_name));
    }

    // we are resetting one of the mighty 10
    if ( gps('reset') ) {
      glz_custom_fields_MySQL("reset", $custom_set, PFX."txp_prefs");
      glz_custom_fields_MySQL("delete", $custom_set, PFX."custom_fields");

      glz_custom_fields_MySQL("reset", glz_custom_number($custom_set), PFX."textpattern", array(
        'custom_set_type' => $custom_set_type,
        'custom_field' => glz_custom_number($custom_set)
      ));

      $glz_notice[] = glz_custom_fields_gTxt("reset", array('{custom_set_name}' => $custom_set_name));
    }

    // we are adding a new custom field
    if ( gps("custom_field_number") ) {
      $custom_set_name = gps("custom_set_name");

      // if no name was specified, abort
      if ( !$custom_set_name )
        $glz_notice[] = glz_custom_fields_gTxt("no_name");
      else {
        $custom_set_name = glz_clean_string($custom_set_name);

        $name_exists = glz_check_custom_set_name($all_custom_sets, $custom_set_name);

        // if name doesn't exist
        if ( $name_exists == FALSE ) {
          glz_custom_fields_MySQL("new", $custom_set_name, PFX."txp_prefs", array(
            'custom_field_number' => $custom_field_number,
            'custom_set_type'     => $custom_set_type,
            'custom_set_position' => $custom_set_position
          ));
          glz_custom_fields_MySQL("new", $custom_set_name, PFX."txp_lang", array(
            'custom_field_number' => $custom_field_number,
            'lang'                => $GLOBALS['prefs']['language']
          ));
          glz_custom_fields_MySQL("new", $custom_set_name, PFX."textpattern", array(
            'custom_field_number' => $custom_field_number,
            'custom_set_type' => $custom_set_type
          ));
          // there are custom fields for which we do not need to touch custom_fields table
          if ( !in_array($custom_set_type, array("textarea", "text_input")) ) {
            glz_custom_fields_MySQL("new", $custom_set_name, PFX."custom_fields", array(
              'custom_field_number' => $custom_field_number,
              'value'               => $value
            ));
          }

          $glz_notice[] = glz_custom_fields_gTxt("created", array('{custom_set_name}' => $custom_set_name));
        }
        // name exists, abort
        else
          $glz_notice[] = glz_custom_fields_gTxt("exists", array('{custom_set_name}' => $custom_set_name));
      }
    }

    // we are editing an existing custom field
    if ( gps('save') ) {
      if ( !empty($custom_set_name) ) {
        $custom_set_name = glz_clean_string($custom_set_name);
        $name_exists = glz_check_custom_set_name($all_custom_sets, $custom_set_name, $custom_set);
        // if name doesn't exist we'll need to create a new custom_set
        if ( $name_exists == FALSE ) {
          glz_custom_fields_MySQL("update", $custom_set, PFX."txp_prefs", array(
            'custom_set_name'     => $custom_set_name,
            'custom_set_type'     => $custom_set_type,
            'custom_set_position' => $custom_set_position
          ));

          // custom sets need to be changed based on their type
          glz_custom_fields_MySQL("update", $custom_set, PFX."textpattern", array(
            'custom_set_type' => $custom_set_type,
            'custom_field' => glz_custom_number($custom_set)
          ));

          // for textareas we do not need to touch custom_fields table
          if ( $custom_set_type != "textarea" ) {
            glz_custom_fields_MySQL("delete", $custom_set, PFX."custom_fields");
            glz_custom_fields_MySQL("new", $custom_set_name, PFX."custom_fields", array(
              'custom_set'  => $custom_set,
              'value'       => $value
            ));
          }

          $glz_notice[] = glz_custom_fields_gTxt("updated", array('{custom_set_name}' => $custom_set_name));
        }
        // name exists, abort
        else
          $glz_notice[] = glz_custom_fields_gTxt("exists", array('{custom_set_name}' => $custom_set_name));
      }
      else
        $glz_notice[] = glz_custom_fields_gTxt('no_name');
    }

    // need to re-fetch data since things modified
    $all_custom_sets = glz_custom_fields_MySQL("all");

  }

  pagetop("Custom Fields");

  // the table with all custom fields follows
  echo
    n.'<div class="listtables">'.n.
    '  <table class="txp-list glz_custom_fields">'.n.
    '    <thead>'.n.
    '      <tr>'.n.
    '        <th>Position</th>'.n.
    '        <th>Name</th>'.n.
    '        <th>Type</th>'.n.
    '        <th>&nbsp;</th>'.n.
    '      </tr>'.n.
    '    </thead>'.n.
    '    <tbody>'.n;

  // looping through all our custom fields to build the table
  $i = 0;
  foreach ( $all_custom_sets as $custom => $custom_set ) {
    // first 10 fields cannot be deleted, just reset
    if ( $i < 10 ) {
      // can't reset a custom field that is not set
      $reset_delete = ( $custom_set['name'] ) ?
        glz_form_buttons("reset", "Reset", $custom, htmlspecialchars($custom_set['name']), $custom_set['type'], '', 'return confirm(\'By proceeding you will RESET ALL data in `textpattern` and `custom_fields` tables for `'.$custom.'`. Are you sure?\');') :
        NULL;
    }
    else {
      $reset_delete = glz_form_buttons("delete", "Delete", $custom, htmlspecialchars($custom_set['name']), $custom_set['type'], '', 'return confirm(\'By proceeding you will DELETE ALL data in `textpattern` and `custom_fields` tables for `'.$custom.'`. Are you sure?\');');
    }

    $edit = glz_form_buttons("edit", "Edit", $custom, htmlspecialchars($custom_set['name']), $custom_set['type'], $custom_set['position']);

    echo
    '      <tr>'.n.
    '        <td class="custom_set_position">'.$custom_set['position'].'</td>'.n.
    '        <td class="custom_set_name">'.$custom_set['name'].'</td>'.n.
    '        <td class="type">'.(($custom_set['name']) ? glz_custom_fields_gTxt($custom_set['type']) : '').'</td>'.n.
    '        <td class="events">'.$reset_delete.sp.$edit.'</td>'.n.
    '      </tr>'.n;

    $i++;
  }

  echo
    '    </tbody>'.n.
    '  </table>'.n;
    '</div>'.n;

  // the form where custom fields are being added/edited
  $legend = gps('edit') ?
    'Edit '.gps('custom_set') :
    'Add new custom field';

  $custom_field = gps('edit') ?
    '<input name="custom_set" value="'.gps('custom_set').'" type="hidden" />' :
    '<input name="custom_field_number" value="'.glz_custom_next($all_custom_sets).'" type="hidden" />';

  $custom_set = gps('edit') ?
    gps('custom_set') :
    NULL;

  $custom_name = gps('edit') ?
    gps('custom_set_name') :
    NULL;

  $custom_set_position = gps('edit') ?
    gps('custom_set_position') :
    NULL;

  $arr_custom_set_types = glz_custom_set_types();

  $custom_set_types = NULL;
  foreach ( $arr_custom_set_types as $custom_type_group => $custom_types ) {
    $custom_set_types .= '<optgroup label="'.ucfirst($custom_type_group).'">'.n;
    foreach ($custom_types as $custom_type) {
      $selected = ( gps('edit') && gps('custom_set_type') == $custom_type ) ?
        ' selected="selected"' :
        NULL;
      $custom_set_types .= '<option value="'.$custom_type.'"'.$selected.'>'.glz_custom_fields_gTxt($custom_type).'</option>'.n;
    }
    $custom_set_types .= '</optgroup>'.n;
  }
  // fetching the values for this custom field
  if ( gps('edit') ) {
    if ( $custom_set_type == "text_input" )
      $arr_values = glz_custom_fields_MySQL('all_values', glz_custom_number($custom_set), '', array('custom_set_name' => $custom_set_name, 'status' => 4));
    else
      $arr_values = glz_custom_fields_MySQL("values", $custom_set, '', array('custom_set_name' => $custom_set_name));

    $values = ( $arr_values ) ?
      implode("\r\n", $arr_values) :
      '';
  }
  else
    $values = '';

  $action = gps('edit') ?
    '<input name="save" value="Save" type="submit" class="submit" />' :
    '<input name="add_new" value="Add new" type="submit" class="submit" />';
  // this needs to be different for a script
  $value = ( isset($custom_set_type) && $custom_set_type == "custom-script" ) ?
    '<input type="text" name="value" id="value" value="'.$values.'" class="left"/><span class="right"><em>Relative path from your website\'s public folder</em></span>' :
    '<textarea name="value" id="value" class="left">'.$values.'</textarea><span class="right"><em>Each value on a separate line</em> <br /><em>One {default} value allowed</em></span>';

  // ok, all is set, let's build the form
  echo
    '<form method="post" action="index.php" id="add_edit_custom_field">'.n.
    '<input name="event" value="glz_custom_fields" type="hidden" />'.n.
    $custom_field.n.
    '<fieldset>'.n.
    ' <legend>'.$legend.'</legend>'.n.
    ' <p class="clearfix">
        <label for="custom_set_name" class="left">Name:</label>
        <input type="text" name="custom_set_name" value="'.htmlspecialchars($custom_name).'" id="custom_set_name" class="left" />
        <span class="right"><em>Only word characters allowed</em></span>
      </p>'.n.
    ' <p class="clearfix">
        <label for="custom_set_type" class="left">Type:</label>
        <select name="custom_set_type" id="custom_set_type" class="left">
    '.      $custom_set_types.'
        </select>
      </p>'.n.
    ' <p class="clearfix">
        <label for="custom_set_position" class="left">Position:</label>
        <input type="text" name="custom_set_position" value="'.htmlspecialchars($custom_set_position).'" id="custom_set_position" class="left" />
        <span class="right"><em>Automatically assigned if blank</em></span>
      </p>'.n.
    ' <p class="clearfix">
        <label for="value" class="left">Value:</label>
    '.  $value.'
      </p>'.n.
    ' '.$action.n.
    '</fieldset>'.n.
    '</form>'.n;
}


// -------------------------------------------------------------
// glz_custom_fields preferences
function glz_custom_fields_preferences() {
  global $event, $glz_notice;

  if ( $_POST && gps('save') ) {
    glz_custom_fields_MySQL("update_plugin_preferences", $_POST['glz_custom_fields_prefs']);
    $glz_notice[] = glz_custom_fields_gTxt("preferences_updated");
    // need to re-fetch from db because this has changed since $prefs has been populated
  }
  $current_preferences = glz_custom_fields_MySQL('plugin_preferences');

  pagetop("glz_custom_fields Preferences");

  // custom_fields
  $arr_values_ordering = array(
    'ascending'   => "Ascending",
    'descending'  => "Descending",
    'custom'      => "As entered"
  );
  $values_ordering = '<select name="glz_custom_fields_prefs[values_ordering]" id="glz_custom_fields_prefs_values_ordering">';
  foreach ( $arr_values_ordering as $value => $title ) {
    $selected = ($current_preferences['values_ordering'] == $value) ? ' selected="selected"' : '';
    $values_ordering .= "<option value=\"$value\"$selected>$title</option>";
  }
  $values_ordering .= "</select>";
  $multiselect_size = '<input type="text" name="glz_custom_fields_prefs[multiselect_size]" id="glz_custom_fields_prefs_multiselect_size" value="'.$current_preferences['multiselect_size'].'" />';
  $custom_scripts_path_error = ( @fopen($current_preferences['custom_scripts_path'], "r") ) ?
    '' :
    '<br /><em class="red">Folder does not exist, please create it.</em>';

  // jquery.datePicker
  $datepicker_url_error = ( @fopen($current_preferences['datepicker_url']."/datePicker.js", "r") ) ?
    '' :
    '<br /><em class="red">Folder does not exist, please create it.</em>';
  $arr_date_format = array("dd/mm/yyyy", "mm/dd/yyyy", "yyyy-mm-dd", "dd mm yy");
  $date_format = '<select name="glz_custom_fields_prefs[datepicker_format]" id="glz_custom_fields_prefs_datepicker_format">';
  foreach ( $arr_date_format as $format ) {
    $selected = ($current_preferences['datepicker_format'] == $format) ? ' selected="selected"' : '';
    $date_format .= "<option value=\"$format\"$selected>$format</option>";
  }
  $date_format .= "</select>";

  $arr_days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
  $first_day = '<select name="glz_custom_fields_prefs[datepicker_first_day]" id="glz_custom_fields_prefs_datepicker_first_day">';
  foreach ( $arr_days as $key => $day ) {
    $selected = ($current_preferences['datepicker_first_day'] == $key) ? ' selected="selected"' : '';
    $first_day .= "<option value=\"$key\"$selected>$day</option>";
  }
  $first_day .= "</select>";

  $start_date = '<input type="text" name="glz_custom_fields_prefs[datepicker_start_date]" id="glz_custom_fields_prefs_datepicker_start_date" value="'.$current_preferences['datepicker_start_date'].'" />';

  // jquery.timePicker
  $timepicker_url_error = ( @fopen($current_preferences['timepicker_url']."/timePicker.js", "r") ) ?
    '' :
    '<br /><em class="red">Folder does not exist, please create it.</em>';
  $arr_time_format = array('true' => "24 hours", 'false' => "12 hours");
  $show_24 = '<select name="glz_custom_fields_prefs[timepicker_show_24]" id="glz_custom_fields_prefs_timepicker_show_24">';
  foreach ( $arr_time_format as $value => $title ) {
    $selected = ($current_preferences['timepicker_show_24'] == $value) ? ' selected="selected"' : '';
    $show_24 .= "<option value=\"$value\"$selected>$title</option>";
  }
  $show_24 .= "</select>";

  $out = <<<EOF
<form action="index.php" method="post">
<table id="list" class="glz_custom_fields_prefs" cellpadding="0" cellspacing="0" align="center">
  <tbody>
    <tr class="heading">
      <td colspan="2"><h2 class="pref-heading">Custom Fields</h2></td>
    </tr>
    <tr>
      <th scope="row"><label for="glz_custom_fields_prefs_values_ordering">Order for custom field values</th>
      <td>{$values_ordering}</td>
    </tr>
    <tr>
      <th scope="row"><label for="glz_custom_fields_prefs_multiselect_size">Multi-select field size</th>
      <td>{$multiselect_size}</td>
    </tr>
    <tr>
      <th scope="row"><label for="glz_custom_fields_prefs_custom_scripts_path">Custom scripts path</th>
      <td><input type="text" name="glz_custom_fields_prefs[custom_scripts_path]" id="glz_custom_fields_prefs_custom_scripts_path" value="{$current_preferences['custom_scripts_path']}" />{$custom_scripts_path_error}</td>
    </tr>

    <tr class="heading">
      <td colspan="2"><h2 class="pref-heading left">Date Picker</h2> <a href="http://www.kelvinluck.com/assets/jquery/datePicker/v2/demo/index.html" title="A flexible unobtrusive calendar component for jQuery" class="right">jQuery datePicker</a></td>
    </tr>
    <tr>
      <th scope="row"><label for="glz_custom_fields_prefs_datepicker_url">Date Picker plugin URL</th>
      <td><input type="text" name="glz_custom_fields_prefs[datepicker_url]" id="glz_custom_fields_prefs_datepicker_url" value="{$current_preferences['datepicker_url']}" />{$datepicker_url_error}</td>
    </tr>
    <tr>
      <th scope="row"><label for="glz_custom_fields_prefs_datepicker_format">Date format</th>
      <td>{$date_format}</td>
    </tr>
    <tr>
      <th scope="row"><label for="glz_custom_fields_prefs_datepicker_first_day">First day of week</th>
      <td>{$first_day}</td>
    </tr>
    <tr>
      <th scope="row"><label for="glz_custom_fields_prefs_datepicker_start_date">Start date</th>
      <td>{$start_date}<br /><em class="grey">MUST be the same as "Date format"</em></td>
    </tr>

    <tr class="heading">
      <td colspan="2"><h2 class="pref-heading left">Time Picker</h2> <a href="http://labs.perifer.se/timedatepicker/" title="jQuery time picker" class="right">jQuery timePicker</a></td>
    </tr>
    <tr>
      <th scope="row"><label for="glz_custom_fields_prefs_timepicker_url">Time Picker plugin URL</th>
      <td><input type="text" name="glz_custom_fields_prefs[timepicker_url]" id="glz_custom_fields_prefs_timepicker_url" value="{$current_preferences['timepicker_url']}" />{$timepicker_url_error}</td>
    </tr>
    <tr>
      <th scope="row"><label for="glz_custom_fields_prefs_timepicker_start_time">Start time</th>
      <td><input type="text" name="glz_custom_fields_prefs[timepicker_start_time]" id="glz_custom_fields_prefs_timepicker_start_time" value="{$current_preferences['timepicker_start_time']}" /></td>
    </tr>
    <tr>
      <th scope="row"><label for="glz_custom_fields_prefs_timepicker_end_time">End time</th>
      <td><input type="text" name="glz_custom_fields_prefs[timepicker_end_time]" id="glz_custom_fields_prefs_timepicker_end_time" value="{$current_preferences['timepicker_end_time']}" /></td>
    </tr>
    <tr>
      <th scope="row"><label for="glz_custom_fields_prefs_timepicker_step">Step</th>
      <td><input type="text" name="glz_custom_fields_prefs[timepicker_step]" id="glz_custom_fields_prefs_timepicker_step" value="{$current_preferences['timepicker_step']}" /></td>
    </tr>
    <tr>
      <th scope="row"><label for="glz_custom_fields_prefs_timepicker_step">Time format</th>
      <td>{$show_24}</td>
    </tr>

    <tr>
      <td colspan="2" class="noline">
        <input class="publish" type="submit" name="save" value="Save" />
        <input type="hidden" name="event" value="plugin_prefs.glz_custom_fields" />
      </td>
    </tr>
  </tbody>
</table>
EOF;

  echo $out;
}

?>

