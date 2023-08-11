<?php

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
			$url = "http://tabs.ultimate-guitar.com/0-9/".$artist."/".$title;
		}else{
			$url = "http://tabs.ultimate-guitar.com/".$artist[0]."/".$artist."/".$title;
		}
		
		if(is_numeric($version) and $version != "1"){
			$url .= '_ver'.$version;
		}
		
		if($type=="chord"){
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
			//die($url);
			$curl_handle = curl_init(); // global handler
			$html = getPage($url);
			// PARSE THE HTML
			$crd = between($html,'<pre class="print-visible">','<div class="fb-meta">');
			$crd = between($crd,'<pre>','</pre>');
			
			if($crd=="" or $crd==false){
				// bug fix
				if(is_numeric($artist[0])){
					$url = "http://tabs.ultimate-guitar.com/0-9/".$artist."/".$title;
				}else{
					$url = "http://tabs.ultimate-guitar.com/".$artist[0]."/".$artist."/".$title;
				}
				$html = getPage($url);
				// PARSE THE HTML
				$crd = between($html,'<pre class="print-visible">','<div class="fb-meta">');
				$crd = between($crd,'<pre>','</pre>');
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
			
	
	}else if(false){
	
		// PARSE FIRST BEFORE PROCESSING DATA
		$artist = strtolower($_POST["data_artist"]);
		$title  = strtolower($_POST["data_title"]);
		
		$artist = str_replace("'","",$artist);
		$title  = str_replace("'","",$title);
		
		$artist = str_replace('"',"",$artist);
		$title  = str_replace('"',"",$title);
		
		$artist = strip_tags($artist);
		$title  = strip_tags($title);
		
		$artist = htmlentities($artist);
		$title  = htmlentities($title);
		
		$artist = str_replace(" ","_",$artist);
		$title  = str_replace(" ","_",$title);
		
		// variables
		$crd = $crd_html = null;
		$tab = $tab_html = null;
		
		// CHECK IF BOTH COMBINATIONS HAVE BEEN STORED IN A TEXT FILE
		// IF NOT, FETCH IT
		// http://tabs.ultimate-guitar.com/g/green_day/homecoming_crd.htm
		
		// OPEN CURL HANDLER
		$curl_handle = curl_init();
		
		// BASE URL
		$url = "http://tabs.ultimate-guitar.com/".$artist[0]."/".urlencode($artist)."/".urlencode($title);
		
		if(!file_exists("pub/$artist.$title.crd")){
		
			// GET THE CHORDS
			$crd_html = getPage($url."_crd.htm");
			
			// PARSE THE HTML
			$crd = between($crd_html,'<pre class="print-visible">','<div class="fb-meta">');
			$crd = between($crd,'<pre>','</pre>');
			
			if($crd!="" and $crd!=false){
			
				// IT EXISTS
				// GET THE BEST CHORDS
				$crd_html = getBestCRD($crd_html);

				// PARSE THE HTML
				$crd = between($crd_html,'<pre class="print-visible">','<div class="fb-meta">');
				$crd = between($crd,'<pre>','</pre>');

				$crd = "<pre>$crd</pre>";
				
			}else{
			
				// CHORD WASN'T FOUND
				/*
				*  SEARCH THE ARTIST
				*  IF THERE ARE ANY SUGGESTION USE THAT AND THEN GET THE CHORDS
				*  IF THERE ARE NO CHORDS
				*  SEARCH FOR THE CHORD NAMES
				*  SEE IF THERE ARE ANY SUGGESTIONS, IF THERE IS USE THAT
				*  IF THERE IS STILL NO CHORDS, FUCK YOU.
				*
				*  ARTIST >> www.ultimate-guitar.com/search.php?search_type=title&value=$artist
				*  TITLE  >> 
				*/
				$tmp = getPage("http://www.ultimate-guitar.com/search.php?search_type=title&value=$artist");
				$tmp = between($tmp,'are you looking for</span>','</div>');
				// THERE ARE THREE OPTIONS
				// OKAY>> <a ... ist']);">Green Day</a> artist?
				// SEARCH AGAIN>> <a ... eight">greenday</a>
				// FUCKED UP>>false
				$ch1 = between($tmp,');">','</a> artist?');
				$ch2 = between($tmp,'">','</a>');
				
				if($ch1 != false){
				
					// this is now the artist
					$artist = strtolower(str_replace(" ","_",$ch1));
					
					// BASE URL
					$url = "http://tabs.ultimate-guitar.com/".$artist[0]."/".urlencode($artist)."/".urlencode($title);
					
					// GET THE CHORDS
					$crd_html = getPage($url."_crd.htm");
					
					// PARSE THE HTML
					$crd = between($crd_html,'<pre class="print-visible">','<div class="fb-meta">');
					$crd = between($crd,'<pre>','</pre>');
					if($crd!="" and $crd!=false){
					
						// IT EXISTS
						// GET THE BEST CHORDS
						$crd_html = getBestCRD($crd_html);

						// PARSE THE HTML
						$crd = between($crd_html,'<pre class="print-visible">','<div class="fb-meta">');
						$crd = between($crd,'<pre>','</pre>');

						$crd = "<pre>$crd</pre>";
						
					}else{
						
						// STILL NO CHORDS
						// THE ARTIST IS NOW GOOD
						// SO SEARCH FOR THE SONG
						// SAME SHIT APPLIES
						
						// NVM
						$crd = '';
						
					}
				
				}else if($ch2 != false){
				
					// A SUGGESTION
					// DO IT AGAIN
					// THIS TIME, ONLY CH1 MATTERS
					// THE ARTIST SHOULD NOW BE THE SUGGESTED ONE
					$artist = strtolower(str_replace(" ","_",$ch2));
					$tmp = getPage("http://www.ultimate-guitar.com/search.php?search_type=title&value=$artist");
					$tmp = between($tmp,'are you looking for</span>','</div>');
					$ch1 = between($tmp,');">','</a> artist?');
					
					if($ch1 !== false){
					
						// this is now the artist
						$artist = strtolower(str_replace(" ","_",$ch1));
						
						// BASE URL
						$url = "http://tabs.ultimate-guitar.com/".$artist[0]."/".urlencode($artist)."/".urlencode($title);
						
						// GET THE CHORDS
						$crd_html = getPage($url."_crd.htm");
						
						// PARSE THE HTML
						$crd = between($crd_html,'<pre class="print-visible">','<div class="fb-meta">');
						$crd = between($crd,'<pre>','</pre>');
						if($crd!="" and $crd!=false){
						
							// IT EXISTS
							// GET THE BEST CHORDS
							$crd_html = getBestCRD($crd_html);

							// PARSE THE HTML
							$crd = between($crd_html,'<pre class="print-visible">','<div class="fb-meta">');
							$crd = between($crd,'<pre>','</pre>');

							$crd = "<pre>$crd</pre>";
							
						}else{
							
							// LOSE HOPE
							$crd = "";
							
						}
					
					}else{
					
						// LOSE HOPE
						$crd = "";
					
					}
						
				}else{
				
					// LOSE HOPE
					$crd = "";
					
				}
				
			}
			
			// SAVE THE FILE
			$crd_file = fopen("pub/$artist.$title.crd","w");
			fwrite($crd_file,$crd);
			fclose($crd_file);
			
		}else{
		
			// THIS CHORD HAS BEEN SAVED
			$crd = file_get_contents("pub/$artist.$title.crd");
			
		}

		
		if(!file_exists("pub/$artist.$title.tab")){
		
			// GET THE CHORDS
			$tab_html = getPage($url."_tab.htm");
			
			// PARSE THE HTML
			$tab = between($tab_html,'<pre class="print-visible">','<div class="fb-meta">');
			$tab = between($tab,'<pre>','</pre>');
			if($tab!="" and $tab!=false){
			
				// IT EXISTS
				// GET THE BEST CHORDS
				$tab_html = getBestTAB($tab_html);

				// PARSE THE HTML
				$tab = between($tab_html,'<pre class="print-visible">','<div class="fb-meta">');
				$tab = between($tab,'<pre>','</pre>');

				$tab = "<pre>$tab</pre>";
				
			}else{
			
				// TAB WASN'T FOUND
				// THE ARTIST IS FIXED SO MEH
				// THE TITLE IS ALSO FIXED SO BLEH
				
				$tab = '';
				
			}
			
			// SAVE THE FILE
			$tab_file = fopen("pub/$artist.$title.tab","w");
			fwrite($tab_file,$tab);
			fclose($tab_file);
			
		}else{
		
			// THIS TAB HAS BEEN SAVED
			$tab = file_get_contents("pub/$artist.$title.tab");
			
		}
		
		// CLOSE CURL
		curl_close($curl_handle);
		
		// Revert the Artist and Title
		// this time, they are now hopefully correct
		$artist = str_replace("_"," ",$artist);
		$title  = str_replace("_"," ",$title);
		
		// SEND BACK THE PARSED DATA
		die(json_encode(array(
			"state"     => "success",
			"artist"    => $artist,
			"title"     => $title,
			"chords"    => $crd,
			"tab"       => $tab,
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
		$url = "http://www.ultimate-guitar.com/search/sug/a/".rawurlencode($_POST['data_query']);
		
		$page = getPage($url);
		
		$data = between($page, "data = ", ";auto.");
		
		// SEND BACK THE PARSED DATA
		die(json_encode(array(
			"state"     => "success",
			"suggestions" => json_decode($data)
		)));
		
	}else if(isset($_POST['data_request']) and $_POST['data_request']=="results" and isset($_POST['data_query'])){
		
		// GET RESULTS FROM UG
		
		// OPEN CURL HANDLER
		$curl_handle = curl_init();
		
		// BASE URL
		//$url = "http://www.ultimate-guitar.com/search.php?search_type=title&value=".($_POST['data_query']);
		//http://www.ultimate-guitar.com/search.php?title=firewall&page=1&tab_type_group=text&app_name=ugt&order=myweight&type=200
		$url_tab = "http://www.ultimate-guitar.com/search.php?title=".rawurlencode($_POST['data_query'])."&page=1&tab_type_group=text&app_name=ugt&order=myweight&type=200";
		$url_crd = "http://www.ultimate-guitar.com/search.php?title=".rawurlencode($_POST['data_query'])."&page=1&tab_type_group=text&app_name=ugt&order=myweight&type=300";
		
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
			while(($page_tab = stristr($page_tab, "http://tabs.ultimate-guitar.com/", false)) && $i < 50){
				$i++;
				// parse the url
				$url = between($page_tab, "http://tabs.ultimate-guitar.com/", "\"");
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
			while(($page_crd = stristr($page_crd, "http://tabs.ultimate-guitar.com/", false)) && $i < 50){
				$i++;
				// parse the url
				// results in a/av/title_crd.htm
				$url = between($page_crd, "http://tabs.ultimate-guitar.com/", "\"");
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
	curl_setopt( $curl_handle, CURLOPT_HEADER, TRUE);
	curl_setopt( $curl_handle, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt( $curl_handle, CURLOPT_URL, $url);
	
	// RETURN THE DATA
	return curl_exec($curl_handle);
	
}
function invenDescSort($item1,$item2){
	if ($item1['votes'] == $item2['votes']) return 0;
	return ($item1['votes'] < $item2['votes']) ? 1 : -1;
}
function getBestCRD($crd_html){

	// GET THE VOTES
	$crd_votes = array();
	
	// ^_temp shouldn't be equal to " " else its the only chord
	$crd_temp = clean(between($crd_html,'<div class="ver_pos">','</div>'));
	if($crd_temp != " "){
		
		// SWEEP THROUGH THE VERSION LIST
		
		$crd_votes = array();

		$crd_temp = clean($crd_html);
		$crd_temp = between($crd_temp,'<h2>Whole song</h2>','</div>');
		$crd_temp = between($crd_temp,'<ul>','</ul>');
		$crd_temp = explode("</li> <li >",$crd_temp);
		$crd_temp[0] = str_replace('<li class="curversion" > ','',$crd_temp[0]);
		$crd_temp[count($crd_temp)-1] = str_replace(" </li>",'',$crd_temp[count($crd_temp)-1]);
		foreach($crd_temp as $curr){
			$stars = stristr($curr,"<span");
			$stars = explode("</span>",$stars);
			$i = 0;
			foreach($stars as $star){
				if(between($star,'class="','">')==="l_s"){
					$i++;
				}
			}
			$url = between($curr,'<a href="','" ');
			if($url=="#"){
				$tmp_votes = str_ireplace("x ","",clean(between($crd_html,'<div class="v_c">','</div>')));
				$crd_votes[] = array(
					"stars" => $i,
					"url"   => $url,
					"votes" => intval(str_replace(" ","",$tmp_votes)),
				);
			}else{
				$crd_votes[] = array(
					"stars" => $i,
					"url" => $url,
				);
			}
		}
		// NOW SWEEP THROUGH EACH OF THOSE URLS
		for($i=0;$i<count($crd_votes);$i++){
			$tmp_url = $crd_votes[$i]["url"];
			if($tmp_url!="#"){
				$tmp_html = getPage($tmp_url);
				// <div class="v_c"> </div>
				$tmp_votes = str_ireplace("x ","",clean(between($tmp_html,'<div class="v_c">','</div>')));
				$crd_votes[$i]["votes"] = intval(str_replace(" ","",$tmp_votes));
			}
		}

		// AFTER THIS, THE ARRAY IS NOW SORTED ACCD TO VOTES
		/*$tmp_votes = array();
		foreach($crd_votes as $key => $row){
			$tmp_votes[$key] = $row["votes"];
		}
		array_multisort($tmp_votes, SORT_DESC, $crd_votes);*/
		usort($crd_votes,'invenDescSort');

		/*

			A NEW ALGORITHM

			IF 1ST === 5 AND 2ND <= 5
				SELECT FIRST
			ELSE IF 1ST - 2ND >= 1 AND 1ST - 3RD >= 1
				SELECT FIRST ITEM
			ELSE IF 2ND - 1ST >= 1 AND 2ND - 3RD >= 1
				SELECT 2ND ITEM
			ELSE IF 3RD - 1ST >=1 AND 3RD -2ND >= 1
				SELECT 3RD ITEM
			ELSE
				SELECT 1ST ITEM
			ENDIF
			

		*/
		$tmp_1 = $crd_votes[0]["stars"];
		$tmp_2 = $crd_votes[1]["stars"];
		$tmp_3 = isset($crd_votes[2]) ? $crd_votes[2]["stars"] : 0 ;

		if($tmp_1==5 and $tmp_2<=5){
			$crd_url = $crd_votes[0]["url"];
		}else if($tmp_1-$tmp_2>=1 and $tmp_1-$tmp_3>=1){
			$crd_url = $crd_votes[0]["url"];
		}else if($tmp_2-$tmp_1>=1 and $tmp_2-$tmp_3>=1){
			$crd_url = $crd_votes[1]["url"];
		}else if($tmp_3-$tmp_1>=1 and $tmp_3-$tmp_2>=1){
			$crd_url = $crd_votes[2]["url"];
		}else{
			$crd_url = $crd_votes[0]["url"];
		}
		
		
		// RETURN
		
		if($crd_url!="#"){
			
			// RETURN THE NEW HTML
			return getPage($crd_url);
			
		}else{
			
			// WHAT A WASTE. ITS STILL THE SAME PAGE -___-
			return $crd_html;
			
		}
	
	}else{
	
		// ONLY CHORD AVAILABLE
		// RETURN ITSELF
		
		return $crd_html;
		
	}
	
}

function getBestTAB($tab_html){

	// GET THE VOTES
	$tab_votes = array();
	
	// ^_temp shouldn't be equal to " " else its the only tab
	$tab_temp = clean(between($tab_html,'<div class="ver_pos">','</div>'));
	if($tab_temp != " "){
		
		// SWEEP THROUGH THE VERSION LIST
		
		$tab_votes = array();

		$tab_temp = clean($tab_html);
		$tab_temp = between($tab_temp,'<h2>Whole song</h2>','</div>');
		$tab_temp = between($tab_temp,'<ul>','</ul>');
		$tab_temp = explode("</li> <li >",$tab_temp);
		$tab_temp[0] = str_replace('<li class="curversion" > ','',$tab_temp[0]);
		$tab_temp[count($tab_temp)-1] = str_replace(" </li>",'',$tab_temp[count($tab_temp)-1]);
		foreach($tab_temp as $curr){
			$stars = stristr($curr,"<span");
			$stars = explode("</span>",$stars);
			$i = 0;
			foreach($stars as $star){
				if(between($star,'class="','">')==="l_s"){
					$i++;
				}
			}
			$url = between($curr,'<a href="','" ');
			if($url=="#"){
				$tmp_votes = str_ireplace("x ","",clean(between($tab_html,'<div class="v_c">','</div>')));
				$tab_votes[] = array(
					"stars" => $i,
					"url"   => $url,
					"votes" => intval(str_replace(" ","",$tmp_votes)),
				);
			}else{
				$tab_votes[] = array(
					"stars" => $i,
					"url" => $url,
				);
			}
		}
		// NOW SWEEP THROUGH EACH OF THOSE URLS
		for($i=0;$i<count($tab_votes);$i++){
			$tmp_url = $tab_votes[$i]["url"];
			if($tmp_url!="#"){
				$tmp_html = getPage($tmp_url);
				// <div class="v_c"> </div>
				$tmp_votes = str_ireplace("x ","",clean(between($tmp_html,'<div class="v_c">','</div>')));
				$tab_votes[$i]["votes"] = intval(str_replace(" ","",$tmp_votes));
			}
		}

		// AFTER THIS, THE ARRAY IS NOW SORTED ACCD TO VOTES
		usort($tab_votes,'invenDescSort');

		/*

			A NEW ALGORITHM

			IF 1ST === 5 AND 2ND <= 5
				SELECT FIRST
			ELSE IF 1ST - 2ND >= 1 AND 1ST - 3RD >= 1
				SELECT FIRST ITEM
			ELSE IF 2ND - 1ST >= 1 AND 2ND - 3RD >= 1
				SELECT 2ND ITEM
			ELSE IF 3RD - 1ST >=1 AND 3RD -2ND >= 1
				SELECT 3RD ITEM
			ELSE
				SELECT 1ST ITEM
			ENDIF
			

		*/
		$tmp_1 = $tab_votes[0]["stars"];
		$tmp_2 = $tab_votes[1]["stars"];
		$tmp_3 = isset($tab_votes[2]) ? $tab_votes[2]["stars"] : 0 ;

		if($tmp_1==5 and $tmp_2<=5){
			$tab_url = $tab_votes[0]["url"];
		}else if($tmp_1-$tmp_2>=1 and $tmp_1-$tmp_3>=1){
			$tab_url = $tab_votes[0]["url"];
		}else if($tmp_2-$tmp_1>=1 and $tmp_2-$tmp_3>=1){
			$tab_url = $tab_votes[1]["url"];
		}else if($tmp_3-$tmp_1>=1 and $tmp_3-$tmp_2>=1){
			$tab_url = $tab_votes[2]["url"];
		}else{
			$tab_url = $tab_votes[0]["url"];
		}
		
		
		// RETURN
		
		if($tab_url!="#"){
			
			// RETURN THE NEW HTML
			return getPage($tab_url);
			
		}else{
			
			// WHAT A WASTE. ITS STILL THE SAME PAGE -___-
			return $tab_html;
			
		}
	
	}else{
	
		// ONLY TAB AVAILABLE
		// RETURN ITSELF
		
		return $tab_html;
		
	}
	
}

die();
$text = file_get_contents("test.html");


die($text);
