simple-spider
=============
A simple PHP spider bot that implements wordpress like hooks to communicate with the spider engine

= Version =
0.5.1

= Example =
```PHP
include "simple-spider.php";

$options = array("limit" => 1, "depth" => 5, "scope" => "all");
$spider = new SimpleSpider($options);


add_action("queue_loaded", "print_queue");
add_filter("the_queue", "filter_queue");

function filter_queue($queue){
	$newqueue = array();
	foreach($queue as $key => $link){
		if(preg_match("/Shopping/", $link)){
			$newqueue[] = $link;
		}
	}

	return $newqueue;
}

function print_queue(){
	global $spider;
	print_r($spider->getQueue());
}

$spider->crawl("http://dmoz.org");

```