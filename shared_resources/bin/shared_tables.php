<?php
// shared tables to be used for multi-site
// used in setting.php file in each multi-site folder

$profile_site_name="profiles.";
$newsevents_site_name="newsevents.";

$common_fields = array (
                  	'sessions',
                  	'authmap',
                  );
        
$profile_fields = array (
                    'field_pf_photo',
                    'field_pf_bio',
                    'field_pf_blog_feed_url',
                    'field_pf_current_interests',
                    'field_pf_emplid',
                    'field_pf_faculty',
                    'field_pf_fax',
                    'field_pf_title',  
                    'field_pf_firstname',                  	
                    'field_pf_lastname',
                    'field_pf_title_fullname',
                    'field_pf_location',
                    'field_pf_location_image',
                    'field_pf_location_url',
                    'field_pf_middlename',
                    'field_pf_my_contact_email',
                    'field_pf_my_delicious',
                    'field_pf_my_facebook',
                    'field_pf_my_linkedin',
                    'field_pf_phone',
                    'field_pf_related_links',
                    'field_pf_research_field',
                    'field_pf_school',
                    'field_pf_seo_tags',
                    'field_pf_tv_collection_url',
                    'field_pf_twitter_account',
                    'field_pf_university_role',
                    'field_pf_pub_name',
                    'field_pf_campus',
                    'field_pf_hideprofile',
                    'field_pf_is_adhoc',
                  );
                  
$shared_tables=array();
 
foreach($common_fields as $field) {
  $shared_tables[$field] = "{$profile_site_name}";
}

foreach($profile_fields as $field) {
  $shared_tables['field_data_' . $field] = "{$profile_site_name}";
  $shared_tables['field_revision_' . $field] = "{$profile_site_name}";
}

// Profiles - get shared field list from config file.
$shared_tables_include_path = !empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : (function_exists('drush_get_context') ? drush_get_context('DRUSH_DRUPAL_ROOT') : '');
$filename = $shared_tables_include_path . "/sites/all/shared_resources/config/profiles_shared_fields.conf";
$handle = fopen($filename, "r");
if (!empty($handle)) {  
  $contents = fread($handle, filesize($filename));
  fclose($handle);
  foreach(explode(';', $contents) as $field) {
    $shared_tables['field_data_' . $field] = "{$profile_site_name}";
    $shared_tables['field_revision_' . $field] = "{$profile_site_name}";  
  }
} 

// News & Events - get shared field list from config file.
$filename = $shared_tables_include_path . "/sites/all/shared_resources/config/newsevents_shared_fields.conf";
$handle = fopen($filename, "r");
if (!empty($handle)) {
  $contents = fread($handle, filesize($filename));
  fclose($handle);
  foreach(explode(';', $contents) as $field) {
    $shared_tables['field_data_' . $field] = "{$newsevents_site_name}";
    $shared_tables['field_revision_' . $field] = "{$newsevents_site_name}";  
  }
}  


// files - images and attachements. using news & events site for testing. - do we need a dedicated site for this?????
$shared_tables['file_managed'] = "{$newsevents_site_name}";
$shared_tables['file_usage'] = "{$newsevents_site_name}";  
 
/*print "<pre>";
print_r($shared_tables);
print "</pre>";  
*/
?>
