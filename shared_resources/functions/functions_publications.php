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
	while($row = mysql_fetch_assoc($src_result)) {

		$key_array = explode(":", $primarykey);

		$where="";
		foreach ($key_array as $key=>$value) {
			//print "<br/>Keyvalue : $key => $value";
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
			//print "<p>insert : ".$insert."</p>";
			
			$des_result = mysql_query($insert, $des_db)
				or die("Error : " . mysql_error());
			$insert_row ++;
		} elseif (mysql_num_rows($search_des_table) == 1) { 	// update							

		  if ($dup_update!=1) { // Do not update existing record
  			//print "<p>updated : {$row[$primarykey]}</p>";	
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

function execute_publication_fields($src_result) {

	global $des_table, $src_db, $des_db;
	global $synclog;
	$process_row=0;
	$cnt=0;
	
	while($row = mysql_fetch_assoc($src_result)) {
	
		//if ($cnt++ > 100) break;
		
		$uid=$row['uid'];
		$nid=$row['nid'];
		
		$sql = "SELECT p.*, c.CAT_NAME, c.CATEGORY_GROUP
						FROM ur_rg_publication p
						JOIN ur_rg_publication_category c on p.cat_id=c.cat_id						
						WHERE PUB_ID = {$nid}";		
		$search_profile = mysql_query($sql, $src_db);
		
		/*
		== Fields to process ==
		  
		all fields except 'title'
		*/

		while($row_profile = mysql_fetch_assoc($search_profile)) {
			// get column metadata //
			$i = 0;
			while ($i < mysql_num_fields($search_profile)) {
				$meta = mysql_fetch_field($search_profile, $i);
				$column_name = strtolower($meta->name);
				if (!($column_name == "title" || $column_name == "cat_id")) { // skip these columns
					
					$fieldname="st_{$column_name}";
					$fieldvalue=$row_profile[$meta->name];
					update_field($fieldname, $nid, $fieldvalue, $row_profile['PUB_ID']);
			 					
				}
				$i++; 
			}
		}

		// field : ur_publication_author
		$fieldname="pub_author";
		type_delta_author($fieldname, $nid, $uid);
		
		// field : ur_publication_author
		$fieldname="pub_owner";
		type_delta_owner($fieldname, $nid, $uid);

		// reference
		$fieldname="st_reference";
		$search_ref = mysql_query($sql, $src_db);
		$result = mysql_fetch_object($search_ref);		 
		if ($result)  {
		  update_field($fieldname, $nid, _publication_formatter($result));
		} 	
		
		$process_row++;
		
	}
	
	fwrite($synclog,"===============================\n");
	fwrite($synclog,"Table name : {$des_table}\n"); 
	fwrite($synclog,"No of inserted row : {$process_row}\n");
	
} 

function type_delta_author($fieldname, $nid, $uid) {
	//FORMAT : pubid, author_order, author_ciation, employer_id

	global $src_db; 

	$sql_tmp = "SELECT pub_id, author_order AS delta, employer_id, author_citation_name
				FROM ur_rg_publication_author
				WHERE pub_id={$nid}
				ORDER BY pub_id, author_order
				";		

	$res = mysql_query($sql_tmp, $src_db);
	
	while($row_tmp = mysql_fetch_assoc($res)) {
		$fieldvalue = $row_tmp['employer_id'];
		$rev_id = $row_tmp['pub_id'];
		$delta = $row_tmp['delta']-1;		
		update_field("st_".$fieldname, $nid, $fieldvalue, $rev_id, $delta); // for author_id
		$fieldvalue = $row_tmp['author_citation_name']; 
		update_field("st_pub_author_citation", $nid, $fieldvalue, $rev_id, $delta); // for author_citation
	}	
	
}

function type_delta_owner($fieldname, $nid, $uid) {
	//FORMAT : pub_id, employer_id

	global $src_db;

	$sql_tmp = "SELECT pub_id, author_order AS delta, employer_id 
				FROM ur_rg_publication_author
				WHERE pub_id={$nid}";		

	$res = mysql_query($sql_tmp, $src_db);
	
	$fieldname = "st_{$fieldname}";
	
	$no=0;
	while($row_tmp = mysql_fetch_assoc($res)) {
		$fieldvalue = $row_tmp['employer_id'];
		$rev_id = $row_tmp['pub_id'];
		$delta = $no++;		
		update_field($fieldname, $nid, $fieldvalue, $rev_id, $delta);
	}	
}

function update_field($fieldname, $nid, $fieldvalue, $rev_id='NULL', $delta=0) {
	global $des_db;
	
	if ($delta==0) {
		$sql_field = "SELECT entity_id FROM field_data_field_{$fieldname} WHERE entity_id={$nid}";			
	} else {
		$sql_field = "SELECT entity_id FROM field_data_field_{$fieldname} WHERE entity_id={$nid} AND delta={$delta}";			
	}
	
	$search_field = mysql_query($sql_field, $des_db);
	//print $sql_field . "\n"; 
	         
	$fieldvalue=mysql_real_escape_string($fieldvalue); // escapes special characters
	
	if (mysql_num_rows($search_field) == 0) { // insert		
		if (!($fieldvalue == "" || $fieldvalue == 'NULL')) {
			$insert = " INSERT INTO field_data_field_{$fieldname} 
						(entity_type, bundle, deleted, entity_id, revision_id, language, delta, field_{$fieldname}_value) 
						VALUES('node', 'publication', 0, {$nid}, {$rev_id}, 'und', {$delta}, '{$fieldvalue}') ";		
//echo "<p>$insert</p>";						
			$search = mysql_query($insert, $des_db);
		}
	} else { //update
		if (!($fieldvalue == "" || $fieldvalue == 'NULL')) {
			$update = " UPDATE field_data_field_{$fieldname} 
						SET field_{$fieldname}_value='{$fieldvalue}',
						revision_id='{$rev_id}', 
						delta={$delta} 
						WHERE entity_id={$nid} ";
			$search = mysql_query($update, $des_db);
		} else { // delete if value is null
			//$delete = " DELETE FROM field_data_field_{$fieldname} WHERE entity_id={$nid} ";
			//$search = mysql_query($delete, $des_db);								
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
 
	$result = mysql_query("UPDATE field_data_field_st_reference
							SET field_st_reference_format='filtered_html'", $des_db);
							
	fwrite($synclog,"===============================\n");
	fwrite($synclog,"Bulk Update completed\n");

}
////////////////////////////////////////////////////////////////////////////////
// The following functions to create ciation came from Research Gateway.
////////////////////////////////////////////////////////////////////////////////

function _publication_formatter($record){
  $output = array(); 
  switch($record->CATEGORY_GROUP){
    case "Books":
      $output[] = _publication_author($record->PUB_ID);
      $output[] = $record->ED_EDS;
      $output[] = $record->YEAR;
      $output[] = '<i>'.$record->TITLE.'</i>';
      $output[] = $record->EDITION;
      $output[] = $record->PUBLISHER;
      $output[] = $record->PLACE_PUBLISHED;
      $output[] = _publication_convert_url($record->URL_DOI);
      break;
    case "Chapters":
      $output[] = _publication_author($record->PUB_ID);
      $output[] = $record->YEAR;
      $output[] = "'".$record->TITLE."'";
      $output[] = $record->EDITORS!="" ? " in ".$record->EDITORS." (ed.)" : "";
      $output[] = ($record->EDITORS=="" ? " in " : "")."<i>".$record->SOURCE_TITLE."</i>";
      $output[] = ($record->EDITION ? "edn. ".$record->EDITION : "");
      $output[] = $record->PUBLISHER;
      $output[] = $record->PLACE_PUBLISHED;
      $output[] = ($record->START_PAGE ? " pp. ".$record->START_PAGE : "") .($record->END_PAGE ? " - ".$record->END_PAGE : "");
      $output[] = _publication_convert_url($record->URL_DOI);
      break;  
    case "Journal Articles":
      $output[] = _publication_author($record->PUB_ID);
      $output[] = $record->YEAR;
      $output[] = "'".$record->TITLE."'";
      $output[] = "<i>".$record->SOURCE_TITLE."</i>";
      $output[] = $record->VOLUME ? "vol. ".$record->VOLUME : "";
      $output[] = $record->NUMBER_ ? "no. ".$record->NUMBER_ : "";
      $output[] = ($record->START_PAGE ? " pp. ".$record->START_PAGE : "") .($record->END_PAGE ? " - ".$record->END_PAGE : "");
      $output[] = _publication_convert_url($record->URL_DOI);
      break;
    case "Conference Papers":  
      $output[] = _publication_author($record->PUB_ID);
      $output[] = $record->ED_EDS;
      $output[] = $record->YEAR;
      $output[] = "'".$record->TITLE."'";
      $output[] = " in <i>".$record->SOURCE_TITLE."</i>";
      $output[] = $record->PUBLISHER;
      $output[] = $record->PLACE_PUBLISHED;
      $output[] = ($record->START_PAGE ? " pp. ".$record->START_PAGE : "") .($record->END_PAGE ? " - ".$record->END_PAGE : "");
      $output[] = " presented at ".$record->CONF_TITLE;
      $output[] = $record->CONF_PLACE;
      $output[] = $record->CONF_DATE;
      $output[] = _publication_convert_url($record->URL_DOI);
      break;
    case "Patents":
      $output[] = _publication_author($record->PUB_ID);
      $output[] = $record->YEAR;
      $output[] = "<i>".$record->TITLE."</i>";
      $output[] = $record->COUNTRY;
      $output[] = "Patent No. ".$record->PATENT_NUMBER;
      $output[] = "Patent Agent:".$record->PATENT_AGENT;
      $output[] = _publication_convert_url($record->URL_DOI);
      break;
    case "Original Creative Works"  :
      switch($record->CAT_ID) {
        case 51:  // (Creative Work - Visual Art)  
          $output[] = _publication_author($record->PUB_ID);
          $output[] = $record->YEAR;
          $output[] = $record->CONF_TITLE;
          $output[] = "Exhibited: ".$record->TITLE." at ".$record->CONF_VENUE_NAME;
          $output[] = $record->CONF_PLACE;
          $output[] = $record->CONF_DATE;
          $output[] = $record->PUBLISHER ? "Publication: ". $record->PUBLISHER : "";
          $output[] = $record->PLACE_PUBLISHED ? $record->PLACE_PUBLISHED : "";
          $output[] = $record->EDITORS ? "Curator/Editor: ".$record->EDITORS : "";
          $output[] = "publication category: ".$record->CAT_NAME;
          $output[] = _publication_convert_url($record->URL_DOI);
          break;
        case 52:  // (Creative Work - Design/Architectural)  
          $output[] = _publication_author($record->PUB_ID);
          $output[] = $record->YEAR;
          $output[] = "<i>".$record->TITLE."</i>";
          $output[] = $record->PUBLISHER ? "Publication: ". $record->PUBLISHER : "";
          $output[] = $record->PLACE_PUBLISHED ? $record->PLACE_PUBLISHED : "";
          $output[] = $record->EDITORS ? "Editor: ".$record->EDITORS : "";
          $output[] = "Publication category: ".$record->CAT_NAME;
          $output[] = _publication_convert_url($record->URL_DOI);
          break;
        case 53:  // (Creative Work - Textual)  
          $output[] = _publication_author($record->PUB_ID);
          $output[] = $record->YEAR;
          $output[] = "<i>".$record->TITLE."</i>";
          $output[] = $record->PUBLISHER ? "Publication: ". $record->PUBLISHER : "";
          $output[] = $record->PLACE_PUBLISHED ? $record->PLACE_PUBLISHED : "";
          $output[] = $record->EDITORS ? "Editor: ".$record->EDITORS : "";
          $output[] = "publication category: ".$record->CAT_NAME;
          $output[] = _publication_convert_url($record->URL_DOI);
          break;
        case 54:  // (Creative Work - Other)  
          $output[] = _publication_author($record->PUB_ID);
          $output[] = $record->YEAR;
          $output[] = "<i>".$record->TITLE."</i>";
          $output[] = "Exhibited: ".$record->TITLE;
          $output[] = $record->PUBLISHER ? "Publication: ". $record->PUBLISHER : "";
          $output[] = $record->PLACE_PUBLISHED ? $record->PLACE_PUBLISHED : "";
          $output[] = $record->EDITORS ? "Editor(s): ".$record->EDITORS : "";
          $output[] = _publication_convert_url($record->URL_DOI);
          break;
        case 55:  // (Creative - Music Score)  
          $output[] = _publication_author($record->PUB_ID);
          $output[] = $record->YEAR;
          $output[] = $record->TITLE;
          $output[] = $record->PUBLISHER ? "Publication: ". $record->PUBLISHER : "";
          $output[] = $record->PLACE_PUBLISHED ? $record->PLACE_PUBLISHED : "";
          $output[] = $record->MUSIC_PUB_DATE ? $record->MUSIC_PUB_DATE : "";
          $output[] = $record->MUSIC_N_PAGES? " pages N: ".$record->MUSIC_N_PAGES : "";
          $output[] = "publication category: ".$record->CAT_NAME;
          $output[] = _publication_convert_url($record->URL_DOI);
          break;
      }
      break;
    case "Curated Exhibition":
      $output[] = _publication_author($record->PUB_ID);
      $output[] = $record->YEAR;
      $output[] = $record->CONF_TITLE;
      $output[] = "Exhibited at: ".$record->CONF_VENUE_NAME;
      $output[] = $record->CONF_PLACE;
      $output[] = $record->CONF_DATE;
      $output[] = $record->TITLE? "Publication: ". $record->TITLE: "";
      $output[] = $record->PUBLISHER;
      $output[] = $record->PLACE_PUBLISHED;
      $output[] = $record->EDITORS ? "Editor: ".$record->EDITORS : "";
      $output[] = _publication_convert_url($record->URL_DOI);
      break;
    case "Recorded or Rendered Work":
      $output[] = _publication_author($record->PUB_ID);
      $output[] = $record->YEAR;
      $output[] = $record->TITLE;
      $output[] = $record->PUBLISHER;
      $output[] = $record->PLACE_PUBLISHED;
      $output[] = $record->EDITORS ? "Editor(s): ".$record->EDITORS : "";
      $output[] = $record->MEDIUM;
      $output[] = $record->M_GROUP_DESC;
      $output[] = $record->DATE_PUBLISH ? "Published: ". $record->DATE_PUBLISH : "";
      $output[] = $record->DURATION ? "Duration: ".$record->DURATION : "";
      $output[] = $record->CAT_NAME;  
      $output[] = _publication_convert_url($record->URL_DOI);
      break;
    case "Live Performances":
      $output[] = _publication_author($record->PUB_ID);
      $output[] = $record->YEAR;
      $output[] = $record->CONF_TITLE;
      $output[] = $record->CONF_PLACE;
      $output[] = $record->CONF_VENUE_NAME;
      $output[] = $record->TITLE ? "Publication: ". $record->TITLE : "";
      $output[] = $record->PUBLISHER;
      $output[] = $record->DATE_PUBLISH;
      $output[] = $record->EDITORS ? "Editor(s): ".$record->EDITORS : "";
      $output[] = $record->CAT_NAME;
      $output[] = _publication_convert_url($record->URL_DOI);
      break;
    case "Reports":
      $output[] = _publication_author($record->PUB_ID);
      $output[] = $record->YEAR; 
      $output[] = $record->TITLE;
      $output[] = $record->PUBLISHER;
      $output[] = $record->PLACE_PUBLISHED;
      $output[] = $record->SERIES_TITLE;
      $output[] = $record->SERIES_NUMBER;
      $output[] = $record->CAT_NAME;
      $output[] = _publication_convert_url($record->URL_DOI);
      break;
    case "Thesis":
      $output[] = _publication_author($record->PUB_ID);
      $output[] = $record->YEAR;
      $output[] = $record->TITLE;
      $output[] = $record->THESIS_TYPE;
      $output[] = "thesis";
      $output[] = $record->PUBLISHER;
      $output[] = $record->PLACE_PUBLISHED;
      $output[] = _publication_convert_url($record->URL_DOI);
      break;
    default:
      $output[] = _publication_author($record->PUB_ID).", ".$record->TITLE;
  }

  $formatted = array();
  while(count($output) > 0){
    if($element = array_shift($output)){
      $formatted[] = $element;
    }
  }
  
  return implode(', ', $formatted);
}

function _publication_author($pubid){
  global $src_db;

  $authors = array();
  $sql = 'SELECT * FROM ur_rg_publication_author WHERE PUB_ID = ' . $pubid . ' ORDER BY AUTHOR_ORDER';
  $result = mysql_query($sql, $src_db);
  while($author = mysql_fetch_object($result)){
    $authors[] = $author->AUTHOR_CITATION_NAME;
  }
  if(count($authors) > 1){
    $last = array_pop($authors);  
    return implode(', ',$authors)." & ".$last;
  } else {
    return $authors[0];
  }
}

function _publication_convert_url($text){
  if(strpos($text, "http://") !== false){
    $url_link = array();
    $urls = explode(' ', $text);
    foreach($urls as $url){
      //$url_link[] = l($url, $url);
      $url_link[] = '<a href=\'' . $url . '\'>' . $url . '</a>';
    }
    return implode(' ', $url_link);
  } else {
    return $text;
  }
}


function copy_publications_node_to_shared_dbs() { 
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
    
    $node_type="publication";
    
    $query = mysql_query("SELECT value FROM {$db_name}.variable	WHERE name='school_publication' LIMIT 1");
    $value = mysql_fetch_row($query);	    
    $school_list=unserialize($value[0]); 
    
    if (empty($school_list)) continue;
    
    foreach ($school_list as $school_name) {
      
      // 1. node table. 
      // note : exclude local contents 
      $primarykey="nid";		// primary key for both tables 
      $src_table="node";	// source table
      $des_table="node";		// destination table      
      $src_result = mysql_query(" 
      	SELECT DISTINCT n.nid, n.vid, n.type, n.language, n.title, n.uid, IFNULL((SELECT status from {$db_name}.node AS target where target.nid=n.nid), 1) AS status, n.created, n.changed
        FROM {$src_table} n
          JOIN field_data_field_st_pub_owner o ON n.nid=o.entity_id
          JOIN field_data_field_pf_emplid e ON e.field_pf_emplid_value = o.field_st_pub_owner_value
          JOIN field_data_field_pf_school s ON e.entity_id=s.entity_id
        WHERE n.type='" . $node_type . "' 
          AND s.field_pf_school_value=\"" . $school_name . "\"
          AND n.nid BETWEEN 199000000 AND 299000000
      	", $des_db);
   				
  		execute($src_result, $db_name);
  
      // 2. node_revision table.
      $primarykey="nid:vid";		// primary key for both tables 
      $src_table="node_revision";	// source table
      $des_table="node_revision";		// destination table        
      $src_result = mysql_query(" 
      	SELECT DISTINCT nr.nid, nr.vid, nr.uid, IFNULL((SELECT status from {$db_name}.node AS target where target.nid=nr.nid), 1) AS status, nr.title
        FROM {$src_table} nr
        	JOIN node n ON n.nid=nr.nid 
          JOIN field_data_field_st_pub_owner o ON nr.nid=o.entity_id
          JOIN field_data_field_pf_emplid e ON e.field_pf_emplid_value = o.field_st_pub_owner_value
          JOIN field_data_field_pf_school s ON e.entity_id=s.entity_id
        WHERE n.type='" . $node_type . "' 
          AND s.field_pf_school_value=\"" . $school_name . "\"
          AND nr.nid BETWEEN 199000000 AND 299000000
      	", $des_db);    
   				
  		execute($src_result, $db_name); 
  		
      // 3. node_comment_statistics table.
      $primarykey="nid";        // primary key for both tables 
      $src_table="node";        // source table
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
            JOIN field_data_field_st_pub_owner o ON n.nid=o.entity_id
            JOIN field_data_field_pf_emplid e ON e.field_pf_emplid_value = o.field_st_pub_owner_value
            JOIN field_data_field_pf_school s ON e.entity_id=s.entity_id
          WHERE n.type='" . $node_type . "' 
            AND s.field_pf_school_value=\"" . $school_name . "\" 
            AND n.nid BETWEEN 199000000 AND 299000000
          ", $des_db);  

        execute($src_result, $db_name, 1);
      }
    }
  }
  
}

?>