<?php

// 05 JAN 2015: CHANGED TO HTTPS
// 12 NOV 2016: PARSE FIX | Line 89
            
set_time_limit(180);

include_once "cors.php";

if(!isset($_POST['__safe'])) die();

// AJAX CHECK
if( isset($_GET["ajaxified"])
&& $_GET["ajaxified"]=="true"
&& !empty($_SERVER['HTTP_X_REQUESTED_FROM'])
&& strtolower($_SERVER['HTTP_X_REQUESTED_FROM']) == 'application'
){

	// AJAXIFIED
	// CHECK IF THERE ARE POST DATAS
	if(isset($_POST['data_request'])
	and $_POST['data_request']=="content"
	and isset($_POST['data_type'])
	and isset($_POST["data_title"])
	and isset($_POST["data_artist"])
	and isset($_POST['data_version'])
	and isset($_POST['data_rating'])
	and isset($_POST['data_ratingcount'])){
	
		$type = clean2($_POST['data_type']);
		$title = clean2($_POST['data_title']);
		$artist = clean2($_POST['data_artist']);
		$version = clean2($_POST['data_version']);
		$rating  = clean2($_POST['data_rating']);
		$ratingcount = clean2($_POST['data_ratingcount']);
		
		if(is_numeric($artist[0])){
			$url = "https://tabs.ultimate-guitar.com/0-9/".$artist."/".$title;
		}else{
			$url = "https://tabs.ultimate-guitar.com/".$artist[0]."/".$artist."/".$title;
		}
		
		if(is_numeric($version) and $version != "1"){
			$url .= '_ver'.$version;
		}
		
		$a = explode('.', $url);
		if(@$a[count($a)-1] == 'htm') {
			// alraedy htm
		} else if($type=="chord"){
			$url .= '_crd.htm';
		}else if($type=="tab"){
			$url .= '_tab.htm';
		}else{
			// produce an error
			die(json_encode(array(
				"state" => "error",
				"reason" => "Invalid or incomplete data entered.",
			)));
		}
		//echo "data/$artist.$title.$version.$type";die();
		if(!file_exists("data/$artist.$title.$version.$type")){
			// get the shit
			$curl_handle = curl_init(); // global handler
			$html = getPage($url);
			// PARSE THE HTML
			$crd = between($html,'<pre class="print-visible">','<div class="fb-meta">');
			$crd = between($crd,'<pre class="js-tab-content">','</pre>');
			
			if($crd=="" or $crd==false){
				// bug fix
				if(is_numeric($artist[0])){
					$url = "https://tabs.ultimate-guitar.com/0-9/".$artist."/".$title;
				}else{
					$url = "https://tabs.ultimate-guitar.com/".$artist[0]."/".$artist."/".$title;
				}
		
				if(is_numeric($version) and $version != "1"){
					$url .= '_ver'.$version;
				}
				
				if($type=="chord"){
					$url .= '_crd.htm';
				}else if($type=="tab"){
					$url .= '_tab.htm';
				}
				
				$html = getPage($url);
				// PARSE THE HTML
				$crd = between($html,'<pre class="print-visible">','<div class="link-tooltip fake-link js-tooltip">');
				//$crd = between($crd,'<pre class="js-tab-content">','</pre>');
				$crd = between($crd,'<pre class="js-tab-content">','<section class="b-suggest-correction">');
				if($crd == "" or $crd == false){
					// produce an error
					die(json_encode(array(
						"state" => "error",
						"reason" => "The chord or tab wasn't found or doesn't exist.",
					)));
				}
			}
		
			// close cUrl
			curl_close($curl_handle);
			
			// save the file
			$crd_file = fopen("data/$artist.$title.$version.$type","w");
			fwrite($crd_file,$crd);
			fclose($crd_file);

		}else{
			// theres already a file
			$crd = file_get_contents("data/$artist.$title.$version.$type");
		}
		
		// Revert the Artist and Title
		// this time, they are now hopefully correct
		$artist = ucwords(str_replace("_"," ",$artist));
		$title  = ucwords(str_replace("_"," ",$title));
		
		// SEND BACK THE PARSED DATA
		die(json_encode(array(
			"state"     => "success",
			"artist"    => $artist,
			"title"     => $title,
			"version"   => $version,
			"type"      => $type,
			"data"      => $crd,
			"rating"    => $rating,
			"rating_count" => $ratingcount,
		)));
			
	
	}else if(isset($_POST['data_request']) and $_POST['data_request']=="suggestions" and isset($_POST['data_query'])){
		
		// GET SUGGESTIONS FROM UG
		// http://www.ultimate-guitar.com/search/sug/a/we the kings.js
		/* $.ajax({
			type: "POST",
			url: "handle.php?ajaxified=true",
			async: false,
			success: function(response){
				console.log(response);
			},
			error: function(error){
				console.log(error);
			},
			data: {
				__safe: true,
				data_request: "suggestions",
				data_query: "taylor"
			},
			dataType:"json",
			accepts:"application/json",
			headers:{"X-Requested-From":"Application"}
		});*/
		
		// OPEN CURL HANDLER
		$curl_handle = curl_init();
		
		// BASE URL
		//$url = "http://www.ultimate-guitar.com/search/sug/a/".rawurlencode($_POST['data_query']);
        
        
        // NEW SHIT!
        // https://cdn.ustatik.com/article/suggestions/h/hel.js
        // first 5 shits only
        $query = substr(str_replace(' ', '_', trim($_POST['data_query'])), 0, 5);
        $url = 'https://cdn.ustatik.com/article/suggestions/' . $query[0] . '/' . $query . '.js';
		
		$page = getPage($url);
		
		//$data = between($page, "data = ", ";auto.");
        
        $data = json_decode($page, true);
		
		// SEND BACK THE PARSED DATA
		die(json_encode(array(
			"state"     => "success",
			//"suggestions" => json_decode($data)
            "suggestions" => $data['suggestions']
		)));
		
	}else if(isset($_POST['data_request']) and $_POST['data_request']=="results" and isset($_POST['data_query'])){
		
		// GET RESULTS FROM UG
		
		// OPEN CURL HANDLER
		$curl_handle = curl_init();
		
		// BASE URL
		//$url = "http://www.ultimate-guitar.com/search.php?search_type=title&value=".($_POST['data_query']);
		//http://www.ultimate-guitar.com/search.php?title=firewall&page=1&tab_type_group=text&app_name=ugt&order=myweight&type=200
		$url_tab = "https://www.ultimate-guitar.com/search.php?title=".rawurlencode($_POST['data_query'])."&page=1&tab_type_group=text&app_name=ugt&order=myweight&type=200";
		$url_crd = "https://www.ultimate-guitar.com/search.php?title=".rawurlencode($_POST['data_query'])."&page=1&tab_type_group=text&app_name=ugt&order=myweight&type=300";
        
		$page_tab = getPage($url_tab);
		$page_crd = getPage($url_crd);
		
		// check how many chords and tabs have been found
		$count_tab = between($page_tab, '<div class="updmsg">', '</div>');
		$count_crd = between($page_crd, '<div class="updmsg">', '</div>');
		
		// if there are results, the text would be similar to
		// You've searched for <b>'hell kit'</b> : <b>3</b> chords found	
		// else the text would just be
		// No matches
		
		// strip spaces off the results
		$count_tab = trim($count_tab);
		$count_crd = trim($count_crd);
		
		$tabs = array();
		$crds = array();
		
		if(strtolower($count_tab) != "no matches"){
			// there are tabs
			// links starts with http://tabs.ultimate-guitar.com/ and before </tr>
			// the title and artist can be parsed through the given link
			// the rating can be found after class="rating"><span class="r_5"></span>
			// the rating count can be found after <b class="ratdig">7</b>
			// get those shits
			$i = 0;
			while(($page_tab = stristr($page_tab, "://tabs.ultimate-guitar.com/", false)) && $i < 50){
				$i++;
				// parse the url
				$url = between($page_tab, "://tabs.ultimate-guitar.com/", "\"");
				// get the artist and title
				$exploded = explode("/", $url);
				$artist = $exploded[count($exploded)-2];
				$title  = $exploded[count($exploded)-1];
				// check version number
				$version = 1;
				if(strpos($title, "_ver") >= 0){
					// get the version shit
					$version = between($title, "_ver", "_tab.htm");
					$title   = str_replace("_ver".$version, "", $title);
				}
				if(!$version || $version===false) $version = 1;
				$artist = str_replace("_", " ", $artist);
				$title  = str_replace("_", " ", $title);
				$title  = str_replace("tab.htm", "", $title);
				$title  = trim($title);
				$title  = ucwords($title);
				$artist = ucwords($artist);
				// get rating and rating count
				$rating = intval(between($page_tab, 'class="r_', '">'));
				$rating_count = intval(between($page_tab, 'class="ratdig">', '</b>'));
				
				// save to memory
				$tabs[] = array(
					"url" => $url,
					"artist" => $artist,
					"title"  => $title,
					"version" => $version,
					"rating"  => $rating,
					"rating_count" => $rating_count,
				);
				// its done bitch, proceed to the next
				$page_tab = substr($page_tab, 5);
			}
		}
		if(strtolower($count_crd) != "no matches"){
			// there are chords
			$i = 0;
			while(($page_crd = stristr($page_crd, "://tabs.ultimate-guitar.com/", false)) && $i < 50){
				$i++;
				// parse the url
				// results in a/av/title_crd.htm
				$url = between($page_crd, "://tabs.ultimate-guitar.com/", "\"");
				// get the artist and title
				$exploded = explode("/", $url);
				$artist = $exploded[count($exploded)-2];
				$title  = $exploded[count($exploded)-1];
				// check version number
				$version = 1;
				if(strpos($title, "_ver") >= 0){
					// get the version shit
					$version = between($title, "_ver", "_crd.htm");
					$title   = str_replace("_ver".$version, "", $title);
				}
				if(!$version || $version===false) $version = 1;
				$artist = str_replace("_", " ", $artist);
				$title  = str_replace("_", " ", $title);
				$title  = str_replace("crd.htm", "", $title);
				$title  = trim($title);
				$title  = ucwords($title);
				$artist = ucwords($artist);
				// get rating and rating count
				$rating = intval(between($page_crd, 'class="r_', '">'));
				$rating_count = intval(between($page_crd, 'class="ratdig">', '</b>'));
				
				// save to memory
				$crds[] = array(
					"url" => $url,
					"artist" => $artist,
					"title"  => $title,
					"version" => $version,
					"rating"  => $rating,
					"rating_count" => $rating_count,
				);
				// its done bitch, proceed to the next
				$page_crd = substr($page_crd, 5);
			}
		
		}
		
		// dump
		die(json_encode(array(
			"state" => "success",
			"results" => array(
				"chords" => $crds,
				"tabs"   => $tabs,
			),
		)));
		
	
	}else{
	
		die(json_encode(array(
			"state" => "error",
			"reason" => "Invalid or incomplete data entered.",
		)));

	}

}

