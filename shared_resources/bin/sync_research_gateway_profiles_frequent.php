<?php
// running every 15 minutes.
// considering buffer to select all possible changes,so gave 16 minutes. - assuming it updates data within a minute.

chdir( dirname ( __FILE__ ) );
chdir ("../");
$cwd=getcwd();

include_once("{$cwd}/functions/functions_profiles.php");   
include_once("{$cwd}/functions/function_rg_img_download.php");
include_once("{$cwd}/functions/RGImg.class.php");
include_once("{$cwd}/functions/functions_credential.php");

set_time_limit(120); 

$log_path = dirname ( __FILE__ ) . "/log_sync_research_gateway";
if (!file_exists("{$log_path}")) mkdir("{$log_path}", 0777);
$synclog = fopen("{$log_path}/log_profiles_frequent_".date('Ymd-His').".txt","w");

// 1. server connect  //////////////////////////////////////////////////////////////////////
$src_db_name = "unsw"; 
$des_db_name = "profiles";
$med_faculty_id="57893";

$hostname_fin10 = "127.0.0.1";	// connect to fin10 server.. Note : DO NOT USE "localhost". USE "127.0.0.1"
$username_fin10 = "resgate_ro";
$password_fin10 = "PegNanUg1";  

$src_db = mysql_connect($hostname_fin10, $username_fin10, $password_fin10) 
	or die("Unable to connect to fin10 server\n");

$selected_fin10 = mysql_select_db($src_db_name,$src_db) 
	or die("Could not select unsw db in fin10 server\n");  

$hostname=gethostname();
$username="drupal";
$password=get_credential($hostname);
$hostname=str_replace("ws001", "db001", $hostname);

if (empty($username) || empty($password)) exit("Cannot set password.\n");

$des_db = mysql_connect($hostname, $username, $password) 
	or die("Unable to connect to MySQL\n");

$selected = mysql_select_db($des_db_name,$des_db) 
	or die("Could not select unsw\n");

fwrite($synclog,"DB connection opened --- sync start time : " . date('Ymd-His') ."\n"); 

// 2. process each table ///////////////////////////////////////////////////////////////////////

// table : users - start // 
$src_table="ur_users";	// source table
$des_table="users";		// destination table
$primarykey="uid";		// primary key for both tables

$src_result = mysql_query("
	SELECT DISTINCT u.uid, u.name, u.mail, u.created FROM {$src_table} u 
		JOIN ur_node n ON (n.uid = u.uid)		
		JOIN ur_content_field_faculty f ON (n.nid = f.nid AND n.vid=f.vid)	
	WHERE f.field_faculty_nid={$med_faculty_id} AND SUBSTRING(u.name,1,1)='z'
		AND TIME_TO_SEC(TIMEDIFF(NOW() , FROM_UNIXTIME(u.created))) <= 16*60
	", $src_db);
   
execute($src_result);
// table : users - end //

// table : profile fields - start //
$src_table="ur_node";	// source table
$des_table="node";		// destination table
$primarykey="nid";		// primary key for both tables

$src_result = mysql_query("
	SELECT n.nid, n.uid
		FROM {$src_table} n 
		JOIN ur_content_field_faculty f ON (n.nid = f.nid AND n.vid=f.vid)
		JOIN ur_node n2 ON n2.nid = f.field_faculty_nid 
	WHERE f.field_faculty_nid={$med_faculty_id}
			AND TIME_TO_SEC(TIMEDIFF(NOW() , FROM_UNIXTIME(n.changed))) <= 16*60
	", $src_db);
	
execute_profile_fields($src_result);

// table : profile fields - end //

// bulk update
bulk_update();

mysql_close($src_db);
mysql_close($des_db);

fwrite($synclog,"===============================\n"); 
fwrite($synclog,"DB connection closed --- sync end time : " . date('Ymd-His') ."\n"); 
fclose($synclog);

?>
