//<?php
// ----------------------------------------------------
// Example public side tags

  // A simple self-closing tag
  // <txp:zem_hello_world name="Bob" />

  function zem_hello_world($atts) {
    extract(lAtts(array(
      'name'  => 'Alice',
    ),$atts));

    // The returned value will replace the tag on the page
    return 'Hello, '.$name;
  }

  // A simple enclosing tag
  // <txp:zem_lowercase>I LIKE SPAM</txp:lowercase>

  function zem_lowercase($atts, $thing='') {
    return strtolower(parse($thing));
  }

  // A simple conditional tag
  // <txp:zem_if_alice name="Alice">
  // Alice!
  // <txp:else />
  // Not alice.
  // </txp:zem_if_alice>

  function zem_if_alice($atts, $thing) {
    extract(lAtts(array(
      'name'  => 'Bob',
    ),$atts));

    return parse(EvalElse($thing, ($name == 'Alice')));
  }

// ----------------------------------------------------
// Example admin side plugin

  // Add a new tab to the Content area.
  // "test" is the name of the associated event; "testing" is the displayed title
  if (@txpinterface == 'admin') {
    $myevent = 'test';
    $mytab = 'testing';

    // Set the privilege levels for our new event
    add_privs($myevent, '1,2');

    // Add a new tab under 'extensions' associated with our event
    register_tab("extensions", $myevent, $mytab);

    // 'zem_admin_test' will be called to handle the new event
    register_callback("zem_admin_test", $myevent);
    // 'zem_admin_test_lifecycle' will be called on plugin installation, activation, disactivation, and deletion
    register_callback("zem_admin_test_lifecycle", "plugin_lifecycle.zem_plugin_example");
  }

  function zem_admin_test($event, $step) {
    // ps() returns the contents of POST vars, if any
    $something = ps("something");
    pagetop("Testing", (ps("do_something") ? "you typed: $something" : ""));

    // The eInput/sInput part of the form is important, setting the event and step respectively

    echo "<div align=\"center\" style=\"margin-top:3em\">";
    echo form(
      tag("Test Form", "h3").
      graf("Type something: ".
        fInput("text", "something", $something, "edit", "", "", "20", "1").
        fInput("submit", "do_something", "Go", "smallerbox").
        eInput("test").sInput("step_a")
      ," style=\"text-align:center\"")
    );
    echo "</div>";
  }

  // Act upon activation/deactivation, installtion/deletion.
  // $event will be "plugin_lifecycle.zem_plugin_example"
  // $step will be one of "installed", "enabled", disabled", and "deleted"
  function zem_admin_test_lifecycle($event, $step) {
    // View source to see the output
    echo comment("$event $step").n;
  }

