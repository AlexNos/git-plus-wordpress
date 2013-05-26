=== Plugin Name ===
Contributors: ericlewis
Tags: debug, template, trace
Requires at least: 3.3
Tested up to: 3.3.1
Stable tag: 0.1

Adds a template trace panel to the Debug Bar. Requires the Debug Bar plugin. 

== Description ==

This plugin will show you what template files are loaded from your theme (or plugins) in order. To achieve this a filter is inserted into load_template(), which is a completely safe addition to the function.

== Installation ==

e.g.

1. Download and activate the Debug Bar plugin
2. Download and activate the Debug Bar Template Trace plugin
3. You should get an admin nag if the wp-includes/theme.php file is not read/writeable. If not add this line in the load_template() function: `$_template_file = apply_filters('load_template', $_template_file );`

== Screenshots ==

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Note that the screenshot is taken from
the directory of the stable readme.txt, so in this case, `/tags/4.3/screenshot-1.png` (or jpg, jpeg, gif)

== Changelog ==

= 0.1.1 = 
* Hook onto template_include as well

= 0.1 =
* Initial version
