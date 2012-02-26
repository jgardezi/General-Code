<?php

$stimer = explode( ' ', microtime() );
$stimer = $stimer[1] + $stimer[0];
//  -----------

chdir( dirname ( __FILE__ ) );
chdir ("../");
$cwd=getcwd();
include_once("{$cwd}/functions/functions_credential.php");

// Database Settings
global $cid;

$hostname=gethostname();
$username="drupal";
$password=get_credential($hostname); 
$hostname="localhost";

$source_db=$_POST["src_site"];
$target_db=$_POST["target_site"];
$db   = strtolower($target_db);           // your database

// Replace options

$search_for   = $source_db;  // the value you want to search for
$replace_with = $target_db;  // the value to replace it with

$cid = mysql_connect($hostname,$username,$password); 

if (!$cid) { echo("Connecting to DB Error: " . mysql_error() . "<br/>"); }

print "<h2>replacing '$source_db' with '$target_db'</h2><br/><br/>";

// nid update moved to a module.
//update_nid($cid, strtolower($target_db));

// 1.
clear_cache();

// 2. 
delete_node($db);

// 3. rename string in files
$path = strtolower("/var/www/html/$target_db/sites");
print "<p>==== replace_text_in_files - start ====</p>";
replace_text_in_files($path, $source_db, $target_db);
print "<p>==== replace_text_in_files - end ====</p>";

// 4. rename directories and files.
$path = strtolower("/var/www/html/$target_db/sites");
print "<p>==== renameFile - start ====</p>";
// repeat 5 times in case the directory name is changed.
renameFile( $path , 0, $source_db, $target_db); 
renameFile( $path , 0, $source_db, $target_db); 
renameFile( $path , 0, $source_db, $target_db); 
renameFile( $path , 0, $source_db, $target_db); 
print "<p>==== renameFile - end ====</p>";

// 5. remove dummy files.
$cmd = 'find ' . strtolower("/var/www/html/$target_db/") .'. -type f -name "sed*" -exec rm -f {} \;';
exec($cmd, $output, $return_val);

if ($return_val == 0) {
  //echo "success";
} else {
  echo "failed - $cmd<br/>" ;
}     

// First, get a list of tables
$SQL = "SHOW TABLES";
$tables_list = mysql_db_query($db, $SQL, $cid);

if (!$tables_list) {
    echo("ERROR: " . mysql_error() . "<br/>$SQL<br/>"); } 


// Loop through the tables

