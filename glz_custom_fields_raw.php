<?php
/**
glz_custom_fields plugin, FROM UK WITH LOVE

@author Gerhard Lazu
@version 1.2
@copyright Gerhard Lazu, 17th April, 2009
@package TXP 4.0.8 (r3078)
@contributors:  Sam Weiss, redbot, Manfre, Vladimir, Julian Reisenberger, Steve Dickinson
@special: Randy Levine, Husain Hakim
*/

before_glz_custom_fields();

if (txpinterface == "admin") {
  
  // we'll be doing this only on the pages that we care about, not everywhere
  if ( in_array(gps('event'), array("article", "prefs", "glz_custom_fields")) ) {
    // checks if all tables exist and everything is setup properly
    glz_custom_fields_install();

    // we need some stylesheets & JS
    ob_start("glz_custom_fields_css_js");

    // these globals have been already set in before_glz_custom_fields() which is called everytime
    global $all_custom_sets;

    // we need to make sure that all custom field values will be converted to strings first - think checkboxes & multi-selects
    if ( (gps("step") == "edit") || (gps("step") == "create") ) {
      add_privs('glz_custom_fields_before_save', "1");
      register_callback('glz_custom_fields_before_save', "article", '', 1);
    }
    // if we edit or create a new article, save our extra custom fields (> 10)
    if ( (gps("step") == "edit") || (gps("step") == "create") ) {
      add_privs('glz_custom_fields_save', "1");
      register_callback('glz_custom_fields_save', "article");
    }
  }
  
  // Custom Fields tab under Extensions
  add_privs('glz_custom_fields', "1");
  register_tab("extensions", 'glz_custom_fields', "Custom Fields");
  register_callback('glz_custom_fields', "glz_custom_fields");
  
  // YES, finally the default custom fields are replaced by the new, pimped ones : )
  add_privs('glz_custom_fields_replace', "1");
  register_callback('glz_custom_fields_replace', "article");
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
    '     <td class="type">'.glz_custom_fields_gTxt($custom_set['type']).'</td>'.n.
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
  foreach ( $arr_custom_set_types as $custom_type ) {
    $selected = ( gps('edit') && gps('custom_set_type') == $custom_type ) ?
      ' selected="selected"' :
      NULL;
    $custom_set_types .= '<option value="'.$custom_type.'"'.$selected.'>'.glz_custom_fields_gTxt($custom_type).'</option>'.n;
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
function glz_custom_fields_replace() {
  global $all_custom_sets;
  // get all custom fields & keep only the ones which are set
  $arr_custom_fields = array_filter($all_custom_sets, "glz_check_custom_set");
  
  // DEBUG
  // dmp($arr_custom_fields);
  
  if ( is_array($arr_custom_fields) && !empty($arr_custom_fields) ) {
    // get all custom fields values for this article
    $arr_article_customs = glz_custom_fields_MySQL("article_customs", glz_get_article_id(), '', $arr_custom_fields);
    
    // DEBUG
    // dmp($arr_article_customs);
    
    if ( is_array($arr_article_customs) )
      extract($arr_article_customs);

    // let's initialize our output
    $out = '';
    
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
      // dmp($custom_set_value);
      
      // adding addslashes(..., '/') to $out escapes all the slashes in your jquery replace, dispelling the validation errors in the CDATA area (in Safari 3.1, Mac with no ill-effects). Thanks Julian!
      $out .= addslashes(graf(
        "<label for=\"$custom_id\">{$custom_set['name']}</label><br />$custom_set_value", " class=\"$custom_class\""
      ));
    }
    // DEBUG
    // dmp($out);
  
    echo
    '<script type="text/javascript">
    <!--//--><![CDATA[//><!--

    $(document).ready(function() {
      // removes all existing custom fields
      $("p:has(label[for*=custom-])").remove();
      $("p:has(label[@for=override-form])").after(\''.$out.'\');
      
      // add a reset link to all radio custom fields
      $(".glz_custom_radio_field").each(function() {
        custom_field_to_reset = $(this).find("input:first").attr("name");
        $(this).find("label:first").after(" <span class=\"small\">[<a href=\"#\" class=\"glz_custom_field_reset\" name=\"" + custom_field_to_reset +"\">Reset</a>]</span>");
      });
      
      // catch the reset action for the above link
      $(".glz_custom_field_reset").click(function() {
        custom_field_to_reset = $(this).attr("name");
        // reset our radio input
        $("input[name=" + custom_field_to_reset + "]").attr("checked", false);
        // add an empty value with the same ID so that it saves the value as empty in the db
        $(this).after("<input type=\"hidden\" value=\"\" name=\""+ custom_field_to_reset +"\" />");
        return false;
      });
    });
    //--><!]]>
    </script>';
  }
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



###################
##### HELPERS #####
###################

// -------------------------------------------------------------
// edit/delete buttons in custom_fields table require a form each
function glz_form_buttons($action, $value, $custom_set, $custom_set_name, $custom_set_type, $onsubmit='') {
  $onsubmit = ($onsubmit) ?
    'onsubmit="'.$onsubmit.'"' :
    '';
  
  return 
    '<form method="post" action="index.php" '.$onsubmit.'>
      <input name="custom_set" value="'.$custom_set.'" type="hidden" />
      <input name="custom_set_name" value="'.$custom_set_name.'" type="hidden" />
      <input name="custom_set_type" value="'.$custom_set_type.'" type="hidden" />
      <input name="event" value="glz_custom_fields" type="hidden" />
      <input name="'.$action.'" value="'.$value.'" type="submit" />
    </form>';
}


// -------------------------------------------------------------
// the types our custom fields can take
function glz_custom_set_types() {
  return array(
    'text_input',
    'select',
    'multi-select',
//    'textarea', // planned for v1.2.x
    'checkbox',
    'radio'
  );
}


// -------------------------------------------------------------
// outputs only custom fields that have been set, i.e. have a name assigned to them
function glz_check_custom_set($var) {
  return !empty($var['name']);
}


// -------------------------------------------------------------
// custom_set without "_set" e.g. custom_1_set => custom_1
// or custom set formatted for IDs e.g. custom-1
function glz_custom_number($custom_set, $delimiter="_") {
  $custom_field = substr($custom_set, 0, -4);
  
  if ($delimiter != "_")
    $custom_field = str_replace("_", $delimiter, $custom_field);
  
  return $custom_field;
}


// -------------------------------------------------------------
// custom_set digit e.g. custom_1_set => 1
function glz_custom_digit($custom_set) {
  $out = explode("_", $custom_set);
  // $out[0] will always be custom
  return $out[1];
}


// -------------------------------------------------------------
// removes empty values from arrays - used for new custom fields
function glz_arr_empty_values($value) {
  if ( !empty($value) )
    return $value;
}


// -------------------------------------------------------------
// returns the custom set from a custom set name e.g. "Rating" gives us custom_1_set
function glz_get_custom_set($value) {
  global $all_custom_sets;
  
  // go through all custom fields and see if the one we're looking for exists
  foreach ( $all_custom_sets as $custom => $custom_set ) {
    if ( $custom_set['name'] == $value )
      return $custom;
  }
  // if it doesn't, return error message
  trigger_error(glz_custom_fields_gTxt('doesnt_exist', array('{custom_set_name}' => $value)));
}


// -------------------------------------------------------------
// get the article ID, EVEN IF it's newly saved
function glz_get_article_id() {
  return ( !empty($GLOBALS['ID']) ?
    $GLOBALS['ID'] :
    gps('ID') );
}


// -------------------------------------------------------------
// helps with range formatting - just DRY
function glz_format_ranges($arr_values, $custom_set_name) {
  //initialize $out
  $out = '';
  foreach ( $arr_values as $key => $value ) {
    $out[$key] = ( strstr($custom_set_name, 'range') ) ?
      glz_custom_fields_range($value, $custom_set_name) :
      $value;
  }
  return $out;
}


// -------------------------------------------------------------
// acts as a callback for the above function
function glz_custom_fields_range($custom_value, $custom_set_name) {
  // last part of the string will be the range unit (e.g. $, &pound;, m<sup>3</sup> etc.)
  $nomenclature = array_pop(explode(' ', $custom_set_name));
  
  // see whether range unit should go after
  if ( strstr($nomenclature, '(after)') ) {
    // trim '(after)' from the measuring unit
    $nomenclature = substr($nomenclature, 0, -7);
    $after = 1;
  }
  
  // check whether it's a range or single value
  $arr_value = explode('-', $custom_value);
  if ( is_array($arr_value) ) {
    // initialize $out
    $out = '';
    foreach ( $arr_value as $value ) {
      // check whether nomenclature goes before or after
      $out[] = ( !isset($after) ) ?
        $nomenclature.number_format($value) :
        number_format($value).$nomenclature;
    }
    return implode('-', $out);
  }
  // our range is a single value
  else {
    // check whether nomenclature goes before or after
    return ( !isset($after) ) ?
      $nomenclature.number_format($value) :
      number_format($value).$nomenclature;
  }
}


// -------------------------------------------------------------
// returns the next available number for custom set
function glz_custom_next($arr_custom_sets) {
  // if the array is exactly 10, our next custom field is 11
  if ( count($arr_custom_sets) == 10 ) {
    return 11;
  }
  // if not, slice the array with an offset of 10 (we don't want to look in custom fields < 10)
  else {
    $arr_extra_custom_sets = array();
    foreach ( array_keys(array_slice($arr_custom_sets, 10)) as $extra_custom_set) {
      // strip on _ and keep only the digit e.g. custom_11_set
      $digit = split("_", $extra_custom_set);
      $arr_extra_custom_sets[] = $digit['1'];
    }
    // order the array
    sort($arr_extra_custom_sets);
    
    foreach ( $arr_extra_custom_sets as $extra_custom_set ) {
      if (!in_array($extra_custom_set+1, $arr_extra_custom_sets))
        return $extra_custom_set+1;
    }
  }
}


// -------------------------------------------------------------
// checks if the custom field name isn't already taken
function glz_check_custom_set_name($arr_custom_fields, $custom_set_name, $custom_set='') {
  foreach ( $arr_custom_fields as $custom => $arr_custom_set ) {
    if ( ($custom_set_name == $arr_custom_set['name']) && (!empty($custom_set) && $custom_set != $custom) )
      return TRUE;
  }
  
  return FALSE;
}


// -------------------------------------------------------------
// formats the custom set output based on its type
function glz_format_custom_set_by_type($custom, $custom_id, $custom_set_type, $arr_custom_field_values, $custom_value = "") {
  if ( is_array($arr_custom_field_values) )
    $arr_custom_field_values = array_map('glz_array_stripslashes', $arr_custom_field_values);
  
  switch ( $custom_set_type ) {
    case "text_input":
      return array(
        fInput("text", $custom, $custom_value, "edit", "", "", "22", "", $custom_id),
        'glz_custom_field'
      );

    case "select":
      return array(
        glz_selectInput($custom, $custom_id, $arr_custom_field_values, $custom_value, 1),
        'glz_custom_select_field'
      );
    
    case "multi-select":
      return array(
        glz_selectInput($custom, $custom_id, $arr_custom_field_values, $custom_value, '', 1),
        'glz_custom_multi-select_field'
      );

    case "checkbox":
      return array(
        glz_checkbox($custom, $arr_custom_field_values, $custom_value),
        'glz_custom_checkbox_field'
      );

    case "radio":
      return array(
        glz_radio($custom, $custom_id, $arr_custom_field_values, $custom_value),
        'glz_custom_radio_field'
      );

    // a type has been passed that is not supported yet
    default:
      return array(
        glz_custom_fields_gTxt('type_not_supported'),
        'glz_custom_field'
      );
  }
}


// -------------------------------------------------------------
// had to duplicate the default selectInput() because trimming \t and \n didn't work + some other mods
function glz_selectInput($name = '', $id = '', $arr_values = '', $custom_value = '', $blank_first = '', $multi = '') {
  if ( is_array($arr_values) ) {
    $out = array();
    
    foreach ($arr_values as $key => $value) {
      $selected = glz_selected_checked('selected', $key, $custom_value);
      $out[] = "<option value=\"$key\"{$selected}>$value</option>";
    }

    // we'll need the extra attributes as well as a name that will produce an array
    /**
      TODO Make this user-configurable
    */
    if ($multi) {
      $multi = ' multiple="multiple" size="3"';
      $name .= "[]";
    }

    return "<select id=\"".glz_idify($id)."\" name=\"$name\" class=\"list\"$multi>".
      ($blank_first ? "<option value=\"\"$selected></option>" : '').
      ( $out ? join('', $out) : '').
      "</select>";
  }
  else {
    return glz_custom_fields_gTxt('field_problems', array('{custom_set_name}' => $name));
  }
}


// -------------------------------------------------------------
// had to duplicate the default checkbox() to keep the looping in here and check against existing value/s
function glz_checkbox($name = '', $arr_values = '', $custom_value = '') {
  $out = array();
  
  foreach ( $arr_values as $key => $value ) {
    $checked = glz_selected_checked('checked', $key, $custom_value);
    
    // Putting an additional span around the input and label combination so the two can be floated together as a pair for left-right, left-right,... arrangement of checkboxes and radio buttons. Thanks Julian!
    $out[] = "<span><input type=\"checkbox\" name=\"{$name}[]\" value=\"$key\" class=\"checkbox\" id=\"".glz_idify($key)."\"{$checked} /><label for=\"".glz_idify($key)."\">$value</label></span><br />";
  }

  return join('', $out);
}


// -------------------------------------------------------------
// had to duplicate the default radio() to keep the looping in here and check against existing value
function glz_radio($name = '', $id = '', $arr_values = '', $custom_value = '') {
  $out = array();
  
  foreach ( $arr_values as $key => $value ) {
    $checked = glz_selected_checked('checked', $key, $custom_value);
    
    // Putting an additional span around the input and label combination so the two can be floated together as a pair for left-right, left-right,... arrangement of checkboxes and radio buttons. Thanks Julian!
    $out[] = "<span><input type=\"radio\" name=\"$name\" value=\"$key\" class=\"radio\" id=\"{$id}_".glz_idify($key)."\"{$checked} /><label for=\"{$id}_".glz_idify($key)."\">$value</label></span><br />";
  }

  return join('', $out);
}


// -------------------------------------------------------------
// checking if this custom field has selected or checked values
function glz_selected_checked($nomenclature, $value, $custom_value = '') {
  // we're comparing against a key which is a "clean" value
  $custom_value = htmlspecialchars($custom_value);
  
  // make an array if $custom_value contains multiple values
  if ( strpos($custom_value, '|') )
    $arr_custom_value = explode('|', $custom_value);
  
  if ( isset($arr_custom_value) )
    $out = ( in_array($value, $arr_custom_value) ) ? " $nomenclature=\"$nomenclature\"" : "";
  else
    $out = ($value == $custom_value) ? " $nomenclature=\"$nomenclature\"" : "";
  
  return $out;
}


//-------------------------------------------------------------
// button gets more consistent styling across browsers rather than input type="submit"
// included in this plugin until in makes it into TXP - if that ever happens...
function glz_fButton($type, $name, $contents='Submit', $value, $class='', $id='', $title='', $onClick='', $disabled = false) {
  $o  = '<button type="'.$type.'" name="'.$name.'"';
  $o .= ' value="'.htmlspecialchars($value).'"';
  $o .= ($class)    ? ' class="'.$class.'"' : '';
  $o .= ($id)       ? ' id="'.$id.'"' : '';
  $o .= ($title)    ? ' title="'.$title.'"' : '';
  $o .= ($onClick)  ? ' onclick="'.$onClick.'"' : '';
  $o .= ($disabled) ? ' disabled="disabled"' : '';
  $o .= '>';
  $o .= $contents;
  $o .= '</button>';
  return $o;
}


// -------------------------------------------------------------
// PHP4 doesn't come with array_combine... Thank you redbot!
function php4_array_combine($keys, $values) {
  $result = array(); // initializing the array

  foreach ( array_map(null, $keys, $values) as $pair ) {
    $result[$pair[0]] = $pair[1];
  }

  return $result;
}


// -------------------------------------------------------------
// converts all values into id safe ones
function glz_idify($value) {
  $patterns[0] = "/\s/";
  $replacements[0] = "-";
  $patterns[1] = "/[^a-zA-Z0-9\-]/";
  $replacements[1] = "";
  
  return preg_replace($patterns, $replacements, strtolower($value));
}


// -------------------------------------------------------------
// strips slashes in arrays, used in conjuction with e.g. array_map
function glz_array_stripslashes(&$value) {
  return stripslashes($value);
}


// -------------------------------------------------------------
// returns all sections/categories that are searchable
function glz_all_searchable_sections_categories($type) {
  $type = (in_array($type, array('category', 'section')) ? $type : 'section');
  $condition = "";
  
  if ( $type == "section" )
    $condition .= "searchable='1'";
  else
    $condition .= "name <> 'root' AND type='article'";
    
  $result = safe_rows('*', "txp_{$type}", $condition);
  
  $out = array();
  foreach ($result as $value) {
    $out[$value['name']] = $value['title'];
  }
  
  return $out;
}


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
    'text_input'        => 'Text Input',
    'select'            => 'Select',
    'multi-select'      => 'Multi-Select',
    'textarea'          => 'Textarea',
    'checkbox'          => 'Checkbox',
    'radio'             => 'Radio',
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
    'custom_sets_all_input'   => 'All custom sets have been set back to input',
    'custom_fields_removed'   => 'custom_fields table has been removed'
  );
  
  $out = ( strstr($lang[$get], "Ooops!") ) ? // Ooops! would appear 0 in the string...
      "<span class=\"red\">{$lang[$get]}</span>" : 
      $lang[$get];

  return strtr($out, $atts);
}



#################
##### MYSQL #####
#################

function glz_custom_fields_MySQL($do, $name='', $table='', $extra='') {
  if ( !empty($do) ) {
    switch ( $do ) {
      case 'all':
        return glz_all_custom_sets();
        break;
      
      case 'values':
        return glz_values_custom_field($name, $extra);
        break;
      
      case 'all_values' :
        return glz_all_existing_custom_values($name, $extra);
        break;
      
      case 'article_customs':
        return glz_article_custom_fields($name, $extra);
        break;
      
      case 'next_custom':
         return glz_next_empty_custom();
        break;
      
      case 'new':
        return glz_new_custom_field($name, $table, $extra);
        break;
      
      case 'update':
        return glz_update_custom_field($name, $table, $extra);
        break;
      
      case 'reset':
        glz_reset_custom_field($name, $table);
        break;
        
      case 'delete':
        glz_delete_custom_field($name, $table);
        break;
        
      case 'check_migration':
        return glz_check_migration();
        break;
      
      case 'mark_migration':
        return glz_mark_migration();
        break;
    }
  }
  else
    trigger_error(glz_custom_fields_gTxt('no_do'));
}


function glz_all_custom_sets() {
  $all_custom_sets = getRows("
    SELECT 
      `name` AS custom_set,
      `val` AS name,
      `html` AS type
    FROM
      `".PFX."txp_prefs`
    WHERE
      `event`='custom'
    ORDER BY 
      `position`
  ");
  
  foreach ( $all_custom_sets as $custom_set ) {
    $out[$custom_set['custom_set']] = array(
      'name'  => $custom_set['name'],
      'type'  => $custom_set['type']
    );
  }
  
  return $out;
}


function glz_values_custom_field($name, $extra) {
  if ( is_array($extra) ) {
    extract($extra);
    
    if ( !empty($name) ) {
      $arr_values = getThings("
        SELECT
          `value`
        FROM
          `".PFX."custom_fields`
        WHERE
          `name` = '{$name}'
        ORDER BY
          `value`
      ");
      
      if ( count($arr_values) > 0 ) {
        // have our values nicely sorted
        /**
          TODO User-configurable. Some folks didn't like this order.
        */
        natsort($arr_values);
        
        // decode all special characters e.g. ", & etc. and use them for keys
        foreach ( $arr_values as $key => $value )
          $arr_values_formatted[htmlspecialchars($value)] = stripslashes($value);
        
        // if this is a range, format ranges accordingly
        return glz_format_ranges($arr_values_formatted, $custom_set_name);
      }
    }
  }
  else
    trigger_error(glz_custom_fields_gTxt('not_specified', array('{what}' => "extra attributes")));
}


function glz_all_existing_custom_values($name, $extra) {
  if ( is_array($extra) ) {
    extract(lAtts(array(
      'custom_set_name'   => "",
      'status'            => 4
    ),$extra));
    
    // we might want to check the custom field values for all articles - think initial migration
    $status_condition = ($status == 0) ? "<> ''" : "= '$status'";
    
    if ( !empty($name) ) {
      $arr_values = getThings("
        SELECT DISTINCT 
          `$name`
        FROM
          `".PFX."textpattern`
        WHERE
          `Status` $status_condition
        AND 
          `$name` <> ''
        ORDER BY 
          `$name`
        ");
      
      // have our values nicely sorted
      /**
        TODO User-configurable. Some folks didn't like this order.
      */
      natsort($arr_values);
      
      // trim all values
      foreach ( $arr_values as $key => $value )
        $arr_values[$key] = trim($value);
      
      // DEBUG
      // dmp($arr_values);
      
      // prepare our array for checking. We need a single string to check for | instances - seems quickest.
      $values_check = join('::', $arr_values);
      
      // DEBUG
      // dmp($values_check);
      
      // check if some of the values are multiple ones
      if ( strstr($values_check, '|') ) {
        // initialize $out
        $out = array();
        // put all values in an array
        foreach ( $arr_values as $value ) {
          $arr_values = explode('|', $value);
          $out = array_merge($out, $arr_values);
        }
        // keep only the unique ones
        $out = array_unique($out);
        // keys and values need to be the same
        $out = php4_array_combine($out, $out);
      }
      
      // check if this is a range
      else if ( strstr($values_check, '-') && strstr($custom_set_name, 'range') )
        // keys won't have the unit ($, £, m<sup>3</sup>, etc.) values will
        $out = glz_format_ranges($arr_values, $custom_set_name);
      else
        // keys and values need to be the same
        $out = php4_array_combine($arr_values, $arr_values);
      
      // calling stripslashes on all array values
      array_map('glz_array_stripslashes', $out);
      
      return $out;
    }
  }
  else
    trigger_error(glz_custom_fields_gTxt('not_specified', array('{what}' => "extra attributes")));
}


function glz_article_custom_fields($name, $extra) {
  if ( is_array($extra) ) {
    // see what custom fields we need to query for
    foreach ( $extra as $custom => $custom_set )
      $select[] = glz_custom_number($custom);

    // prepare the select elements
    $select = implode(',', $select);
    
    $arr_article_customs = getRow("
      SELECT 
        $select
      FROM
        `".PFX."textpattern`
      WHERE
        `ID`='$name'
    ");
    
    return $arr_article_customs;
  }
  else
    trigger_error(glz_custom_fields_gTxt('not_specified', array('{what}' => "extra attributes")));
}


function glz_next_empty_custom() {
  global $all_custom_sets;
  
  foreach ( $all_custom_sets as $custom => $custom_set ) {
    if ( empty($custom_set['name']) )
      return $custom;
  }
}


function glz_new_custom_field($name, $table, $extra) {
  if ( is_array($extra) ) {
    extract($extra);
    // DRYing up, we'll be using this variable quiet often
    $custom_set = ( isset($custom_field_number) ) ?
      "custom_{$custom_field_number}_set" :
      $custom_set;
    
    if ( ($table == PFX."txp_prefs")  ) {
      $query = "
        INSERT INTO 
          `".PFX."txp_prefs` (`prefs_id`,`name`,`val`,`type`,`event`,`html`,`position`) 
        VALUES 
          ('1','{$custom_set}','{$name}','1','custom','{$custom_set_type}',{$custom_field_number})
      ";
    }
    else if ( $table == PFX."txp_lang" ) {
      $query = "
        INSERT INTO 
          `".PFX."txp_lang` (`id`,`lang`,`name`,`event`,`data`,`lastmod`) 
        VALUES 
          ('','{$lang}','{$custom_set}','prefs','{$name}',now())
      ";
    }
    else if ( $table == PFX."textpattern" ) {
      $query = "
        ALTER TABLE
          `".PFX."textpattern`
        ADD
          `custom_{$custom_field_number}` varchar(255) NOT NULL DEFAULT ''
        AFTER
          `custom_{$after}`
      ";
    }
    else if ( $table == PFX."custom_fields" ) {
      $arr_values = array_filter(explode("\r\n", $value), 'glz_arr_empty_values');
      if ( is_array($arr_values) && !empty($arr_values) ) {
        // initialize null
        $insert = '';
        foreach ( $arr_values as $value ) {
          // don't insert empty values
          if ( !empty($value) )
            // make sure special characters are escaped before inserting them in the database
            $value = addslashes(addslashes(trim($value)));
            // if this is the last value, query will have to be different
            $insert .= (addslashes(addslashes(end($arr_values))) != $value ) ?
              "('{$custom_set}','{$value}'), " :
              "('{$custom_set}','{$value}')";
        }
        $query = "
          INSERT INTO 
            `".PFX."custom_fields` (`name`,`value`)
          VALUES
            {$insert}
        ";
      }
    }
    if ( isset($query) && !empty($query) )
      safe_query($query);
  }
  else
    trigger_error(glz_custom_fields_gTxt('not_specified', array('{what}' => "extra attributes")));
}


function glz_update_custom_field($name, $table, $extra) {
  if ( is_array($extra) ) {
    extract($extra);
  
    if ( ($table == PFX."txp_prefs")  ) {
      $query = "
        UPDATE 
          `".PFX."txp_prefs`
        SET
          `val` = '{$custom_set_name}',
          `html` = '{$custom_set_type}' 
        WHERE
          `name`='{$name}'
      ";
    }
    
    safe_query($query);
  }
  else
    trigger_error(glz_custom_fields_gTxt('not_specified', array('{what}' => "extra attributes")));
}


function glz_reset_custom_field($name, $table) {
  if ( $table == PFX."txp_prefs" ) {
    $query = "
      UPDATE 
        `".PFX."txp_prefs`
      SET
        `val` = '',
        `html` = 'text_input'
      WHERE
        `name`='{$name}'
    ";
  }
  else if ( $table == PFX."textpattern" ) {
    $query = "
      UPDATE
        `".PFX."textpattern`
      SET
        `{$name}` = ''
    ";
  }
  
  safe_query($query);
}


function glz_delete_custom_field($name, $table) {
  // remember, custom fields under 10 MUST NOT be deleted
  if ( glz_custom_digit($name) > 10 ) {
    if ( in_array($table, array(PFX."txp_prefs", PFX."txp_lang", PFX."custom_fields")) ) {
      $query = "
        DELETE FROM 
          `{$table}`
        WHERE
          `name`='{$name}'
      ";
    }
    else if ( $table == PFX."textpattern" ) {
      $query = "
        ALTER TABLE
          `".PFX."textpattern`
        DROP
          `{$name}`
      ";
    }
    safe_query($query);
  }
  else {
    if ( $table == PFX."txp_prefs" )
      glz_custom_fields_MySQL("reset", $name, $table);
    else if ( ($table == PFX."custom_fields") ) {
      $query = "
        DELETE FROM 
          `{$table}`
        WHERE
          `name`='{$name}'
      ";
      safe_query($query);
    }
  }
}

// -------------------------------------------------------------
// checks if custom_fields table has any values in it
function glz_check_migration() {
  $query = "
    SELECT
      COUNT(*)
    FROM
      `".PFX."custom_fields`
  ";
  
  return getThing($query);
}

// -------------------------------------------------------------
// make a note of glz_custom_fields migration in txp_prefs
function glz_mark_migration() {
  $query = "
    INSERT INTO 
      `".PFX."txp_prefs` (`prefs_id`,`name`,`val`,`type`,`event`,`html`,`position`) 
    VALUES 
      ('1','glz_custom_fields_migrated','1','1','admin','text_input','0')
  ";
  
  safe_query($query);
}



#####################
##### FRONT-END #####
#####################

// -------------------------------------------------------------
/**
 * DROP-DOWN SEARCH FORM
 Call it like this: <txp:glz_custom_fields_search_form results_page="#" searchby="Area:input,City:select,Price:radio,section" section="default,articles" category="textpattern" />
 */
function glz_custom_fields_search_form($atts) {
  global $all_custom_sets, $s, $glz_search_options;
  
  $glz_search_options = array();
  
  // DEBUG
  // dmp($all_custom_sets);
  
  // we have $_POST, let's see if it comes from glz_custom_fields_search
  // DEBUG
  // dmp($_POST);
  if ( $_POST AND in_array("glz_custom_fields_search", $_POST) ) {
    // stripPost() doesn't know how to handle arrays
    foreach ($_POST as $key => $value)
      $glz_search_options[$key] = (is_array($value)) ? stripslashes(join("|", $value)) : stripslashes($value);
  }
  
  extract(lAtts(array(
    'results_page'  => "search",
    'searchby'      => "",
    'section'       => "",
    'category'      => "",
    'labels'        => 1
  ), $atts));
  
  if ( !isset($glz_search_options['section']) ) {
    if ( $section != "" )
      $glz_search_options['section'] = $section;
  }
  
  if ( !isset($glz_search_options['category']) && $category != "" && $category != "all" )
    $glz_search_options['category'] = $category;
  
  // DEBUG
  // dmp($glz_search_options);
  
  if ( !empty($searchby) ) {
    // initialize our custom search array
    $arr_query_custom = array();
    
    // see which custom sets are searchby values associated to - if any
    if ( strstr($searchby, ",") ) {
      // go through values 1 by 1 and add them to the above array
      foreach ( do_list($searchby) as $key => $query_custom ) {
        // now we have types for our search fields
        // they are separated by :
        if ( strstr($query_custom, ":") )
          list($query_custom, $query_custom_type) = explode(":", $query_custom);
        else
          // if we don't unset this, it will use the one from the previous searchby value
          unset($query_custom_type);
        
        // if this is a section or category, we just need to set the type
        if ( in_array($query_custom, array("section", "category")) ) {
          $query_custom_type = "checkbox";
          $custom = $key;
        }
        // otherwise we need to check for the type of this custom field
        else {
          // get the values we have in our Prefs for this custom set - we might not get anything back
          $custom = array_search($query_custom, $GLOBALS['prefs']);
          
           if ( $custom ) {
              if ( !isset($query_custom_type) )
                list($query_custom, $query_custom_type) = array_values($all_custom_sets[$custom]);
            }
            // if we don't get a custom back, lets default to search input
            else
              $query_custom_type = "text_input";
        }
        // add this custom set to our search array
        $arr_query_custom[$custom] = array(
          'name'  => $query_custom,
          'type'  => $query_custom_type);
      }
    }
    else
      // we are searching for a single custom set, add it to our custom sets search array
      $arr_query_custom[$searchby] = array_search($query_custom_set, $GLOBALS['prefs']);
    
    // DEBUG
    // dmp($arr_query_custom);
    
    // start our form
    $out[] = '<form method="post" action="'.hu.$results_page.'">'.n
      .'<fieldset>'.n;
    
    // build our selects
    foreach ( $arr_query_custom as $custom => $custom_search ) {
      // if the key is an integer, we're dealing with a section, category or search input
      if ( !is_int($custom) ) {
        // custom_x_set needs to be custom-x for ids - don't ask... (legacy stuff)
        $custom_id = glz_custom_number($custom, "-");
        // custom_x_set now becomes custom_x
        $custom = glz_custom_number($custom);
      }
      else {
        // set both the id and name to e.g. section or category
        $custom_id = strtolower($custom_search['name']);
        $custom = $custom_id;
      }
      
      // values for sections and categories have already been defined
      if ( in_array($custom, array("section", "category")) ) {
        if ( $$custom == "all" )
            $arr_custom_values = glz_all_searchable_sections_categories($custom);
        else
          continue;
      }
      // get all existing custom values for live articles
      else
        $arr_custom_values = glz_custom_fields_MySQL('all_values', $custom, '', array('custom_set_name' => $custom_search['name'], 'status' => 4));
      
      // DEBUG
      // dmp($arr_custom_values);
      
      if ( is_array($arr_custom_values) ) {
        $checked_value = (isset($glz_search_options[$custom])) ? $glz_search_options[$custom] : '';
        
        if ( in_array($custom, array("section", "category")) )
          $checked_value = str_replace(",", "|", $checked_value);
        
        // join the values if they are multiple ones e.g. coming in an array
        if ( is_array($checked_value) )
          $checked_value = join("|", $checked_value);
        // the way our custom field value is going to look like
        list($custom_value, $custom_class) = glz_format_custom_set_by_type($custom, $custom_id, $custom_search['type'], $arr_custom_values, $checked_value);
      
        // DEBUG
        // dmp($custom_value);
        // dmp($custom_search);
      
        $out[] = ($labels) ?
          graf("<label for=\"$custom_id\">{$custom_search['name']}</label><br />$custom_value", " class=\"$custom_class\"") :
          graf("$custom_value", " class=\"$custom_class\"");
      }
      else
        return '<p>'.glz_custom_fields_gTxt('no_articles_found').'</p></form>';
    }
    
    // end our form
    $out[] = glz_fButton("submit", "submit", "Search", "glz_custom_fields_search").n
         ."</fieldset>".n
         ."</form>".n.n;
    // DEBUG
    // dmp(join($out));

    return join($out);
  }
  else
    return trigger_error(glz_custom_fields_gTxt('searchby_not_set'));;
}



##########################
##### PRE-REQUISITES #####
##########################

// -------------------------------------------------------------
// we are setting up the pre-requisite values for glz_custom_fields
function before_glz_custom_fields() {
  // we will be reusing these globals across the whole plugin
  global $all_custom_sets, $glz_notice, $prefs;
  
  // let's initialize our glz_notice, available throughout the entire plugin
  $glz_notice = array();
  
  // let's get all custom field sets from prefs
  $all_custom_sets = glz_custom_fields_MySQL("all");
}


// -------------------------------------------------------------
// checks if custom_fields table exists
function glz_custom_fields_install() {
  global $all_custom_sets, $glz_notice, $prefs;
  
  // if jQuery is not present, trigger error
  // improvement courtesy of Sam Weiss
  if ( !file_exists($GLOBALS['txpcfg']['txpath'].'/jquery.js') ) {
    trigger_error(glz_custom_fields_gTxt('jquery_missing'));
    trigger_error(glz_custom_fields_gTxt('check_path'));
  }
  
  // if we don't have a search section, let's create it because we'll need it when searching by custom fields
  if( !getRow("SELECT name FROM `".PFX."txp_section` WHERE name='search'") ) {
    safe_query("
      INSERT INTO 
        `".PFX."txp_section` (`name`, `page`, `css`, `is_default`, `in_rss`, `on_frontpage`, `searchable`, `title`)
      VALUES
        ('search', 'default', 'default', '0', '0', '0', '0', 'Search')
    ");
    // add a notice that search section has bee created
    $glz_notice[] = glz_custom_fields_gTxt("search_section_created");
  }
  
  // if we don't have the custom_fields table, let's create it
  if ( !getRows("SHOW TABLES LIKE '".PFX."custom_fields'") ) {
    safe_query("
      CREATE TABLE `".PFX."custom_fields` (
        `name` varchar(255) NOT NULL default '',
        `value` varchar(255) NOT NULL default '',
        INDEX (`name`)
      ) TYPE=MyISAM
    ");
  }
  else {
    // if we have definitely migrated using this function, skip everything
    if ( isset($prefs['glz_custom_fields_migrated']) )
      return;
    // abort the migration if there are values in custom_fields table,
    // we don't want to overwrite anything
    else if ( glz_custom_fields_MySQL('check_migration') > 0 ) {
      // DEBUG
      // dmp(glz_custom_fields_MySQL('check_migration'));
      $glz_notice[] = glz_custom_fields_gTxt("migration_skip");
      // make a note of this migration in txp_prefs
      glz_custom_fields_MySQL('mark_migration');
      return;
    }
    
    // go through all values in custom field columns in textpattern table one by one
    foreach ($all_custom_sets as $custom => $custom_set) {
      // check only for custom fields that have been set
      if ( $custom_set['name'] ) {
        // get all existing custom values for ALL articles
        $all_values = glz_custom_fields_MySQL('all_values', glz_custom_number($custom), '', array('custom_set_name' => $custom_set['name'], 'status' => 0));
        // if we have results, let's create SQL queries that will add them to custom_fields table
        if ( count($all_values) > 0 ) {
          // initialize insert
          $insert = '';
          foreach ( $all_values as $escaped_value => $value ) {
            // don't insert empty values or values that are over 255 characters
            // values over 255 characters hint to a textarea custom field
            if ( !empty($escaped_value) && strlen($escaped_value) < 255 )
              // if this is the last value, query will have to be different
              $insert .= ( end($all_values) != $value ) ?
                "('{$custom}','{$escaped_value}')," :
                "('{$custom}','{$escaped_value}')";
          }
          $query = "
            INSERT INTO 
              `".PFX."custom_fields` (`name`,`value`)
            VALUES
              {$insert}
          ";
          if ( isset($query) && !empty($query) ) {
            // create all custom field values in custom_fields table
            safe_query($query);
            // update the type of this custom field to select (might want to make this user-adjustable at some point)
            glz_custom_fields_MySQL("update", $custom, PFX."txp_prefs", array(
              'custom_set_type'   => "select",
              'custom_set_name'   => $custom_set['name']
            ));
            $glz_notice[] = glz_custom_fields_gTxt("migration_success");
          }
        }
      }
    }
    
    // make a note of this migration in txp_prefs
    glz_custom_fields_MySQL('mark_migration');
  }
}


// -------------------------------------------------------------
// uninstalls glz_custom_fields
function glz_custom_fields_uninstall() {
  global $all_custom_sets, $glz_notice;
  
  // change all custom fields back to input
  foreach ($all_custom_sets as $custom_set) {
    glz_custom_fields_MySQL("update", $custom, PFX."txp_prefs", array(
      'custom_set_type'   => "input",
      'custom_set_name'   => $custom_set['name']
    ));
  }
  $glz_notice[] = glz_custom_fields_gTxt("custom_sets_all_input");
  
  // remove custom_fields table
  safe_query("
    DROP TABLE `".PFX."custom_fields`
  ");
  $glz_notice[] = glz_custom_fields_gTxt("custom_fields_removed");
}


// -------------------------------------------------------------
// adds the css & js we need
function glz_custom_fields_css_js($buffer) {
  global $glz_notice;
  
  $css =<<<css
<style type="text/css" media="screen">
    /* - - - - - - - - - - - - - - - - - - - - -

    ### TEXTPATTERN CUSTOM FIELDS ###

    Title : glz_custom_fields stylesheet
    Author : Gerhard Lazu
    URL : http://www.gerhardlazu.com/ & http://www.calti.co.uk
    
    Created : 14th May 2007
    Last modified: 22nd April 2009
    
    - - - - - - - - - - - - - - - - - - - - - */
    
    
    /* CLASSES
    -------------------------------------------------------------- */
    .green {
      color: #6B3;
    }
    .red {
      color: #C00;
    }
    
    
    /* TABLE
    -------------------------------------------------------------- */
    #glz_custom_fields {
      width: 50em;
      margin: 0 auto;
      border: 1px solid #DDD;
    }
    
    #glz_custom_fields thead tr {
      font-size: 1.2em;
      font-weight: 700;
      background: #EEE;
    }
    
    #glz_custom_fields tbody tr.alt {
      background: #FFC;
    }
    #glz_custom_fields tbody tr.over {
      background: #FF6;
    }
    
    #glz_custom_fields td {
      padding: 0.2em 1em;
      vertical-align: middle;
    }
    #glz_custom_fields thead td {
      padding: 0.3em 0.8em;
    }
    #glz_custom_fields td.custom_set {
      width: 8em;
    }
    #glz_custom_fields td.custom_set_name {
      width: 18em;
    }
    #glz_custom_fields td.type {
      width: 6em;
    }
    #glz_custom_fields td.events {
      width: 12em;
      text-align: right;
    }
    
    
    /* FORMS
    -------------------------------------------------------------- */
    input:focus,
    select:focus,
    textarea:focus {
      background: #FFC;
    }
    
    #glz_custom_fields td.events form {
      display: inline;
    }
    
    #add_edit_custom_field {
      width: 50em;
      margin: 2em auto 0 auto;
    }
    #add_edit_custom_field legend {
      font-size: 1.2em;
      font-weight: 700;
    }
    #add_edit_custom_field label {
      float: left;
      width: 10em;
      font-weight: 700;
    }
    #add_edit_custom_field p input,
    #add_edit_custom_field p select {
      width: 20em;
    }
    #add_edit_custom_field p textarea {
      width: 30em;
      height: 10em;
    }
    #add_edit_custom_field p em {
      font-size: 0.9em;
      font-weight: 500;
      color: #777;
    }
    #add_edit_custom_field input.publish {
      margin-left: 11em;
    }
    
    /* select on write tab for the custom fields */
    td#article-col-1 #advanced p select.list {
      width: 100%;
    }
    
    td#article-col-1 #advanced p input.radio,
    td#article-col-1 #advanced p input.checkbox {
      width: auto;
    }
    
  </style>
