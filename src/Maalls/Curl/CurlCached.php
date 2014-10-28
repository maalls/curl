<?php

namespace Maalls\Curl;

class CurlCached {
  
  private $url;
  private $options;
  private $logger;
  
  private $directory;
  private $cacheDuration = false;
  
  private $cachedContent;
  private $cachedInfos;
  private $cachedErrno;
  private $cachedErrmsg;
  
  private $isCached;
  
  private $retry_5xx = true;
  private $cached_http_codes = array();
  
  private static $infoKeyMap = array(
    CURLINFO_EFFECTIVE_URL => "url",
    CURLINFO_CONTENT_TYPE => "content_type",
    CURLINFO_HTTP_CODE => "http_code",
    CURLINFO_HEADER_SIZE => "header_size",
    CURLINFO_REQUEST_SIZE => "request_size",
    CURLINFO_FILETIME => "filetime",
    CURLINFO_SSL_VERIFYRESULT => "ssl_verify_result",
    CURLINFO_REDIRECT_COUNT => "redirect_count",
    CURLINFO_TOTAL_TIME => "total_time",
    CURLINFO_NAMELOOKUP_TIME => "namelookup_time",
    CURLINFO_CONNECT_TIME => "connect_time",
    CURLINFO_PRETRANSFER_TIME => "pretransfer_time",
    CURLINFO_SIZE_UPLOAD => "size_upload",
    CURLINFO_SIZE_DOWNLOAD => "size_download",
    CURLINFO_SPEED_DOWNLOAD => "speed_download",
    CURLINFO_SPEED_UPLOAD => "speed_upload",
    CURLINFO_CONTENT_LENGTH_DOWNLOAD => "download_content_length",
    CURLINFO_CONTENT_LENGTH_UPLOAD => "upload_content_length",
    CURLINFO_STARTTRANSFER_TIME => "starttransfer_time",
    CURLINFO_REDIRECT_TIME => "redirect_time"
  );
  
  public function __construct($url = null, $options = array()) {
    
    $this->init($url);
    
    if($options) {
      
      $this->setOptions($options);
      
    }
    
  }
      
  public function setLogger($logger) {
    
    $this->logger = $logger;
    
  }
  
  public function setCacheDirectory($directory) {
    
    $this->directory = $directory;
    
  }
  
  public function setCacheDuration($duration = false) {
    
    $this->cacheDuration = $duration;
    
  }
  
  public function init($url = null) {
    
    $this->options = array();
    $this->cachedContent = null;
    $this->cachedInfos = array();
    $this->cachedErrno = 0;
    $this->cachedErrmsg = "";
    $this->isCached = false;
    $this->url = $url;
        
  }
  
  public function setOption($option, $value) {
    
    if($option == CURLOPT_URL) {
      
      $this->url = $value;
      
    }
    
    $this->options[$option] = $value;
        
  }
  
  public function setOptions($options) {
    
    if(isset($options[CURLOPT_URL])) {
      
      $this->url = $options[CURLOPT_URL];
      
    }

    foreach($options as $key => $value) {
    
      $this->options[$key] = $value;
      
    }
            
  }
  
  public function getInfo($info = null) {
    
    if($info) return $this->cachedInfos[self::$infoKeyMap[$info]];
    else return $this->cachedInfos;
        
  }

  public function getErrno() {

    return $this->cachedErrno;

  }

  public function getError() {

    return $this->cachedErrmsg;

  }

  
  public function getContent() {
    
    return $this->cachedContent;
    
  }
      
  public function close() {
    
    $this->init(null);
    
  }
  
  public function execute() {
    
    $this->initDirectory();

    if(!$this->url) throw new ErrorException("Url field required.");
    
    $filename = $this->getFilename($this->url);
    $this->loadFromCache($filename);
    
    if(!$this->isCached) {
    
      $this->loadFromHttp($filename);
      
    }
    
    return $this->cachedContent;
    
  }
  
  private function initDirectory() {
    
    if(!$this->directory) throw new ErrorException("Cache directory undefined.");
    
    if(!file_exists($this->directory)) {
      
      if(!mkdir($this->directory, null, true)) throw new ErrorException("Unable to create the following directory for cache :" . $this->directory);
      
    }
    else {
      
      if(!is_dir($this->directory)) throw new ErrorException("Cache directory already exists as file : " . $this->directory);
      
    }
        
  }
  
