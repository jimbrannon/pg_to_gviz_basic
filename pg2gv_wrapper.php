<?php
/*
 * a simple project specific wrapper for the pg_to_gviz_basic DMI
 * 
 * put the default values in here and pass them to pg_to_gviz_basic()
 * this is so ya don't get tempted to hardwire project data or project business logic into the generic universal DMI code
 * and therefore have to make lots of copies of this code
 * and therefore have a DMI code version control nightmare in the future when bug fixes and/or new features are added to the original DMI code
 * but not to all your project copies of the code
 */
// point this to the pg_to_gviz_basic.php code you checked out of git (don't mess with this code)
include "pg_to_gviz_basic.php";
// define the default values you want to use
$max_num_fields = 20; // an integer = 20 is the official default
$db_host = ""; // a string = "" is the official default
$db_port = 5432; // an integer = 5432 is the official default
$db_user = ""; // a string = "" is the official default
$db_password = ""; // a string = "" is the official default
$db_name = ""; // a string = "" is the official default
$debug_arg = "false"; // a string = "false" is the official default
$silent_debug_arg = "false"; // a string = "false" is the official default
$output_format_arg = "json"; // a string = "json" is the official default so it always works with gviz
$output_gv_type = "table"; // a string = "table" is the official default - no restrictions on column order or column types on tables
$output_type = "generic"; // a string = "generic" is the official default
$output_fieldtypes = ""; // an empty string = "" is the default, meaning to use the default pg to gviz mapping
$data_table_name = ""; // a string = "" is the official default
$category_table_name = ""; // a string = "" is the official default
$category_index_field = ""; // a string = "" is the official default
$category_index_selections = ""; // a string = "" is the official default
$category_label_field = ""; // a string = "" is the official default
$category_show_all_arg = "false"; // a string = "false" is the official default
$category_min = ""; // a string = "" is the official default
$category_max = ""; // a string = "" is the official default
$series_table_name = ""; // a string = "" is the official default
$series_fields = ""; // a string = "" is the official default -  comma delimited, NO SPACES!!
$series_index_field = ""; // a string = "" is the official default
$series_index_selections = ""; // a string = "" is the official default
$series_label_field = ""; // a string = "" is the official default
$series_value_field = ""; // a string = "" is the official default
$series_show_all_arg = "false"; // a string = "false" is the official default
$filter_index_field = ""; // a string = "" is the official default
$filter_index_selections = ""; // a string = "" is the official default
$drupal_user_id_field = ""; // a string = "" is the official default
$drupal_user_id = 0; // an integer = 0 is the official default
$category_axis_label = ""; // a string = "" is the official default
$series_axis_label = ""; // a string = "" is the official default
$conv_factor = 1.0; // a real = 1.0 is the official default
$precision = 99; // an integer = 99 is the official default
/*
 * call the pg_to_gviz_basic function
 * 
 * it outputs to standard out, so no need to handle a return value in this case
 * the return will be a boolean whether it worked or not, handle it if you want to
 */
$result = pg_to_gviz_basic (
		$max_num_fields,
		$db_host,
		$db_port,
		$db_user,
		$db_password,
		$db_name,
		$debug_arg,
		$silent_debug_arg,
		$output_format_arg,
		$output_gv_type,
		$output_type,
		$output_fieldtypes,
		$data_table_name,
		$category_table_name,
		$category_index_field,
		$category_index_selections,
		$category_label_field,
		$category_show_all_arg,
		$series_table_name,
		$series_fields,
		$series_index_field,
		$series_index_selections,
		$series_label_field,
		$series_value_field,
		$series_show_all_arg,
		$filter_index_field,
		$filter_index_selections,
		$drupal_user_id_field,
		$drupal_user_id,
		$category_axis_label,
		$series_axis_label,
		$conv_factor,
		$precision,
		$category_min,
		$category_max
	);
if ($result) {
	// handle a return value interpreted as true
} else {
	// handle a return value interpreted as false
}
?>