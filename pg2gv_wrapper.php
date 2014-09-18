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
//$output_format_arg = "csv"; // a string = "json" is the official default so it always works with gviz
//$output_format_arg = "html_table_2d"; // a string = "json" is the official default so it always works with gviz
//$output_format_arg = "html_table_raw"; // a string = "json" is the official default so it always works with gviz
$output_gv_type = "table"; // a string = "table" is the official default - no restrictions on column order or column types on tables
$output_type = "generic"; // a string = "generic" is the official default
//$output_type = "category1"; // a string = "generic" is the official default
//$output_type = "category2"; // a string = "generic" is the official default
$output_fieldtypes = ""; // an empty string = "" is the default, meaning to use the default pg to gviz mapping
$data_table_name = ""; // a string = "" is the official default
$category_table_name = ""; // a string = "" is the official default
$category_index_field = ""; // a string = "" is the official default
$category_index_selections = ""; // a string = "" is the official default
$category_label_field = ""; // a string = "" is the official default
$category_show_all_arg = "false"; // a string = "false" is the official default
$category_min = ""; // a string = "" is the official default
$category_max = ""; // a string = "" is the official default
$category_range_number = ""; // a string = "" is the official default
$category_range_date = ""; // a string = "" is the official default
$category_range_datetime = ""; // a string = "" is the official default
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
 * code specific to the category min/max filters
 * ---------------------------------------------
 * the below code (as it originally exists) in the lre git repo
 * is generic enough that it can be left in the wrapper without
 * affecting anything else
 * (but if you change it, caveat emptor)
 * the idea is this:
 * add a category index "range" url arg handling routine to THIS wrapper
 * then if this arg is defined, adjust the default category min max filter values accordingly
 * because of units and other issues, this url is field type specific
 * so there will be three (initially) possible category index range URL args
 *  - category_range_number
 *  - category_range_date
 *  - category_range_datetime
 * !!!IT IS IMPERATIVE THAT THE CORRECT ARG IS DEFINED THAT MATCHES THE FIELD TYPE!!!
 * several potential cases:
 * "range" not defined => do nothing and the above min max defaults will be used and
 *                         the DMI will over-ride them with the URL arg min max values
 *                         if they exist (this is the default behavior)
 * "range defined" =>
 * 		1. use the min max defaults above, and over-ride them with
 *		the category min max URL args, might need these in step 2
 *		2. adjust the MISSING min max defaults as follows:
 *			if BOTH have values => do nothing, "range" value will be ignored and DMI will use min max
 *			if NEITHER is defined =>
 *				If it is a category_range_number =>
 *					A. assume category index field is a number type (integer, long, real, single, double) (anything else and it breaks!!!)
 *					B. min = 0
 *					C. max = min + range
 *				If it is a category_range_date =>
 *					A. assume category index field is type date (anything else and it breaks!!!)
 *					   and category period units are days
 *					B. max = now()
 *					C. min = max - period
 *				If it is a category_range_datetime =>
 *					A. assume category index field is type datetime (anything else and it breaks!!!)
 *					   and category period units are seconds
 *					B. max = now()
 *					C. min = max - period
 * 			if MIN is defined (and not max) =>
 * 				A. max = min + range
 * 			if MAX is defined (and not min) =>
 * 				A. min = max - range
 * this should be all the cases, and in all of them both min and max will
 * 	end up with the desired value in the DMI
 * 
 */
$category_min = getargs ("category_min",$category_min);
$category_max = getargs ("category_max",$category_max);
$category_range_number = getargs ("category_range_number",$category_range_number);
$category_range_date = getargs ("category_range_date",$category_range_date);
$category_range_datetime = getargs ("category_range_datetime",$category_range_datetime);
if (strlen(trim($category_range_number))) {
	$rangeval = $category_range_number + 0; // a number
	if (strlen(trim($category_min))) {
		if (strlen(trim($category_max))) {
			// both defined, do nothing
		} else {
			// min defined, calculate max
			// convert to value, adjust, convert to string
			$minval = $category_min + 0;
			$maxval = $minval + $rangeval;
			$category_max = strval($maxval);
		}
	} elseif (strlen(trim($category_max))) {
		// max defined, calculate min
		// convert to value, adjust, convert to string
		$maxval = $category_max + 0;
		$minval = $maxval - $rangeval;
		$category_min = strval($minval);
	} else {
		// neither defined
		$minval = 0;
		$category_min = strval($minval);
		$maxval = $minval + $rangeval;
		$category_max = strval($maxval);
	}
} elseif (strlen(trim($category_range_date))) {
	$rangeval = $category_range_date + 0; // number of days
	if (strlen(trim($category_min))) {
		if (strlen(trim($category_max))) {
			// both defined, do nothing
		} else {
			// min defined, calculate max
			// convert to value, adjust, convert to string
			$minval = DateTime::createFromFormat('Y-m-d', $category_min);
			$maxval = $minval->modify("+$rangeval day");
			$category_max = $maxval->format('Y-m-d');
		}
	} elseif (strlen(trim($category_max))) {
		// max defined, calculate min
		// convert to value, adjust, convert to string
		$maxval = DateTime::createFromFormat('Y-m-d', $category_max);
		$minval = $maxval->modify("-$rangeval day");
		$category_min = $minval->format('Y-m-d');
	} else {
		// neither defined, calculate min and max
		$maxval = new DateTime();
		$category_max = $maxval->format('Y-m-d');
		$minval = $maxval->modify("-$rangeval day");
		$category_min = $minval->format('Y-m-d');
	}
} elseif (strlen(trim($category_range_datetime))) {
	$rangeval = $category_range_datetime + 0; // number of seconds
	if (strlen(trim($category_min))) {
		if (strlen(trim($category_max))) {
			// both defined, do nothing
		} else {
			// min defined, calculate max
			// convert to value, adjust, convert to string
			$minval = DateTime::createFromFormat('Y-m-d H:i:s', $category_min);
			$maxval = $minval->modify("+$rangeval seconds");
			$category_max = $maxval->format('Y-m-d H:i:s');
		}
	} elseif (strlen(trim($category_max))) {
		// max defined, calculate min
		// convert to value, adjust, convert to string
		$maxval = DateTime::createFromFormat('Y-m-d H:i:s', $category_max);
		$minval = $maxval->modify("-$rangeval seconds");
		$category_min = $minval->format('Y-m-d H:i:s');
	} else {
		// neither defined, calculate min and max
		$maxval = new DateTime();
		$category_max = $maxval->format('Y-m-d H:i:s');
		$minval = $maxval->modify("-$rangeval seconds");
		$category_min = $minval->format('Y-m-d H:i:s');
	}
} else {
	// range not defined, so do nothing
}
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