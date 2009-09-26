<?php

// I would do this through a factory class, but some folks are still running PHP4...
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
        glz_new_custom_field($name, $table, $extra);
        glz_custom_fields_update_count();
        break;

      case 'update':
        return glz_update_custom_field($name, $table, $extra);
        break;

      case 'reset':
        return glz_reset_custom_field($name, $table);
        break;

      case 'delete':
        glz_delete_custom_field($name, $table);
        glz_custom_fields_update_count();
        break;

      case 'check_migration':
        return glz_check_migration();
        break;

      case 'mark_migration':
        return glz_mark_migration();
        break;

      case 'unmark_migration':
        return glz_unmark_migration();
        break;

      case 'custom_set_exists':
        return glz_check_custom_set_exists($name);
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
          `custom_{$custom_field_number}` varchar(16383) NOT NULL DEFAULT ''
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
      safe_query("
        UPDATE
            `".PFX."txp_prefs`
          SET
            `val` = '{$custom_set_name}',
            `html` = '{$custom_set_type}'
          WHERE
            `name`='{$name}'
      ");
    }
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
      safe_query("
        DELETE FROM
          `{$table}`
        WHERE
          `name`='{$name}'
      ");
    }
  }
}


// -------------------------------------------------------------
// checks if custom_fields table has any values in it
function glz_check_migration() {
  return getThing("
    SELECT
      COUNT(*)
    FROM
      `".PFX."custom_fields`
  ");
}


// -------------------------------------------------------------
// make a note of glz_custom_fields migration in txp_prefs
function glz_mark_migration() {
  safe_query("
    INSERT INTO
      `".PFX."txp_prefs` (`prefs_id`,`name`,`val`,`type`,`event`,`html`,`position`)
    VALUES
      ('1','glz_custom_fields_migrated','1','1','admin','text_input','0')
  ");
}


// -------------------------------------------------------------
// remove glz_custom_fields migration from txp_prefs if it's been set
function glz_unmark_migration() {
  if (getRows("SELECT * FROM '".PFX."txp_prefs' WHERE `name`='glz_custom_fields_migrated'")) {
    safe_query("
      DELETE FROM
        `".PFX."txp_prefs`
      WHERE
        `name`='glz_custom_fields_migrated'
    ");
  }
}


// -------------------------------------------------------------
// check if one of the special custom fields exists
function glz_check_custom_set_exists($name) {
  if ( !empty($name) ) {
    return getThing("
      SELECT
        `name`, `val`
      FROM
        `".PFX."txp_prefs`
      WHERE
        `html` = '{$name}'
      AND
        `name` LIKE 'custom_%'
      ORDER BY
        `name`
    ");
  }
}


// -------------------------------------------------------------
// updates max_custom_fields
function glz_custom_fields_update_count() {
  set_pref('max_custom_fields', safe_count("txp_prefs", "event='custom'"));
}

?>

