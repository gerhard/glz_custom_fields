$(function() {
  // creating a global object to store variables, functions etc.
  var GLZ_CUSTOM_FIELDS;
  if (GLZ_CUSTOM_FIELDS == undefined)
    GLZ_CUSTOM_FIELDS = {};
  GLZ_CUSTOM_FIELDS.special_custom_types = ["date-picker", "time-picker"];
  GLZ_CUSTOM_FIELDS.no_value_custom_types = ["text_input", "textarea"];
  GLZ_CUSTOM_FIELDS.messages = {
    'textarea' : "<em>Each value on a separate line</em><br /><em>One {default} value allowed</em>",
    'script' : "<em>File name in your custom scripts path</em>"
  }

  // disable all custom field references in Advanced Prefs
  // prefs_ui doesn't offer support for this, getting the custom fields to display right here is not crucial at this point
  var custom_field_tr = $("tr[id*=prefs-custom], tr[id*=custom_fields], tr:has(h3[class*=custom-prefs])");
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
      $("label[for=value]").after('<input type="text" id="value" name="value" class="left" />');
    $("input#value").attr('value', "no value allowed").attr('disabled', true);
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
      $("label[for=value]").after('<input type="text" id="value" name="value" class="left" />');
    if ( $.inArray($("input#value").attr('value'), ["", "no value allowed"]) != -1 )
      $("input#value").attr('value', "");
    $("input#value").attr('disabled', false);
    $("input#value + span.right").html(GLZ_CUSTOM_FIELDS.messages['script']);
  }

  function toggle_type_link() {
    $("select#custom_set_type").parent().find('span').remove();
    if ( $.inArray($("select#custom_set_type :selected").attr("value"), [].concat(GLZ_CUSTOM_FIELDS.special_custom_types, ["multi-select", "custom-script"])) != -1 )
      $("select#custom_set_type").after("<span class=\"right\"><em><a href=\"http://"+window.location.host+window.location.pathname+"?event=plugin_prefs.glz_custom_fields\">Configure glz_custom_fields</a></em></span>");
  }

});
