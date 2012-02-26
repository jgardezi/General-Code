<?php

///////////////////////////////////////////////////////////////
///     F U N C T I O N
///////////////////////////////////////////////////////////////

function get_between($input, $start, $end) 
{ 
  $substr = substr($input, strlen($start)+strpos($input, $start), (strlen($input) - strpos($input, $end))*(-1)); 
  return $substr; 
} 

function execute($src_result, $target_db="", $dup_update=0) {

	global $primarykey, $des_table, $src_db, $des_db;
	global $synclog;
	
	$insert_row=0;
	$update_row=0;
	
	$des_table=($target_db=="" ? $des_table : $target_db.".".$des_table);  
	//print "des_table : $des_table\n";
	if (empty($src_result)) return;
	while($row = mysql_fetch_assoc($src_result)) {

		$key_array = explode(":", $primarykey);

		$where="";
		foreach ($key_array as $key=>$value) {
			//print "Keyvalue : $key => $value\n";
			$where .= "{$value}='".mysql_real_escape_string($row[$value]) . "' | ";
		}		
		$pre_where=explode("|", $where);
		unset($pre_where[count($key_array)]);
		$where=implode(" AND " , $pre_where );

		$sql = "SELECT {$key_array[0]} FROM {$des_table} WHERE {$where}"; 
		//print "<p>$sql</p>";
		
		$search_des_table = mysql_query($sql, $des_db);

		//print "<br/> num_rows : " . mysql_num_rows($search_des_table);

		if (mysql_num_rows($search_des_table) == 0) { // insert
		
			$insert = formulate_insert_row($des_table, $row, $src_db, $primarykey);
			//print "insert : ".$insert."\n"; 
			
			$des_result = mysql_query($insert, $des_db)
				or die("Error : " . mysql_error());
			$insert_row ++;
		} elseif (mysql_num_rows($search_des_table) == 1) { 	// update							
      
		  if ($dup_update!=1) { // Do not update existing record
  			//print "updated : {$row[$primarykey]}\n";	
  			$update = formulate_update_row($des_table, $row, $src_db, $primarykey);
  			$des_result = mysql_query($update, $des_db)
  				or die("Error : " . mysql_error());
  			$update_row ++;				
		  }
							
		} else {
			print "<p>oops there are more than one record..!! : key ==> $row[$primarykey]</p>";
			fwrite($synclog,"oops there are more than one record..!! : {$sql}\nkey ==> {$row[$primarykey]}\n"); 
		}
	}
	fwrite($synclog,"===============================\n");
	fwrite($synclog,"Table name : {$des_table}\n"); 
	fwrite($synclog,"No of inserted row : {$insert_row}\n"); 
	fwrite($synclog,"No of updated row  : {$update_row}\n"); 
	
}

