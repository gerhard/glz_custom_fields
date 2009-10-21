<?php

// Including helper files. If we can't have classes, we will use gems : )
require_once('lib/gTxt.php');
require_once('lib/db.php');
require_once('lib/helpers.php');
require_once('lib/callbacks.php');

// globals, expensive operations mostly
before_glz_custom_fields();

if (@txpinterface == "admin") {

  // INSTALL ROUTINES
  // checks if all tables exist and everything is setup properly
  register_callback("glz_custom_fields_install", "plugin_lifecycle.glz_custom_fields", "installed");

  // UNINSTALL ROUTINES
  // drops custom_fields table, takes all custom fields back to input & remove all glz_custom_fields preferences
  register_callback("glz_custom_fields_uninstall", "plugin_lifecycle.glz_custom_fields", "deleted");

  // we'll be doing this only on the pages that we care about, not everywhere
  if ( in_array(gps('event'), array("article", "prefs", "glz_custom_fields", "plugin_prefs.glz_custom_fields")) ) {
    // we need some stylesheets & JS
    add_privs('glz_custom_fields_css_js', "1");
    register_callback('glz_custom_fields_css_js', "admin_side", 'head_end');

    // we need to make sure that all custom field values will be converted to strings first - think checkboxes & multi-selects
    if ( (gps("step") == "edit") || (gps("step") == "create") ) {
      add_privs('glz_custom_fields_before_save', "1");
      register_callback('glz_custom_fields_before_save', "article", '', 1);
    }
  }

  // Custom Fields tab under Extensions
  add_privs('glz_custom_fields', "1");
  register_tab("extensions", 'glz_custom_fields', "Custom Fields");
  register_callback('glz_custom_fields', "glz_custom_fields");

  add_privs('plugin_prefs.glz_custom_fields', "1");
  register_callback('glz_custom_fields_preferences', 'plugin_prefs.glz_custom_fields');


  // YES, finally the default custom fields are replaced by the new, pimped ones : )
  register_callback('glz_custom_fields_replace', 'article_ui', 'custom_fields');
  // YES, now we have textarea custom fields as well ; )
  register_callback('glz_custom_fields_replace', 'article_ui', 'excerpt');
}

// -------------------------------------------------------------
// everything is happening in this function... generates the content for Extensions > Custom Fields
function glz_custom_fields() {
  global $event, $all_custom_sets, $glz_notice;

  // we have $_POST, let's see if there is any CRUD
  if ( $_POST ) {
    $incoming = stripPost();
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
        $custom_set_name = preg_replace('/[^A-Za-z0-9\s]/', '', $custom_set_name);

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
        $custom_set_name = preg_replace('/[^A-Za-z0-9\s]/', '', $custom_set_name);
        $name_exists = glz_check_custom_set_name($all_custom_sets, $custom_set_name, $custom_set);
        // if name doesn't exist
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

          // there are custom fields for which we do not need to touch custom_fields table
          if ( !in_array($custom_set_type, array("textarea", "text_input")) ) {
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
    n.'<table cellspacing="0" id="glz_custom_fields" class="stripeMe">'.n.
    ' <thead>'.n.
    '   <tr>'.n.
    '     <td>Custom set</td>'.n.
    '     <td>Name</td>'.n.
    '     <td>Type</td>'.n.
    '     <td colspan="2">Position</td>'.n.
    '   </tr>'.n.
    ' </thead>'.n.
    ' <tbody>'.n;

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
    '   <tr>'.n.
    '     <td class="custom_set">'.$custom.'</td>'.n.
    '     <td class="custom_set_name">'.$custom_set['name'].'</td>'.n.
    '     <td class="type">'.(($custom_set['name']) ? glz_custom_fields_gTxt($custom_set['type']) : '').'</td>'.n.
    '     <td class="custom_set_position">'.$custom_set['position'].'</td>'.n.
    '     <td class="events">'.$reset_delete.sp.$edit.'</td>'.n.
    '   </tr>'.n;

    $i++;
  }

  echo
    ' </tbody>'.n.
    '</table>'.n;

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
    $arr_values = glz_custom_fields_MySQL("values", $custom_set, '', array('custom_set_name' => $custom_field['custom_set_name']));
    $values = ( $arr_values ) ?
      implode("\r\n", $arr_values) :
      '';
  }
  else
    $values = '';

  $action = gps('edit') ?
    '<input name="save" value="Save" type="submit" class="publish" />' :
    '<input name="add_new" value="Add new" type="submit" class="publish" />';

  // ok, all is set, let's build the form
  echo
    '<form method="post" action="index.php" id="add_edit_custom_field">'.n.
    '<input name="event" value="glz_custom_fields" type="hidden" />'.n.
    $custom_field.n.
    '<fieldset>'.n.
    ' <legend>'.$legend.'</legend>'.n.
    ' <p class="clearfix">
        <label for="custom_set_name" class="left">Name:</label>
        <input name="custom_set_name" value="'.htmlspecialchars($custom_name).'" id="custom_set_name" class="left" />
        <span class="left"><em>Only word characters allowed</em></span>
      </p>'.n.
    ' <p class="clearfix">
        <label for="custom_set_type" class="left">Type:</label>
        <select name="custom_set_type" id="custom_set_type" class="left">
    '.      $custom_set_types.'
        </select>
      </p>'.n.
    ' <p class="clearfix">
        <label for="custom_set_position" class="left">Position:</label>
        <input name="custom_set_position" value="'.htmlspecialchars($custom_set_position).'" id="custom_set_position" class="left" />
        <span class="left"><em>Automatically assigned</em></span>
      </p>'.n.
    ' <p class="clearfix">
        <label for="value" class="left">Value:</label>
        <textarea name="value" id="value" class="left">'.$values.'</textarea>
        <span class="left"><em>Each value on a separate line</em> <br /><em>One {default} value allowed</em></span>
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

  // ordering values
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

  // jquery.datePicker
  $arr_date_format = array("dd/mm/yyyy", "mm/dd/yyyy", "yyyy-mm-dd", "dd mmm yy");
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

  $out = <<<EOF
<form action="index.php" method="post">
<table id="list" cellpadding="0" cellspacing="0" align="center">
  <tbody>
    <tr>
      <td colspan="2"><h2 class="pref-heading">Custom Sets Ordering</h2></td>
    </tr>
    <tr>
      <th scope="row"><label for="glz_custom_fields_prefs_values_ordering">Order for custom field values</th>
      <td>{$values_ordering}</td>
    </tr>
    <tr>
      <td colspan="2"><h2 class="pref-heading">Date Picker</h2></td>
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

