<?php
/**
 * A Wrapper class to malipulate remote images
 *
 * @author Jeffrey Cai
 */
class RGImg { 
  
  private $remote_url; // the remote image url
  private $original_img_dir; // the path to store large image
  private $large_img_dir; // the path to store large image
  private $medium_img_dir; // the path to store medium image
  private $thumbnail_img_dir; // the path to store thumbnail image
  
  private function __construct($original_img_dir, $large_img_dir, $medium_img_dir, $thumbnail_img_dir) {
    $this->setOriginalImgDir($original_img_dir);
    $this->setLargeImgDir($large_img_dir);
    $this->setMediumImgDir($medium_img_dir);
    $this->setThumbnailImgDir($thumbnail_img_dir);
  }
  
/**
   * Download remote image and store locally
   *
   * @param boolean $override
   */
  public function originalImage($override = true) {
    // check remote url is set
    $remote_url = $this->remote_url;
    if (empty($remote_url))
      throw new Exception ('Please set Remote Url first');
    
    // get file name
    $file_name = end(explode('/', $remote_url));
    
    // if we do not allow override, skip it
    if (!$override && is_file($this->original_img_dir.$file_name)) {
      //$this->log('Skip to copy original image '.$remote_url);
      return;
    } 
    
    // proxy for external url    
    $proxy = 'tcp://infpapxvip.it.unsw.edu.au:8080';    
    $aContext = array(
      'http' => array(
      	'proxy' => $proxy,
      	'request_fulluri' => True,
    	),
    );    
    $context = stream_context_create($aContext); 
    
    // copy from remote
    if ($content = file_get_contents($remote_url, false, $context)) {
      if (file_put_contents($this->original_img_dir.$file_name, $content)) {
        //$this->log('Succeed to copy original image '.$remote_url);
      } else {
        throw new Exception ('Fail to copy remote image, '.$remote_url.' to '.$this->original_img_dir);
      }
    }
    else
      throw new Exception ('Can not get file from remote address: '.$remote_url);
  }
  
  /**
   * Download remote image and store locally
   *
   * @param boolean $override
   */
  public function largeImage($max_width, $max_height, $override = true) {
    // check remote url is set
    $remote_url = $this->remote_url;
    if (empty($remote_url))
      throw new Exception ('Please set Remote Url first');
    
    // get file name
    $file_name = end(explode('/', $remote_url));
    $large_file_name = array_shift(explode('.', $file_name)).'.jpg';
    
    // if we do not allow override, skip it
    if (!$override && is_file($this->large_img_dir.$large_file_name)) {
      //$this->log('Skip to copy large image '.$remote_url);
      return;
    }
    
    // medium and store the image
    if ($img = $this->resize_image($this->original_img_dir.$file_name, $max_width, $max_height, true)) {
      if (imagejpeg($img, $this->large_img_dir.$large_file_name)) {
        //$this->log('Succeed to large image.');
      }
    } else {
      throw new Exception ('Fail to resize image : '.$file_name);
    }
  }
  
  /**
   * Resize the large image and store it locally
   * This method does not crop the large image
   *
   * @param int $max_width, the maximum width to expect
   * @param int $max_height, the maximum height to expect
   * @param boolean $override 
   */
  public function mediumImage($max_width, $max_height, $override = true) {
    // check remote url is set
    $remote_url = $this->remote_url;
    if (empty($remote_url))
      throw new Exception ('Please set Remote Url first');
    
    // get file name
    $file_name = end(explode('/', $remote_url));
    $medium_file_name = array_shift(explode('.', $file_name)).'.jpg';
    
    // if we do not allow override, skip it
    if (!$override && is_file($this->medium_img_dir.$medium_file_name)) {
      //$this->log('Skip to medium image '.$remote_url);
      return;
    }
    
    // medium and store the image
    if ($img = $this->resize_image($this->original_img_dir.$file_name, $max_width, $max_height, true)) {
      if (imagejpeg($img, $this->medium_img_dir.$medium_file_name)) {
        //$this->log('Succeed to medium image.');
      }
    } else {
      throw new Exception ('Fail to resize image : '.$file_name);
    }
  }
  
