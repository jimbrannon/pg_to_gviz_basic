<?php
	$debug = false;
	$debug_arg = getargs ("debug",0);
	if ($debug_arg) $debug = true;
// csv - used by google, comma delimited text file
// json - used by google, json data source for gv objects
//		unfortunately, we'll need a third dimension, since the field types change based on the gv object type, $output_gv_type
//		table - the default for now
//		column_graph
//		annotated_time_line
//		etc. as needed
// html_table_raw - 
// html_table_2d -
$output_format_arg = getargs ("output_format","html_table_2d");
if ($debug) echo "initial output_format: $output_format_arg<br>";
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
if ($debug) {
echo "final output_format: $output_format<br>";
}
switch ($output_format) {
	case 'json':
		$output_gv_type = getargs ("output_gv_type","table");
		if ($debug) echo "output_gv_type: $output_gv_type<br>";
		break;
	case 'gvcsv':
	case 'html_table_2d':
	case 'html_table_raw':
	default:
		// nothing to see here. move along, please.
}
// $output_type
// category1pg - suitable for column, bar, line graphs - a category (text) axis and a value (number) axis
//				this one is the simplest - source is a pg crosstab:
//				a category key field, a category label field, and columns for every series
//				but this one is the less useful, because crosstabs are difficult to create dynamically in postgresql,
//				so if the number of series varies, then this is not the ideal...
// category2pg - suitable for column, bar, line graphs - a category (text) axis and a value (number) axis
//				this one is a little more complex - the source is a pg relational type table:
//				a category key field, a category label field,
//				a series key field, a series label field,
//				and a value column
//				the code below converts this into a multi-column table - to make it suitable for gv json and etc.
//				this one is more useful, because it can create as many series columns as are in the data,
// more types coming... 
$output_type = getargs ("output_type","category");
if ($debug) echo "output_type: $output_type<br>";
// get the needed args for each type
switch ($output_type) {
	case 'category1pg':
		break;
	case 'category2pg':
		break;
	default:
}
// other args
$category_axis_label = getargs ("category_axis_label","histogram bins");
$series_axis_label = getargs ("series_axis_label","count");
$conv_factor = getargs ("conversion_factor",1.0);
$precision = getargs ("output_precision",99);
if ($debug) {
	echo "output_type: $output_type<br>";
	echo "category_axis_label: $category_axis_label<br>";
	echo "series_axis_label: $series_axis_label<br>";
	echo "conversion_factor: $conv_factor<br>";
	echo "output_precision: $precision<br>";
}
// first get the readings from the database into arrays
$db_host = getargs ("db_host","lredb2");
$db_port = getargs ("db_port",5431);
$db_user = getargs ("db_user","jim_dev");
$db_password = getargs ("db_password","jimdev");
$db_name = getargs ("db_name","div3welldata");
$table_category_name = getargs ("table_name","public.selected_histograms");
$table_category_index_field = getargs ("category_index_field","bin_index");
$table_category_label_field = getargs ("category_label_field","bin_label");
$table_series_index_field = getargs ("series_index_field","tester_num");
$table_series_label_field = getargs ("series_label_field","tester_num");
if ($debug) {
	echo "db_host: $db_host<br>";
	echo "db_port: $db_port<br>";
	echo "db_user: $db_user<br>";
	echo "db_password: $db_password<br>";
	echo "db_name: $db_name<br>";
	echo "readings_table_name: $readings_table_name<br>";
	echo "readings_table_index_field: $readings_tablecategory_index_field<br>";
	echo "readings_table_datetime_field: $readings_table_datetime_field<br>";
	echo "readings_table_value_field: $readings_table_value_field<br>";
	echo "readings_id: $readings_id_arg<br>";
}
$dbhandle = pg_connect("host=$db_host port=$db_port user=$db_user password=$db_password dbname=$db_name") or die("Could not connect");
$db_query = "SELECT * FROM $table_name";
if (strlen(trim($readings_id_arg))) {
	$db_query .= " WHERE $readings_table_index_field = $readings_id_arg";
}
$pg_results = pg_query($dbhandle, $db_query_readings);
if ($debug) echo "db_query_readings: $db_query_readings<br>";
if ($debug) echo "found ".pg_num_rows($pg_results_readings)." readings.<br>";
$readings_dates = array();
$reading_timestamp_array = array();
$reading_rateval_array = array();
$reading_count=0;
$readingstable = array();
// set up the json array for readings
$readingstable["cols"][0]["id"] = "timestamp";
$readingstable["cols"][0]["label"] = "Timestamp";
$readingstable["cols"][0]["type"] = "string";
$readingstable["cols"][1]["id"] = "value";
$readingstable["cols"][1]["label"] = "Reading";
$readingstable["cols"][1]["type"] = "number";
$row_count = 0;
while ($readings_row = pg_fetch_object($pg_results_readings)) {
	$readings_dates[] = $readings_row->$readings_table_datetime_field;
	$reading_timestamp_array[] = strtotime($readings_dates[$reading_count]);
	$reading_rateval_array[] = $readings_row->$readings_table_value_field;
	$readingstable["rows"][$reading_count]["c"][0]["v"] = $readings_dates[$reading_count];
	$readingstable["rows"][$reading_count]["c"][1]["v"] = $reading_rateval_array[$reading_count];
	++$reading_count;
}
if ($debug) echo "processed $reading_count readings:<br>";
// set up the json array for volumes
$interval_timestamp = array();
$interval_label = array();
$interval_volume = array();
$interval_count = 0;
$volumestable = array();
$volumestable["cols"][0]["id"] = "timestamp";
$volumestable["cols"][0]["label"] = $timestamp_label;
$volumestable["cols"][0]["type"] = "string";
$volumestable["cols"][1]["id"] = "value";
$volumestable["cols"][1]["label"] = $value_label;
$volumestable["cols"][1]["type"] = "number";
// now figure out what output to create
switch ($output_type) {
	case 'volumes': // output the interval volumes
		if ($debug) echo "calculating and outputting interval volumes<br>";
		$interval_count = create_interval_volumes(
				$intervals_start_time_arg,
				$intervals_end_time_arg,
				$interval_size_arg,
				$interval_rollover,
				$readings_dates,
				$reading_timestamp_array,
				$reading_rateval_array,
				$reading_count,
				$max_reading_gap,
				$interval_timestamp,
				$interval_label,
				$interval_volume,
				$debug);
		if ($debug) echo "created $interval_count intervals";
		for ($i=0;$i<$interval_count;$i++) {
			$volumestable["rows"][$i]["c"][0]["v"] = $interval_label[$i];
			if ($precision==99) {
				$volumestable["rows"][$i]["c"][1]["v"] = $interval_volume[$i]*$conv_factor;
			} else {
				$volumestable["rows"][$i]["c"][1]["v"] = round($interval_volume[$i]*$conv_factor,$precision);
			}
		}
		switch ($output_format) {
			case 'csv': // output them to a csv file - this is required as part of gviz data source
				if ($debug) echo "outputting to a csv file:<br>";
				$path = '/tmp/';
				$file_name_base = "rate_readings_data_".date('YmdHis');
				$file_name = $file_name_base.".csv";
				$csvfile = $path.$file_name;
				if ($debug) echo "file name: $csvfile<br>";
				$handle = fopen($csvfile, 'w');
				if(!$handle) {
					echo "<br>";
					echo "System error creating CSV file: $csvfile <br>Contact Support.";
					echo "<br>";
				} else {
					$column_count = 2;
					$row = array();
					for ($c=0;$c<$column_count;$c++) {
						$row[] = $volumestable["cols"][$c]["label"];
					}
					fputcsv($handle,$row);
					for ($r=0;$r<$interval_count;$r++) {
						$row = array();
						for ($c=0;$c<$column_count;$c++) {
							$row[] = $volumestable["rows"][$r]["c"][$c]["v"];
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
				$json_data_table = json_encode($volumestable);
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
				break;
			case 'html_table_2d': // output a simple html table, rows and columns, i.e. 2 dimensional
				$column_count = 2;
				echo "<table cellspacing=\"0\" border=\"2\">\n";
				echo "<tr>";
				for ($c=0;$c<$column_count;$c++) {
					echo "<td>";
					echo $volumestable["cols"][$c]["label"];
					echo "</td>";
				}
				echo "</tr>\n";
				for ($r=0;$r<$interval_count;$r++) {
					echo "<tr>";
					for ($c=0;$c<$column_count;$c++) {
						echo "<td>";
						echo $volumestable["rows"][$r]["c"][$c]["v"];
						echo "</td>";
					}
					echo "</tr>\n";
				}
				echo "</table>\n";
				break;
			case 'html_table_raw': // dump the data array into a html table supporting more than 2 dimensions
			default:
				html_show_array($volumestable);
		}
		break;
	case 'raw': // output the readings data
	default:
		if ($debug) echo "outputting raw readings data<br>";
		switch ($output_format) {
			case 'csv': // output them to a csv file - this is required as part of 
				if ($debug) echo "outputting to a csv file:<br>";
				$path = '/tmp/';
				$file_name_base = "rate_readings_data_".date('YmdHis');
				$file_name = $file_name_base.".csv";
				$csvfile = $path.$file_name;
				if ($debug) echo "file name: $csvfile<br>";
				$handle = fopen($csvfile, 'w');
				if(!$handle) {
					echo "<br>";
					echo "System error creating CSV file: $csvfile <br>Contact Support.";
					echo "<br>";
				} else {
					$column_count = 2;
					$row = array();
					for ($c=0;$c<$column_count;$c++) {
						$row[] = $readingstable["cols"][$c]["label"];
					}
					fputcsv($handle,$row);
					for ($r=0;$r<$reading_count;$r++) {
						$row = array();
						for ($c=0;$c<$column_count;$c++) {
							$row[] = $readingstable["rows"][$r]["c"][$c]["v"];
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
			case 'json':
				// now create the gv json object
				//   first the json data table
				$json_data_table = json_encode($readingstable);
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
				break;
			case 'html_table_2d':
				$column_count = 2;
				echo "<table cellspacing=\"0\" border=\"2\">\n";
				echo "<tr>";
				for ($c=0;$c<$column_count;$c++) {
					echo "<td>";
					echo $readingstable["cols"][$c]["label"];
					echo "</td>";
				}
				echo "</tr>\n";
				for ($r=0;$r<$reading_count;$r++) {
					echo "<tr>";
					for ($c=0;$c<$column_count;$c++) {
						echo "<td>";
						echo $readingstable["rows"][$r]["c"][$c]["v"];
						echo "</td>";
					}
					echo "</tr>\n";
				}
				echo "</table>\n";
				break;
			case 'html_table_raw':
			default:
				html_show_array($readingstable);
		}
}
