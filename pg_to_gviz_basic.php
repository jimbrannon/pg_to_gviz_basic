<?php
// put some default values up here so ya don't have to dig for them; 
$db_host = "lredb2"; //"" is the official default
$db_port = 5431; //"" is the official default
$db_user = "jim_dev"; //"" is the official default
$db_password = "jimdev";//"" is the official default
$db_name = "div3welldata";//"" is the official default
$table_name = "public.selected_histograms"; //"" is the official default
$debug_arg = "false"; //false is the official default
$silent_debug_arg = "true"; //false is the official default
$output_format_arg = "json"; //"json" is the official default so it always works with gviz
$output_gv_type = "table"; //"table" is the official default - no restrictions on column order or column types on tables
$output_type = "category1"; //"" is the official default
$category_index_field = "bin_index"; //"" is the official default
$category_index_selections = ""; //"" is the official default
$category_label_field = "bin_label"; //"" is the official default
$series_fields = "count"; //"" is the official default
$series_index_field = ""; //"" is the official default
$series_index_selections = ""; //"" is the official default
$series_label_field = ""; //"" is the official default
$filter_index_field = ""; //"" is the official default
$filter_index_selections = ""; //"" is the official default
$drupal_user_id_field = "user_index"; //"" is the official default
$drupal_user_id = 1; //"" is the official default
$category_axis_label = ""; //"" is the official default
$series_axis_label = ""; //"" is the official default
$conv_factor = 1.0; //1.0 is the official default
$precision = 99; //99 is the official default
// get the debug arg first
$debug_arg = getargs ("debug",$debug_arg);
if (strlen(trim($debug_arg))) {
	switch (strtolower($debug_arg)) {
		case "false":
		case "no":
			$debug = false;
			break;
		default:
			$debug = true;
	}
} else {
	$debug = false;
}
if ($debug) echo "debug arg: $debug_arg<br>";
// get the silent debug arg
$silent_debug_arg = getargs ("silent_debug",$silent_debug_arg);
if (strlen(trim($silent_debug_arg))) {
	switch (strtolower($silent_debug_arg)) {
		case "false":
		case "no":
			$silent_debug = false;
			break;
		default:
			$silent_debug = true;
	}
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
// csv - used by google, comma delimited text file
// json - used by google, json data source for gv objects
//		unfortunately, we'll need a third dimension, since the field types change based on the gv object type, $output_gv_type
//		table - the default for now
//		column_graph
//		annotated_time_line
//		etc. as needed
// html_table_raw - 
// html_table_2d -
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
// $output_type
// category1 - suitable for column, bar, line graphs - a category (text) axis and a value (number) axis
//				this one is the simplest - source is a pg crosstab:
//				a category key field, a category label field, and columns for every series
//				but this one is the less useful, because crosstabs are difficult to create dynamically in postgresql,
//				so if the number of series varies, then this is not the ideal...
// category2 - suitable for column, bar, line graphs - a category (text) axis and a value (number) axis
//				this one is a little more complex - the source is a pg relational type table:
//				a category key field, a category label field,
//				a series key field, a series label field,
//				and a value column
//				the code below converts this into a multi-column table - to make it suitable for gv json and etc.
//				this one is more useful, because it can create as many series columns as are in the data,
// more types coming... 
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
		$table_name = getargs ("table_name",$table_name);
		$category_index_field = getargs ("category_index_field",$category_index_field);
		$category_index_selections = getargs ("category_index_selections",$category_index_selections);
		$category_label_field = getargs ("category_label_field",$category_label_field);
		$series_fields = getargs ("series_fields",$series_fields);
		$filter_index_field = getargs ("filter_index_field",$filter_index_field);
		$filter_index_selections = getargs ("filter_index_selections",$filter_index_selections);
		if ($debug) {
			echo "table_name*: $table_name<br>";
			echo "category_index_field*: $category_index_field<br>";
			echo "category_index_selections: $category_index_selections<br>";
			echo "category_label_field: $category_label_field<br>";
			echo "series_fields*: $series_fields<br>";
			echo "filter_index_field: $filter_index_field<br>";
			echo "filter_index_selections: $filter_index_selections<br>";
		}
		if (!strlen(trim($table_name))) {
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
		break;
	case 'category2':
		$table_name = getargs ("table_name",$table_name);
		$category_index_field = getargs ("category_index_field",$category_index_field);
		$category_index_selections = getargs ("category_index_selections",$category_index_selections);
		$category_label_field = getargs ("category_label_field",$category_label_field);
		$series_index_field = getargs ("series_index_field",$series_index_field);
		$series_index_selections = getargs ("series_index_selections",$series_index_selections);
		$series_label_field = getargs ("series_label_field",$series_label_field);
		$filter_index_field = getargs ("filter_index_field",$filter_index_field);
		$filter_index_selections = getargs ("filter_index_selections",$filter_index_selections);
		if ($debug) {
			echo "table_name*: $table_name<br>";
			echo "category_index_field*: $category_index_field<br>";
			echo "category_index_selections*: $category_index_selections<br>";
			echo "category_label_field: $category_label_field<br>";
			echo "series_index_field*: $series_index_field<br>";
			echo "series_index_selections*: $series_index_selections<br>";
			echo "series_label_field: $series_label_field<br>";
			echo "filter_index_field: $filter_index_field<br>";
			echo "filter_index_selections: $filter_index_selections<br>";
		}
		if (!strlen(trim($table_name))) {
			echo "Error: Missing table_name arg.  table_name is required for a category1 output.<br>";
			echo "See pg_to_gviz_basic.php documentation for more information.<br>";
			exit;
		}
		if (!strlen(trim($category_index_field)) && strlen(trim($series_index_selections))) {
			echo "Error: Missing category arg.  Either category_index_field or category_label_field is required for a category1 output.<br>";
			echo "See pg_to_gviz_basic.php documentation for more information.<br>";
			exit;
		}
		if (!strlen(trim($series_index_field)) && strlen(trim($series_index_selections))) {
			echo "Error: Missing category arg.  Either category_index_field or category_label_field is required for a category1 output.<br>";
			echo "See pg_to_gviz_basic.php documentation for more information.<br>";
			exit;
		}
		break;
	default:
		$table_name = getargs ("table_name",$table_name);
		$category_index_field = getargs ("category_index_field",$category_index_field);
		$category_index_selections = getargs ("category_index_selections",$category_index_selections);
		$category_label_field = getargs ("category_label_field",$category_label_field);
		$series_fields = getargs ("series_fields",$series_fields);
		$filter_index_field = getargs ("filter_index_field",$filter_index_field);
		$filter_index_selections = getargs ("filter_index_selections",$filter_index_selections);
		if ($debug) {
			echo "table_name*: $table_name<br>";
			echo "category_index_field*: $category_index_field<br>";
			echo "category_index_selections: $category_index_selections<br>";
			echo "category_label_field: $category_label_field<br>";
			echo "series_fields*: $series_fields<br>";
			echo "filter_index_field: $filter_index_field<br>";
			echo "filter_index_selections: $filter_index_selections<br>";
		}
		if (!strlen(trim($table_name))) {
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
			$cat_fld = $category_label_field;
			$cat_lbl = $category_label_field;
		} else {
			$cat_fld = $category_index_field;
			$cat_lbl = $category_index_field;
		}
		$db_query_fields = "$cat_fld, ";
		// now tack on the the series fields
		// if the delimiter in the url ever changes from not being a comma, then we'll have to explode and parse the series fields
		$db_query_fields .= $series_fields;
		// the basic query
		$db_query = "SELECT $db_query_fields FROM $table_name";
		$where = 0;
		// now the optional WHERE clause(s) if necessary
		if (strlen(trim($category_index_selections))) { // we have a list of category indices to handle
			$db_query .= " WHERE (";
			$where = 1;
			$index_counter = 0;
			$category_indices = array();
			$category_indices = explode(",",$category_index_selections);
			foreach ($category_indices as $category_index) {
				if ($index_counter) {
					$db_query .= " OR";
				}
				$db_query .= " $category_index_field = $category_index";
				++$index_counter;
			}
			$db_query .= " )";
		}
		if (strlen(trim($filter_index_selections))) { // we have a list of filter indices to handle
			if ($where) {
				$db_query .= " AND (";
			} else {
				$db_query .= " WHERE (";
				$where = 1;
			}
			$index_counter = 0;
			$filter_indices = array();
			$filter_indices = explode(",",$filter_index_selections);
			foreach ($filter_indices as $filter_index) {
				if ($index_counter) {
					$db_query .= " OR";
				}
				$db_query .= " $filter_index_field = $filter_index";
				++$index_counter;
			}
			$db_query .= " )";
		}
		if (strlen(trim($drupal_user_id_field)) && strlen(trim($drupal_user_id))) { // we have a drupal_user id to handle
			if ($where) {
				$db_query .= " AND (";
			} else {
				$db_query .= " WHERE (";
				$where = 1;
			}
			$db_query .= " $drupal_user_id_field = $drupal_user_id )";
		}
		// finally the ORDER BY clause.  the categories will be ordered here by index.  the series are ordered as named in the list in the arg
		$db_query .= " ORDER BY $category_index_field";
		if ($debug) echo "db_query: $db_query<br>";
		// now try the query!
		$pg_results = pg_query($dbhandle, $db_query);
		$num_records = pg_num_rows($pg_results);
		if ($debug) echo "query resulted in $num_records records.<br>";
		// create the gviz json array header records
		// note that we use the gviz json array format regardless of the output type
		//   this makes it easier for creating the gviz json, and the others are easy anyway, so...
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
		$column_count = $series_counter;
		if ($debug) echo "created gviz json array for $column_count series<br>";
		// load the gviz json array with data
		$row_count = 0;
		while ($row = pg_fetch_object($pg_results)) {
			$datatable["rows"][$row_count]["c"][0]["v"] = $row->$cat_fld;
			$series_counter = 0;
			foreach ($series_fields_array as $series) {
				$datatable["rows"][$row_count]["c"][$series_counter+1]["v"] = $row->$series * $conv_factor;
				++$series_counter;
			}
			++$row_count;
		}
		if ($debug) echo "loaded gviz json array with $row_count records of data<br>";
		break;
	case 'category2':
		break;
	default:
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
			for ($c=0;$c<=$column_count;$c++) {
				$row[] = $datatable["cols"][$c]["label"];
			}
			fputcsv($handle,$row);
			for ($r=0;$r<$row_count;$r++) {
				$row = array();
				for ($c=0;$c<=$column_count;$c++) {
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
		for ($c=0;$c<=$column_count;$c++) {
			echo "<td>";
			echo $datatable["cols"][$c]["label"];
			echo "</td>";
		}
		echo "</tr>\n";
		for ($r=0;$r<$row_count;$r++) {
			echo "<tr>";
			for ($c=0;$c<=$column_count;$c++) {
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
/*
 * other functions I need
 */
function output_array($array){
    foreach($array as $key => $val){
        echo "    $key = ".$val."<br>";
    }
}
function getargs ($key,$def) {
        if(isset($_GET[$key])){
                if(empty($_GET[$key])) {
                        $output = $def;
                } else {
                        $output = $_GET[$key];
                }
        } else {
                $output = $def; 
        }
        return $output;
}
function do_offset($level){
    $offset = "";             // offset for subarry 
    for ($i=1; $i<$level;$i++){
    $offset = $offset . "<td></td>";
    }
    return $offset;
}

function show_array($array, $level, $sub){
    if (is_array($array) == 1){          // check if input is an array
       foreach($array as $key_val => $value) {
           $offset = "";
           if (is_array($value) == 1){   // array is multidimensional
           echo "<tr>";
           $offset = do_offset($level);
           echo $offset . "<td>" . $key_val . "</td>";
           show_array($value, $level+1, 1);
           }
           else{                        // (sub)array is not multidim
           if ($sub != 1){          // first entry for subarray
               echo "<tr nosub>";
               $offset = do_offset($level);
           }
           $sub = 0;
           echo $offset . "<td main ".$sub." width=\"120\">" . $key_val . 
               "</td><td width=\"120\">" . $value . "</td>"; 
           echo "</tr>\n";
           }
       } //foreach $array
    }  
    else{ // argument $array is not an array
        return;
    }
}

function html_show_array($array){
  echo "<table cellspacing=\"0\" border=\"2\">\n";
  show_array($array, 1, 0);
  echo "</table>\n";
}
?>
