<?php
include "misc_io.php";
/*
 * the defaults
 *
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
$data_table_name = ""; // a string = "" is the official default
$category_table_name = ""; // a string = "" is the official default
$category_index_field = ""; // a string = "" is the official default
$category_index_selections = ""; // a string = "" is the official default
$category_label_field = ""; // a string = "" is the official default
$category_show_all_arg = "false"; // a string = "false" is the official default
$series_table_name = ""; // a string = "" is the official default
$series_fields = ""; // a string = "" is the official default
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
 */

/*
 * this is a wrapper around the big DMI code base.
 * it's purpose is to separate the arg defaults from the rest of the code.
 * the defaults are the only parts of the code that project engineers want to mess with,
 * and since often they do not want to or can not pass args via the URL arg structure
 * (either using the PHP CLI or some args want to be kept secret)
 * then they need a way to edit the code to hardwire them.
 * this allows them to have a project specific VERY shallow layer of code
 * and not have to mess with the generic universal pg_to_gviz_basic DMI PHP code
 * and therefore have to make multiple project specific copies of all this code
 */
function pg_to_gviz_basic(
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
			$precision
	) {
	// get the debug arg first
	$debug_arg = getargs ("debug",$debug_arg);
	if (strlen(trim($debug_arg))) {
		$debug = strtobool($debug_arg);
	} else {
		$debug = false;
	}
	if ($debug) echo "debug arg: $debug_arg<br>";
	// get the silent debug arg
	$silent_debug_arg = getargs ("silent_debug",$silent_debug_arg);
	if (strlen(trim($silent_debug_arg))) {
		$silent_debug = strtobool($silent_debug_arg);
	} else {
		$silent_debug = false;
	}
	if ($silent_debug) {
		$path = '/tmp/';
		$file_name_base = "pg_to_gviz_basic_debug_".date('YmdHis');
		$file_name = $file_name_base.".txt";
		$txtfile = $path.$file_name;
		$silent_debug_handle = fopen($txtfile, 'w');
	}
	/*
	 * csv - used by google, comma delimited text file
	 * json - used by google, json data source for gv objects
	 * unfortunately, we'll need a third dimension, since the field types change based on the gv object type, $output_gv_type
	 * table - the default for now
	 * column_graph
	 * annotated_time_line
	 * etc. as needed
	 * html_table_raw -
	 * html_table_2d -
	 */
	$output_format_arg = getargs ("output_format",$output_format_arg);
	if ($debug) echo "initial output_format: $output_format_arg<br>";
	if ($silent_debug) fwrite($silent_debug_handle,"initial output_format: $output_format_arg<br>");
	// but let google args over-ride it...
	// ----------------------
	// this script operates both as a simple (no querying) gv data source returning either a json data stream or a csv file
	//   and also a stand-alone callable data service returning data in various forms: json, csv file, and html tables
	// as the former it can handle a few tqx args, including specifically the 'out' arg as follows:
	//   if it is 'csv', it returns a csv file containing comma delimited data
	//   if it is 'json', then it returns the appropriate json to feed google visualizations
	// it can not (yet) handle the tq arg (query strings)
	// ----------------------
	// get the gv json tq args, if any
	// ----------------------
	$tq = getargs ("tq","");  // perhaps we should expand this to send an error mesage that queries are not handled...
	if ($debug) {
		echo "Google Visualization JSON data source arguments:<br>";
		echo "&#160;&#160;tq: $tq<br>";
	}
	if ($silent_debug) {
		fwrite($silent_debug_handle,"Google Visualization JSON data source arguments:<br>");
		fwrite($silent_debug_handle,"&#160;&#160;tq: $tq<br>");
	}
	// ----------------------
	// get the gv json tqx args, if any
	// ----------------------
	$tqxlist = array();
	$tqxlist = explode(";",getargs ("tqx",""));
	$tqx = array();
	$tqx_args = 0;
	foreach ($tqxlist as $tqxstr) {
		++$tqx_args;
		$tmp = array();
		$tmp = explode(':',$tqxstr);
		$tqx[$tmp[0]] = $tmp[1];
	}
	// figure out the output format to use - use google's passed arg, if none, then use the output_format arg
	if (strlen($tqx['out'])) {
		$output_format = strtolower($tqx['out']); // i.e. ignore the other arg if this one exists
	} else {
		$output_format = strtolower($output_format_arg); // assumes this is set above
	}
	if ($debug)echo "final output_format: $output_format<br>";
	if ($silent_debug)fwrite($silent_debug_handle,"final output_format: $output_format<br>");
	switch ($output_format) {
		case 'json':
			$output_gv_type = getargs ("output_gv_type",$output_gv_type);
			if ($debug) echo "output_gv_type: $output_gv_type<br>";
			// assume this is for google viz and therefore make sure the args for the header are set...
			if (!isset($tqx['responseHandler']) || !strlen($tqx['responseHandler'])) $tqx['responseHandler']='google.visualization.Query.setResponse';
			if (!isset($tqx['version']) || !strlen($tqx['version'])) $tqx['version']='0.6';
			if (!isset($tqx['reqId'])) $tqx['reqId']=0;
			break;
		case 'csv':
		case 'html_table_2d':
		case 'html_table_raw':
		default:
			// nothing to see here. move along, please.
	}
	/*
	 * $output_type
	* category1 - suitable for column, bar, line graphs - a category (text) axis and a value (number) axis
	* 				this one is the simplest - source is a pg crosstab:
	* 				a category key field, a category label field, and columns for every series
	* 				but this one is the less useful, because crosstabs are difficult to create dynamically in postgresql,
	* 				so if the number of series varies, then this is not the ideal...
	* category2 - suitable for column, bar, line graphs - a category (text) axis and a value (number) axis
	* 				this one is a little more complex - the source is a pg relational type table:
	* 				a category key field, a category label field,
	* 				a series key field, a series label field,
	* 				and a value column
	* 				the code below converts this into a multi-column table - to make it suitable for gv json and etc.
	* 				this one is more useful, because it can create as many series columns as are in the data,
	* generic - this is actually a special case of any string passed into this arg that is not predefined
	* 				this type recreates an output table JUST LIKE the input table, so it is really a lot like category 1
	* 				more like a crosstab source than the relational source
	* 				the idea is that the string defines the gviz column types as follows (this is from an email I sent out)
	*
	*					When it's just a table and not some specific data structure that is required for a particular google viz,
	*					then you can create an "output_type" that is a sequence of characters that tell the MGK what type to declare
	*					the json columns.  So for example, "SNNS" would tell it to make the columns text, number, number, text.
	*					The default will be text when not specified.
	*					O (0) - map from original pg field type to gv column type
	*					N (1) - number
	*					B (2) - boolean (use this with care until we work out the bugs, boolean never stays boolean through transitions)
	*					D (3) - date
	*					T (4) - timeofday
	*					A (5) - datetime
	*					S (6) - text
	*					Note that these are GOOGLE VIZ data types, NOT postgres data types NOR PHP data types!
	*					The fact that our data has to pass from strongly typed pg fields, through the PHP-PG API into a
	*					PHP query result data object and then into PHP arrays, and then into a variety of output types
	*					(HTML text streams, CSV files, JSON text streams, etc.) is why this is a bit of a maze with lots of trap-doors.
	*
	*/
	$output_type = getargs ("output_type",$output_type);
	if ($debug) echo "output_type: $output_type<br>";
	// common args for all types
	$db_host = getargs ("db_host",$db_host);
	$db_port = getargs ("db_port",$db_port);
	$db_user = getargs ("db_user",$db_user);
	$db_password = getargs ("db_password",$db_password);
	$db_name = getargs ("db_name",$db_name);
	if ($debug) {
		echo "db_host: $db_host<br>";
		echo "db_port: $db_port<br>";
		echo "db_user: $db_user<br>";
		echo "db_password: $db_password<br>";
		echo "db_name: $db_name<br>";
	}
	// get the needed args for each type
	switch ($output_type) {
		case 'category1':
			$data_table_name = getargs ("data_table_name",$data_table_name);
			$category_table_name = getargs ("category_table_name",$category_table_name);
			$category_index_field = getargs ("category_index_field",$category_index_field);
			$category_index_selections = getargs ("category_index_selections",$category_index_selections);
			$category_label_field = getargs ("category_label_field",$category_label_field);
			$category_show_all_arg = getargs ("category_show_all",$category_show_all_arg);
			if (strlen(trim($category_show_all_arg))) {
				$category_show_all = strtobool($category_show_all_arg);
			} else {
				$category_show_all = false;
			}
			$series_fields = getargs ("series_fields",$series_fields);
			$filter_index_field = getargs ("filter_index_field",$filter_index_field);
			$filter_index_selections = getargs ("filter_index_selections",$filter_index_selections);
			if ($debug) {
				echo "data_table_name*: $data_table_name<br>";
				echo "category_table_name: $category_table_name<br>";
				echo "category_index_field*: $category_index_field<br>";
				echo "category_index_selections: $category_index_selections<br>";
				echo "category_label_field: $category_label_field<br>";
				echo "category_show_all_arg: $category_show_all_arg<br>";
				echo "category_show_all: $category_show_all<br>";
				echo "series_fields*: $series_fields<br>";
				echo "filter_index_field: $filter_index_field<br>";
				echo "filter_index_selections: $filter_index_selections<br>";
			}
			if (!strlen(trim($data_table_name))) {
				echo "Error: Missing table_name arg.  table_name is required for a category1 output.<br>";
				echo "See pg_to_gviz_basic.php documentation for more information.<br>";
				exit;
			}
			if (!strlen(trim($category_index_field))) {
				echo "Error: Missing category_index_field arg.  category_index_field is required for a category1 output.<br>";
				echo "See pg_to_gviz_basic.php documentation for more information.<br>";
				exit;
			}
			if (!strlen(trim($series_fields))) {
				echo "Error: Missing series_fields arg.  series_fields is required for a category1 output.<br>";
				echo "See pg_to_gviz_basic.php documentation for more information.<br>";
				exit;
			}
			/*
			 * build the (OUTPUT) column type array with defaults
			 * the defaults are O (0) = map from original pg field type to gv column type
			 */
			$field_types = array();
			for ($i=0;$i<$max_num_fields;++$i) {
				$field_types[] = 0; // O (0) - map from original pg field type to gv column type
			}
			break;
		case 'category2':
			$data_table_name = getargs ("data_table_name",$data_table_name);
			$category_table_name = getargs ("category_table_name",$category_table_name);
			$category_index_field = getargs ("category_index_field",$category_index_field);
			$category_index_selections = getargs ("category_index_selections",$category_index_selections);
			$category_label_field = getargs ("category_label_field",$category_label_field);
			$category_show_all_arg = getargs ("category_show_all",$category_show_all_arg);
			if (strlen(trim($category_show_all_arg))) {
				$category_show_all = strtobool($category_show_all_arg);
			} else {
				$category_show_all = false;
			}
			$series_table_name = getargs ("series_table_name",$series_table_name);
			$series_index_field = getargs ("series_index_field",$series_index_field);
			$series_index_selections = getargs ("series_index_selections",$series_index_selections);
			$series_label_field = getargs ("series_label_field",$series_label_field);
			$series_show_all_arg = getargs ("series_show_all",$series_show_all_arg);
			if (strlen(trim($series_show_all_arg))) {
				$series_show_all = strtobool($series_show_all_arg);
			} else {
				$series_show_all = false;
			}
			$series_value_field = getargs ("series_value_field",$series_value_field);
			$filter_index_field = getargs ("filter_index_field",$filter_index_field);
			$filter_index_selections = getargs ("filter_index_selections",$filter_index_selections);
			if ($debug) {
				echo "data_table_name*: $data_table_name<br>";
				echo "category_table_name: $category_table_name<br>";
				echo "category_index_field*: $category_index_field<br>";
				echo "category_index_selections: $category_index_selections<br>";
				echo "category_label_field: $category_label_field<br>";
				echo "category_show_all_arg: $category_show_all_arg<br>";
				echo "category_show_all: $category_show_all<br>";
				echo "series_index_field*: $series_index_field<br>";
				echo "series_index_selections: $series_index_selections<br>";
				echo "series_label_field: $series_label_field<br>";
				echo "series_value_field*: $series_value_field<br>";
				echo "series_table_name: $series_table_name<br>";
				echo "series_show_all_arg: $series_show_all_arg<br>";
				echo "series_show_all: $series_show_all<br>";
				echo "filter_index_field: $filter_index_field<br>";
				echo "filter_index_selections: $filter_index_selections<br>";
			}
			if (!strlen(trim($data_table_name))) {
				echo "Error: Missing data_table_name arg.  data_table_name is required for a category2 output.<br>";
				echo "See pg_to_gviz_basic.php documentation for more information.<br>";
				exit;
			}
			if (!strlen(trim($category_index_field))) {
				echo "Error: Missing category_index_field arg.  category_index_field is required for a category2 output.<br>";
				echo "See pg_to_gviz_basic.php documentation for more information.<br>";
				exit;
			}
			if (!strlen(trim($series_index_field))) {
				echo "Error: Missing series_index_field arg.  series_index_field is required for a category2 output.<br>";
				echo "See pg_to_gviz_basic.php documentation for more information.<br>";
				exit;
			}
			if (!strlen(trim($series_value_field))) {
				echo "Error: Missing series_value_field arg.  series_value_field is required for a category2 output.<br>";
				echo "See pg_to_gviz_basic.php documentation for more information.<br>";
				exit;
			}
			/*
			 * build the (OUTPUT) column type array with defaults
			 * the defaults are O (0) = map from original pg field type to gv column type
			 */
			$field_types = array();
			for ($i=0;$i<$max_num_fields;++$i) {
				$field_types[] = 0; // O (0) - map from original pg field type to gv column type
			}
			break;
		case 'generic':
			// make them all maps from original pg field type to gv column types
			$output_type = str_repeat('o',$max_num_fields);
		default:
			$data_table_name = getargs ("data_table_name",$data_table_name);
			$series_fields = getargs ("series_fields",$series_fields);
			$filter_index_field = getargs ("filter_index_field",$filter_index_field);
			$filter_index_selections = getargs ("filter_index_selections",$filter_index_selections);
			if ($debug) {
				echo "data_table_name*: $data_table_name<br>";
				echo "series_fields*: $series_fields<br>";
				echo "filter_index_field: $filter_index_field<br>";
				echo "filter_index_selections: $filter_index_selections<br>";
			}
			if (!strlen(trim($data_table_name))) {
				echo "Error: Missing table_name arg.  table_name is required for a category1 output.<br>";
				echo "See pg_to_gviz_basic.php documentation for more information.<br>";
				exit;
			}
			if (!strlen(trim($series_fields))) {
				echo "Error: Missing series_fields arg.  series_fields is required for a category1 output.<br>";
				echo "See pg_to_gviz_basic.php documentation for more information.<br>";
				exit;
			}
			/*
			 * build the (OUTPUT) column type array with defaults
			 * the defaults are O (0) = map from original pg field type to gv column type
			 */
			$field_types = array();
			for ($i=0;$i<$max_num_fields;++$i) {
				$field_types[] = 0; // O (0) - map from original pg field type to gv column type
			}
			/*
			 * blow up any string that exists into a series of acceptable field types
			 */
			for ($i=0;$i<strlen(trim($output_type));++$i) {
				$field_types[] = 0;
				switch (strtolower($output_type[$i])) {
					case 'o': // O (0) - map from original pg field type to gv column type
						$field_types[$i] = 0;
						break;
					case 'n': // N (1) - number
						$field_types[$i] = 1;
						break;
					case 'b': // B (2) - boolean (use this with care until we work out the bugs, boolean never stays boolean through transitions)
						$field_types[$i] = 2;
						break;
					case 'd': // D (3) - date
						$field_types[$i] = 3;
						break;
					case 't': // T (4) - timeofday
						$field_types[$i] = 4;
						break;
					case 'a': // A (5) - datetime
						$field_types[$i] = 5;
						break;
					case 's': // S (6) - text
						$field_types[$i] = 6;
						break;
					default: // O (0) - map from original pg field type to gv column type
						$field_types[$i] = 0;
				}
			}
	}
	// other args
	$drupal_user_id_field = getargs ("drupal_user_id_field",$drupal_user_id_field);
	$drupal_user_id = getargs ("drupal_user_id",$drupal_user_id);
	$category_axis_label = getargs ("category_axis_label",$category_axis_label);
	$series_axis_label = getargs ("series_axis_label",$series_axis_label);
	$conv_factor = getargs ("conversion_factor",$conv_factor);
	$precision = getargs ("output_precision",$precision);
	if ($debug) {
		echo "output_type: $output_type<br>";
		echo "category_axis_label: $category_axis_label<br>";
		echo "series_axis_label: $series_axis_label<br>";
		echo "conversion_factor: $conv_factor<br>";
		echo "output_precision: $precision<br>";
	}
	// first make a db connection
	$dbhandle = pg_connect("host=$db_host port=$db_port user=$db_user password=$db_password dbname=$db_name") or die("Could not connect");
	/*
	 * get the data from the database and load it into arrays ready for processing into the various output formats
	*
	* !!!!!!!!!!!! here is where the magic happens !!!!!!!!!!!!
	*/
	switch ($output_type) {
		// if we got this far, the required args have something in them, but still have the optional args to deal with...
		case 'category1':
			// first construct the query appropriate to the supplied args
			// the field list string
			if (strlen(trim($category_label_field))) { // category label field supplied.  Yea, user!
				$data_db_query_fields = "$category_index_field, $category_label_field, ";
				$cat_lbl = $category_label_field;
			} else {
				$data_db_query_fields = "$category_index_field, ";
				$cat_lbl = $category_index_field;
			}
			// now tack on the the series fields
			// if the delimiter in the url ever changes from not being a comma, then we'll have to explode and parse the series fields
			$data_db_query_fields .= $series_fields;
			// the basic query
			$data_db_query = "SELECT $data_db_query_fields FROM $data_table_name";
			$where = 0;
			// now the optional WHERE clause(s) if necessary
			if (strlen(trim($category_index_selections))) { // we have a list of category indices to handle
				$data_db_query .= " WHERE (";
				$where = 1;
				$index_counter = 0;
				$category_indices = array();
				$category_indices = explode(",",$category_index_selections);
				foreach ($category_indices as $category_index) {
					if ($index_counter) {
						$data_db_query .= " OR";
					}
					$data_db_query .= " $category_index_field = $category_index";
					++$index_counter;
				}
				$data_db_query .= " )";
			}
			if (strlen(trim($filter_index_selections))) { // we have a list of filter indices to handle
				if ($where) {
					$data_db_query .= " AND (";
				} else {
					$data_db_query .= " WHERE (";
					$where = 1;
				}
				$index_counter = 0;
				$filter_indices = array();
				$filter_indices = explode(",",$filter_index_selections);
				foreach ($filter_indices as $filter_index) {
					if ($index_counter) {
						$data_db_query .= " OR";
					}
					$data_db_query .= " $filter_index_field = $filter_index";
					++$index_counter;
				}
				$data_db_query .= " )";
			}
			if (strlen(trim($drupal_user_id_field)) && strlen(trim($drupal_user_id))) { // we have a drupal_user id to handle
				if ($where) {
					$data_db_query .= " AND (";
				} else {
					$data_db_query .= " WHERE (";
					$where = 1;
				}
				$data_db_query .= " $drupal_user_id_field = $drupal_user_id )";
			}
			// finally the ORDER BY clause.  the categories will be ordered here by index.  the series are ordered as named in the list in the arg
			$data_db_query .= " ORDER BY $category_index_field";
			if ($debug) echo "db_query: $data_db_query<br>";
			// now try the query!
			$data_pg_results = pg_query($dbhandle, $data_db_query);
			$data_num_records = pg_num_rows($data_pg_results);
			if ($debug) echo "data_db_query resulted in $data_num_records records.<br>";
			// create the gviz json array header records
			// note that we use the gviz json array format regardless of the output type
			//   this makes it easier for creating the gviz json, and the others are easy anyway
			//
			// use the field names for the labels
			$datatable = array();
			$datatable["cols"][0]["id"] = "category";
			$datatable["cols"][0]["label"] = $cat_lbl;
			$datatable["cols"][0]["type"] = "string";
			$series_fields_array = array();
			$series_fields_array = explode(",",$series_fields);
			if ($debug) echo "exploded series:<br>";
			if ($debug) html_show_array($series_fields_array);
			$series_counter = 0;
			foreach ($series_fields_array as $series) {
				$datatable["cols"][$series_counter+1]["id"] = "series".($series_counter+1);
				$datatable["cols"][$series_counter+1]["label"] = $series;
				$datatable["cols"][$series_counter+1]["type"] = "number";
				++$series_counter;
			}
			$table_column_count = $series_counter;
			if ($debug) echo "created gviz json array for $table_column_count series<br>";
			// load the gviz json array with data
			//
			// figure out whether to use the data "as is" or create records for "every" category
			// assumes the category index and category label fields are the same in both the data and category tables
			// if a category table was given and if the show all flag is set to true,
			// then create a table with all the combinations, regardless of whether there is data
			if (strlen(trim($category_table_name)) && $category_show_all) {
				// the field list string
				if (strlen(trim($category_label_field))) { // category label field supplied.  Yea, user!
					$category_db_query_fields = "$category_index_field, $category_label_field ";
				} else {
					$category_db_query_fields = "$category_index_field ";
				}
				// the basic query
				$category_db_query = "SELECT $category_db_query_fields FROM $category_table_name";
				if ($debug) echo "category_db_query: $category_db_query<br>";
				// now try the query!
				$category_pg_results = pg_query($dbhandle, $category_db_query);
				$category_num_records = pg_num_rows($category_pg_results);
				if ($debug) echo "category_db_query resulted in $category_num_records records.<br>";
				// create the empty data array and
				// additionally create a hash table so I EFFICIENTLY know where to put the data later
				$default_value = null;
				$category_hash = array();
				$category_row_count = 0;
				while ($category_row = pg_fetch_object($category_pg_results)) {
					$datatable["rows"][$category_row_count]["c"][0]["v"] = $category_row->$cat_lbl;
					$category_hash[$category_row->$category_index_field] = $category_row_count;
					$category_series_counter = 0;
					foreach ($series_fields_array as $series) {
						$datatable["rows"][$category_row_count]["c"][$category_series_counter+1]["v"] = $default_value;
						++$category_series_counter;
					}
					++$category_row_count;
				}
				if ($debug) echo "created empty json array with $category_row_count records<br>";
				// now put the data into the data table, using my hash table
				$data_row_count = 0;
				while ($data_row = pg_fetch_object($data_pg_results)) {
					//$datatable["rows"][$data_row_count]["c"][0]["v"] = $data_row->$cat_lbl;
					$data_series_counter = 0;
					foreach ($series_fields_array as $series) {
						$table_row = $category_hash[$data_row->$category_index_field];
						$datatable["rows"][$table_row]["c"][$data_series_counter+1]["v"] = $data_row->$series * $conv_factor;
						++$data_series_counter;
					}
					++$data_row_count;
				}
				$table_row_count = $category_row_count;
				if ($debug) echo "loaded gviz json array with $data_row_count records of data<br>";
			} else {
				$data_row_count = 0;
				while ($data_row = pg_fetch_object($data_pg_results)) {
					$datatable["rows"][$data_row_count]["c"][0]["v"] = $data_row->$cat_lbl;
					$data_series_counter = 0;
					foreach ($series_fields_array as $series) {
						$datatable["rows"][$data_row_count]["c"][$data_series_counter+1]["v"] = $data_row->$series * $conv_factor;
						++$data_series_counter;
					}
					++$data_row_count;
				}
				$table_row_count = $data_row_count;
				if ($debug) echo "loaded gviz json array with $data_row_count records of data<br>";
			}
			break;
		case 'category2':
			/*
			* remember, this is constructing a crosstab from a n-tuple type data source
			* a 2-tuple in this case
			*   dimension 1 called "category" and define the rows - and become the values in column 1
			*   dimension 2 called "series" and become columns 2 through whatever
			*   data occurs (potentially) at intersections of these two dimensions
			*
			* as you surely expect, creating the list of rows and columns is the challenge
			*   for categories, it can either be the derived from the data itself, or from a separate table
			*   for series, it is derived from the data itself
			* this structure is because of the evolution of this type, it was imagined to end up in a gviz graph,
			*   and this is a convenient data structure for creating multiple series graphs
			*   i.e. a blank row (category) makes sense, but a blank column (series) makes less sense
			*
			* note that categories and series require a index (integer) field and potentially a label field
			*
			* first construct the query appropriate to the supplied args and get the data
			*/
			// put the category (rows) fields in the the field list strings
			if (strlen(trim($category_label_field))) { // category label field supplied.  Yea, user!
				$data_db_query_fields = "$category_index_field, $category_label_field, ";
				$category_db_query_fields  = "$category_index_field, max($category_label_field) as $category_label_field";
				$cat_lbl = $category_label_field;
			} else {
				$data_db_query_fields = "$category_index_field, ";
				$category_db_query_fields  = "$category_index_field";
				$cat_lbl = $category_index_field;
			}
			// now tack on the the series fields to the the field list strings
			if (strlen(trim($series_label_field))) { // series label field supplied.  Yea, user!
				$data_db_query_fields  .= "$series_index_field, $series_label_field, ";
				$series_db_query_fields = "$series_index_field, max($series_label_field) as $series_label_field";
				$ser_lbl = $series_label_field;
			} else {
				$data_db_query_fields  .= "$series_index_field, ";
				$series_db_query_fields = "$series_index_field";
				$ser_lbl = $series_index_field;
			}
			// now tack on the the series VALUE field
			$data_db_query_fields .= "$series_value_field";
			// the data basic query
			$data_db_query = "SELECT $data_db_query_fields FROM $data_table_name";
			// the category basic query
			$category_db_query = "SELECT $category_db_query_fields FROM $data_table_name";
			// the series basic query
			$series_db_query = "SELECT $series_db_query_fields FROM $data_table_name";
			$where = 0;
			/*
			* now the optional WHERE clause(s) if necessary
			*
			* first is when a list of category indices are defined
			*   i.e. a filter - do not include rows where the category value is not in this list
			*   the category index field MUST be defined, so we can assume this is OK
			*/
			if (strlen(trim($category_index_selections))) {
				$data_db_query .= " WHERE (";
				$series_db_query .= " WHERE (";
				$category_db_query .= " WHERE (";
				$where = 1;
				$index_counter = 0;
				$category_indices = array();
				$category_indices = explode(",",$category_index_selections);
				foreach ($category_indices as $category_index) {
					if ($index_counter) {
						$data_db_query .= " OR";
						$series_db_query .= " OR";
						$category_db_query .= " OR";
					}
					$data_db_query .= " $category_index_field = $category_index";
					$series_db_query .= " $category_index_field = $category_index";
					$category_db_query .= " $category_index_field = $category_index";
					++$index_counter;
				}
				$data_db_query .= " )";
				$series_db_query .= " )";
				$category_db_query .= " )";
			}
			/*
			* still working on the optional WHERE clause(s)
			*
			* next is when a list of "filter field" indices are defined
			*   i.e. a filter - do not include rows where the filter field value is not in this list
			*   the filter field may not be defined, so we have to handle this
			*/
			if (strlen(trim($filter_index_field)) && strlen(trim($filter_index_selections))) {
				if ($where) {
					$data_db_query .= " AND (";
					$series_db_query .= " AND (";
					$category_db_query .= " AND (";
				} else {
					$data_db_query .= " WHERE (";
					$series_db_query .= " WHERE (";
					$category_db_query .= " WHERE (";
					$where = 1;
				}
				$index_counter = 0;
				$filter_indices = array();
				$filter_indices = explode(",",$filter_index_selections);
				foreach ($filter_indices as $filter_index) {
					if ($index_counter) {
						$data_db_query .= " OR";
						$series_db_query .= " OR";
						$category_db_query .= " OR";
					}
					$data_db_query .= " $filter_index_field = $filter_index";
					$series_db_query .= " $filter_index_field = $filter_index";
					$category_db_query .= " $filter_index_field = $filter_index";
					++$index_counter;
				}
				$data_db_query .= " )";
				$series_db_query .= " )";
				$category_db_query .= " )";
			}
			/*
			* STILL working on the optional WHERE clause(s)
			*
			* next is when a drupal user id index is defined
			*   i.e. a filter - do not include rows where the filter field value is not in this list
			*   the filter field may not be defined, so we have to handle this
			*/
			if (strlen(trim($drupal_user_id_field)) && strlen(trim($drupal_user_id))) { // we have a drupal_user id to handle
				if ($where) {
					$data_db_query .= " AND (";
					$series_db_query .= " AND (";
					$category_db_query .= " AND (";
				} else {
					$data_db_query .= " WHERE (";
					$series_db_query .= " WHERE (";
					$category_db_query .= " WHERE (";
					$where = 1;
				}
				$data_db_query .= " $drupal_user_id_field = $drupal_user_id )";
				$series_db_query .= " $drupal_user_id_field = $drupal_user_id )";
				$category_db_query .= " $drupal_user_id_field = $drupal_user_id )";
			}
			/*
			 * finally the ORDER BY and GROUP BY clauses
			 *
			 * the data query output will be ordered by (only) categories and series index fields
			 * the series query output will be ordered by AND grouped by the series index field
			 * the category query output will be ordered by AND grouped by the category index field
			 */
			$data_db_query .= " ORDER BY $series_index_field,$category_index_field";
			$series_db_query .= " GROUP BY $series_index_field ORDER BY $series_index_field";
			$category_db_query .= " GROUP BY $category_index_field ORDER BY $category_index_field";
			if ($debug) echo "data_db_query: $data_db_query<br>";
			if ($debug) echo "series_db_query: $series_db_query<br>";
			if ($debug) echo "category_db_query: $category_db_query<br>";
			/*
			 * now do the queries that are done in all cases
			 */
			// the data query
			$data_pg_results = pg_query($dbhandle, $data_db_query);
			$data_num_records = pg_num_rows($data_pg_results);
			if ($debug) echo "data_db_query resulted in $data_num_records records.<br>";
			// the series values query
			$series_pg_results = pg_query($dbhandle, $series_db_query);
			$series_num_records = pg_num_rows($series_pg_results);
			if ($debug) echo "series_db_query resulted in $series_num_records records.<br>";
			// the category values query.  note: could be replaced below if category table is defined and show all is set to true
			$category_pg_results = pg_query($dbhandle, $category_db_query);
			$category_num_records = pg_num_rows($category_pg_results);
			if ($debug) echo "category_db_query resulted in $category_num_records records.<br>";
			/*
			 * create the gviz json array header records
			 *
			 * note that we use the gviz json array format regardless of the output type
			 *   this makes it easier for creating the gviz json with a single function call,
			 *   the others are straightforward and can easily be extracted from the array
			 */
			// use the category label (or category index) field name for the column 0 (category values) column header
			$datatable = array();
			$datatable["cols"][0]["id"] = "category";
			$datatable["cols"][0]["label"] = $cat_lbl;
			$datatable["cols"][0]["type"] = "string";
			/*
			 * create an order hash for the series indices
			 *
			 * now we have to figure out whether to use the series in the data or the series in a separate table
			 * we only need the series in the separate table if series show all = true,
			 *   so the table has to be defined AND show_all has to = true
			 *   if both are satisfied then get the series indices from the table, otherwise use the values already acquired from the data
			 *
			 * !!! assumes the series index and series label fields are the same in both the data and series tables !!!
			 */
			if (strlen(trim($series_table_name)) && $series_show_all) {
				// get a new list of series from the series table
				if (strlen(trim($series_label_field))) { // category label field supplied.  Yea, user!
					$series_db_query_fields  = "$series_index_field, max($series_label_field) as $series_label_field";
					$ser_lbl = $series_label_field;
				} else {
					$series_db_query_fields  = "$series_index_field";
					$ser_lbl = $series_index_field;
				}
				// the series basic query
				$series_db_query = "SELECT $series_db_query_fields FROM $series_table_name";
				// allow the series list to still be used if defined
				if (strlen(trim($series_index_selections))) {
					$series_db_query .= " WHERE (";
					$index_counter = 0;
					$series_indices = array();
					$series_indices = explode(",",$series_index_selections);
					foreach ($series_indices as $series_index) {
						if ($index_counter) {
							$series_db_query .= " OR";
						}
						$series_db_query .= " $series_index_field = $series_index";
						++$index_counter;
					}
					$series_db_query .= " )";
				}
				$series_db_query .= " GROUP BY $series_index_field ORDER BY $series_index_field";
				if ($debug) echo "replacement series_db_query: $series_db_query<br>";
				// the replacement series values query
				$series_pg_results = pg_query($dbhandle, $series_db_query);
				$series_num_records = pg_num_rows($series_pg_results);
				if ($debug) echo "replacement series_db_query resulted in $series_num_records records.<br>";
			}
			/*
			 * create an order hash for the series indices
			 * and also label the json array columns (series) with the series label (or index) value for each series
			 */
			$series_counter = 0;
			while ($series_row = pg_fetch_object($series_pg_results)) {
				$series_hash[$series_row->$series_index_field] = $series_counter;
				$datatable["cols"][$series_counter+1]["id"] = "series".($series_counter+1);
				$datatable["cols"][$series_counter+1]["label"] = $series_row->$ser_lbl;
				$datatable["cols"][$series_counter+1]["type"] = "number";
				++$series_counter;
			}
			$table_column_count = $series_counter;
			if ($debug) echo "created gviz json array structure for $series_counter series<br>";
			/*
			 * create an order hash for the category indices
			 *
			 * now we have to figure out whether to use the categories in the data or the categories in a separate table
			 * we only need the categories in the separate table if show all = true,
			 *   so the table has to be defined AND show_all has to = true
			 *   if both are satisfied then get the categories from the table, otherwise use the values already acquired from the data
			 *
			 * !!! assumes the category index and category label fields are the same in both the data and category tables !!!
			 */
			if (strlen(trim($category_table_name)) && $category_show_all) {
				// get a new list of categories from the category table
				if (strlen(trim($category_label_field))) { // category label field supplied.  Yea, user!
					$category_db_query_fields  = "$category_index_field, max($category_label_field) as $category_label_field";
					$cat_lbl = $category_label_field;
				} else {
					$category_db_query_fields  = "$category_index_field";
					$cat_lbl = $category_index_field;
				}
				// the category basic query
				$category_db_query = "SELECT $category_db_query_fields FROM $category_table_name";
				// allow the category list to still be used if defined
				if (strlen(trim($category_index_selections))) {
					$category_db_query .= " WHERE (";
					$index_counter = 0;
					$category_indices = array();
					$category_indices = explode(",",$category_index_selections);
					foreach ($category_indices as $category_index) {
						if ($index_counter) {
							$category_db_query .= " OR";
						}
						$category_db_query .= " $category_index_field = $category_index";
						++$index_counter;
					}
					$category_db_query .= " )";
				}
				$category_db_query .= " GROUP BY $category_index_field ORDER BY $category_index_field";
				if ($debug) echo "replacement category_db_query: $category_db_query<br>";
				// the replacement category values query
				$category_pg_results = pg_query($dbhandle, $category_db_query);
				$category_num_records = pg_num_rows($category_pg_results);
				if ($debug) echo "replacement category_db_query resulted in $category_num_records records.<br>";
			}
			/*
			 * now create the category hash
			 * and load up column 0 with category labels
			 * and fill out the empty array with the default value
			 */
			$default_value = null;
			$category_hash = array();
			$category_row_count = 0;
			while ($category_row = pg_fetch_object($category_pg_results)) {
				$datatable["rows"][$category_row_count]["c"][0]["v"] = $category_row->$cat_lbl;
				$category_hash[$category_row->$category_index_field] = $category_row_count;
				for ($category_series_counter=0;$category_series_counter<$table_column_count;++$category_series_counter) {
					$datatable["rows"][$category_row_count]["c"][$category_series_counter+1]["v"] = $default_value;
				}
				++$category_row_count;
			}
			$table_row_count = $category_row_count;
			if ($debug) echo "created empty json array with $category_row_count records<br>";
			// now put the data into the data table, using the hash tables
			$data_row_count = 0;
			while ($data_row = pg_fetch_object($data_pg_results)) {
				$table_row = $category_hash[$data_row->$category_index_field];
				$table_column = $series_hash[$data_row->$series_index_field];
				$datatable["rows"][$table_row]["c"][$table_column+1]["v"] = $data_row->$series_value_field * $conv_factor;
				++$data_row_count;
			}
			if ($debug) echo "loaded gviz json array with $data_row_count records of data<br>";
			break;
		default:
			/*
			 * assume it is a string of types, and they are already defined in the type array
			 * construct the query appropriate to the supplied args
			 *
			 * the field list string - no categories or series here, "the war is over, we are all just folk now" (folk = fields)
			 * if the delimiter in the url ever changes from not being a comma, then we'll have to explode and parse the series fields
			 */
			$data_db_query_fields = $series_fields;
			// the basic query
			$data_db_query = "SELECT $data_db_query_fields FROM $data_table_name";
			$where = 0;
			// now the optional WHERE clause(s) if necessary
			if (strlen(trim($filter_index_selections))) { // we have a list of filter indices to handle
				$data_db_query .= " WHERE (";
				$where = 1;
				$index_counter = 0;
				$filter_indices = array();
				$filter_indices = explode(",",$filter_index_selections);
				foreach ($filter_indices as $filter_index) {
					if ($index_counter) {
						$data_db_query .= " OR";
					}
					$data_db_query .= " $filter_index_field = $filter_index";
					++$index_counter;
				}
				$data_db_query .= " )";
			}
			if (strlen(trim($drupal_user_id_field)) && strlen(trim($drupal_user_id))) { // we have a drupal_user id to handle
				if ($where) {
					$data_db_query .= " AND (";
				} else {
					$data_db_query .= " WHERE (";
					$where = 1;
				}
				$data_db_query .= " $drupal_user_id_field = $drupal_user_id )";
			}
			if ($debug) echo "db_query: $data_db_query<br>";
			// now try the query!
			$data_pg_results = pg_query($dbhandle, $data_db_query);
			$data_num_records = pg_num_rows($data_pg_results);
			if ($debug) echo "data_db_query resulted in $data_num_records records.<br>";
			// create the gviz json array header records
			// note that we use the gviz json array format regardless of the output type
			//   this makes it easier for creating the gviz json, and the others are easy anyway
			//
			// use the field names for the labels
			$datatable = array();
			$series_fields_array = array();
			$series_fields_array = explode(",",$series_fields);
			if ($debug) echo "exploded series:<br>";
			if ($debug) html_show_array($series_fields_array);
			$series_counter = 0;
			foreach ($series_fields_array as $series) {
				$datatable["cols"][$series_counter]["id"] = "field".($series_counter);
				$datatable["cols"][$series_counter]["label"] = $series;
				switch ($field_types[$series_counter]) {
					case 1: // N (1) - number
						$datatable["cols"][$series_counter]["type"] = "number";
						break;
					case 2: // B (2) - boolean
						$datatable["cols"][$series_counter]["type"] = "boolean";
						break;
					case 3: // D (3) - date
						$datatable["cols"][$series_counter]["type"] = "date";
						break;
					case 4: // T (4) - timeofday
						$datatable["cols"][$series_counter]["type"] = "timeofday";
						break;
					case 5: // A (5) - datetime
						$datatable["cols"][$series_counter]["type"] = "datetime";
						break;
					case 6: // S (6) - text
						$datatable["cols"][$series_counter]["type"] = "text";
						break;
					case 0: // O (0) - mapping from pg field type to gv column type
					default:
						$datatable["cols"][$series_counter]["type"] = ;
				}
				++$series_counter;
			}
			$table_column_count = $series_counter - 1; // note this is different! because there is no category column in this case
			if ($debug) echo "created gviz json array for $series_counter fields<br>";
			// load the gviz json array with data
			$data_row_count = 0;
			while ($data_row = pg_fetch_object($data_pg_results)) {
				$data_series_counter = 0;
				foreach ($series_fields_array as $series) {
					$datatable["rows"][$data_row_count]["c"][$data_series_counter]["v"] = $data_row->$series;
					++$data_series_counter;
				}
				++$data_row_count;
			}
			$table_row_count = $data_row_count;
			if ($debug) echo "loaded gviz json array with $data_row_count records of data<br>";
	}
	switch ($output_format) {
		case 'csv': // output them to a csv file - this is required as part of gviz data source
			if ($debug) echo "outputting to a csv file:<br>";
			$path = '/tmp/';
			$file_name_base = "pg_to_gviz_basic_data_".date('YmdHis');
			$file_name = $file_name_base.".csv";
			$csvfile = $path.$file_name;
			if ($debug) echo "file name: $csvfile<br>";
			$handle = fopen($csvfile, 'w');
			if(!$handle) {
				echo "<br>";
				echo "System error creating CSV file: $csvfile <br>Contact Support.";
				echo "<br>";
			} else {
				$row = array();
				for ($c=0;$c<=$table_column_count;$c++) {
					$row[] = $datatable["cols"][$c]["label"];
				}
				fputcsv($handle,$row);
				for ($r=0;$r<$table_row_count;$r++) {
					$row = array();
					for ($c=0;$c<=$table_column_count;$c++) {
						$row[] = $datatable["rows"][$r]["c"][$c]["v"];
					}
					fputcsv($handle,$row);
				}
			}
			fclose($handle);
			if ($debug) echo "finished creating csv file<br>";
			if (file_exists($csvfile)) {
				if ($debug) echo "now downloading csv file<br>";
				header('Content-Description: File Transfer');
				header('Content-Type: application/octet-stream');
				header('Content-Disposition: attachment; filename='.basename($csvfile));
				header('Content-Transfer-Encoding: binary');
				header('Expires: 0');
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header('Pragma: public');
				header('Content-Length: ' . filesize($csvfile));
				ob_clean();
				flush();
				readfile($csvfile);
			} else {
				echo "<br>";
				echo "System error reading CSV file: $csvfile <br>Contact Support.";
				echo "<br>";
			}
			break;
		case 'json': //return a json data stream tailored for a gviz object
			//   first the json data table
			$json_data_table = json_encode($datatable);
			//   some stuff that seemed necessary to make the json readaable by the gviz libraries
			$json_data_table = preg_replace('/\"new/','new',$json_data_table);
			$json_data_table = preg_replace('/\)\"/',')',$json_data_table);
			$json_data_table = preg_replace('/\"v\":/','v:',$json_data_table);
			$json_data_table = preg_replace('/\"c\":/','c:',$json_data_table);
			$json_data_table = preg_replace('/\"cols\":/','cols:',$json_data_table);
			$json_data_table = preg_replace('/\"rows\":/','rows:',$json_data_table);
			$json_data_table = preg_replace('/{\"id\":/','{id:',$json_data_table);
			$json_data_table = preg_replace('/,\"label\":/',',label:',$json_data_table);
			$json_data_table = preg_replace('/,\"type\":/',',type:',$json_data_table);
			// and echo the results
			echo $tqx['responseHandler']."({version:'".$tqx['version']."',reqId:'".$tqx['reqId']."',status:'ok',table:$json_data_table});";
			//echo $tqx['responseHandler']."({\"version\":\"".$tqx['version']."\",\"reqId\":\"".$tqx['reqId']."\",\"status\":\"ok\",\"table\":$json_data_table});";
			//write this to a temp file for debugging
			if ($silent_debug) {
				fwrite($silent_debug_handle,$tqx['responseHandler']."({version:'".$tqx['version']."',reqId:'".$tqx['reqId']."',status:'ok',table:$json_data_table});");
			}
			break;
		case 'html_table_2d': // output a simple html table, rows and columns, i.e. 2 dimensional
			echo "<table cellspacing=\"0\" border=\"2\">\n";
			echo "<tr>";
			for ($c=0;$c<=$table_column_count;$c++) {
				echo "<td>";
				echo $datatable["cols"][$c]["label"];
				echo "</td>";
			}
			echo "</tr>\n";
			for ($r=0;$r<$table_row_count;$r++) {
				echo "<tr>";
				for ($c=0;$c<=$table_column_count;$c++) {
					echo "<td>";
					echo $datatable["rows"][$r]["c"][$c]["v"];
					echo "</td>";
				}
				echo "</tr>\n";
			}
			echo "</table>\n";
			break;
		case 'html_table_raw': // dump the data array into a html table supporting more than 2 dimensions
		default:
			html_show_array($datatable);
	}
	if ($silent_debug) {
		fclose($silent_debug_handle);
	}
}
?>