function execute_project_fields($src_result) {
	global $des_table, $src_db, $des_db;
	global $synclog;
	$process_row=0;
	$cnt=0;
	
	while($row = mysql_fetch_assoc($src_result)) {
	
		//if ($cnt++ > 500) break;
		
		$uid=$row['uid'];
		$nid=$row['nid'];

		$sql = "SELECT * FROM ur_content_type_research_project 
            WHERE nid={$nid}
            ORDER BY vid DESC
            LIMIT 1
            ";		
		$search_profile = mysql_query($sql, $src_db);
		
		/*
		== Fields to process ==
		
		prop_no
		
		*/
		while($row_profile = mysql_fetch_assoc($search_profile)) {		
			// get column metadata //
			$i = 0;
			while ($i < mysql_num_fields($search_profile)) {
				$meta = mysql_fetch_field($search_profile, $i);
				if (substr($meta->name,0,6)=="field_" && (strpos($meta->name, "_image_")==false)) {
					if (substr($meta->name,-6)=="_value") {
						$fieldname=get_between($meta->name,"field_","_value");
					} else if (substr($meta->name,-4)=="_url") {
						$fieldname=substr($meta->name,6, strlen($meta->name)-6-4);
					} else {
						$fieldname="";
					}
					
					if ($fieldname != "") {					
					  //print   $fieldname . "\n";
						$fieldvalue=$row_profile[$meta->name];
						update_field("st_".$fieldname, $nid, $fieldvalue, $row_profile['vid']);
					}

				} 
				$i++;
			}
		}
		
		// field : body
		$fieldname="body";
		$sql_tmp = "SELECT body, vid FROM ur_node_revisions WHERE nid={$nid} ORDER BY vid DESC LIMIT 1";		
		$res = mysql_query($sql_tmp, $src_db);
		$row_tmp = mysql_fetch_row($res);
		$fieldvalue = $row_tmp[0];
		$rev_id = $row_tmp[1];
		update_field("st_".$fieldname, $nid, $fieldvalue, $rev_id);
		
		// field : collaborators
		$fieldname="collaborators";
		type_delta_name($fieldname, $nid, $uid);
		
		// disabling...
		// field : default_search_keyword 
		// $fieldname="default_search_keyword";
		// type_no_delta($fieldname, $nid, $nid);
				
		// field : faculty
		$fieldname="faculty";
		$fieldvalue="Medicine";
		update_field("st_".$fieldname, $nid, $fieldvalue);		
	
		
		// field : key_contact
		$fieldname="keycontact";
		type_no_delta_nid($fieldname, $nid, $uid, 1); //$reference => 1 : user_reference, 2 : node_reference 
		
		
		// field : media_image_collection
		$fieldname="media_image_collection";
		type_no_delta($fieldname, $nid, $uid);
		
		// field : opportunities
		// skip this.
		
		// field : projectteam
		$fieldname="projectteam";
		type_delta_nid($fieldname, $nid, $uid, "uid", 1); //$reference => 1 : user_reference, 2 : node_reference 
		
		// field : project_facility
		// skip this.
		
		// field : project_image
		// ????????????????????????????
		
		// field : publications
		$fieldname="publications";
		type_delta($fieldname, $nid, $uid, "field_{$fieldname}_publication_id", 2); //$reference => 1 : user_reference, 2 : node_reference 		

/*
		// field : related_links
		$fieldname="related_links";
		type_delta($fieldname, $nid, $uid, "field_{$fieldname}_nid");		
*/ 
		
		// field : research_field
		$fieldname="research_field";
		type_delta_nid($fieldname, $nid, $uid, "title");		
		
		
		// field : seo_tags
		$fieldname="seo_tags";
		type_delta_nid($fieldname, $nid, $uid, "title");
		
		
		// field : subtitle
		$fieldname="subtitle";
		type_no_delta($fieldname, $nid, $uid);


		// field : supporters
		$fieldname="supporters";
		type_delta_nid($fieldname, $nid, $uid, "title");
			
		// field : tv_collection_url
		$fieldname="tv_collection_url";
		type_no_delta($fieldname, $nid, $uid);

		// field : archived
		$fieldname="archived";
		type_no_delta($fieldname, $nid, $uid);
		 
		// field : project image
		$fieldname="project_image";
		$sql_tmp = "SELECT nid,  field_project_image_fid
								FROM ur_content_field_project_image 
								WHERE nid={$nid} AND field_project_image_fid IS NOT NULL
                ORDER BY vid DESC
                LIMIT 1       
								";		
		$res = mysql_query($sql_tmp, $src_db);		
		if ($res) {
		  $row = mysql_fetch_row($res);
  		$fid=$row[1]; // field_project_image_fid
  		if (!empty($fid)) process_image($fieldname, $nid, $nid, $fid);
		}
		
		$process_row++;
		
	}
	
	fwrite($synclog,"===============================\n");
	fwrite($synclog,"Table name : {$des_table}\n"); 
	fwrite($synclog,"No of inserted row : {$process_row}\n");
	
}

