<?php
/*
Plugin Name: Location Marker Maps
Plugin URI: http://adventesia.com/
Description: Google maps with custom markers for single and multiple location maps. Create Locations in a Magic Field group and magically they are plotted on googlemaps.
Version: 1.1
Author: Sean Reichle
Author URI: http://seanreichle.com
License: MIT
*/

//ini_set('display_errors', 1); 

global $lm_maps, $lm_maps_options;
global $wpdb, $blog_id;  

//Plugin options
$lm_maps_options['table'] = $wpdb->prefix . "lm_markers";

//useful for get quickly the path for  images/javascript files and css files
//return something like: http://wordpress.local/wp-content/plugins/locationmaps/
$lm_maps_options['wpurl'] = get_bloginfo('wpurl');
$lm_maps_options['UpdateteTable'] = false;
define('LM_BASENAME',plugins_url().'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__)));
define('LM_URL',LM_BASENAME);

//return something like: /Users/user/sites/wordpres/wp-content/plugins/locationmaps
define("LM_PATH", dirname(__FILE__));

define('LM_FILES_NAME','files_mf');
define('LM_CACHE_NAME','cache');

//define name for settings MF
define('LM_SETTINGS_KEY', 'lm_settings');
define('LM_DB_VERSION_KEY', 'lm_db_version');
define('LM_DB_VERSION', 1);

/*%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// Install - Create Database Table
%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%*/
register_activation_hook(__FILE__,'lm_install');
function lm_install(){
	global $wpdb, $lm_maps_options;
    $table_name = $lm_maps_options['table'];
	
	$sql = "CREATE TABLE if not exists $table_name (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  post_id mediumint(9) NOT NULL,
	  store_id mediumint(9) NOT NULL,
	  title varchar(254) NOT NULL,
	  address varchar(60) NOT NULL,
	  suite varchar(60) NOT NULL,
	  city varchar(30) NOT NULL,
	  state varchar(30) NOT NULL,
	  zip varchar(11) NOT NULL,
	  phone varchar(25) NOT NULL,
	  email varchar(60) NOT NULL,
	  hours varchar(128) NOT NULL,
	  lat varchar(30) NOT NULL,
	  lng varchar(30) NOT NULL,
	  status varchar(30) NOT NULL,
	  UNIQUE KEY id (id)
	);";
	require_once(ABSPATH.'wp-admin/includes/upgrade.php');
	$ret = dbDelta($sql);
	$lm_maps_options['UpdateteTable']= true;
}
 
 
/*%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// Un-Install
%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%*/
register_deactivation_hook( __FILE__, 'lm_uninstall' );
function lm_uninstall(){
	global $wpdb, $lm_maps_options;
    $table_name = $lm_maps_options['table']; 
	$sql = "Drop table if exists $table_name";
	$wpdb->query($sql);
}


/*%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// Init Hook 
%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%*/
add_action( 'init', 'lm_init' );
function lm_init() { 
	global $wpdb, $lm_maps_options;
	$table_name = $lm_maps_options['table'];
	$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name;" ) );
	
	$the_query = new WP_Query('post_type=location&posts_per_page=-1');	
	$locationPosts = $the_query->posts;
	
	if($count==0){
		$lm_maps_options['UpdateteTable'] = true;
	}elseif($count != sizeof($locationPosts)){
		//$lm_maps_options['UpdateteTable'] = true;
		/*
		lm_uninstall();
		lm_install();
		*/
	}
}


/*%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// Add shortcode hook to get count of locations!
%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%*/
function lm_get_locations_count(){
	global $wpdb, $lm_maps_options;
	$table_name = $lm_maps_options['table'];
	$output = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM ". $wpdb->prefix . "lm_markers") );
	return $output;
}
add_shortcode('get_location_count', 'lm_get_locations_count');


/*%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// CSS Files
%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%*/
add_action( 'admin_enqueue_scripts', 'lm_enqueue_admin_script' );
function lm_enqueue_admin_script() {
	wp_register_style( 'lm_admin_css',LM_BASENAME.'css/admin.css' );
	wp_enqueue_style( 'lm_admin_css' ); 
}


/*%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// Javascriopt files
%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%*/
add_action('wp_enqueue_scripts', 'lm_enqueue_scripts');
function lm_enqueue_scripts() {	
	//include jquery
	global $wpdb, $wp_query;
	$locationPage = get_page_id('Locations');
	$this_page_name = $wp_query->queried_object->post_name;
	$lm_maps_options['location_page_id'] = $locationPage;
	$lm_maps_options['page_name'] = $this_page_name;
	
	if(strtolower($this_page_name) == "locations"){
		wp_deregister_script( 'jquery' );
		wp_register_script( 'jquery', 'http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js');
		wp_enqueue_script( 'jquery' );
	
		wp_deregister_script( 'googlemaps' );
		wp_register_script( 'googlemaps', 'https://maps.googleapis.com/maps/api/js?key=AIzaSyD1DhwoRNTApURHvrTbr4LekvvMEcS7PLg&sensor=true');
		wp_enqueue_script( 'googlemaps' );
		
		wp_deregister_script( 'locationMarkerMaps' );
		wp_register_script( 'locationMarkerMaps', LM_BASENAME.'js/locationmaps.js');
		wp_enqueue_script( 'locationMarkerMaps');
	}
}

