<?php

chdir( dirname ( __FILE__ ) );
chdir ("../");
$cwd=getcwd();

include_once("{$cwd}/functions/functions_profiles.php");   
include_once("{$cwd}/functions/function_rg_img_download.php");
include_once("{$cwd}/functions/RGImg.class.php");
include_once("{$cwd}/functions/functions_credential.php");

set_time_limit(1800);  
ini_set('memory_limit','256M'); // for image processing... otherwise it creates memory error. not a good solution, though.

$log_path = dirname ( __FILE__ ) . "\\log_sync_research_gateway";
//print ($log_path);
if (!file_exists("{$log_path}")) mkdir("{$log_path}", 0777);
$synclog = fopen("{$log_path}/log_profiles_".date('Ymd-His').".txt","w");

/*
 * 1. server connect  
 */ 
$src_db_name = "rqw";
$des_db_name = "profiles";
$med_faculty_id="57893";

$hostname_fin10 = "127.0.0.1";	// connect to fin10 server.. Note : DO NOT USE "localhost". USE "127.0.0.1"
$username_fin10 = "root";
$password_fin10 = "";

$src_db = mysql_connect($hostname_fin10, $username_fin10, $password_fin10) 
	or die("Unable to connect to fin10 server\n");

$selected_fin10 = mysql_select_db($src_db_name,$src_db) 
	or die("Could not select unsw db in fin10 server\n");  

$hostname= "localhost";
$username="root";
$password=get_credential($hostname);
$hostname=str_replace("ws001", "db001", $hostname);

print ($hostname ." ". $username ." ". $password );


//if (empty($username) || empty($password)) exit("Cannot set username or password.\n");

$des_db = mysql_connect($hostname, $username, $password) 
	or die("Unable to connect to MySQL\n");

$selected = mysql_select_db($des_db_name,$des_db) 
	or die("Could not select unsw\n");

fwrite($synclog,"DB connection opened --- sync start time : " . date('Ymd-His') ."\n"); 

// 2. process each table ///////////////////////////////////////////////////////////////////////
// table : users - start //

$src_table="ur_users";	// source table   
$des_table="users";		  // destination table
$primarykey="uid";		  // primary key for both tables

$src_result = mysql_query("
  SELECT DISTINCT u.uid, u.name, u.mail, u.created FROM {$src_table} u  
  JOIN ur_node n ON (n.uid = u.uid)		
  JOIN ur_content_field_faculty f ON (n.nid = f.nid AND n.vid=f.vid)	
  WHERE f.field_faculty_nid={$med_faculty_id} AND SUBSTRING(u.name,1,1)='z'
  ", $src_db);
	
execute($src_result);
// table : users - end //

// table : profile fields - start //
$src_table="ur_node";	// source table
$des_table="node";		// destination table
$primarykey="nid";		// primary key for both tables

$src_result = mysql_query("
  SELECT DISTINCT n.nid, n.uid FROM {$src_table} n 
  JOIN ur_content_field_faculty f ON (n.nid = f.nid  AND n.vid=f.vid)
  JOIN ur_node n2 ON n2.nid = f.field_faculty_nid 
  WHERE f.field_faculty_nid={$med_faculty_id}
  ", $src_db);
execute_profile_fields($src_result);
// table : profile fields - end //

// bulk update
bulk_update();

// copy users records to target database - we are copying the record in users table from profiles db to target db.
copy_users_record_to_shared_dbs();

search_api_delete_blocked_unpublished();

//copy over profile fields to other database
copy_profile_fields_to_dbs(); 

// updating title + fullname (format : Title + FirstName + LastName)
$des_table="field_data_field_pf_title_fullname";		// destination table
$primarykey="entity_id";		// primary key for both tables

$src_result = mysql_query("
	SELECT 
  	l.entity_type, 
  	l.bundle, 
  	l.deleted, 
  	l.entity_id, 
  	NULL as revision_id, 
  	l.language, 
  	l.delta, 
		IFNULL(TRIM(CONCAT(t.field_pf_title_value, ' ', f.field_pf_firstname_value, ' ', l.field_pf_lastname_value)), l.field_pf_lastname_value) AS field_pf_title_fullname_value,
		NULL AS field_pf_title_fullname_format
	FROM field_data_field_pf_lastname l
		LEFT JOIN field_data_field_pf_title t ON t.entity_id = l.entity_id
		LEFT JOIN field_data_field_pf_firstname f ON f.entity_id = l.entity_id
	", $des_db);
execute($src_result);

// create school taxonomy -- not in working condition
// push_school_taxonomy(); 

mysql_close($src_db);
mysql_close($des_db);

fwrite($synclog,"===============================\n"); 
fwrite($synclog,"DB connection closed --- sync end time : " . date('Ymd-His') ."\n"); 
fclose($synclog);

?>
