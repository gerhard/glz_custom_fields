<?php

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
    $out[] = '<form method="post" action="'.hu.$results_page.'" id="glz_custom_fields_search">'.n
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
    return trigger_error(glz_custom_fields_gTxt('searchby_not_set'));
}


?>