// FUNCTIONS USED
function between( $string=null, $first=null, $last=null ){
	if( $string == null || $string == '' || $first == null || $first == '' || $last == null || $last == '' ) return '';
	return substr(stristr( stristr( $string , $first ), $last ,true),strlen($first));
}
function clean( $data=null, $param=null ){
	if( $data == null || $data == '' ) return '';
	return preg_replace('/\s+/', ' ',$data);
}
function clean2($text){
	//return str_replace(" ", "_", htmlentities(rawurlencode(strip_tags(str_replace("'", "", str_replace('"', "", strtolower($text)))))));
	return str_replace(" ", "_", htmlentities(strip_tags(str_replace("'", "", str_replace('"', "", strtolower($text))))));
}
function getPage($url){
	
	// GLOBALLY USE THE HANDLE FOR SPEED
	global $curl_handle;
	
	curl_setopt( $curl_handle, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/29.0.1547.57 Safari/537.36");
	curl_setopt( $curl_handle, CURLOPT_TIMEOUT, 20);
	curl_setopt( $curl_handle, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt( $curl_handle, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt( $curl_handle, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt( $curl_handle, CURLOPT_REFERER, $url);
	curl_setopt( $curl_handle, CURLOPT_HEADER, FALSE); //disable header in result
	curl_setopt( $curl_handle, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt( $curl_handle, CURLOPT_URL, $url);
	
	// RETURN THE DATA
	return curl_exec($curl_handle);
	
}
function invenDescSort($item1,$item2){
	if ($item1['votes'] == $item2['votes']) return 0;
	return ($item1['votes'] < $item2['votes']) ? 1 : -1;
}

die();
$text = file_get_contents("test.html");


die($text);
