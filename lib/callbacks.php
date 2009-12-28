<?php

// -------------------------------------------------------------
// replaces the default custom fields under write tab
function glz_custom_fields_replace($event, $step, $data, $rs) {
  global $all_custom_sets, $date_picker;
  // get all custom fields & keep only the ones which are set, filter by step
  // and return date picker custom fields only if jquery.datePicker is in the right location
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

      // if current article holds no value for this custom field and we have no default value, make it empty
      $custom_value = (!empty($$custom) ? $$custom : '');
      // DEBUG
      // dmp("custom_value: {$custom_value}");

      // check if there is a default value
      // if there is, strip the { }
      $default_value = glz_return_clean_default(glz_default_value($arr_custom_field_values));
      // DEBUG
      // dmp("default_value: {$default_value}");

      // now that we've found our default, we need to clean our custom_field values
      if (is_array($arr_custom_field_values))
        array_walk($arr_custom_field_values, "glz_clean_default_array_values");

      // DEBUG
      // dmp($arr_custom_field_values);

      // the way our custom field value is going to look like
      list($custom_set_value, $custom_class) = glz_format_custom_set_by_type($custom, $custom_id, $custom_set['type'], $arr_custom_field_values, $custom_value, $default_value);

      // DEBUG
      // dmp($custom_set_value);

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
// adds the css & js we need
function glz_custom_fields_css_js() {
  global $glz_notice, $date_picker, $time_picker, $prefs;

  // here come our custom stylesheetz
  $css = <<<EOF
<style type="text/css" media="all">
/* - - - - - - - - - - - - - - - - - - - - -

### TEXTPATTERN CUSTOM FIELDS ###

Title : glz_custom_fields stylesheet
Author : Gerhard Lazu
URL : http://www.gerhardlazu.com/

Created : 14th May 2007
Last modified: 28th September 2009

- - - - - - - - - - - - - - - - - - - - - */

.clearfix:after {
  content: ".";
  display: block;
  clear: both;
  visibility: hidden;
  line-height: 0;
  height: 0;
}

.clearfix {
  display: inline-block;
}

html[xmlns] .clearfix {
  display: block;
}

* html .clearfix {
  height: 1%;
}

/* CLASSES
-------------------------------------------------------------- */
.green {
  color: #6B3;
}
.red {
  color: #C00;
}
.left {
  float: left;
}
.right {
  float: right;
}


/* TABLE
-------------------------------------------------------------- */
.glz_custom_fields {
  margin: 0 auto;
  border: 1px solid #DDD;
  width: 600px;
}

.glz_custom_fields thead tr {
  font-size: 1.1em;
  font-weight: 700;
  background: #DDD;
}

.glz_custom_fields tbody tr.alt {
  background: #EEE;
}

.glz_custom_fields td {
  padding: 2px 10px;
  vertical-align: middle;
}
.glz_custom_fields thead td {
  padding: 2px 8px 3px 8px;
}
.glz_custom_fields td.custom_set_position {
  text-align: right;
  width: 10%;
}
.glz_custom_fields td.custom_set_name {
  width: 30%;
}
.glz_custom_fields td.type {
  width: 30%;
}
.glz_custom_fields td.events {
  width: 30%;
  text-align: right;
}

.glz_custom_fields_prefs {
  width: 500px;
}
.glz_custom_fields_prefs#list tr th {
  font-weight: normal;
  text-align: right;
  vertical-align: top;
  width: 50%;
}
.glz_custom_fields_prefs#list tr.heading td {
  padding-top: 30px;
}
.glz_custom_fields_prefs#list tr.heading td h2 {
  margin-top: 0
}
.glz_custom_fields_prefs tr td select,
.glz_custom_fields_prefs tr td input {
  width: 100%;
}
.glz_custom_fields_prefs tr td input.publish {
  margin-left: 51%;
  width: auto;
}



/* FORMS
-------------------------------------------------------------- */
.glz_custom_fields td.events form {
  display: inline;
}

