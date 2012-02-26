<?php

function process_content_type() {

  include_once(DRUPAL_ROOT . "/sites/all/shared_resources/functions/functions_profiles.php");   
  include_once(DRUPAL_ROOT . "/sites/all/shared_resources/functions/functions_credential.php");   
  
  global $des_db;
  global $src_db;
  global $synclog;
   
  set_time_limit(1800);

  $log_path = DRUPAL_ROOT . "/sites/all/shared_resources/bin/log_sync_research_gateway";
  if (!file_exists("{$log_path}")) mkdir("{$log_path}", 0777);  
  $synclog = fopen("{$log_path}/log_profiles_".date('Ymd-His').".txt","w");
 
  $hostname=gethostname();
  $username="drupal";
  $password=get_credential($hostname);
  $hostname=str_replace("ws001", "db001", $hostname);

  $des_db = mysql_connect($hostname, $username, $password);
  if (!$des_db) {
    exit('Could not connect to database: ' . mysql_error());
  }

  $src_db = $des_db;

  // 2. process each table ///////////////////////////////////////////////////////////////////////

  // sync shared content types to NDARC site.
  // shared content types : Project, Publication, News, Events
  copy_shared_content_type_to_dbs();

  mysql_close($des_db); 
  
  // Clear the cached pages and blocks.
  cache_clear_all();
}