while ($table_rows = mysql_fetch_array($tables_list)) {
    
    $count_tables_checked++;
    
    $table = $table_rows['Tables_in_'.$db];
    
    //echo '<br/>Checking table: '.$table.'<br/>***************<br/>';  // we have tables!
   
    $SQL = "DESCRIBE ".$table ;    // fetch the table description so we know what to do with it
    $fields_list = mysql_db_query($db, $SQL, $cid);
    
    // Make a simple array of field column names
    
    $index_fields = "";  // reset fields for each table.
    $column_name = "";
    $table_index = "";
    $i = 0;
    
    while ($field_rows = mysql_fetch_array($fields_list)) {
                
        $column_name[$i++] = $field_rows['Field'];
        
        if ($field_rows['Key'] == 'PRI') $table_index[$i] = true ;
        
    }

//    print_r ($column_name);
//    print_r ($table_index);

// now let's get the data and do search and replaces on it...
    
    $SQL = "SELECT * FROM ".$table;     // fetch the table contents
    $data = mysql_db_query($db, $SQL, $cid);
    
    if (!$data) {
        echo("ERROR: " . mysql_error() . "<br/>$SQL<br/>"); } 


    while ($row = mysql_fetch_array($data)) {

        // Initialise the UPDATE string we're going to build, and we don't do an update for each damn column...
        
        $need_to_update = false;
        $UPDATE_SQL = 'UPDATE '.$table. ' SET ';
        $WHERE_SQL = ' WHERE ';
        
        $j = 0;

        foreach ($column_name as $current_column) {
            $j++;
            $count_items_checked++;

//            echo "<br/>Current Column = $current_column";

            $data_to_fix = $row[$current_column];
            $edited_data = $data_to_fix;            // set the same now - if they're different later we know we need to update
            
//            if ($current_column == $index_field) $index_value = $row[$current_column];    // if it's the index column, store it for use in the update
    
            $unserialized = unserialize($data_to_fix);  // unserialise - if false returned we don't try to process it as serialised
            
            if ($unserialized) {
                
//                echo "<br/>unserialize OK - now searching and replacing the following array:<br/>";
//                echo "<br/>$data_to_fix";
//                
//                print_r($unserialized);
            
                recursive_array_replace($search_for, $replace_with, $unserialized);
                
                $edited_data = serialize($unserialized);
                
//                echo "**Output of search and replace: <br/>";
//                echo "$edited_data <br/>";
//                print_r($unserialized);        
//                echo "---------------------------------<br/>";
                
              }
            
            else {
                
                if (is_string($data_to_fix)) $edited_data = str_replace($search_for,$replace_with,$data_to_fix) ;
                
                }
                
            if ($data_to_fix != $edited_data) {   // If they're not the same, we need to add them to the update string
                
                $count_items_changed++;
                
                if ($need_to_update != false) $UPDATE_SQL = $UPDATE_SQL.',';  // if this isn't our first time here, add a comma
                $UPDATE_SQL = $UPDATE_SQL.' '.$current_column.' = "'.mysql_real_escape_string($edited_data).'"' ;
                $need_to_update = true; // only set if we need to update - avoids wasted UPDATE statements
                
            }
            
            if ($table_index[$j]){
                $WHERE_SQL = $WHERE_SQL.$current_column.' = "'.$row[$current_column].'" AND ';
            }
        }
        
        if ($need_to_update) {
            
            $count_updates_run;
            
            $WHERE_SQL = substr($WHERE_SQL,0,-4); // strip off the excess AND - the easiest way to code this without extra flags, etc.
            
            $UPDATE_SQL = $UPDATE_SQL.$WHERE_SQL;
            //echo $UPDATE_SQL.'<br/><br/>';
            
            $result = mysql_db_query($db,$UPDATE_SQL,$cid);
    
            if (!$result) {
                    echo("ERROR: " . mysql_error() . "<br/>$UPDATE_SQL<br/>"); } 
            
        }
        
    }

}

// Report

$report = $count_tables_checked." tables checked; ".$count_items_checked." items checked; ".$count_items_changed." items changed;";
echo '<p style="margin:auto; text-align:center">';
echo $report;

mysql_close($cid); 

//  End TIMER
//  ---------
$etimer = explode( ' ', microtime() );
$etimer = $etimer[1] + $etimer[0];
printf( "<br/>Script timer: <b>%f</b> seconds.", ($etimer-$stimer) );
echo '</p>';
//  ---------

//remove httpd log file - it grows fast..!!
$cmd = 'rm /var/log/httpd/error_log* -f';
exec($cmd, $output, $return_val);

if ($return_val == 0) {
  //echo "success";
} else {
  echo "failed - $cmd<br/>" ;
}     
exit();

function recursive_array_replace($find, $replace, &$data) {
    
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                recursive_array_replace($find, $replace, $data[$key]);
            } else {
                // have to check if it's string to ensure no switching to string for booleans/numbers/nulls - don't need any nasty conversions
                if (is_string($value)) $data[$key] = str_replace($find, $replace, $value);
            }
        }
    } else {
        if (is_string($data)) $data = str_replace($find, $replace, $data);
    }
    
} 

function clear_cache() {
  
  global $cid;
  
  $cache_tables=array(
      "cache", 
      "cache_block", 
      "cache_bootstrap", 
      "cache_field",
      "cache_filter",
      "cache_form",
      "cache_image",
      "cache_menu",
      "cache_page",
      "cache_path",
      "cache_update",
      "cache_token",
      
      "watchdog",
      "flood",
      "sessions",
      
      );
  
  print "<p>==== cache - start ====</p>";
  foreach ($cache_tables as $tbl) {  
    $sql = "TRUNCATE TABLE $tbl";
    $result = mysql_query($sql, $cid);  
    //print "<p>$sql</p>";
  }
  print "<p>==== cache - end ====</p>";
    
  
}