#add_edit_custom_field {
  width: 600px;
  margin: 2em auto 0 auto;
}
#add_edit_custom_field fieldset {
  padding: 10px;
}
#add_edit_custom_field legend {
  font-size: 1.1em;
  font-weight: 700;
}
#add_edit_custom_field label {
  width: 20%;
  font-weight: 700;
}
#add_edit_custom_field p input,
#add_edit_custom_field p select {
  width: 40%;
}
#add_edit_custom_field p textarea {
  font-size: 0.9em;
  padding: 1px 0 1px 3px;
  width: 40%; height: 100px;
}
#add_edit_custom_field p span {
  padding-top: 3px;
  width: 210px;
}
#add_edit_custom_field p em,
.glz_custom_fields_prefs tr em {
  font-size: 0.9em;
  font-weight: 500;
  color: #777;
}
#add_edit_custom_field input.publish {
  margin-left: 110px;
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
EOF;
  // and here come our javascriptz
  $js = '';
  $date_picker_js = '';
  if ( $date_picker ) {
    $css .= '<link rel="stylesheet" type="text/css" media="screen" href="'.hu.'scripts/jquery.datePicker/datePicker.css" />'.n;
    foreach (array('date.js', 'datePicker.js') as $file) {
      $js .= '<script type="text/javascript" src="'.hu.'scripts/jquery.datePicker/'.$file.'"></script>'.n;
    }
    $date_picker_js = <<<EOF
try {
  Date.firstDayOfWeek = {$prefs['datepicker_first_day']};
  Date.format = '{$prefs['datepicker_format']}';
  Date.fullYearStart = '19';
  $(".date-picker").datePicker({startDate:'{$prefs['datepicker_start_date']}'});
} catch(err) {
  $('#messagepane').html('<a href="http://{$prefs['siteurl']}/textpattern/?event=plugin&amp;step=plugin_help&amp;name=glz_custom_fields">Please configure the jQuery DatePicker plugin</a>');
}
EOF;
  }
  $time_picker_js = '';
  if ( $time_picker ) {
    $css .= '<link rel="stylesheet" type="text/css" media="screen" href="'.hu.'scripts/jquery.timePicker/timePicker.css" />'.n;
    $js .= '<script type="text/javascript" src="'.hu.'scripts/jquery.timePicker/timePicker.js"></script>'.n;
    $time_picker_js = <<<EOF
try {
  $(".time-picker").timePicker({
    startTime:'{$prefs['timepicker_start_time']}',
    endTime: '{$prefs['timepicker_end_time']}',
    step: {$prefs['timepicker_step']},
    show24Hours: {$prefs['timepicker_show_24']}
  });
} catch(err) {
  $('#messagepane').html('<a href="http://{$prefs['siteurl']}/textpattern/?event=plugin&amp;step=plugin_help&amp;name=glz_custom_fields">Please configure the jQuery TimePicker plugin</a>');
}
EOF;
  }
  $js .= <<<EOF
<script type="text/javascript">
<!--//--><![CDATA[//><!--

$(function() {
  // creating a global object to store variables, functions etc.
  var GLZ_CUSTOM_FIELDS;
  if (GLZ_CUSTOM_FIELDS == undefined)
    GLZ_CUSTOM_FIELDS = {};
  GLZ_CUSTOM_FIELDS.special_custom_types = ["date-picker", "time-picker"];
  GLZ_CUSTOM_FIELDS.no_value_custom_types = ["text_input", "textarea"];
  GLZ_CUSTOM_FIELDS.messages = {
    'textarea' : "<em>Each value on a separate line</em><br /><em>One {default} value allowed</em>",
    'script' : "<em>Full path to your script</em>"
  }

  // sweet jQuery table striping
  $(".stripeMe tr").mouseover(function() { $(this).addClass("over"); }).mouseout(function() { $(this).removeClass("over"); });
  $(".stripeMe tr:even").addClass("alt");

  // disable all custom field references in Advanced Prefs
  // prefs_ui doesn't offer support for this, getting the custom fields to display right here is not crucial at this point
  var custom_field_tr = $("tr[id*=prefs-custom_], tr:has(h2:contains('Custom Fields'))").not('.glz_custom_fields_prefs tr:has(h2)');
  if ( custom_field_tr ) {
    $.each (custom_field_tr, function() {
      $(this).hide();
    });
  }

  // toggle custom field value based on its type
  
  toggle_type_link();
  if ( $.inArray($("select#custom_set_type :selected").attr("value"), [].concat(GLZ_CUSTOM_FIELDS.special_custom_types, GLZ_CUSTOM_FIELDS.no_value_custom_types)) != -1 ) {
    custom_field_value_off();
  }
  else if ( $("select#custom_set_type :selected").attr("value") == "custom-script" )
    custom_field_value_path();

  $("select#custom_set_type").change( function() {
    toggle_type_link();
    if ( $.inArray($("select#custom_set_type :selected").attr("value"), [].concat(GLZ_CUSTOM_FIELDS.special_custom_types, GLZ_CUSTOM_FIELDS.no_value_custom_types)) != -1 ) {
      custom_field_value_off();
    }
    else if ( $("select#custom_set_type :selected").attr("value") == "custom-script" )
      custom_field_value_path();
    else {
      custom_field_value_on();
    }
  });

  // enable date-picker custom sets
  {$date_picker_js}
  
  // enable time-picker custom sets
  {$time_picker_js}

  // add a reset link to all radio custom fields
  if ($(".glz_custom_radio_field").length > 0) {
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
  }

  // ### RE-USABLE FUNCTIONS ###

  function custom_field_value_off() {
    if ($("textarea#value").length) {
      GLZ_CUSTOM_FIELDS.textarea_value = $("textarea#value").html();
      $("textarea#value + span.right").html('');
      $("textarea#value").remove();
    }
    
    if (!$("input#value").length)
      $("label[for=value]").after('<input id="value" name="value" class="left" />');
    $("input#value").attr('value', "no value allowed").attr('disabled', "disabled");
    $("input#value + span.right").html('');
  }

  function custom_field_value_on() {
    if ( $("input#value").length )
      $("input#value").remove();
    if ( !$("textarea#value").length ) {
      $("label[for=value]").after('<textarea id="value" name="value" class="left"></textarea>');
      $("textarea#value + span.right").html(GLZ_CUSTOM_FIELDS.messages['textarea']);
    }
    if ( GLZ_CUSTOM_FIELDS.textarea_value )
      $("textarea#value").html(GLZ_CUSTOM_FIELDS.textarea_value);
  }

  function custom_field_value_path() {
    if ($("textarea#value").length) {
      $("textarea#value + span.right").html('');
      $("textarea#value").remove();
    }
    if (!$("input#value").length)
      $("label[for=value]").after('<input id="value" name="value" class="left" />');
    if ( $.inArray($("input#value").attr('value'), ["", "no value allowed"]) != -1 )
      $("input#value").attr('value', "{$prefs['path_to_site']}/scripts/")
    $("input#value").attr('disabled', "");
    $("input#value + span.right").html(GLZ_CUSTOM_FIELDS.messages['script']);
  }

  function toggle_type_link() {
    $("select#custom_set_type").parent().find('span').remove();
    if ( $.inArray($("select#custom_set_type :selected").attr("value"), [].concat(GLZ_CUSTOM_FIELDS.special_custom_types, ["multi-select"])) != -1 )
      $("select#custom_set_type").after("<span class=\"right\"><em><a href=\"http://{$prefs['siteurl']}/textpattern?event=plugin_prefs.glz_custom_fields\">Configure plugin</a></em></span>");
  }

});

