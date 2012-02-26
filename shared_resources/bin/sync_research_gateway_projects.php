<?php

chdir( dirname ( __FILE__ ) );
chdir ("../");
$cwd=getcwd();

include_once("{$cwd}/functions/functions_projects.php");  
include_once("{$cwd}/functions/function_rg_img_download.php");
include_once("{$cwd}/functions/RGImg.class.php");
include_once("{$cwd}/functions/functions_credential.php");

set_time_limit(1800); 
ini_set('memory_limit','256M'); // for image processing... otherwise it creates memory error. not a good solution, though.

$log_path = dirname ( __FILE__ ) . "/log_sync_research_gateway";
if (!file_exists("{$log_path}")) mkdir("{$log_path}", 0777);
$synclog = fopen("{$log_path}/log_projects_".date('Ymd-His').".txt","w");

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
// table : node - start //
// copy all medicine node
 
$src_table="ur_node";	// source table
$des_table="node";		// destination table
$primarykey="nid";		// primary key for both tables

$src_result = mysql_query("
  SELECT DISTINCT n.nid, n.vid, n.type, 'und' AS language, n.title, n.uid, n.status,  n.created, n.changed, n.comment, n.promote, n.sticky, n.tnid, 
  n.translate
  FROM {$src_table} n 
    JOIN ur_users u ON (n.uid = u.uid)		
    JOIN ur_content_field_faculty f ON (n.nid = f.nid AND n.vid=f.vid)	
  WHERE f.field_faculty_nid={$med_faculty_id} AND n.type='research_project'
  ", $src_db); 
	
execute($src_result);
// table : node - end //

// table : node_revisions - start //
// copy all medicine node

$src_table="ur_node_revisions";	// source table
$des_table="node_revision";		// destination table
$primarykey="nid";		// primary key for both tables

$src_result = mysql_query("
  SELECT nr.nid, nr.vid, nr.uid, nr.title, '' as log, nr.status,  nr.timestamp 
  FROM {$src_table} nr
  JOIN ur_node n ON nr.nid=n.nid
  JOIN ur_users u ON (n.uid = u.uid)		
  JOIN ur_content_field_faculty f ON (n.nid = f.nid AND n.vid=f.vid)	
  WHERE f.field_faculty_nid={$med_faculty_id} AND n.type='research_project'
  ", $src_db); 
	
execute($src_result);
// table : node_revisions - end //

// table : project fields - start //
$src_table="ur_content_type_research_project";	// source table
$des_table="-- node fields --";		// destination table
$primarykey="nid";		// primary key for both tables

$src_result = mysql_query("
  SELECT c.nid, u.uid FROM {$src_table} c 
  JOIN ur_node n ON n.nid=c.nid
  JOIN ur_users u ON (n.uid = u.uid)		
  JOIN ur_content_field_faculty f ON (n.nid = f.nid AND n.vid=f.vid)	
  WHERE f.field_faculty_nid={$med_faculty_id}
  ", $src_db);

execute_project_fields($src_result);
// table : project fields - end //

// bulk update
bulk_update();

copy_projects_node_to_shared_dbs();  
  
mysql_close($src_db);
mysql_close($des_db);

fwrite($synclog,"===============================\n"); 
fwrite($synclog,"DB connection closed --- sync end time : " . date('Ymd-His') ."\n"); 
fclose($synclog);
?>
