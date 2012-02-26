<?php

///////////////////////////////////////////////////////////////
///     F U N C T I O N
///////////////////////////////////////////////////////////////

function get_between($input, $start, $end) { 
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
		//   print "sql : $sql\n";		
		 //drupal_set_message('<pre>$sql: '. print_r($sql, TRUE) .'</pre>');
		$search_des_table = mysql_query($sql, $des_db);

 //print "num_rows : " . mysql_num_rows($search_des_table) . "\n";      
 // drupal_set_message('<pre>num_rows: '. print_r(mysql_num_rows($search_des_table), TRUE) .'</pre>');
		if (mysql_num_rows($search_des_table) == 0) { // insert
		 
			$insert = formulate_insert_row($des_table, $row, $src_db, $primarykey);  
			//print "insert : ".$insert."\n";
			
			$des_result = mysql_query($insert, $des_db);
      
      if (!$des_result) {          
        $msg="Error : {$insert} : " . mysql_error() . "\n"; 
        print $msg;
        fwrite($synclog,$msg); 
				die($msg);
      }
			$insert_row ++;
		} elseif (mysql_num_rows($search_des_table) == 1) { 	// update							
			//drupal_set_message('<pre>update: '. print_r($row[$primarykey], TRUE) .'</pre>');
		  if ($dup_update!=1) { // Do not update existing record
  			//print "updated : {$row[$primarykey]}\n";	
  			//drupal_set_message('<pre>updated: '. print_r($row[$primarykey], TRUE) .'</pre>');
  			$update = formulate_update_row($des_table, $row, $src_db, $primarykey);
  			$des_result = mysql_query($update, $des_db);
        if (!$des_result) {              
          $msg="Error : {$update} : " . mysql_error() . "\n"; 
          print $msg;
          fwrite($synclog,$msg); 
          die($msg);
        }
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

function execute_profile($src_result) {

	global $des_table, $des_db;
	global $synclog;
	
	$insert_row=0;
	$update_row=0;
	
	while($row = mysql_fetch_assoc($src_result)) {
		$uid=$row['uid'];
		$sql = "SELECT uid FROM {$des_table} WHERE uid={$uid}";
		
		$search_des_table = mysql_query($sql, $des_db);

		//print "<br/> num_rows : " . mysql_num_rows($search_des_table);

		// insert only...
		if (mysql_num_rows($search_des_table) == 0) { // insert
		
			$insert = " INSERT INTO {$des_table} (pid, type, uid) VALUES({$uid}, 'main' , {$uid}) ";
			
			$des_result = mysql_query($insert, $des_db)
				or die("Error : " . mysql_error());
			$insert_row ++;
		}
	}
	fwrite($synclog,"===============================\n");
	fwrite($synclog,"Table name : {$des_table}\n"); 
	fwrite($synclog,"No of inserted row : {$insert_row}\n"); 
	fwrite($synclog,"No of updated row  : {$update_row}\n"); 
	
}


function execute_profile_fields($src_result) {

	global $des_table, $src_db, $des_db;
	global $synclog;
	$process_row=0;
	$cnt=0;
	
	while($row = mysql_fetch_assoc($src_result)) {
	
		//if ($cnt++ > 500) break;
	
		$uid=$row['uid'];
		$nid=$row['nid'];
							
		$sql = "SELECT * FROM ur_content_type_profile WHERE nid={$nid} ORDER BY vid DESC LIMIT 1";		
		$search_profile = mysql_query($sql, $src_db);
		
		/*
		== Fields to process ==
		
		hideprofile
		firstname
		lastname
		title
		emplid
		university_role
		my_linkedin
		my_delicious
		my_contact_email
		middlename
		
		*/

		while($row_profile = mysql_fetch_assoc($search_profile)) {
		
			// get column metadata //
			$i = 0;
			while ($i < mysql_num_fields($search_profile)) {
				$meta = mysql_fetch_field($search_profile, $i);
				if (substr($meta->name,0,6)=="field_") {
				//print $meta->name . "\n";
					if (substr($meta->name,-6)=="_value") {
						$fieldname=get_between($meta->name,"field_","_value");
					} else if (substr($meta->name,-4)=="_url") {
						$fieldname=substr($meta->name,6, strlen($meta->name)-6-4);
					} else {
						$fieldname="";
					}
										
					if ($fieldname != "" && substr($fieldname,0,9)!="override_") {						
						$fieldvalue=$row_profile[$meta->name];
						//print $fieldname . " : $fieldvalue\n";
						update_field($fieldname, $uid, $fieldvalue, $row_profile['vid']);
					}

				}
				$i++;
			}
			
			// field : profile_image_fid
			$fid=$row_profile["field_profile_image_fid"];			
			if ($fid!=NULL) {	
				process_image($fieldname, $nid, $uid, $fid);	
			}	else {
        // if NULL, remove the record in from profiles table. table name : field_data_field_pf_photo
        $sql="DELETE FROM profiles.field_data_field_pf_photo WHERE entity_id = {$uid}";          
        mysql_query($sql, $des_db);
      }

		}

		// field : faculty
		$fieldname="faculty";
		$fieldvalue="Medicine";
		update_field($fieldname, $uid, $fieldvalue);

		// field : bio
		$fieldname="bio";
		$sql_tmp = "SELECT body, vid FROM ur_node_revisions WHERE nid={$nid} ORDER BY vid DESC LIMIT 1";		
		$res = mysql_query($sql_tmp, $src_db);
		$row_tmp = mysql_fetch_row($res);
		$fieldvalue = $row_tmp[0];
		$rev_id = $row_tmp[1];
		update_field($fieldname, $uid, $fieldvalue, $rev_id);
		
		// type_delta
	  $fieldnames=array(
		              "current_interests",
		              "related_links",
		              "research_field",
		              "seo_tags",
		            );		            
		foreach ($fieldnames as $fieldname) {
		  type_delta($fieldname, $nid, $uid);  
		}
		
		// type_no_delta
		$fieldnames=array(
		              "fax",
		              "location",
		              "phone",
		              "tv_collection_url",
		              "twitter_account",
		              "my_facebook",
		              //"blog_feed_url",
		            );		            
		foreach ($fieldnames as $fieldname) {
		  type_no_delta($fieldname, $nid, $uid);  
		}
		
		// field : location_url
		$fieldname="location_url"; 
		type_no_delta_url($fieldname, $nid, $uid);
		
		// field : school
		$fieldname="school";
		type_no_delta_nid($fieldname, $nid, $uid);
				
		// field : campus
		// campus is a taxonomy in research gateway, so we need special function for this field.		
		taxonomy_to_field("campus", $nid, $uid);
				
		$process_row++;
		
	}
	
	fwrite($synclog,"===============================\n");
	fwrite($synclog,"Table name : {$des_table}\n"); 
	fwrite($synclog,"No of processed row : {$process_row}\n");
	
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
		$filepath="http://research.unsw.edu.au/sites/all/files/images/profile/{$filename}";
		
		if (rg_img_download("profile", $filepath)) {		  
  		// step 1. file_managed table
		  $result = mysql_query("SELECT fid, filesize 
  														FROM newsevents.file_managed
  														WHERE uid={$uid} AND LEFT(uri,26)='public://profile/pictures/'
  														LIMIT 1
  													",
		                        $des_db);
		  
      $uri="public://profile/pictures/{$filename}";
      if (mysql_num_rows($result) > 0) {        
         $row = mysql_fetch_row($result);
         $fid = $row[0];
         $filesize = $row[1];
         // if file size == 9999 then it is RG image, lets replace it. otherwise just skip it (must be local image).
         if ($filesize=="9999") {
           // okay. this is the image from RG, then replace it all the time.
           $sql="UPDATE newsevents.file_managed 
     							SET uid={$uid}, 
     									uri='{$uri}',
     									filename='{$filename}' 									 
     							WHERE fid = {$fid}
     							";
           mysql_query($sql, $des_db);
         }         
      } else {    
        // if file size == 9999 then it is RG image.
         $sql="INSERT INTO newsevents.file_managed 
         							(uid, filename, uri, filemime, filesize, status, timestamp) 
         							VALUES ({$uid}, '{$filename}', '{$uri}', 'image/jpeg', 9999, 1, UNIX_TIMESTAMP())
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
              WHERE u.fid < {$fid} AND u.id={$uid}
              ";
             
        $del_result = mysql_query($del_sql, $des_db);
        while($row_file = mysql_fetch_assoc($del_result)) {
          $sql="DELETE FROM newsevents.file_usage      							
                  WHERE fid = {$row_file['fid']}
                  ";          
          mysql_query($sql, $des_db);
        }
        
        $sql="UPDATE newsevents.file_usage 
     							SET id={$uid}
     							WHERE fid = {$fid}
     							";
        mysql_query($sql, $des_db);
      } else {
         $res = mysql_query("INSERT INTO newsevents.file_usage (fid, module, type, id, count) VALUES ({$fid}, 'user', 'user', {$uid}, 1)", $des_db);
      }
      
      // step 3. update users table in profiles db.
      /*
		  $result = mysql_query("SELECT uid 
  														FROM profiles.users
  														WHERE uid={$uid}
  													",
		                        $des_db);
      if (mysql_num_rows($result) > 0) {
        mysql_query("UPDATE profiles.users SET picture={$fid} WHERE uid = {$uid}", $des_db);
      }
      */
		  $result = mysql_query("SELECT entity_id  
  														FROM profiles.field_data_field_pf_photo
  														WHERE entity_id={$uid} AND entity_type='user'
  													", 
		                        $des_db);

      if (mysql_num_rows($result) > 0) {        
        mysql_query("UPDATE profiles.field_data_field_pf_photo 
        							SET field_pf_photo_fid={$fid} WHERE entity_id = {$uid}
        						", $des_db);
      } else {        
        $sql="INSERT INTO profiles.field_data_field_pf_photo 
         							(entity_type, bundle, deleted, entity_id, revision_id, language, delta, field_pf_photo_fid, field_pf_photo_alt, field_pf_photo_title) 
         							VALUES ('user', 'user', 0, {$uid}, {$uid}, 'und', 0, {$fid}, NULL, NULL)
         						";         
         mysql_query($sql, $des_db); 
      }
      
		}
	}
}

function taxonomy_to_field($fieldname, $nid, $uid) {
	//FORMAT : vid, nid, delta, nid_reference

	global $src_db;

	$sql_tmp = "SELECT t.name AS title
				FROM ur_term_node n 
				JOIN ur_term_data t ON n.tid=t.tid
				WHERE t.vid = (SELECT v.vid FROM ur_vocabulary v WHERE v.name='Campus') AND n.nid={$nid}";
	
	$res = mysql_query($sql_tmp, $src_db);
	while($row_tmp = mysql_fetch_assoc($res)) {	
		$fieldvalue = $row_tmp['title'];				
		update_field($fieldname, $uid, $fieldvalue);
	}
}

function type_delta($fieldname, $nid, $uid) {
	//FORMAT : vid, nid, delta, nid_reference

	global $src_db;

	$sql_tmp = "SELECT n.title, c.delta, n.vid 
				FROM ur_content_field_{$fieldname} c
				JOIN ur_node n ON c.field_{$fieldname}_nid=n.nid
				WHERE c.nid={$nid} AND c.field_{$fieldname}_nid IS NOT NULL";		

	$res = mysql_query($sql_tmp, $src_db);
	while($row_tmp = mysql_fetch_assoc($res)) {
		$fieldvalue = $row_tmp['title'];
		$rev_id = $row_tmp['vid'];
		$delta = $row_tmp['delta'];
		update_field($fieldname, $uid, $fieldvalue, $rev_id, $delta);
	}
}

function type_no_delta($fieldname, $nid, $uid) {
	//FORMAT : vid, nid, value

	global $src_db;
	
	$sql_tmp = "SELECT field_{$fieldname}_value, vid 
				FROM ur_content_field_{$fieldname} 
				WHERE nid={$nid}
				ORDER BY vid DESC LIMIT 1";		
	//print $sql_tmp;
	$res = mysql_query($sql_tmp, $src_db);
	while($row_tmp = mysql_fetch_assoc($res)) {
		$fieldvalue = $row_tmp["field_{$fieldname}_value"];
		$rev_id = $row_tmp['vid'];
		update_field($fieldname, $uid, $fieldvalue, $rev_id);
	}
}

function type_no_delta_url($fieldname, $nid, $uid) {
	//FORMAT : vid, nid, value

	global $src_db;
	
	$sql_tmp = "SELECT field_{$fieldname}_url, vid 
				FROM ur_content_field_{$fieldname} 
				WHERE nid={$nid}
				ORDER BY vid DESC LIMIT 1";		
	//print $sql_tmp . "\n";
	$res = mysql_query($sql_tmp, $src_db);
	while($row_tmp = mysql_fetch_assoc($res)) {
		$fieldvalue = $row_tmp["field_{$fieldname}_url"];
		$rev_id = $row_tmp['vid'];
		//print '$fieldvalue:' . $fieldvalue . "\n";
		update_field($fieldname, $uid, $fieldvalue, $rev_id);
	}
}

function type_no_delta_nid($fieldname, $nid, $uid) {
	//FORMAT : vid, nid, nid_reference

	global $src_db;

	$sql_tmp = "SELECT n.title, n.vid 
				FROM ur_content_field_{$fieldname} c
				JOIN ur_node n ON c.field_{$fieldname}_nid=n.nid
				WHERE c.nid={$nid}
				ORDER BY c.vid DESC LIMIT 1";	

	$res = mysql_query($sql_tmp, $src_db);
	//print $sql_tmp;
	while($row_tmp = mysql_fetch_assoc($res)) {
		$fieldvalue = $row_tmp['title'];
		$rev_id = $row_tmp['vid'];
		update_field($fieldname, $uid, $fieldvalue, $rev_id);
	}
}

function update_field($fieldname, $uid, $fieldvalue, $rev_id='NULL', $delta=0) {
	global $des_db;
	
	$fieldname="pf_".$fieldname;
	
	if ($delta==0) {
		$sql_field = "SELECT entity_id FROM field_data_field_{$fieldname} WHERE entity_id={$uid}";			
	} else {
		$sql_field = "SELECT entity_id FROM field_data_field_{$fieldname} WHERE entity_id={$uid} AND delta={$delta}";			
	}
	
	$search_field = mysql_query($sql_field, $des_db);
	if (empty($search_field)) print $sql_field." : {$fieldvalue}\n";

	$fieldvalue=mysql_real_escape_string($fieldvalue); // escapes special characters
	
	if (mysql_num_rows($search_field) == 0) { // insert		
		if (!($fieldvalue == "" || $fieldvalue == 'NULL')) {
			$insert = " INSERT INTO field_data_field_{$fieldname} 
						(entity_type, bundle, deleted, entity_id, revision_id, language, delta, field_{$fieldname}_value) 
						VALUES('user', 'user', 0, {$uid}, {$rev_id}, 'und', {$delta}, '{$fieldvalue}') ";  // default language in Drupal 7. Leave it empty will make url_alias not working
			$search = mysql_query($insert, $des_db);
		}
	} else { //update
		if (!($fieldvalue == "" || $fieldvalue == 'NULL')) {
			$update = "UPDATE field_data_field_{$fieldname} 
						SET field_{$fieldname}_value='{$fieldvalue}', 
						revision_id='{$rev_id}', 
						language='und',
						delta={$delta} 
						WHERE entity_id={$uid} ";
			$search = mysql_query($update, $des_db);
		} else { // delete if value is null
			$delete = "DELETE FROM field_data_field_{$fieldname} WHERE entity_id={$uid} ";
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
		// $where .= "{$value}='{$row[$value]}' | ";
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
	global $des_db, $src_db;
	global $synclog;

	$result = mysql_query("UPDATE users 
							SET timezone='Australia/Sydney' 
							WHERE timezone IS NULL", $des_db);

	$result = mysql_query("UPDATE field_data_field_pf_bio
							SET field_pf_bio_format='filtered_html'", $des_db);
 
	/// Note: !! for some reason, this script updated many staff to BLOCKED user. So I will just disable this function.
	/// processing Terminated users - start
	// 1. update user status from RG if the user is created less then three days.	
	// otherwise we are not going to replace 'status' field from RG.
	$result = mysql_query("UPDATE users 
							SET status=1
							WHERE SUBSTR(name, -7) REGEXP ('[0-9]')
							AND TIME_TO_SEC(TIMEDIFF(NOW() , FROM_UNIXTIME(created))) <= 60*60*24*3
							", $des_db);
	
	// 2. change status to BLOCKED if the user is terminiated in RG(If not found in RG, it means the user was termniated).
	$result = mysql_query("SELECT uid, SUBSTR(name, -7) AS name FROM users WHERE SUBSTR(name, -7) REGEXP ('[0-9]')", $des_db);
  while($row = mysql_fetch_assoc($result)) {    
    $zID=$row['name'];		
    $uid=$row['uid'];
    //print $zID . "\n";
    
		$rg_res = mysql_query("SELECT empl_status	FROM ur_rg_personnel WHERE employer_id={$zID}", $src_db);
		if (mysql_num_rows($rg_res) == 0) {
		  $result2 = mysql_query("UPDATE users SET status=0 where uid={$uid}", $des_db);
		}		
	}
	/// processing Terminated users - end
	
	fwrite($synclog,"===============================\n");
	fwrite($synclog,"Bulk Update completed\n");

}

function copy_profile_fields_to_dbs() {
	global $des_db;
	global $synclog;
	global $DB_TO_COPY;
	
	// get variable value from profiles database. This value is set in Configuration of 'Content Control' module.
  $query = mysql_query("SELECT value FROM variable	WHERE name='profiles_copy_to_db_name' LIMIT 1", $des_db);
	$value = mysql_fetch_row($query);	
  $target_dbs=explode("\n", unserialize($value[0]));  
  $db_list=array();  
  foreach ($target_dbs as $db_name) {  
    $db=explode("(", $db_name);        
    $db_list[]=trim($db[0]);
  }
  $DB_TO_COPY=$db_list;
   
  $newid = 1000;
  
	$sql = "SELECT id, field_name 
				FROM field_config
				WHERE field_name LIKE 'field_pf_%' 
				ORDER BY id ASC 
				";

	$res = mysql_query($sql, $des_db);

	while($row = mysql_fetch_assoc($res)) {	  
		$id = $row['id'];
		$field_name = $row['field_name'];
		
		$sql_update = "UPDATE field_config SET id={$newid} WHERE id={$id} AND id != {$newid}";

		$result = mysql_query($sql_update, $des_db);
		if (!$result) {
			print "Error while update table : {$sql_update}\n";
		} else {
			$sql_update_instance = "UPDATE field_config_instance SET id={$newid}, field_id={$newid} WHERE field_name='{$field_name}' AND bundle = 'user'";
			$result_instance = mysql_query($sql_update_instance, $des_db);
			if (!$result_instance) print "Error while update table : {$sql_update_instance}\n"; 
		}
		$newid++;
	}

	// move records - field_config_instance.
	$sql_update = "UPDATE field_config_instance SET id=(2000+id), field_id=(2000+field_id) WHERE bundle != 'user' AND id < 2000";
	$result = mysql_query($sql_update, $des_db);
	if (!$result) print "<p>Error while update table : {$sql_update}</p>";
	
	// copying records to other database	
	global $des_table;	
	global $primarykey;		
		
	foreach($DB_TO_COPY as $db_name) {
	  //print '$db_name:' . $db_name . "\n";
		$primarykey= "id";
		
		$des_table = "field_config";
		$sql = "SELECT * FROM {$des_table} WHERE (id >= 1000 AND id < 2000) AND field_name LIKE 'field_pf_%'";
		$result = mysql_query($sql, $des_db);		
		execute($result, $db_name);		
		
		// Delete profiles field if id is greater than 2000. 
		$sql = "DELETE FROM {$des_table} WHERE id >= 2000 AND field_name LIKE 'field_pf_%'";
		$result = mysql_query($sql, $des_db);	
		
		$des_table = "field_config_instance";		
		$sql = "SELECT
							id,
							field_id,
							field_name, 
  						entity_type, 
  						bundle,
  						IFNULL(
  							(
  								SELECT des_tbl.data 
  								FROM {$db_name}.{$des_table} des_tbl 
  								WHERE des_tbl.field_name = src_tbl.field_name LIMIT 1
  							),data
  						) as data,
  						deleted 
						FROM {$des_table} src_tbl
						WHERE entity_type='user' AND bundle='user' AND field_name LIKE 'field_pf_%'";
		//print $sql . "\n";
		$result = mysql_query($sql, $des_db);		
		execute($result, $db_name);		

		// Delete profiles field if id is greater than 2000. 
		$sql = "DELETE FROM {$des_table} WHERE id >= 2000 AND field_name LIKE 'field_pf_%'";
		$result = mysql_query($sql, $des_db);
		
		// change primary key if it's less than 2000.
		$sql_update = "UPDATE {$db_name}.field_config SET id=(2000+id) WHERE id >=1000 AND id < 2000 AND (field_name NOT LIKE 'field_pf_%')";
		$result = mysql_query($sql_update, $des_db);
		if (!$result) print "<p>Error while update table : {$sql_update}</p>";
		
		$sql_update = "UPDATE {$db_name}.field_config_instance SET id=(2000+id), field_id=(2000+field_id) WHERE bundle != 'user' AND id >=1000 AND id < 2000";
		$result = mysql_query($sql_update, $des_db);
		if (!$result) print "<p>Error while update table : {$sql_update}</p>";  
		
		/*
		// set increment number
		$query = mysql_query("SELECT MAX(id) AS id FROM {$db_name}.field_config ", $des_db); 
		$row = mysql_fetch_array($query);
		$next_id = $row['id'] + 1;
		$query = mysql_query("ALTER TABLE field_config AUTO_INCREMENT = {$next_id} ", $des_db);
		if (!$query) print "<p>Error while update table : {$query}</p>";
				
		// set increment number
		$query = mysql_query("SELECT MAX(id) AS id FROM {$db_name}.field_config_instance ", $des_db);
		$row = mysql_fetch_array($query);
		$next_id = $row['id'] + 1;
		$query = mysql_query("ALTER TABLE field_config_instance AUTO_INCREMENT = {$next_id} ", $des_db);
		if (!$query) print "<p>Error while update table : {$query}</p>";
		*/ 
	}
		
	fwrite($synclog,"===============================\n");
	fwrite($synclog,"copying profile fields to other database completed.\n");
}

function push_school_taxonomy() {
  global $src_db, $des_db;
  global $primarykey; 
  global $src_table, $des_table; 
  
   
  
	$DB_TO_COPY=array(
							"newseventsdev",
	            "newsevents", 
            );  
            
  // get vocabulary id of School - source db 
	$query = mysql_query("SELECT vid FROM taxonomy_vocabulary WHERE machine_name='school' ", $des_db);
	
	if (mysql_num_rows($query) == 0) return;   
	  
	$row = mysql_fetch_array($query);	
	$source_vid=$row['vid'];

	
	foreach($DB_TO_COPY as $db_name) {  
	  
	  $src_table="taxonomy_term_data";	// source table
    $des_table=$src_table;		// destination table
    $primarykey="vid:name";	  	// primary key for both tables 
    
  	// get vocabulary id of School - target db
    $query = mysql_query("SELECT vid FROM {$db_name}.taxonomy_vocabulary WHERE machine_name='school' ", $des_db);
    $row = mysql_fetch_array($query);

    if ($row) {
      $target_vid=$row['vid'];
    } else { // if vocabulary School is not found.
      $query = mysql_query("
      INSERT INTO {$db_name}.taxonomy_vocabulary 
      (name, machine_name, description, hierarchy, module, weight) 
      VALUES ('School', 'school', 'School', 0, 'taxonomy', 0)
      ", $des_db);
      $query = mysql_query("SELECT vid FROM {$db_name}.taxonomy_vocabulary WHERE machine_name='school' ", $des_db);
      $row = mysql_fetch_array($query);
      $target_vid=$row['vid']; 
    } 	

    if ($target_vid=="" || $target_vid==NULL) {    
      print ("target vid is NULL.");
      continue; 
    }
    
  	// vid should be the one of target database. 
    $src_result = mysql_query("  
    	SELECT {$target_vid} as vid, name, description, format, weight 
    	FROM {$src_table}
    	WHERE vid={$source_vid} 
    	", $des_db);
    
  	execute($src_result, $db_name);  
  	

  	// copying taxonomy_term_hierarchy table.
  	$src_table="taxonomy_term_data";	// source table
    $des_table="taxonomy_term_hierarchy";		// destination table
    $primarykey="tid";	  	// primary key for both tables
 
    $src_result = mysql_query("  
    	SELECT tid, 0 as parent  
    	FROM {$db_name}.{$src_table}
    	WHERE vid={$target_vid} 
    	", $des_db);
    
  	execute($src_result, $db_name); 
	} 
		   
}

function search_api_delete_blocked_unpublished() {
  // get variable value from profiles database. This value is set in Configuration of 'Content Control' module.
  $query = mysql_query("SELECT value FROM profiles.variable WHERE name='profiles_copy_to_db_name' LIMIT 1");
	$value = mysql_fetch_row($query);	
  $target_dbs=explode("\n", unserialize($value[0]));  
  foreach ($target_dbs as $db_name) {      
    $db=explode("(", $db_name);    
    $dbname=$db[0];
    
    mysql_select_db($db[0]); 
    
    $sql = "show tables like 'search_api_db_%'";

    $res = mysql_query($sql);

    while($row = mysql_fetch_row($res)) {	  
      $tname = $row[0];
      
      // 1. process users
      $sql_del = "SELECT s.item_id 
                  FROM {$tname} s 
                  JOIN users u ON u.uid=s.item_id
                  WHERE u.status=0 OR s.item_id < 100
                  ";
                  
      $res_del = mysql_query($sql_del);      
      while($row_del = mysql_fetch_row($res_del)) {	  
        $del=mysql_query("DELETE FROM {$tname} WHERE item_id={$row_del[0]}");        
      }
      
      // 2. process nodes
      $sql_del = "SELECT s.item_id 
                  FROM {$tname} s 
                  JOIN node n ON n.nid=s.item_id
                  WHERE n.status=0
                  ";
                  
      $res_del = mysql_query($sql_del);      
      while($row_del = mysql_fetch_row($res_del)) {	  
        $del=mysql_query("DELETE FROM {$tname} WHERE item_id={$row_del[0]}");        
      }      
      
    }

  }
      
}

function copy_users_record_to_shared_dbs() { 
  
	global $des_db;
	global $des_table;
	global $primarykey;

  // get variable value from profiles database. This value is set in Configuration of 'Content Control' module.
  $query = mysql_query("SELECT value FROM variable	WHERE name='profiles_copy_to_db_name' LIMIT 1", $des_db);
	$value = mysql_fetch_row($query);	
  $target_dbs=explode("\n", unserialize($value[0]));  
  $db_list=array();  
  $school_list=array();  
  foreach ($target_dbs as $db_name) {  
    $tmp=explode("(" , $db_name);
    $db_list[]=trim($tmp[0]);
    
    $tmp2=explode("(" , $db_name);    
    if (count($tmp2)==2) {
      $tmp3=explode(')' , $tmp[1]);      
      $school_list[$tmp[0]]=explode(";" , $tmp3[0]);
    }
  }
  
  foreach($school_list as $db_name=>$schools) {        
    foreach ($schools as $school) {
      // 1. users table.
      // note : exclude local users
    	$primarykey="uid";		// primary key for both tables 
      $src_table="users";	// source table
      $des_table="users";		// destination table
      
      if ($school=="*") {
        // process Research Gateway data - ALL
        $query="
          SELECT u.uid, u.name, u.mail, u.signature_format, u.created
          FROM {$src_table} u                      
          WHERE            
            u.uid BETWEEN 1000 AND 1000000
          ";
      } else {
        // process Research Gateway data - Only selected Schools
        $query="
          SELECT u.uid, u.name, u.mail, u.signature_format, u.created
          FROM {$src_table} u          
            JOIN field_data_field_pf_school s ON u.uid=s.entity_id
            LEFT JOIN field_data_field_target_sites t ON u.uid=t.entity_id
          WHERE
            (s.field_pf_school_value=\"" . $school . "\" OR t.field_target_sites_value=\"" . $db_name . "\") 
            AND (u.uid BETWEEN 1000 AND 1000000)
          ";
      }
      
      $src_result = mysql_query($query, $des_db);
 
  		execute($src_result, $db_name); 
    }
  }
}

?>
