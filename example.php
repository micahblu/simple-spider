#!/usr/bin/php
<?php
include "simple-spider.php";

$options = array("limit" => 1, "depth" => 5, "scope" => "all");
$spider = new SimpleSpider($options);

add_action("crawl_begin", "crawl_init");
add_action("page_content", "page_content_filter");
add_action("crawl_end", "finito");
add_action("queue_loaded", "view_queue");
add_filter("the_queue", "filter_queue");

function crawl_init(){
	echo "\nBeginning crawl\n";
}

function finito(){
	echo "All Done\n";
}

function page_content_filter(){
	global $spider;
	$content = $spider->getContent();

	//do something cool with $content
}

function view_queue(){
	global $spider;
	print_r($spider->getQueue());
}

function filter_queue($queue){
	$newqueue = array();
	foreach($queue as $key => $link){
		if(preg_match("/Shopping/", $link)){
			$newqueue[] = $link;
		}
	}

	return $newqueue;
}

$spider->crawl("http://dmoz.org");
?>