function delete_node($db) {

  global $cid;

  print "<p>==== delete_node - start ====</p>";
  
  $start_id=300000000;
  
  // 1
  // all projects.
  $sql = "DELETE FROM {$db}.node WHERE nid > 10000 AND nid < $start_id";
  $result = mysql_query($sql, $cid);
  
  $sql = "DELETE FROM {$db}.node_revision WHERE nid > 10000 AND nid < $start_id";
  $result = mysql_query($sql, $cid);
  
  $sql = "DELETE FROM {$db}.users WHERE uid > 1000 AND uid < $start_id";
  $result = mysql_query($sql, $cid);
    
  // 2
  $sql = "SHOW TABLES FROM $db";
  $result = mysql_query($sql);
  if (!$result) {
      echo "DB Error, could not list tables\n";
      echo 'MySQL Error: ' . mysql_error();      
  }
 
  while ($row = mysql_fetch_row($result)) {      
      if (substr($row[0], 0, 17) == "field_data_field_" || substr($row[0], 0, 21) == "field_revision_field_") {
        $sql = "DELETE FROM {$db}.{$row[0]} WHERE nid > 10000 AND nid < $start_id";
        //print "<p>$sql</p>";
        $result2 = mysql_query($sql, $cid);
        mysql_free_result($result2);
      }
  }
  
  print "<p>==== delete_node - end ====</p>";

}
  
function replace_text_in_files($path, $source_db, $target_db) {
  $ignore = array( 'cgi-bin', '.', '..' );

  $dh = @opendir( $path );

  while( false !== ( $file = readdir( $dh ) ) ) {
    if( !in_array( $file, $ignore ) ) {
      $spaces = str_repeat( '&nbsp;', ( $level * 4 ) );
      if( is_dir( "$path/$file" ) ) {
        // do nothing for directory
        replace_text_in_files( "$path/$file", $source_db, $target_db );
      } else {
        $filename=strtolower($path)."/".$file;
        $cmd="grep '$source_db' $filename";
        exec($cmd, $output, $return_val);
        if ($return_val == 0) {                
          $filename=strtolower($path)."/".$file;;
          if (!empty($source_db) && !empty($target_db)) {
            $cmd="sed -i 's/$source_db/$target_db/g' '$filename'";
            exec($cmd, $output, $return_val);

            if ($return_val == 0) {
              //echo "success - $cmd<br/>" ;
            } else {
              echo "failed - $cmd<br/>" ;
            } 
          }
        }               
      }
    }  
  }
  closedir( $dh );
}

function renameFile( $path = '.', $level = 0, $source_db, $target_db ){

  $ignore = array( 'cgi-bin', '.', '..' );

  $dh = @opendir( $path );

  while( false !== ( $file = readdir( $dh ) ) ) {
    if( !in_array( $file, $ignore ) ) {
      $spaces = str_repeat( '&nbsp;', ( $level * 4 ) );      
      if( is_dir( "$path/$file" ) ) {
        //print "dir - $path/$file<br>";
        if(stripos($file, $source_db) !== false) {
          //echo "<strong>$spaces $file</strong> => <strong>" . str_replace($source_db, $target_db, $file) . "</strong><br />";
          $cmd = 'mv "'.$path."/".$file.'" "'.str_replace($source_db, $target_db, $path."/".$file).'"';
          exec($cmd, $output, $return_val);

          if ($return_val == 0) {
            //echo "success";
          } else {
            echo "failed - $cmd<br/>" ;
          } 
          
        }
        
        renameFile( "$path/$file", ($level+1), $source_db, $target_db );

      } else {

        if(stripos($file, $source_db) !== false) {                
          //echo "$spaces $file => " . str_replace($source_db, $target_db, $file) . "<br />";          
          $cmd = 'mv "'.$path."/".$file.'" "'.str_replace($source_db, $target_db, $path."/".$file).'"';
          exec($cmd, $output, $return_val);

          if ($return_val == 0) {
            //echo "success";
          } else {
            echo "failed - $cmd<br/>" ;
          }     
        }
      }
    }  
  }
  closedir( $dh );
}

