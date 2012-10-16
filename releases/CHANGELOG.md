### What's new in v1.4.0

All users tracking (livestats) has been completely removed. The feature
didn't prove as useful as I've hoped, there was no point in keeping it
around.

Textpattern 4.5 compatibility.



### What's new in v1.3.0

As a word of caution, all existing plugin preferences will be 
overwritten on install. This is related to the way path/urls are now 
saved in the db.

Also, the scripts folder has now been renamed to plugins. You should use
it for all other TXP plugins that you don't want to store in the db.
Plugins is more descriptive to its purpose.

There is a livestats functionality which is disabled by default. You
can follow the controversy that it raised starting with this forum post:
http://forum.textpattern.com/viewtopic.php?pid=250362#p250362

You can enable it from the plugin preferences page.

Now for the fixes:

* no longer checking if jQuery is present - has been present in TXP 
for a very long time now 
* fixed installation issues on TXP 4.4.1
* include\_once fix for custom scripts
* CSS &amp; JS loads on the default page, right after user logs in GH-21
* fixed editing of custom script names
* CSS &amp; JS code now in separate files
* fixes duplicate excerpt bug when used together with MLP GH-23
* disabling excerpts won't affect textarea custom fields, they are now
  placed right after the article body GH-26