//--><!]]>
</script>
EOF;

  // displays the notices we have gathered throughout the entire plugin
  if ( count($glz_notice) > 0 ) {
    // let's turn our notices into a string
    $glz_notice = join("<br />", array_unique($glz_notice));

    $js .= '<script type="text/javascript">
    <!--//--><![CDATA[//><!--

    $(document).ready(function() {
      // add our notices
      $("#messagepane").html(\''.$glz_notice.'\');
    });
    //--><!]]>
    </script>';
  }

  echo $js.n.t.$css.n.t;
}


// -------------------------------------------------------------
// we are setting up the pre-requisite values for glz_custom_fields
function before_glz_custom_fields() {
  // we will be reusing these globals across the whole plugin
  global $all_custom_sets, $glz_notice, $prefs, $date_picker, $time_picker;

  // glz_notice collects all plugin notices
  $glz_notice = array();

  // let's get all custom field sets from prefs
  $all_custom_sets = glz_custom_fields_MySQL("all");

  // let's see if we have a date-picker custom field (first of the special ones)
  $date_picker = glz_custom_fields_MySQL("custom_set_exists", "date-picker");
  
  // let's see if we have a time-picker custom field
  $time_picker = glz_custom_fields_MySQL("custom_set_exists", "time-picker");
}


// -------------------------------------------------------------
// bootstrapping routines, run through plugin_lifecycle
function glz_custom_fields_install() {
  global $all_custom_sets, $glz_notice, $prefs;

  // if jQuery is not present, trigger error
  // improvement courtesy of Sam Weiss
  if ( !file_exists($GLOBALS['txpcfg']['txpath'].'/jquery.js') ) {
    trigger_error(glz_custom_fields_gTxt('jquery_missing'));
    trigger_error(glz_custom_fields_gTxt('check_path'));
  }

  // default custom fields are set to custom_set
  // need to change this because it confuses our set_types()
  safe_query("
    UPDATE
      `".PFX."txp_prefs`
    SET
      `html` = 'text_input'
    WHERE
      `event` = 'custom'
    AND
      `html` = 'custom_set'
  ");

  // set plugin preferences
  $arr_plugin_preferences = array(
    'values_ordering'       => "custom",
    'multiselect_size'      => "5",
    'datepicker_format'     => "dd/mm/yyyy",
    'datepicker_first_day'  => 1,
    'datepicker_start_date' => "01/01/1990",
    'timepicker_start_time' => "00:00",
    'timepicker_end_time'   => "23:30",
    'timepicker_step'       => 30,
    'timepicker_show_24'    => true
  );
  glz_custom_fields_MySQL("update_plugin_preferences", $arr_plugin_preferences);

  // let's update plugin preferences, make sure they won't appear under Admin > Preferences
  safe_query("
    UPDATE
      `".PFX."txp_prefs`
    SET
      `type` = '10'
    WHERE
      `event` = 'glz_custom_f'
  ");

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
      `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
       `name` varchar(255) NOT NULL default '',
       `value` varchar(255) NOT NULL default '',
       PRIMARY KEY (id),
       INDEX (`name`)
     ) TYPE=MyISAM
   ");
  }
  else {
    // if there isn't and id column, add it
    if ( !getRows("SHOW COLUMNS FROM ".PFX."custom_fields LIKE 'id'") ) {
      safe_query("
        ALTER TABLE `".PFX."custom_fields`
          ADD `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT KEY
      ");
    }

   // if we have definitely migrated using this function, skip everything
   if ( isset($prefs['migrated']) )
     return;
   // abort the migration if there are values in custom_fields table, we don't want to overwrite anything
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
             'custom_set_name'   => $custom_set['name'],
             'custom_set_type'   => "select",
             'custom_set_position' => $custom_set['position']
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

?>