css;
  
  $js =<<<js
<script type="text/javascript">
  <!--//--><![CDATA[//><!--
  
  $(document).ready(function() {
    // sweet jQuery table striping
    $(".stripeMe tr").mouseover(function() { $(this).addClass("over"); }).mouseout(function() { $(this).removeClass("over"); });
    $(".stripeMe tr:even").addClass("alt");
    
    // disable all custom field references in Advanced Prefs
    var custom_field_tr = $("tr:has(label[for*=custom_]), tr:has(h2:contains('Custom Fields'))");
    if ( custom_field_tr ) {
      $.each (custom_field_tr, function() {
        $(this).hide();
      });
    };
    
    // toggle custom field value based on its type
    if ( $("select#type option[@selected]").attr("value") == "text_input" ) {
      custom_field_value_off();
    };
    
    $("select#type").click( function() {
      if ( $("select#type option[@selected]").attr("value") != "text_input" && !$("textarea#value").length ) {
        custom_field_value_on();
      }
      else if ( $("select#type option[@selected]").attr("value") == "text_input" && !$("input#value").length ) {
        custom_field_value_off();
      }
    });
    
    // let's have Advanced Options displayed by default - why hide them in the first place?
    $('#advanced').show();
    
    
    // ### RE-USABLE FUNCTIONS ###
    
    function custom_field_value_off() {
      $("label[@for=value] em").hide();
      $("textarea#value").remove();
      $("label[@for=value]").after('<input id="value" value="no value allowed" name="value"/>');
      $("input#value").attr("disabled", "disabled");
    }
    
    function custom_field_value_on() {
      $("label[@for=value] em").show();
      $("input#value").remove();
      $("label[@for=value]").after('<textarea id="value" name="value"></textarea>');
    }
    
  });
  
  //--><!]]>
  </script>
js;
  
  // displays the notices we have gathered throughout the entire plugin
  if ( count($glz_notice) > 0 ) {
    // let's turn our notices into a string
    $glz_notice = join("<br />", array_unique($glz_notice));

    $noticejs = '<script type="text/javascript">
    <!--//--><![CDATA[//><!--

    $(document).ready(function() {
      // add our notices
      $("#nav-primary table tbody tr td:first").html(\''.$glz_notice.'\');
    });
    //--><!]]>
    </script>';
  }
  else {
    $noticejs = "";
  }
  
  
  $find = '</head>';
  $replace = $js.n.t.$noticejs.n.t.$css.n.t.$find;

  return str_replace($find, $replace, $buffer);
}

?>