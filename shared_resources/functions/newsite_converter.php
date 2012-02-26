<?php

$svr = $_SERVER['SERVER_NAME'];
$process = False;
if(stripos($svr, '.local') > 0) $process=True;

if($process==False) {
    exit('cannot run this script on this server.');    
}

chdir( dirname ( __FILE__ ) );
chdir ("../");
$cwd=getcwd();

include_once("{$cwd}/functions/functions_credential.php");
$hostname=gethostname();
$username="drupal";
$password=get_credential($hostname);
$hostname="localhost";

$dbcnx = mysql_connect ($hostname, $username, $password);

$result = @mysql_query('SHOW DATABASES');
if (!$result) {
  exit('<p>show databases: ' . mysql_error() . '</p>');
}

while ($row = mysql_fetch_array($result)) {  
  $opts .= '<option value="'.$row[0].'">'.$row[0].'</option>';   
} 

?>

<style>
      
      body, p, h1, h2, h3, h4, table, td, th, ul, ol, textarea, input {
        font-family: verdana,helvetica,arial,sans-serif;
      }
      
      #note {
        font-size: 90%;
        color: #A00;
      }
      
      .db {
        padding: 20px 0 10px 0;
      }
      
      .eg {
        padding: 10px;
        font-size: 80%;
        color: #AAA;
      }
    </style>

<body>
  <h3>Site Converter for <?php print $svr;?></h3>
  <div id="note">
    Note:<br/>
    - This converter will search and replace all string in database and files.<br/>
    - Execute this script in developer's virtual machine.<br/>
    - Commit and push to server via Git when the change is completed.<br/>
    - Search and replace <font color='red'><b>Upper Case</b></font> as well..!!<br/>
    <br/>
    Process:<br/>
    1. Change mode to 777 for the site directory to give permission to update file name.<br/>
    2. Input the text to be replaced in Source database field.<br/>
    3. Input the text to replace in Target database field.<br/>
    4. When the convert is done, export and import database into development server.<br/>
    <br/>    
  </div>
  <form action="newsite_searchreplacedb.php" method="post"> 
  <div class="db">
    Source database copied from: <input name="src_site" size="20" />
    <!--
    <select name='src_site'>
      <?php print $opts; ?>
    </select>
    -->
  </div>

  <div class="db">
    Target database to convert: <input name="target_site" size="20" />
    <!--
    <select name='target_site'>
      <?php print $opts; ?>
    </select>
    -->
  </div>


  <div style="padding-top: 30px;">
    <input value="Start..!!"  type="submit" />
  </div>
  </form>
</body>