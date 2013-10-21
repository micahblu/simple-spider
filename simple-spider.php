#!/usr/bin/php
<?php
/**
 * Simple Spider
 *
 * A simple php spider engine that allows WordPress like hooks into the spider engine
 *
 * @version 0.5.1
 * @author Micah Blu
 * @license GPLv3
 * @copyright Micah Blu
 */

include "simple_html_dom.php";

/**
 * Declare Filter & Actions global scope variables & functions
 *
 * @since 0.5.0
 */
$filters = array();
$actions = array();

function apply_filter($filter, $params){
	global $filters;

	if(count($filters) > 0 && isset($filters[$filter])){
		foreach($filters[$filter] as $hook => $func){
			if(function_exists($func)){
				return call_user_func($func, $params);
			}
		}
	}
}

function add_filter($filter, $hook){
	global $filters;

	$filters[$filter][] = $hook;
}

function do_action($action){
	global $actions;

	if(count($actions) > 0 && isset($actions[$action])){
		foreach($actions[$action] as $hook => $func){
			if(function_exists($func)){
				call_user_func($func);
			}
		}
	}
}

function add_action($action, $hook){
	global $actions;

	$actions[$action][] = $hook;
}

/**
 * SimpleSpider Class
 *
 * @since 0.5.0
 */
class SimpleSpider{

	private $queue = array();
	private $queued = array();
	private $options = array();
	private $contents = '';
	private $response = array();
	private $iteration = 0;
	private $curURL = '';

	public function __construct($options=null){

		//set default options
		$this->options = array(
			"limit" => 10,
			"depth" => 1,
			"scope" => "local", // local, remote, all
			"sleep" => 0,
			"timeout" => 5,
			"user_agent" => "Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1"
		);

		if(is_array($options)){
			// set incoming options
			foreach($options as $option => $value){
				if(isset($options[$option])){
					$this->options[$option] = $value;
				}
			}
		}
	}

	public function crawl($url){
		
		if($this->iteration == 0){
			do_action("crawl_begin");
		}

		array_push($this->queued, $url);

		$urlparts = parse_url($url);

		$this->curURL = $url;
		$strArray = explode(".", $urlparts['host']);
		$this->tld = end($strArray);
		$str = str_replace("." . $this->tld, "", $urlparts['host']);
		$this->protocol = $urlparts['scheme'];
		$strArray = explode(".", $str);
		$this->domain = end($strArray);

		$ch = curl_init();

		$cookie = tempnam("/tmp", "CURLCOOKIE");

		curl_setopt( $ch, CURLOPT_USERAGENT, $this->options['user_agent'] );
    curl_setopt( $ch, CURLOPT_URL, $url );
    curl_setopt( $ch, CURLOPT_COOKIEJAR, $cookie );
    curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
    curl_setopt( $ch, CURLOPT_ENCODING, "" );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_AUTOREFERER, true );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );    # required for https urls
    curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $this->options['timeout'] );
    curl_setopt( $ch, CURLOPT_TIMEOUT, $this->options['timeout'] );
    curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );

		$this->contents = curl_exec($ch);
		$this->response = curl_getinfo($ch);

		//close curl session and free up resources
		curl_close($ch);

		do_action("page_content");
		do_action("page_response");
		
		$this->iteration++;

		//queue our links
		$this->queueLinks();

		$this->queue = apply_filter("the_queue", $this->queue);
	
		do_action("queue_loaded");
		
		if($this->iteration == $this->options['limit']){
			do_action("crawl_end");
			exit;
		}

		//sleep?
		if($this->options['sleep'] > 0){
			sleep($this->options['sleep']);
			do_action("spider_sleeping");
		}
		// Next..
		if(count($this->queue) > 0){
			$next = array_shift($this->queue);
			$this->crawl($next);
		}
	}

	private function queueLinks(){

		$html = str_get_html($this->contents);
		
		if(is_object($html)){ // protects from fatal error if $html is not an object
			foreach($html->find('a') as $link){
				if(!preg_match("/^https?:\/\//", $link->href)){
					$root = parse_url($this->curURL);
					$link->href = $root["scheme"] . "://" . $root["host"] . $link->href;
				}
				if(!in_array($link->href, $this->queue) && !in_array($link->href, $this->queued)){
					array_push($this->queue, $link->href);
				}
			}
		}
	}

	public function getCurrentURL(){
		return $this->curURL;
	}
	public function getContent(){
		return $this->contents;
	}

	public function getResponse(){
		return $this->response;
	}

	public function getQueue(){
		return $this->queue;
	}

	private function isURL($str){
		if(preg_match("/^https?:\/\/(.*)/", $str)){
			return true;
		}else{
			return false;
		}
	}
}
?>	