/*%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// Utility Function
%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%*/
function get_page_id($page_name){
	global $wpdb;
	$page_name = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_name = '".$page_name."'");
	return $page_name;
}
/*%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// Administration page
%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%*/
//add_action('admin_menu','lm_admin');
function lm_admin() {
} 

/*%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// Adding metaboxes into the  pages for create posts 
// Also adding code for save this data
%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%*/
//add_action( 'add_meta_boxes', 'lm_add_meta_boxes');
function lm_add_meta_boxes() {
}

/*%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// Update Content Hook
%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%*/
add_filter( 'the_content', 'filterOutContent54' );
function filterOutContent54($content=""){
	global $lm_maps_options;
	return $content;
}

/*%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// Add locations json to page for template handlers of plugin 
// Template must have hidden div with id='map_markers' and visible div with id='map_canvas'
%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%*/
add_action('wp_footer', 'lm_injectAddressMarkers');
function lm_injectAddressMarkers(){
	global $lm_maps ,$wpdb, $lm_maps_options;
	$ip = getIp();
	print "<script language='javascript'> var LM_BASENAME='".$lm_maps_options['wpurl']."'; var autoLookup='".$_GET['l']."'; var users_ip='".$ip."'; </script>"; 	
	if($lm_maps_options['UpdateteTable']==true){
		UpdatePluginDB();
	}
}

function getIp() {
	$ip = $_SERVER['REMOTE_ADDR'];
	if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
		$ip = $_SERVER['HTTP_CLIENT_IP'];
	} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	}
	return $ip;
}

/*%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// Ajax Responder
%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%*/
function lm_ajaxResponse(){
	global $wpdb, $lm_maps_options;
	$table_name = $lm_maps_options['table']	;
	$action = $_POST['lm_action'];

	switch($action){
		case "returnNearest":
			//Send back nearest location json.
			$lat = $_POST['lat'];
			$lng = $_POST['lng'];
			findNearestStore($lat,$lng);
			break;
			
		case "updatelatlng":
			$post_id = $_POST['post_id'];
			$lat = $_POST['lat'];
			$lng = $_POST['lng'];
			$updateSQL = "Update $table_name set lat='$lat', lng='$lng' where post_id = $post_id";
			$update = $wpdb->query($updateSQL);
			break;
			
		case "returnLocations":
			print getLocationJson();
			break;
			
		case "returnSearchTool":
			print returnSearchTool();
			break;
			
		default:
			break;
		
	}
	die();
}
add_action('wp_ajax_lm_ajaxResponse', 'lm_ajaxResponse');
add_action('wp_ajax_nopriv_lm_ajaxResponse', 'lm_ajaxResponse');

function orderByDistance($a, $b){
    if ($a['distance'] == $b['distance']) {
        return 0;
    }
    return ($a['distance'] < $b['distance']) ? -1 : 1;
}

function stripTags($astr){
	while(stristr($astr,"<")){
		$part2Remove = substr( $astr, strpos($astr,"<"), strpos($astr,">")+1 );
		$astr = str_replace($part2Remove,"",$astr);
	}
	return $astr;
}


function findNearestStore($lat,$lng){
	$stores = array();
	$storeInfo = json_decode(getLocationJson());
	foreach($storeInfo as $key=>$store){
		$i['post_id'] = $store->post_id;
		$i['store_id'] = $store->store_id;
		$i['title'] = $store->title;
		$i['address'] = $store->address;
		$i['city'] = $store->city;
		$i['state'] = $store->state;
		$i['zip'] = $store->zip;
		$i['phone'] = $store->phone;
		$i['email'] = $store->email;
		$i['hours'] = $store->hours;
		$i['lat'] = $store->lat;
		$i['lng'] = $store->lng;
		$i['status'] = $store->status;
		$i['distance'] = lm_Distance($lat,$lng, $store->lat, $store->lng);
		array_push($stores,$i); 
	}
	usort($stores, "orderByDistance");
	print json_encode($stores);
}

function object_2_array($result){
    $array = array();
    foreach ($result as $key=>$value)
    {
        if (is_object($value))
        {
            $array[$key]=object_2_array($value);
        }
        if (is_array($value))
        {
            $array[$key]=object_2_array($value);
        }
        else
        {
            $array[$key]=$value;
        }
    }
    return $array;
}  

