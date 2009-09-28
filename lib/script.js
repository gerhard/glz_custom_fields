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