  private function getFilename($url) {
    
    return $this->directory . "/" . sha1($url) . ".cache";
    
  }
  
  private function loadFromCache($filename) {
    
    $this->isCached = false;
    
    if(is_file($filename)) {
      
      $this->logInfo("Cache file found for " . $this->url);
      
      if($this->cacheDuration !== null && $this->cacheDuration !== false && (filemtime($filename) < time() - $this->cacheDuration)) {
        
        $this->logInfo("File expired.");
        
      }
      elseif($fh = fopen($filename, "r")) {
        
        $fileContent = unserialize(fread($fh, filesize($filename)));
        $options = $fileContent["options"];
        $s = substr($fileContent["infos"][self::$infoKeyMap[CURLINFO_HTTP_CODE]], 0, 1);
        
        $this->logInfo("status: " . $fileContent["infos"][self::$infoKeyMap[CURLINFO_HTTP_CODE]]);
        
        if(($this->retry_5xx && $s == "5") || $s = "0" || !$s) {
          
          $this->logInfo("Cached 5** or 0 HTTP status should be retried.");
          
        }
        elseif($options == $this->options) {
        
          $this->cachedContent = $fileContent["content"];
          $this->cachedInfos = $fileContent["infos"];
          if (isset($fileContent["errno"])) $this->cachedErrno = $fileContent["errno"];
          if (isset($fileContent["errmsg"])) $this->cachedErrmsg = $fileContent["errmsg"];
          $this->isCached = true;
          
        
        }
        else {
          
          $this->logInfo("Cache options miss-match.");
                    
        }
        
        fclose($fh);
        
      }
      else throw new ErrorException("Unable to open existing file " . $filename);
      
    }
        
  }
  
  private function loadFromHttp($filename) {
    
    $this->logInfo("Retrieving " . $this->url . " via CURL.");
    $ch = curl_init($this->url);
    curl_setopt_array($ch, $this->options);
    $content = curl_exec($ch);
    $infos = curl_getinfo($ch);
    $errno = curl_errno($ch);
    $errmsg = curl_error($ch);
    $fileContent = serialize(array(
      "content" => $content,
      "options" => $this->options,
      "infos" => $infos,
      "errno" => $errno,
      "errmsg" => $errmsg
    ));
    
    $use_cache = true;
    
    if($this->cached_http_codes) {
      
      $use_cache = false;
      
      foreach($this->cached_http_codes as $code_pattern) {
        
        if(preg_match("@^$code_pattern$@", curl_getinfo($ch, CURLINFO_HTTP_CODE))) {
          
          $use_cache = true;
          break;
          
        }
        
      }
      
      if(!$use_cache) $this->logInfo("Cache disable for URL because of status code " . curl_getinfo($ch, CURLINFO_HTTP_CODE));
      
    }
    
    if($use_cache) {
      
      if($fh = fopen($filename, "w")) {

        fwrite($fh, $fileContent);
        fclose($fh);

      }
      else throw new ErrorException("Unable to create file " . $filename);
      
    }
    
    $this->cachedContent = $content;
    $this->cachedInfos = $infos;
    $this->cachedErrno = $errno;
    $this->cachedErrmsg = $errmsg;
    
  }
  
  public function isCached() {
    
    return $this->isCached;
    
  }
  
  private function logInfo($msg) {
    
    if($this->logger) $this->logger->info($msg);
    
  }


  public function log($msg, $level = null) {
    
    if($this->logger) $this->logger->log($msg, $level);
    
  }
  
  public function clearCache($url = null) {
    
    $this->initDirectory();

    if($url) {

      return unlink($this->getFilename($url));
    
    }
    else {
      
      foreach(glob($this->directory . "/*.cache") as $filename) {

        unlink($filename);

      }
      
    }
    
  }
  
  public function retry5xx($retry) {
    
    $this->retry_5xx = $retry;
    
  }
  
  public function getUrl() {
    
    return $this->url;
    
  }
  
  public function setCachedHttpCodes($codes) {
    
    $this->cached_http_codes = $codes;
    
  }
  
}