  /**
   * Thumbnail the large image and store it locally
   * This method crops the large image
   *
   * @param int $width
   * @param int $height
   * @param boolean $override
   */
  public function thumbnailImage($width, $height, $override = true) {
    // check remote url is set
    $remote_url = $this->remote_url;
    if (empty($remote_url))
      throw new Exception ('Please set Remote Url first');
    
    // get file name
    $file_name = end(explode('/', $remote_url));
    $thumbnail_file_name = array_shift(explode('.', $file_name)).'.jpg';
    
    // if we do not allow override, skip it
    if (!$override && is_file($this->thumbnail_img_dir.$thumbnail_file_name)) {
      //$this->log('Skip to thumbnail image '.$remote_url);
      return;
    }
    
    // thumbnail and store the image
    if ($img = $this->resize_image($this->original_img_dir.$file_name, $width, $height, true)) {
      if (imagejpeg($img, $this->thumbnail_img_dir.$thumbnail_file_name)) {
        //$this->log('Succeed to thumbnail image.');
      }
    } else {
      throw new Exception ('Fail to resize image : '.$file_name);
    }
  }
  
  
  /**
   * Store the remote image url in the class
   *
   * @param string $url 
   */
  public function setRemoteImgUrl($url) {
    $this->remote_url = $url;
  }
  
  public function setOriginalImgDir($dir) {
    if (is_dir($dir) && is_writable($dir))
      $this->original_img_dir = $dir;
    else
      throw new Exception ('* The Original Img Dir is not created or writable. '.$dir);
  }
  
  public function setLargeImgDir($dir) {
    if (is_dir($dir) && is_writable($dir))
      $this->large_img_dir = $dir;
    else
      throw new Exception ('* The Large Img Dir is not created or writable. '.$dir);
  }
  
  public function setMediumImgDir($dir) {
    if (is_dir($dir) && is_writable($dir))
      $this->medium_img_dir = $dir;
    else
      throw new Exception ('* The Medium Img Dir is not created or writable. '.$dir);
  }
  
  public function setThumbnailImgDir($dir) {
    if (is_dir($dir) && is_writable($dir))
      $this->thumbnail_img_dir = $dir;
    else
      throw new Exception ('* The Thumbnail Img Dir is not created or writable. '.$dir);
  }
 
  /**
   * Use GD library to medium an image
   *
   * @param string $file, path of the image file
   * @param int $w
   * @param int $h
   * @param boolean $crop
   */
  function resize_image($file, $w, $h, $crop=FALSE) {
    list($width, $height) = getimagesize($file);
    $r = $w / $h;
    $rate = $width / $height;
    // if we want it cropped
    if ($crop) {
      if ($rate < $r) {
        $src_w = $width;
        $src_h = ceil($width / $r);
        $src_x = 0;
        $src_y = ceil(($height - $src_h) / 2);
        $dst_w = $w;
        $dst_h = $h;
        $dst_x = 0;
        $dst_y = 0;
      }
      else {
        $src_w = ceil($r * $height);
        $src_h = $height;
        $src_x = ceil(($width - $src_w) / 2);
        $src_y = 0;
        $dst_w = $w;
        $dst_h = $h;
        $dst_x = 0;
        $dst_y = 0;
      }
      $newwidth = $w;
      $newheight = $h;
    }
    // if we don't want it cropped
    else {
      if ($rate < $r) {
        $src_w = $width;
        $src_h = $height;
        $src_x = 0;
        $src_y = 0;
        $dst_w = $h * $rate;
        $dst_h = $h;
        $dst_x = 0;
        $dst_y = 0;
      }
      else {
        $src_w = $width;
        $src_h = $height;
        $src_x = 0;
        $src_y = 0;
        $dst_w = $w;
        $dst_h = $w / $rate;
        $dst_x = 0;
        $dst_y = 0;
      }
      $newwidth = $dst_w;
      $newheight = $dst_h;
    }
   
    $type = strtolower(substr(strrchr($file,"."),1));
    if($type == 'jpeg') $type = 'jpg';
    switch($type){
      case 'bmp': $src_image = @imagecreatefromwbmp($file); break;
      case 'gif': $src_image = @imagecreatefromgif($file); break;
      case 'jpg': $src_image = @imagecreatefromjpeg($file); break;
      case 'png': $src_image = @imagecreatefrompng($file); break;
      default : return "Unsupported picture type!";
    } 
  
    if ($src_image) {
      $dst_image = imagecreatetruecolor($newwidth, $newheight);
      imagecopyresampled($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);
      return $dst_image;
    } else {
      return null;
    }
  }
  
  
  /** Singleton **/
  private static $instance;
  private $count = 0;

  public static function getInstance($original_img_dir, $large_img_dir, $medium_img_dir, $thumbnail_img_dir) {
    if (!isset(self::$instance)) {
      $className = __CLASS__;
      self::$instance = new $className($original_img_dir, $large_img_dir, $medium_img_dir, $thumbnail_img_dir);
    }
    return self::$instance;
  }

  public function __clone() {
    trigger_error('Clone is not allowed.', E_USER_ERROR);
  }

  public function __wakeup() {
    trigger_error('Unserializing is not allowed.', E_USER_ERROR);
  }
}

?>
