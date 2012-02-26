<?php
function rg_img_download($type, $rg_img_url) {

  /** Global settings **/
  chdir( dirname ( __FILE__ ) );
  // dir path to store the original image
  $original_dir = "../../shared_files/$type/pictures/";
  $large_dir = "../../shared_files/styles/280x280/public/$type/pictures/";
  // dir path$original_dir to store the resized image
  $medium_dir = "../../shared_files/styles/136x136/public/$type/pictures/";
  // dir path to store the thumbnail image
  $thumb_dir = "../../shared_files/styles/80x80/public/$type/pictures/"; 
  
  if (!file_exists("{$original_dir}")) mkdir($original_dir, 0755, true);
  if (!file_exists("{$large_dir}")) mkdir($large_dir, 0755, true);
  if (!file_exists("{$medium_dir}")) mkdir($medium_dir, 0755, true);
  if (!file_exists("{$thumb_dir}")) mkdir($thumb_dir, 0755, true);
  
  /** Now we start the real business **/
  try {
    // create the main instance to malipulate images 
    $rg_img = RGImg::getInstance($original_dir, $large_dir, $medium_dir, $thumb_dir);
    
    //$rg_img->resetLog(); 
    //$rg_img->log('** Start ** ');
    //$rg_img->log($type . ": " . $rg_img_url);
    
    try {
      $rg_img->setRemoteImgUrl($rg_img_url);
      $rg_img->originalImage();
  //    $rg_img->storeOriginal(); // does not override if existed.
  //    $rg_img->storeOriginal(true); // override no matter if it is existed.
      $rg_img->largeImage(280, 280); // does not override
      $rg_img->mediumImage(136, 136); // does not override
      $rg_img->thumbnailImage(80, 80); // does not override
      return true;
      
  //    $rg_img->thumbnailOriginal(136, 136); // override
    } catch (Exception $e) {
      // if you get everything input right, this catch should not happen.
      //$rg_img->log('Error: '.$e->getMessage());
      return false;
    }
    /**** endfor ****/
    //$rg_img->closeLog();

  } catch (Exception $e) {
    // if something bad happens, just print it on the screen / browser
    echo $e->getMessage() . "\n";
    return false;
  }
}
?>