function process_image($fieldname, $nid, $uid, $fid_rg='NULL') {
//Process photo image file
	global $src_db;
	global $des_db;
	
	$sql_file = "SELECT *
					FROM ur_files
					WHERE fid={$fid_rg} LIMIT 1";			
	$res_file = mysql_query($sql_file, $src_db);

	while($row_file = mysql_fetch_assoc($res_file)) {	
		$filename=mysql_real_escape_string($row_file['filename']);		
		$filepath="http://research.unsw.edu.au/sites/all/files/images/project/{$filename}";
		
		if (rg_img_download("project", $filepath)) {		  
      // step 1. file_managed table
      $uri="public://project/pictures/{$filename}";
      $result = mysql_query("SELECT fid, filesize
                              FROM newsevents.file_managed
                              WHERE uri='{$uri}'
                              ",
                            $des_db);
      
      if (mysql_num_rows($result) > 0) {        
         $row = mysql_fetch_row($result);
         $fid = $row[0];
         $filesize = $row[1];
         // if file size == 9999 then it is RG image, lets replace it. otherwise just skip it (must be local image).
         if ($filesize=="9999") {
           // okay. this is the image from RG, then delete & replace it all the time.   
           /*
           $sql="DELETE FROM newsevents.file_managed      							
     							WHERE fid < {$fid} AND filesize=9999
     							";
           mysql_query($sql, $des_db); 
           */
           $sql="UPDATE newsevents.file_managed 
     							SET uid=1, 
     									uri='{$uri}',
     									filename='{$filename}'  									 
     							WHERE fid = {$fid}
     							";
           mysql_query($sql, $des_db);
         }
           
      } else {    
         $sql="INSERT INTO newsevents.file_managed 
         							(uid, filename, uri, filemime, filesize, status, timestamp) 
         							VALUES (1, '{$filename}', '{$uri}', 'image/jpeg', 9999, 1, UNIX_TIMESTAMP())
         						";
         mysql_query($sql, $des_db);
         $fid = mysql_insert_id();
      } 
      
      
		  // step 2. file_usage table
  		$result = mysql_query("SELECT fid 
  														FROM newsevents.file_usage
  														WHERE fid={$fid}
  		                      ", $des_db);
      if (mysql_num_rows($result) > 0) {                 
        
        $del_sql="SELECT u.fid FROM newsevents.file_usage	u     
              LEFT JOIN newsevents.file_managed  m 
              ON u.fid = m.fid					
              WHERE u.fid < {$fid} AND u.id={$nid}
              ";
             
        $del_result = mysql_query($del_sql, $des_db);
        while($row_file = mysql_fetch_assoc($del_result)) {
          $sql="DELETE FROM newsevents.file_usage      							
                  WHERE fid = {$row_file['fid']}
                  ";
          //print $fid . " removed.\n";
          mysql_query($sql, $des_db);
        }
        
        $sql="UPDATE newsevents.file_usage 
     							SET id={$nid}
     							WHERE fid = {$fid}
     							";
        mysql_query($sql, $des_db);
      } else {
        $res = mysql_query("INSERT INTO newsevents.file_usage (fid, module, type, id, count) VALUES ({$fid}, 'file', 'node', {$nid}, 1)", $des_db);  
      }
            
      // step 3. update users table in profiles db.
		  $result = mysql_query("SELECT entity_id  
  														FROM profiles.field_data_field_st_project_image
  														WHERE entity_id={$nid} AND entity_type='node'
  													", 
		                        $des_db);
      if (mysql_num_rows($result) > 0) {
        mysql_query("UPDATE profiles.field_data_field_st_project_image 
        							SET field_st_project_image_fid={$fid} WHERE entity_id = {$nid}
        						", $des_db);
      } else {
        $sql="INSERT INTO profiles.field_data_field_st_project_image 
         							(entity_type, bundle, deleted, entity_id, revision_id, language, delta, field_st_project_image_fid, field_st_project_image_alt, field_st_project_image_title) 
         							VALUES ('node', 'research_project', 0, {$nid}, {$nid}, 'und', 0, {$fid}, NULL, NULL)
         						";
         mysql_query($sql, $des_db);
      }
      
		}
	}

}

function type_delta_name($fieldname, $nid, $uid) {
	//FORMAT : vid, nid, delta, nid_reference

	global $src_db;

	$sql_tmp = "SELECT c.field_{$fieldname}_name AS title, c.delta, c.vid 
				FROM ur_content_field_{$fieldname} c
				WHERE c.nid={$nid} AND c.field_{$fieldname}_name IS NOT NULL";		

	$res = mysql_query($sql_tmp, $src_db);
	
	$fieldname = "st_{$fieldname}";
	
	while($row_tmp = mysql_fetch_assoc($res)) {
		$fieldvalue = $row_tmp['title'];
		$rev_id = $row_tmp['vid'];
		$delta = $row_tmp['delta'];		
		update_field($fieldname, $nid, $fieldvalue, $rev_id, $delta);
	}	
}

function type_delta($fieldname, $nid, $uid, $orig_field_name="", $reference=0) {
	//FORMAT : vid, nid, delta, id

	global $src_db;

	$sql_tmp = "SELECT c.{$orig_field_name} AS id, c.delta, c.vid 
				FROM ur_content_field_{$fieldname} c
				WHERE c.nid={$nid} AND c.{$orig_field_name} IS NOT NULL";		

	$fieldname = "st_{$fieldname}";
//echo "<p>$sql_tmp</p>";
	$res = mysql_query($sql_tmp, $src_db);
	while($row_tmp = mysql_fetch_assoc($res)) {
		$fieldvalue = $row_tmp['id'];
		$rev_id = $row_tmp['vid'];
		$delta = $row_tmp['delta'];
		update_field($fieldname, $nid, $fieldvalue, $rev_id, $delta, $reference);
	}
}

function type_delta_nid($fieldname, $nid, $uid, $return_column="", $reference=0) {
	//FORMAT : vid, nid, delta, nid_reference

	global $src_db;

	$sql_tmp = "SELECT n.{$return_column}, c.delta, n.vid 
				FROM ur_content_field_{$fieldname} c
				JOIN ur_node n ON c.field_{$fieldname}_nid=n.nid
				WHERE c.nid={$nid} AND c.field_{$fieldname}_nid IS NOT NULL";		

	$fieldname = "st_{$fieldname}";
//echo "<p>$sql_tmp</p>";	
	$res = mysql_query($sql_tmp, $src_db);
	while($row_tmp = mysql_fetch_assoc($res)) {
		$fieldvalue = $row_tmp["{$return_column}"];
		$rev_id = $row_tmp['vid'];
		$delta = $row_tmp['delta'];
		update_field($fieldname, $nid, $fieldvalue, $rev_id, $delta, $reference);
	}
}

function type_no_delta($fieldname, $nid, $uid) {
	//FORMAT : vid, nid, value

	global $src_db;
	
	$sql_tmp = "SELECT field_{$fieldname}_value, vid 
				FROM ur_content_field_{$fieldname} 
				WHERE nid={$nid}
				ORDER BY vid DESC LIMIT 1";		
//echo "<p>$sql_tmp</p>";		
	
	$res = mysql_query($sql_tmp, $src_db);
	while($row_tmp = mysql_fetch_assoc($res)) {
		$fieldvalue = $row_tmp["field_{$fieldname}_value"];
		$rev_id = $row_tmp['vid'];
		update_field("st_".$fieldname, $nid, $fieldvalue, $rev_id);
	}
}


function type_no_delta_nid($fieldname, $nid, $uid, $reference=0) {
	//FORMAT : vid, nid, nid_reference
	// nid_reference is "uid"
	
	global $src_db;

	$sql_tmp = "SELECT n.uid, c.vid 
				FROM ur_content_field_{$fieldname} c
				JOIN ur_node n ON c.field_{$fieldname}_nid=n.nid
				WHERE c.nid={$nid}
				ORDER BY c.vid DESC LIMIT 1";	
  //print "$sql_tmp\n";				
	$res = mysql_query($sql_tmp, $src_db);
	
	$fieldname = "st_{$fieldname}";
	
	while($row_tmp = mysql_fetch_assoc($res)) {
		$fieldvalue = $row_tmp['uid'];
		$rev_id = $row_tmp['vid'];
		update_field($fieldname, $nid, $fieldvalue, $rev_id, 0, $reference);
	}
}

function update_field($fieldname, $nid, $fieldvalue, $rev_id='NULL', $delta=0, $reference=0) {
	global $des_db;
	
	if ($delta==0) {
		$sql_field = "SELECT entity_id FROM field_data_field_{$fieldname} WHERE entity_id={$nid}";			
	} else {
		$sql_field = "SELECT entity_id FROM field_data_field_{$fieldname} WHERE entity_id={$nid} AND delta={$delta}";			
	}
	
	$search_field = mysql_query($sql_field, $des_db);
	//print $sql_field . " : {$fieldvalue}\n";

	$fieldvalue=mysql_real_escape_string($fieldvalue); // escapes special characters
	
	if ($reference==1) {
    $postfix="uid";
  } else if ($reference==2) {
    $postfix="nid";
  } else {
    $postfix="value";
  }
	
	//$postfix = ( substr($fieldname,-6)=="_image" ? "fid" : "value" );
	if (empty($search_field)) return;
	if (mysql_num_rows($search_field) == 0) { // insert		
		if (!($fieldvalue == "" || $fieldvalue == 'NULL')) {
			$insert = " INSERT INTO field_data_field_{$fieldname} 
						(entity_type, bundle, deleted, entity_id, revision_id, language, delta, field_{$fieldname}_{$postfix}) 
						VALUES('node', 'research_project', 0, {$nid}, {$rev_id}, 'und', {$delta}, '{$fieldvalue}') ";		
    //print "$insert\n";						
			$search = mysql_query($insert, $des_db);
		}
	} else { //update
		if (!($fieldvalue == "" || $fieldvalue == 'NULL')) {
			$update = "UPDATE field_data_field_{$fieldname} 
						SET field_{$fieldname}_{$postfix}='{$fieldvalue}', 
						revision_id='{$rev_id}', 
						language='und',
						delta={$delta} 
						WHERE entity_id={$nid} ";
			$search = mysql_query($update, $des_db);
		} else { // delete if value is null
			$delete = "DELETE FROM field_data_field_{$fieldname} WHERE entity_id={$nid} ";
			$search = mysql_query($delete, $des_db);								
		}
	}
}

function formulate_update_row($table, $row, $db, $primarykey) {
	$fields=""; $values = "";
	$flag=true;
	$key_array = explode(":", $primarykey);
	$where="";
	foreach ($key_array as $key=>$value) {
		$where .= "{$value}='".mysql_real_escape_string($row[$value]) . "' | "; 
	}	
	$pre_where=explode("|", $where);
	unset($pre_where[count($key_array)]);
	$where=implode(" AND " , $pre_where );
	
	foreach($row as $key=>$value) {
		$values = "";
		if (strpos($primarykey,$key)==false) {		
			if($flag) $flag=false; else { $fields.= ", "; }
			if (is_null($value)) {
				$values.= "NULL";  		// always string ??? -- looks working fine
			} else {
				$values.= "'".mysql_real_escape_string($value, $db)."'";  		// always string ??? -- looks working fine
			}
			$fields.= $key . "=" . $values;
		}
	}

	return " UPDATE {$table} SET {$fields} WHERE {$where} ";
}

function formulate_insert_row($table, $row, $db, $primarykey) {
	$fields=""; $values = "";
	$flag=true;
	
	foreach($row as $key=>$value) {
		if($flag) $flag=false; else { $values.= ", "; $fields.= ", "; }
		if (is_null($value)) {
			$values.= "NULL";  		// always string ??? -- looks working fine
		} else {
			$values.= "'".mysql_real_escape_string($value, $db)."'";  		// always string ??? -- looks working fine
		}
		$fields.= $key;
	}

	return " INSERT INTO {$table} ({$fields}) VALUES({$values}) ";
}

function bulk_update() {
	global $des_db;
	global $synclog;

	$result = mysql_query("UPDATE field_data_field_st_body 
						SET field_st_body_format ='filtered_html' 
						WHERE field_st_body_format  IS NULL", $des_db);
	
	fwrite($synclog,"===============================\n");
	fwrite($synclog,"Bulk Update completed\n");

}

function copy_projects_node_to_shared_dbs() { 
/*
  Projects

  a. From RG to Profiles : sync status

  b. Profiles to each DB
    - if option is true (update from RG): sync status
    - if option is false(No update from RG): 
      * if new record : set status = 0
      * if Status = 0 : do not update
      * if Status = 1 : do not update
*/

	global $des_db;
	global $des_table;
	global $primarykey;
  
	// get variable value from profiles database. This value is set in Configuration of 'Content Control' module.
  $query = mysql_query("SELECT value FROM variable	WHERE name='projects_pubs_copy_to_db_name' LIMIT 1", $des_db);
	$value = mysql_fetch_row($query);	
  $target_dbs=explode("\n", unserialize($value[0]));  
  $db_list=array();  
  foreach ($target_dbs as $db_name) {  
    $db_list[]=trim($db_name);
  }
  $DB_TO_COPY=$db_list;
  
  foreach($DB_TO_COPY as $db_name) {
       
    $node_type="research_project";
      
    $query = mysql_query("SELECT value FROM {$db_name}.variable	WHERE name='school_project' LIMIT 1");
    $value = mysql_fetch_row($query);	    
    $school_list=unserialize($value[0]); 
    
    if (empty($school_list)) continue;
    
    foreach ($school_list as $school_name) {      
      $project_query = mysql_query("SELECT value FROM {$db_name}.variable WHERE name='status_update_project' LIMIT 1");
      $project_status = mysql_fetch_row($project_query);	
      $project_status = unserialize($project_status[0]);
      $node_status=($project_status==TRUE ? "n.status":"IFNULL((SELECT status from {$db_name}.node AS target where target.nid=n.nid), 0) AS status");
      $node_revision_status=($project_status==TRUE ? "nr.status":"IFNULL((SELECT status from {$db_name}.node AS target where target.nid=n.nid), 0) AS status");

      // 1. node table.
      // note : exclude local contents 
    	$primarykey="nid";		// primary key for both tables 
      $src_table="node";	// source table
      $des_table="node";		// destination table
      $src_result = mysql_query(" 
      	SELECT n.nid, n.vid, n.type, n.language, n.title, n.uid, " . $node_status . ", n.created, n.changed
   		 	FROM {$src_table} n
          JOIN field_data_field_st_keycontact k ON k.entity_id = n.nid
          JOIN field_data_field_pf_school s ON k.field_st_keycontact_uid=s.entity_id
      	WHERE n.type='" . $node_type . "' 
      		AND s.field_pf_school_value=\"" . $school_name . "\"
      		AND n.nid BETWEEN 10000 AND 9999999      	
      	", $des_db);

  		execute($src_result, $db_name);
  
  		// 2. node_revision table.
    	$primarykey="nid:vid";		// primary key for both tables 
      $src_table="node_revision";	// source table
      $des_table="node_revision";		// destination table
      $src_result = mysql_query(" 
      	SELECT nr.nid, nr.vid, nr.uid, " . $node_revision_status . ", nr.title
   		 	FROM {$src_table} nr
   		 		JOIN node n ON n.nid=nr.nid
          JOIN field_data_field_st_keycontact k ON k.entity_id = nr.nid
          JOIN field_data_field_pf_school s ON k.field_st_keycontact_uid=s.entity_id
      	WHERE n.type='" . $node_type . "' 
                AND s.field_pf_school_value=\"" . $school_name . "\"      	
                AND nr.nid BETWEEN 10000 AND 9999999

      	", $des_db);    
   				
  		execute($src_result, $db_name);  
  		
  		// 3. node_comment_statistics table.
    	$primarykey="nid";		// primary key for both tables 
      $src_table="node";	// source table
      $des_table="node_comment_statistics";		// destination table
      
      $db_result=@mysql_query("SELECT * FROM $db_name.$des_table LIMIT 1");
      if ($db_result) {
        $src_result = mysql_query(" 
          SELECT 	n.nid, 
                  '0' as cid, 
                  UNIX_TIMESTAMP() as last_comment_timestamp, 
                  'NULL' as last_comment_name, 
                  n.uid as last_comment_uid, 
                  '0' as comment_count
            FROM {$src_table} n
            JOIN field_data_field_st_keycontact k ON k.entity_id = n.nid
            JOIN field_data_field_pf_school s ON k.field_st_keycontact_uid=s.entity_id
          WHERE n.type='" . $node_type . "' 
            AND s.field_pf_school_value=\"" . $school_name . "\"
            AND n.nid BETWEEN 10000 AND 9999999
          ", $des_db);  

        execute($src_result, $db_name, 1);
      }
    }  
  }
}

?>