function lm_Distance($LatA,$LngA, $LatB, $LngB, $metric="mi"){
	$newdistance = 0;
	$distance='';
	$distance1 = sin(deg2rad($LatA)) 
				* sin(deg2rad($LatB)) 
				+ cos(deg2rad($LatA)) 
				* cos(deg2rad($LatB)) 
				* cos(deg2rad($LngA-$LngB)); 
	$distance = (rad2deg(acos($distance1))) * 69.09;
	switch($metric) {
		case "km":
			$newdistance = round($distance * 1.6093,2); 
			break;
		case "mi":
		default:
			$newdistance = round($distance,2);
			break;
	}
	if($newdistance>=10) $newdistance = round($newdistance);
	return $newdistance; 
}


function returnSearchTool(){
	global $lm_maps,$wpdb;
	
	$LookUp = "";
	$searchBar = "";
	$searchBar .= "<div id='map_search_container' data-uri='".get_bloginfo('url')."' class='left'>";
	$searchBar .= "<form name='searchZip' id='searchZip' class='nice' method='post' action='#' onsubmit='Geocode(); return false;' >";
	$searchBar .= "<span class='hide-now label'>Search</span>"; 
  $searchBar .= "<div class='input-text left'><input type='text' name='search_addy' placeholder='Enter your zipcode' class='input-text left' id='search_addy' value='$LookUp'><div class='help show-ie'><p>Enter Your Zip Code</p></div></div> <input type='button' name='submit' id='submit' class='left simple button small blue radius nice' value='GO' onclick='Geocode();' >";
	$searchBar .= "<input type='hidden' name='lm_action' value='lookupzip'></form></div>";
	return $searchBar;
}

function lm_full_update(){
	global $lm_maps, $lm_maps_options, $wpdb;
	$table_name = $lm_maps_options['table']; 
	$sql = "Drop table if exists $table_name";
	$wpdb->query($sql);
}

function getLocationJson(){
	global $lm_maps, $lm_maps_options, $wpdb;
	$newContent ="";
	$locations = array();
	$json = "";
	
    $table_name = $lm_maps_options['table'];
	$sql = "Select * from $table_name where status<>'private'";
	$locations = $wpdb->get_results($sql);
	
	if(sizeof($locations)>0) $json = json_encode($locations);
	return  $json;
}

function get_lm_map_markup(){
	global $lm_maps;
	$map = "<div id='map_canvas' style='height:".$lm_maps->getMapHeight()."px; width:".$lm_maps->getMapWidth()."px;'></div>";
	return $map;
}

//add_action('plugins_loaded', 'UpdatePluginDB');
function UpdatePluginDB(){
	global $lm_maps, $lm_maps_options, $wpdb;
	$locations = array();
	$info = array();
	$table_name = $lm_maps_options['table'];	
	$the_query = new WP_Query('post_type=location&posts_per_page=-1');	
	//Get current location posts
	$locationPosts = $the_query->posts;
	//Get Stored location posts
	
	$sql = "Select * from $table_name";
	$result = $wpdb->query($sql);
	if(sizeof($locationPosts)-1 != $result){
		foreach($locationPosts as $key=>$post){
			$info['post_id'] = $post->ID;
			$info['post_status'] = $post->post_status;
			$post->post_title = stripTags(str_replace("'","",$post->post_title));
			$post->post_title = str_replace('"',"",$post->post_title);
			$info['title'] = $post->post_title;
			
			$locationArray = get_group("location", $post->ID);
			if(sizeof($locationArray)>0){
				$location = $locationArray[1];
				$info['store_id'] = @$location["location_store_number"][1];
				$info['address'] = @$location["location_address"][1];
				$info['suite'] = @$location["location_suite_number"][1];
				$info['city'] = @$location["location_city"][1];
				$info['state'] = @$location["location_state"][1];
				$info['zip'] = @$location["location_zip_code"][1];
				$info['phone'] = @$location["location_phone"][1];
				$info['email'] = @$location["location_email"][1];
				$info['hours'] = @$location["location_store_hours"][1];
			}
	
			//Save this item to our new table.
			$insert = "";									
			$wpdb->flush();
			$data = array(
				'post_id'=>$info['post_id'],
				'store_id'=>$info['store_id'],
				'title'=>$info['title'],
				'address'=>$info['address'],
				'suite'=>$info['suite'],
				'city'=>$info['city'],
				'state'=>$info['state'],
				'zip'=>$info['zip'],
				'phone'=>$info['phone'],
				'email'=>$info['email'],
				'hours'=>$info['hours'],
				'lat'=>$info['lat'],
				'lng'=>$info['lng'],
				'status'=>$info['post_status']
			);
			$ret = $wpdb->insert($table_name,$data);
			
			
		}
	}
	return sizeof($locationArray);
}

/*%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// Location Marker Maps plugin Class
%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%*/
class lm_maps{ 
	var $width = "600";
	var $height = "450"; 
	var $tablename = "";
	
	function lm_maps(){
		global $wpdb, $lm_maps_options;
		$tablename = $lm_maps_options['table'];
	}
	
	function get_tablename(){
		return $this->tablename;
	}
	
	function getMapWidth(){
		return $this->width;
	}

	function getMapHeight(){
		return $this->height;
	}
	
}
$lm_maps = new lm_maps();
?>