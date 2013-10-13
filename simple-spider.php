#!/usr/bin/php
<?php
/**
 * Spider
 */

include "simple_html_dom.php";

$filters = array();
$actions = array();

function apply_filter($filter, $params){
	global $filters;

	if(count($filters) > 0 && isset($filters[$filter])){
		foreach($filters[$filter] as $hook => $func){
			call_user_func($func, $params);
		}
	}
}

function add_filter($filter, $hook){
	global $filters;

	$filters[$filter][] = $hook;
}

function do_action($action){
	global $actions;

	if(count($actions) > 0){
		foreach($actions[$action] as $hook => $func){
			call_user_func($func);
		}
	}
}

function add_action($action, $hook){
	global $actions;

	$actions[$action][] = $hook;
}


class Spider{

	private $queue = array();

	private $queued = array();
	
	private $limit = 1000;

	private $contents = '';

	private $response = array();

	private $depth = 1;

	private $iteration = 0;

	private $curURL = '';

	public function __construct($options=null){
		if(is_array($options)){
			
			if( !isset($options['startwith']) || !$this->isURL($options['startwith']) ){
				die("Startwith must be a valid URL");
			}

			$this->limit = isset($options['limit']) ? $options['limit'] : 100;
			$this->curURL = $options['startwith'];
		}
	}

	public function crawl($url){
		
		if($this->iteration == 0){
			do_action("crawl_begin");
		}
		
		echo "Crawling $url\n";

		array_push($this->queued, $url);

		$urlparts = parse_url($url);

		$this->curURL = $url;

		$this->tld = end(explode(".", $urlparts['host']));
		$str = str_replace("." . $this->tld, "", $urlparts['host']);
		$this->protocol = $urlparts['scheme'];
		$this->domain = end(explode(".", $str));

		$ch = curl_init();	

		$timeout = 5;

		$cookie = tempnam ("/tmp", "CURLCOOKIE");

		curl_setopt( $ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1" );
    curl_setopt( $ch, CURLOPT_URL, $url );
    curl_setopt( $ch, CURLOPT_COOKIEJAR, $cookie );
    curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
    curl_setopt( $ch, CURLOPT_ENCODING, "" );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_AUTOREFERER, true );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );    # required for https urls
    curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $timeout );
    curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
    curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );

		$this->contents = curl_exec( $ch );
		$this->response = curl_getinfo($ch);

		//free up resources
		curl_close($ch);

		$this->iteration++;

		if($this->iteration >= $this->limit){
			do_action("crawl_end");
			exit;
		}

		$this->queueLinks();
				
		apply_filter("page_content", array($this->response, $this->contents));
		//apply_filter("page_response", $this->response);

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

		apply_filter("queued_links", $this->queue);
	}

	public function getContent(){
		return $this->contents;
	}

	public function getResponse(){
		return $this->response;
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