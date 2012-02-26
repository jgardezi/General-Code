<?php

function get_credential($hostname="localhost") {    
  if ($hostname == "meddlws001") {
    $password="!mcsu9532";
  } else if ($hostname == "medulws001") {
    $password="!mcsu9532";
  } else if ($hostname == "medplws001") {
    $password="56719d7b00";
  } else if ( $hostname == 'localhost') {
  	$password = "";
  } else {
    $password="!mcsu8208";
  }  
  return $password;
}

function get_db_name($server_name) {    

  $server_name=str_replace("cmsdev", "cms", $server_name);
  $server_name=str_replace("cmsuat", "cms", $server_name);
  
  switch ($server_name) {
    case "preru.cms.med.unsw.edu.au":
      $dbname="preru";
      break;
    case "aihi.cms.med.unsw.edu.au":
      $dbname="aihi";
      break;
    case "aihi.cms.med.unsw.edu.au":
      $dbname="aihi";
      break;
    case "hiv.cms.med.unsw.edu.au":
      $dbname="hiv";
      break;
    case "lowy.cms.med.unsw.edu.au":
      $dbname="lowy";
      break;
    case "barpnet.cms.med.unsw.edu.au":
      $dbname="barpnet";
      break;
    case "coi.cms.med.unsw.edu.au":
      $dbname="coi";
      break;
    case "medicine.cms.med.unsw.edu.au":
      $dbname="medicine";
      break;
    case "prince.cms.med.unsw.edu.au":
      $dbname="prince";
      break;
    case "aihi.cms.med.unsw.edu.au":
      $dbname="aihi";
      break;
    default :
      // for local VM
      $dir = $_SERVER['DOCUMENT_ROOT'];
      $dir_name = end(explode('/', $dir));
      $dbname = str_replace("mcsu_sites_", "", $dir_name);
      break;      
  }
  return $dbname;
}

?>
