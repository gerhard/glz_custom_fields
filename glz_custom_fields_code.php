<?php

// Including helper files. If we can't have classes, we will use gems : )
require_once('lib/gTxt.php');
require_once('lib/db.php');
require_once('lib/helpers.php');
require_once('lib/callbacks.php');

// globals, expensive operations mostly
before_glz_custom_fields();

if (txpinterface == "admin") {

  // INSTALL ROUTINES
  // checks if all tables exist and everything is setup properly
  register_callback("glz_custom_fields_install", "plugin_lifecycle.glz_custom_fields", "installed");

  // UNINSTALL ROUTINES
  // drops custom_fields table, takes all custom fields back to input & unmarks glz_custom_fields migration
  register_callback("glz_custom_fields_uninstall", "plugin_lifecycle.glz_custom_fields", "uninstalled");

  // we'll be doing this only on the pages that we care about, not everywhere
  if ( in_array(gps('event'), array("article", "prefs", "glz_custom_fields")) ) {
    // we need some stylesheets & JS
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

      glz_custom_fields_MySQL("reset", glz_custom_number($custom_set), PFX."textpattern");

      $glz_notice[] = glz_custom_fields_gTxt("reset", array('{custom_set_name}' => $custom_set_name));
    }

    // we are adding a new custom field
    if ( gps("custom_field_number") ) {
      $custom_set_name = gps("custom_set_name");

      // if no name was specified, abort
      if ( !$custom_set_name )
        $glz_notice[] = glz_custom_fields_gTxt("no_name");
      else {
        $name_exists = glz_check_custom_set_name($all_custom_sets, $custom_set_name);

        // if name doesn't exist
        if ( $name_exists == FALSE ) {
          glz_custom_fields_MySQL("new", $custom_set_name, PFX."txp_prefs", array(
            'custom_field_number' => $custom_field_number,
            'custom_set_type'     => $custom_set_type
          ));
          glz_custom_fields_MySQL("new", $custom_set_name, PFX."txp_lang", array(
            'custom_field_number' => $custom_field_number,
            'lang'                => $GLOBALS['prefs']['language']
          ));
          glz_custom_fields_MySQL("new", $custom_set_name, PFX."textpattern", array(
            'custom_field_number' => $custom_field_number,
            'after'               => intval($custom_field_number-1)
          ));
          glz_custom_fields_MySQL("new", $custom_set_name, PFX."custom_fields", array(
            'custom_field_number' => $custom_field_number,
            'value'               => $value
          ));

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
        $name_exists = glz_check_custom_set_name($all_custom_sets, $custom_set_name, $custom_set);

        // if name doesn't exist
        if ( $name_exists == FALSE ) {
          glz_custom_fields_MySQL("update", $custom_set, PFX."txp_prefs", array(
            'custom_set_type'   => $custom_set_type,
            'custom_set_name'   => $custom_set_name
          ));
          glz_custom_fields_MySQL("delete", $custom_set, PFX."custom_fields");
          glz_custom_fields_MySQL("new", $custom_set_name, PFX."custom_fields", array(
            'custom_set'    => $custom_set,
            'value'         => $value
          ));

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
    '     <td colspan="2">Type</td>'.n.
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
        glz_form_buttons("reset", "Reset", $custom, $custom_set['name'], $custom_set['type'], 'return confirm(\'By proceeding you will RESET ALL data in `textpattern` and `custom_fields` tables for `'.$custom.'`. Are you sure?\');') :
        NULL;
    }
    else {
      $reset_delete = glz_form_buttons("delete", "Delete", $custom, $custom_set['name'], $custom_set['type'], 'return confirm(\'By proceeding you will DELETE ALL data in `textpattern` and `custom_fields` tables for `'.$custom.'`. Are you sure?\');');
    }

    echo
    '   <tr>'.n.
    '     <td class="custom_set">'.$custom.'</td>'.n.
    '     <td class="custom_set_name">'.$custom_set['name'].'</td>'.n.
    '     <td class="type">'.(($custom_set['name']) ? glz_custom_fields_gTxt($custom_set['type']) : '').'</td>'.n.
    '     <td class="events">'.$reset_delete.sp.glz_form_buttons("edit", "Edit", $custom, htmlspecialchars($custom_set['name']), $custom_set['type']).'</td>'.n.
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
    ' <p>
        <label for="custom_set_name">Name:</label>
        <input name="custom_set_name" value="'.htmlspecialchars($custom_name).'" id="custom_set_name" />
      </p>'.n.
    ' <p>
        <label for="custom_set_type">Type:</label>
        <select name="custom_set_type" id="type">
    '.      $custom_set_types.'
        </select>
      </p>'.n.
    ' <p>
        <label for="value">Value:<br /><em>Each value on a separate line</em></label>
        <textarea name="value" id="value">'.$values.'</textarea>
      </p>'.n.
    ' '.$action.n.
    '</fieldset>'.n.
    '</form>'.n;
}

// -------------------------------------------------------------
// replaces the default custom fields under write tab
function glz_custom_fields_replace($event, $step, $data, $rs) {
  global $all_custom_sets, $date_picker;
  // get all custom fields & keep only the ones which are set + filter by step
  $arr_custom_fields = glz_check_custom_set($all_custom_sets, $step);

  // DEBUG
  // dmp($arr_custom_fields);

  $out = ' ';

  if ( is_array($arr_custom_fields) && !empty($arr_custom_fields) ) {
    // get all custom fields values for this article
    $arr_article_customs = glz_custom_fields_MySQL("article_customs", glz_get_article_id(), '', $arr_custom_fields);

    // DEBUG
    // dmp($arr_article_customs);

    if ( is_array($arr_article_customs) )
      extract($arr_article_customs);

    // let's see which custom fields are set
    foreach ( $arr_custom_fields as $custom => $custom_set ) {
      // get all possible/default value(s) for this custom set from custom_fields table
      $arr_custom_field_values = glz_custom_fields_MySQL("values", $custom, '', array('custom_set_name' => $custom_set['name']));

      // DEBUG
      // dmp($arr_custom_field_values);

      //custom_set formatted for id e.g. custom_1_set => custom-1 - don't ask...
      $custom_id = glz_custom_number($custom, "-");
      //custom_set without "_set" e.g. custom_1_set => custom_1
      $custom = glz_custom_number($custom);

      // if current article holds no value for this custom field, make it empty
      $custom_value = ( !empty($$custom) ) ?
        $$custom :
        '';
      // DEBUG
      // dmp($custom_value);

      // the way our custom field value is going to look like
      list($custom_set_value, $custom_class) = glz_format_custom_set_by_type($custom, $custom_id, $custom_set['type'], $arr_custom_field_values, $custom_value);

      // DEBUG
      //dmp($custom_set_value);

      $out .= graf(
        "<label for=\"$custom_id\">{$custom_set['name']}</label><br />$custom_set_value", " class=\"$custom_class\""
      );


    }
  }

  // DEBUG
  // dmp($out);

  // if we're writing textarea custom fields, we need to include the excerpt as well
  if ($step == "excerpt")
    $out = $data.$out;

  return $out;
}


// -------------------------------------------------------------
// prep our custom fields for the db (watch out for multi-selects, checkboxes & radios, they might have multiple values)
function glz_custom_fields_before_save() {
  // keep only the custom fields
  foreach ($_POST as $key => $value) {
    //check for custom fields with multiple values e.g. arrays
    if ( strstr($key, 'custom_') && is_array($value) ) {
      $value = implode($value, '|');
      // feed our custom fields back into the $_POST
      $_POST[$key] = $value;
    }
  }
  // DEBUG
  // dmp($_POST);
}

// -------------------------------------------------------------
// save our pimped custom fields (the ones above 10)
function glz_custom_fields_save() {
  $ID = glz_get_article_id();

  if ( $ID ) {
    //initialize $set
    $set = '';
    // see whether we have custom fields > 10
    foreach ($_POST as $key => $value) {
      if (strstr($key, 'custom_')) {
        list($rubbish, $digit) = explode("_", $key);
        // keep only the values that are above 10
        if ( $digit > 10 ) $set[] = "`$key`='".trim($value)."'";
      }
    }
    // anything worthy saving?
    if ( is_array($set) ) {
      // DEBUG
      // dmp($set);
      // ok, update the custom fields
      safe_update("textpattern", implode($set, ','), "`ID`='$ID'");
    }
  }
}

?>