function copy_shared_content_type_to_dbs() {

  //drupal_set_message('<pre>$base_root:'. print_r($base_root, TRUE) .'</pre>');
  //drupal_set_message('<pre>$$databases:'. print_r($databases['default']['default']['database'], TRUE) .'</pre>');

  /* shared field - id range
   *
   * in 'field_config' and 'field_config_instance' table
   *
   * 1000 ~ 1999 : profile
   * 2000 ~ 2999 : projects and publications
   * 3000 ~ 3999 : news/events
   * 1,000,000 : local fields in each sites.  !!!!!!!!!
   */

   
  global $databases;
  global $des_db;	// actually this is a server name.
   
  // copying records to other database
  global $des_table;
  global $primarykey;

  $source_db_name=$databases['default']['default']['database']; //current db.

  if (stristr($source_db_name, "profiles")) {    
    $target_db_names=array_filter(preg_split("/\n\r|\n|\r/", variable_get("projects_pubs_copy_to_db_name",""))); // get target db list from new&events db variable table.
    $id_min=2000;
    $id_max=2999;
  } else if (stristr($source_db_name, "newsevents")) {    
    $target_db_names=array_filter(preg_split("/\n\r|\n|\r/", variable_get("news_events_copy_to_db_name",""))); // get target db list from new&events db variable table.
    $id_min=3000;
    $id_max=3999;
    
  } else {
    form_set_error('', t('This content type must be managed in main site. i.e. in \'Profiles\' or \'News & Events\' site.'));
    return;
  }

  $arr_content_types=array_filter(preg_split("/\n\r|\n|\r/", variable_get("shared_content_types")));
  $table_list=array();  
  foreach ($arr_content_types as $type_name) {
    $type_name=trim($type_name); 
    $table_list[]="'{$type_name}'";
  }
  
  if (empty($target_db_names) || empty($table_list)) { 
    form_set_error('', t('Target database/content type is not set.'));
    return;
  }

   
  foreach ($target_db_names as $target_db_name) {
    $content_type_list=implode(', ', $table_list);
  
    // field_config
    // A-1. Delete fields if id is in reserved range
    $sql = "DELETE FROM {$target_db_name}.'field_config'
  					WHERE (id >= {$id_min} AND id <= {$id_max}) 
  						AND id IN (
            	SELECT field_id 
            	FROM field_config_instance
            	WHERE bundle IN ({$content_type_list}) AND deleted=0
            )";  
    $result = mysql_query($sql, $des_db);
  
    // A-2. Copy to target db from source db.
    $primarykey= "id";
    $src_table = "{$source_db_name}.field_config";
    $des_table = "{$target_db_name}.field_config";
    $sql = "SELECT
  						(select @rownum1:=@rownum1+1 rownum1 FROM (SELECT @rownum1:={$id_min}-1) r) as id,	
  						field_name,	
  						type,	
  						module,	
  						active,	
  						storage_type,
  						storage_module,	
  						storage_active,	
  						locked,	
  						data,	
  						cardinality,	
  						translatable,	
  						deleted  
  					FROM {$src_table}
  		          WHERE 
  		          deleted=0 AND
  		          id IN (
  	          	SELECT field_id 
  	          	FROM {$src_table}_instance
  	          	WHERE bundle IN ({$content_type_list}) 
  	          )";
    $result = mysql_query($sql, $des_db);
    execute($result);
  
    // A-3. For all local field should start from 1,000,000
    $query = mysql_query("SELECT MAX(id) AS id FROM {$des_table}", $des_db);
    $row = mysql_fetch_array($query);
    if ($row['id'] < 1000000) {
      $query = mysql_query("ALTER TABLE {$des_table} AUTO_INCREMENT = 1000000 ", $des_db);
    }
  
  
    // A-4. update field list for shared_tables.php
    $result = mysql_query("SELECT field_name FROM {$target_db_name}.field_config 
    												WHERE 
    													id>={$id_min} 
    													AND id <= {$id_max}
    													AND deleted=0
    											", $des_db);
    /*
    drupal_set_message('<pre>'. print_r("SELECT field_name FROM {$target_db_name}.field_config 
    												WHERE 
    													id>={$id_min} 
    													AND id <= {$id_max}
    													AND deleted=0
    											", TRUE) .'</pre>');
    */
    $field_list=array();    
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {  
      $field_list[]=$row["field_name"];
      //drupal_set_message('<pre>'. print_r($row["field_name"], TRUE) .'</pre>');
    }
    
    _update_shared_fields($source_db_name, $field_list);
     
    // field_config_instance
    // B-1. Delete fields in target db if id is in reserved range 
    $sql = "DELETE FROM {$target_db_name}.field_config_instance
  					WHERE (id>={$id_min} AND id <= {$id_max}) AND NOT bundle IN ({$content_type_list})";
    $result = mysql_query($sql, $des_db);
  
    // B-2. Copy to target db from source db.
    // !! copy all fields only if the record is not in target table in order to have flexibility in each site.
    // !! 'data' field includes field details - weight, title, widget info etc.
    $primarykey= "id";
    $src_table = "{$source_db_name}.field_config_instance";
    $des_table = "{$target_db_name}.field_config_instance";
    
    // B-2-1. copy all fields except 'data' field which include field instance details.
    $sql = "SELECT
  						(SELECT @rownum1:=@rownum1+1 rownum1 FROM (SELECT @rownum1:={$id_min}-1) r) as id,
  						(SELECT id FROM {$target_db_name}.field_config 
  							WHERE field_config.field_name=field_config_instance.field_name LIMIT 1  
  						) as field_id, 
  						field_name, 
  						entity_type, 
  						bundle,
  						IFNULL(
  							(
  								SELECT data FROM {$des_table} 
  								WHERE field_name={$src_table}.field_name LIMIT 1
  							),data
  						) as data,
  						deleted 
    				FROM {$src_table}
  					WHERE entity_type='node' AND (bundle IN ({$content_type_list})) AND deleted=0";
    $result = mysql_query($sql, $des_db);
    
    execute($result);
 
    
    // B-3. For all local field should start from 1,000,000
    $query = mysql_query("SELECT MAX(id) AS id FROM {$des_table}", $des_db);
    $row = mysql_fetch_array($query);
    if ($row['id'] < 1000000) {
      $query = mysql_query("ALTER TABLE {$des_table} AUTO_INCREMENT = 1000000 ", $des_db);
    }
  
    // C. copy node_type record
    $primarykey = "type";  
    $src_table = "{$source_db_name}.node_type";
    $des_table = "{$target_db_name}.node_type";
    $sql = "SELECT *
    				FROM {$src_table}
  					WHERE {$primarykey} IN ({$content_type_list})";  
    // print $sql . "\n";
    $result = mysql_query($sql, $des_db);
    execute($result);
  
    // D. copy comment related variables
    $primarykey = "name";
    $src_table = "{$source_db_name}.variable";
    $des_table = "{$target_db_name}.variable";
 
    $arr_content_types=array_filter(preg_split("/\n\r|\n|\r/", variable_get("shared_content_types")));
    foreach ($arr_content_types as $value) {
      $type_name=trim($value); 
  
      $sql = "SELECT *
    				FROM {$src_table}
  					WHERE {$primarykey} LIKE '%" . $type_name . "%' AND {$primarykey} LIKE 'comment_%'  
  				 ";   
      //drupal_set_message('<pre>sql:'. print_r($sql, TRUE) .'</pre>');  
      $result = mysql_query($sql, $des_db);
      execute($result); 
    }

  }
}

function _update_shared_fields($database="error", $field_list="") {

  $path = DRUPAL_ROOT . "/sites/all/shared_resources/config";

  if (!file_exists("{$path}")) mkdir("{$path}", 0775);
  $write = fopen("{$path}/{$database}_shared_fields.conf","w+");
  $field_list=implode(';',$field_list);
  fwrite($write,$field_list);
  fclose($write);
}

?>
