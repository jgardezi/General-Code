<?php

chdir( dirname ( __FILE__ ) );
chdir ("../");
$cwd=getcwd(); 

include_once("{$cwd}/functions/functions_publications.php"); 
include_once("{$cwd}/functions/functions_credential.php");

set_time_limit(1800); 

$log_path = dirname ( __FILE__ ) . "/log_sync_research_gateway";
if (!file_exists("{$log_path}")) mkdir("{$log_path}", 0777);
$synclog = fopen("{$log_path}/log_publications_".date('Ymd-His').".txt","w");

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
// create node records from publication table
$src_table="ur_rg_publication";	// source table
$des_table="node";		// destination table
$primarykey="nid";		// primary key for both tables

$timestamp = date("Ymd");
$dw = date( "w", $timestamp); // 0 (for Sunday) through 6 (for Saturday)
$dy = date( "Y", $timestamp);
$restrict_where = $dw == 0 ? " AND p.year='$dy' " : ""; // process this years pubs only if not Sunday

// get publications owned by medicine staff
$sql = "  
  SELECT DISTINCT p.PUB_ID AS nid, p.PUB_ID AS vid, 'publication' AS type, 'und' AS language, LEFT(p.title,255) AS title, 1 AS uid, 1 AS status,  UNIX_TIMESTAMP() AS created, UNIX_TIMESTAMP() AS changed, 0 AS comment, 0 AS promote, 0 AS sticky, 0 AS tnid, 0 AS translate	
  FROM {$src_table} p	
  JOIN ur_rg_publication_owner o ON (o.PUB_ID = p.PUB_ID)	
  JOIN ur_content_type_profile cp ON cp.field_emplid_value=o.EMPLOYER_ID
  JOIN ur_content_field_faculty f ON (cp.nid = f.nid AND cp.vid=f.vid)	
  WHERE f.field_faculty_nid={$med_faculty_id} and p.title IS NOT NULL 
  {$restrict_where} 
	";
$src_result = mysql_query($sql, $src_db);

execute($src_result);

// table : node - end //

// table : node_revisions - start //
// updating from "destination db" - our medicine profiles db.

$src_table="node";	// source table
$des_table="node_revision";		// destination table
$primarykey="nid";		// primary key for both tables

$restrict_limit = $dw == 0 ? "" : " ORDER BY n.nid DESC LIMIT 500 "; // process last 500 pubs only if not Sunday

$src_result = mysql_query("
  SELECT n.nid, n.vid, n.uid, n.title, '' AS log, n.changed AS timestamp, n.status AS status
  FROM {$src_table} n
  WHERE n.type='publication'
  {$restrict_limit}
  ", $des_db); 
	
execute($src_result); 
// table : node_revisions - end //

// table : publication fields - start //
// updating from "destination db"
$src_table="node";	// source table
$des_table="-- node fields --";		// destination table
$primarykey="nid";		// primary key for both tables

$src_result = mysql_query("
  SELECT n.nid, n.uid
  FROM {$src_table} n
  WHERE n.type='publication'
  {$restrict_limit}
  ", $des_db);

execute_publication_fields($src_result);

bulk_update();

// table : publication fields - end // 

copy_publications_node_to_shared_dbs(); 

mysql_close($src_db);
mysql_close($des_db);

fwrite($synclog,"===============================\n"); 
fwrite($synclog,"DB connection closed --- sync end time : " . date('Ymd-His') ."\n"); 
fclose($synclog);

?>