function get_start_id($cid, $db) {
  $db_selected = mysql_select_db($db, $cid);
  $query = mysql_query("SELECT value FROM variable	WHERE name='user_id_adhoc' LIMIT 1", $cid);
	$value = mysql_fetch_row($query);	
  $value_id=explode("\n", unserialize($value[0]));  
  return $value_id[0];  
}

function update_nid($cid, $db) {

  $start_id=get_start_id($cid, $db);
  
  if (empty($start_id) || $start_id == 1000000) {
    exit("variable, user_id_adhoc is not SET. Please set in Content Control configuration.");
  }
  
  $max_id = $start_id +  99999;

  $white_list = array (
                  "nid",
                  "vid",
                  "entity_id",
                  "revision_id",
                  "item_id",
                  "sid",      
                );
   
  
  // First, get a list of tables
  $SQL = "SHOW TABLES";
  $tables_list = mysql_query($SQL, $cid);

  if (!$tables_list) echo("ERROR: " . mysql_error() . "<br/>$SQL<br/>"); 

  // get nid list and map old it to new id.
  $SQL = "SELECT nid FROM node WHERE (nid >= 300000000) OR (nid > $max_id AND nid<190000000)";
  $result_nid = mysql_query($SQL, $cid);
  $target_nid = array();
  $newid=$start_id;
  while ($rows = mysql_fetch_array($result_nid)) {
    //print "$rows[0]<br>";
    
    for ($i=$newid;$i<=$max_id;$i++) {
      $SQL = "SELECT nid FROM node WHERE nid = $i";
      $nid_result = mysql_query($SQL, $cid);
      $num_rows = mysql_num_rows($nid_result);
      if ($num_rows==0) {
        $newid=$i;
        break;
      } else {
        continue;
      }
    }
    $target_nid["$rows[0]"]=$newid;
  }

  if (!empty($target_nid)) {
    print "<pre>";
    print "<div><b>node id mapping</b></div>";
    print_r($target_nid);
    print "</pre>";
  } else {
    print "<div><b>no node id to convert. exiting.</b></div>";
    return;
  }

  // Loop through the tables

  while ($table_rows = mysql_fetch_array($tables_list)) {

    $table = $table_rows['Tables_in_'.$db];

    //echo '<br/>Checking table: '.$table.'<br/>***************<br/>';  // we have tables!

    $SQL = "DESCRIBE ".$table ;    // fetch the table description so we know what to do with it
    $fields_list = mysql_query($SQL, $cid);

    // Make a simple array of field column names

    $column_name = "";    
    $i = 0;

    while ($field_rows = mysql_fetch_array($fields_list)) {
      $column_name[$i++] = $field_rows['Field'];
    }

    //      print_r ($column_name);
    $SQL = "SELECT * FROM ".$table;     // fetch the table contents
    $data = mysql_query($SQL, $cid);

    if (!$data) {
      echo("ERROR: " . mysql_error() . "<br/>$SQL<br/>");      
    } 

    while ($row = mysql_fetch_array($data)) {          
      foreach ($column_name as $current_column) {
        if (in_array($current_column, $white_list)) {                    
          if (in_array($row[$current_column], array_keys($target_nid))) {            
            
            $old_id = $row[$current_column];
            $new_id = $target_nid[$old_id];
            
            $UPDATE_SQL = "UPDATE $table SET $current_column=$new_id WHERE $current_column=$old_id";
            //echo $UPDATE_SQL.'<br/><br/>';

            $result = mysql_query($UPDATE_SQL,$cid);            
            if (!$result) {
              echo("ERROR: " . mysql_error() . "$UPDATE_SQL<br/><br>"); 
            }
          }
        }
      }
    }
  }
  
  $SQL = "UPDATE node SET vid=nid WHERE nid>=$start_id AND nid<=$max_id";
  $result = mysql_query($SQL,$cid);
  if (!$result) {
    echo("ERROR: " . mysql_error() . "<br/>$UPDATE_SQL<br/>"); 
  }
  
  $SQL = "UPDATE node_revision SET vid=nid WHERE nid>=$start_id AND nid<=$max_id";  
  $result = mysql_query($SQL,$cid);
  if (!$result) {
    echo("ERROR: " . mysql_error() . "<br/>$UPDATE_SQL<br/>"); 
  }
  
}  

?>