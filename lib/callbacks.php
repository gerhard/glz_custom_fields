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
      $default_value = glz_default_value($arr_custom_field_values);
      // DEBUG
      // dmp("default_value: {$default_value}");

      // now that we've found our default, we need to clean our custom_field values
      if (is_array($arr_custom_field_values))
        array_walk($arr_custom_field_values, "glz_clean_default_array_values");

      // the way our custom field value is going to look like
      list($custom_set_value, $custom_class) = glz_format_custom_set_by_type($custom, $custom_id, $custom_set['type'], $arr_custom_field_values, $custom_value, $default_value);

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
// adds the css & js we need
function glz_custom_fields_css_js() {
  global $glz_notice, $date_picker, $prefs;

  // here come our custom stylesheetz
  $css = <<<EOF
<style type="text/css" media="screen">
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
#glz_custom_fields td.custom_set_position {
  width: 5em;
}
#glz_custom_fields td.custom_set_name {
  width: 14em;
}
#glz_custom_fields td.type {
  width: 7em;
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
EOF;
  if ( $date_picker )
    $css .= '<link rel="stylesheet" type="text/css" media="screen" href="'.hu.'scripts/jquery.datePicker/datePicker.css" />'.n;

  // and here come our javascriptz
  $js = '';
  if ( $date_picker ) {
    foreach (array('date.js', 'jquery.datePicker.js') as $file) {
      $js .= '<script type="text/javascript" src="'.hu.'scripts/jquery.datePicker/'.$file.'"></script>'.n;
    }
  }
  $js .= <<<EOF
<script type="text/javascript">
<!--//--><![CDATA[//><!--

$(document).ready(function() {
  // sweet jQuery table striping
  $(".stripeMe tr").mouseover(function() { $(this).addClass("over"); }).mouseout(function() { $(this).removeClass("over"); });
  $(".stripeMe tr:even").addClass("alt");

  // disable all custom field references in Advanced Prefs
  // prefs_ui doesn't offer support for this, getting the custom fields to display right here is not crucial at this point
  var custom_field_tr = $("tr[id*=prefs-custom_], tr:has(h2:contains('Custom Fields'))");
  if ( custom_field_tr ) {
    $.each (custom_field_tr, function() {
      $(this).hide();
    });
  };

  // toggle custom field value based on its type
  special_custom_types = ["text_input", "date-picker", "textarea"];
  if ( $.inArray($("select#type option[selected]").attr("value"), special_custom_types) != -1 ) {
    custom_field_value_off();
  };

  $("select#type").click( function() {
    if ( $.inArray($("select#type option[selected]").attr("value"), special_custom_types) == -1 && !$("textarea#value").length ) {
      custom_field_value_on();
    }
    else if ( $.inArray($("select#type option[selected]").attr("value"), special_custom_types) != -1 && !$("input#value").length ) {
      custom_field_value_off();
    }
  });

  // enable date-picker custom sets
  try {
    Date.firstDayOfWeek = {$prefs['datepicker_first_day']};
    Date.format = '{$prefs['datepicker_format']}';
    $(".date-picker").datePicker();
  } catch(err) {
    console.error("Please download and enable the jQuery DatePicker plugin (check glz_custom_fields help for more information). http://www.kelvinluck.com/assets/jquery/datePicker");
  }

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
    $("label[for=value] em").hide();
    $("textarea#value").remove();
    $("label[for=value]").after('<input id="value" value="no value allowed" name="value"/>');
    $("input#value").attr("disabled", "disabled");
  }

  function custom_field_value_on() {
    $("label[for=value] em").show();
    $("input#value").remove();
    $("label[for=value]").after('<textarea id="value" name="value"></textarea>');
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
  global $all_custom_sets, $glz_notice, $prefs, $date_picker;

  // glz_notice collects all plugin notices
  $glz_notice = array();

  // let's get all custom field sets from prefs
  $all_custom_sets = glz_custom_fields_MySQL("all");

  // let's see if we have a date-picker custom field (first of the special ones)
  $date_picker = glz_custom_fields_MySQL("custom_set_exists", "date-picker");
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

  // we know this routine hasn't run, ther are no plugin preferences in the db
  if ( !isset($prefs['values_ordering']) ) {
    // default plugin preferences
    $arr_plugin_preferences = array(
      'values_ordering' => "custom",
      'datepicker_format' => "dd/mm/yyyy",
      'datepicker_first_day' => 1
    );
    glz_custom_fields_MySQL("update_plugin_preferences", $arr_plugin_preferences);
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


// -------------------------------------------------------------
// uninstalls glz_custom_fields
#function glz_custom_fields_uninstall() {
#  global $all_custom_sets, $glz_notice;

#  // change all custom fields back to custom_set
#  foreach ($all_custom_sets as $custom_set) {
#    glz_custom_fields_MySQL("update", $custom, PFX."txp_prefs", array(
#      'custom_set_name'   => $custom_set['name'],
#      'custom_set_type'   => "custom_set",
#      'custom_set_position' => $custom_set['position']
#    ));

#    // change all custom field columns back to varchar
#    glz_custom_fields_MySQL("update", $custom, PFX."textpattern", array(
#      'custom_set_type' => "custom_set",
#      'custom_field'    => glz_custom_number($custom)
#    ));
#  }

#  // update custom_fields number
#  glz_custom_fields_update_count();

#  $glz_notice[] = glz_custom_fields_gTxt("custom_sets_all_input");

#  safe_query("
#    DROP TABLE `".PFX."custom_fields`
#  ");

#  glz_custom_fields_MySQL("remove_plugin_preferences");
#}

?>
