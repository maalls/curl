<?php

namespace Maalls\Curl;
/**
* @author maalls
* @repo https://github.com/maalls/curl
**/

class Curl {
  
  private $ch;
  private $url;
  private $logger;
  
  public function __construct($url = null, $options = array(), $logger = null) {
    
    $this->init($url);
    
    if($options) {
      
      $this->setOptions($options);
      
    }
    
    $this->logger = $logger;
    
  }
  
  public function __destruct() {
    
    if($this->ch) {
      
      curl_close($this->ch);
      $this->ch = null;
      
    }
    
  }
    
  public function init($url = null) {
    
    if($this->ch) $this->close();
    
    $this->ch = curl_init($url);
    $this->url = $url;
    
  }

  public function execute() {
    
    $microtime = microtime(true);
    $rsp = curl_exec($this->ch);
    $microtime = microtime(true) - $microtime;
    if($this->logger) $this->logger->info("Calling " . $this->url . " in " . $microtime . " got " . $this->getInfo(CURLINFO_HTTP_CODE));
    return $rsp;
    
  }
  
  public function getUrl() {
    
    return $this->url;
    
  }
  
  public function setOption($option, $value) {
    
    curl_setopt($this->ch, $option, $value);

    if($option == CURLOPT_URL) $this->url = $value;   
    
  }
  
  public function setOptions($options) {
    
    if(!is_array($options)) {
      
      throw new ErrorException("setOptions() required an array(). ");
      
    }
        
    curl_setopt_array($this->ch, $options);
    if(isset($options[CURLOPT_URL])) $this->url = $options[CURLOPT_URL];
    
  }
  
  public function getInfo($info = null) {
    if($info === null) return curl_getinfo($this->ch);
    return curl_getinfo($this->ch, $info);
    
  }
  
  public function getContent() {
    
    return curl_multi_getcontent($this->ch);
    
  }
  
  public function addToMultiHandler($mh) {
    
    curl_multi_add_handle($mh, $this->ch);
    
  }
  
  public function removeFromMultiHandler($mh) {
    
    curl_multi_remove_handle($mh, $this->ch);
    
  }
  
  public function close() {
    
    curl_close($this->ch);
    $this->ch = null;
  }

  public function getError() {

    return curl_error($this->ch);

  }

  public function getErrno() {

    return curl_errno($this->ch);

  }
  
  public function log($msg, $level = null) {
    
    if($this->logger) $this->logger->log($msg, $level);
    
  }

  public function setLogger($logger) {
    
    $this->logger = $logger;
    
  }
  
}

 
