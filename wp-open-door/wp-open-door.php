<?
/**
 * Plugin Name: Open Door
 * Plugin URI: http://weinhof9.de/open-door/
 * Description: This plugin shows whether your door is currently locked or unlocked through the Opening Hours plugin
 * Version: 0.4.2
 * Author: Weinhof9
 * Author URI: http://weinhof9
 * License: GPL2
 */
 
define("open_door_options_name", "open_door_options");
define("open_door_door_state", "door_state");
define("open_door_door_state_timestamp", "door_state_timestamp");
define("open_door_debug_log", "debug_log");
define("open_door_demo_door_state", "demo_door_state");
define('OPEN_DOOR_PLUGIN_DIR', plugin_dir_path(__FILE__));



require_once(OPEN_DOOR_PLUGIN_DIR.'config.php');


function html_form_code() {
	unset( $options );
	$options= get_option( open_door_options_name );
	$state= $options[open_door_demo_door_state];

	echo '<p>Demo door is currently '.$state.' !</p>';

	echo '<form action="' . esc_url( $_SERVER['REQUEST_URI'] ) . '" method="post">';
	echo '<p>';
	echo 'New Door State: ';
	echo '<input type="text" name="do-state" pattern="[a-zA-Z0-9 ]+" value="' . ( isset( $_POST["do-state"] ) ? esc_attr( $_POST["do-state"] ) : '' ) . '" size="40" />';
	echo '</p>';
	echo '<p><input type="submit" name="do-submitted" value="Set Door State"></p>';
	wp_nonce_field( 'open_demo_door', 'demo_door_nonce_field' );
	echo '</form>';

	$nonce_open = wp_create_nonce( 'open' );
	$nonce_closed = wp_create_nonce( 'closed' );
	$value_open=endoodle("open", $nonce_open);
	$value_closed=endoodle("closed", $nonce_closed);
	echo '<h6> Special Version for Communication with NodeMCU (PHP '.phpversion().')</h6>';
	echo '<form action="' . esc_url( $_SERVER['REQUEST_URI'] ) . '" method="post">';
	
	echo '<input type="hidden" name="door-state-open" value="'.$value_open.'" size="40"/><br>';
	echo '<input type="hidden" name="door-state-closed" value="'.$value_closed.'" size="40"/><br>';

	echo '<button name="nonce_open" value="'.$nonce_open.'" type="submit"> open </button> ';
	echo '<button name="nonce_closed" value="'.$nonce_closed.'" type="submit"> close </button> ';

	echo '</form>';

	//foreach (hash_algos() as $v) {
	//	echo $v.'<br>';
	//} 
}

function get_door_last_seen_minutes()
{
	unset($options);
	$options= get_option( open_door_options_name );
	$last_update= $options[open_door_door_state_timestamp];
	if( $last_update == NULL ) return 9999999999999999.0;
	$seconds_since_last_checkin = current_time( 'timestamp' , 1) - $last_update;
	$minutes_since_last_checkin = $seconds_since_last_checkin/60;
	return $minutes_since_last_checkin;
}

function get_door_state()
{
	unset($options);
	$options= get_option( open_door_options_name );	
	$state= $options[open_door_door_state];
	$minutes = get_door_last_seen_minutes();
	if( $minutes >= 10.0 )
		return 'unknown';
	return $state;
}

function get_door_state_bool()
{
	if(get_door_state()=="open") return true;
	else if(get_door_state()=="closed") return false;
	else return false;
}

function set_door_state($newstate)
{
	unset($options);
	$options= get_option( open_door_options_name );
	$oldstate = $options[open_door_door_state];
	$options[open_door_door_state] = $newstate;
	$options[open_door_door_state_timestamp] = current_time( 'timestamp' , 1);
	update_option( open_door_options_name, $options);
	return $oldstate ;
}

function open_door_shortcode() {
	ob_start();
	change_door_state();
	return ob_get_clean();
}

function open_door_activated()
{
	unset( $options );
	$options = get_option( open_door_options_name );
	if( false == $options or $options == "" ) {
		$options = array(open_door_door_state => 'closed', open_door_demo_door_state => "testing");
		update_option( open_door_options_name, $options);
	}
}
function open_door_deactivated()
{
    delete_option(open_door_options_name);
}

register_activation_hook( __FILE__, "open_door_activated");
register_deactivation_hook( __FILE__, "open_door_deactivated");

add_shortcode( 'open_door_endpoint', 'open_door_shortcode' );
add_shortcode( 'open_door_test_form', 'open_door_form_shortcode' );



add_action( 'rest_api_init', function () {
 register_rest_route( 'open-door', '/state', array(
	array(
	        'methods' => 'GET',
	        'callback' => 'jsonGetDoorState',
	),
	array(
		'methods' => 'PUT,POST,PATCH',
        	'callback' => 'jsonSetDoorState',		
	),
    ));
 register_rest_route( 'open-door', '/log', array(
	array(
	        'methods' => 'GET',
	        'callback' => 'jsonGetLog',
	),
    ));
});


function get_nonce() {
	for($i=0; $i<3; $i++){
		$bytes = openssl_random_pseudo_bytes(32, $cstrong);
		if($cstrong == true) break;
	}
	return base64_encode($bytes);	
}

function get_user_ip() {
if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
//check ip from share internet
$ip = $_SERVER['HTTP_CLIENT_IP'];
} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
//to check ip is pass from proxy
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
} else {
$ip = $_SERVER['REMOTE_ADDR'];
}
return apply_filters( 'wpb_get_ip', $ip );
}

function format_log_entry($entry){
	return date('Y-m-d H:i:s')." [".get_user_ip()."] : ".$entry."\n";
}

function write_log($entry){	
	$opendoorlog = plugin_dir_path( __FILE__ )."log.txt";
	$logfile = fopen($opendoorlog, "a");
	fwrite($logfile, format_log_entry($entry));
	fclose($logfile );
}

function jsonGetLog( $request ) {	
	$options= get_option( open_door_options_name );
	$log = $options[open_door_debug_log];
	$container = array( "open-door" => array(
    		"log" => $log,
	), );
	return $container;
}

function jsonGetDoorState( $request ) {	
	$state = get_door_state();
	write_log("Get Door State: ".$state);
	if( $_SESSION['open-door-nonce'] == NULL ) {
		$_SESSION['open-door-nonce'] = get_nonce();
	}
	$config = new Config_Open_Door();
	$container = array( "open-door" => array(
    		"state" => $state,
		"nonce" => $_SESSION['open-door-nonce'],	
		"timeout" => $config->get_open_door_timeout(),
		"lastseen" => get_door_last_seen_minutes(),	
	),	);
	return $container;
}

function make_error($error) {
	write_log("Error: ".$error);
	return array( 'error'=>$error);
}

function jsonSetDoorState( $request ) {		
	$parameters = $request->get_json_params();	
	$values = $parameters['open-door'];
	if($values == NULL) return make_error( 'Unknown request');
	
	$nonce = $values['nonce'];
	if($nonce == NULL) return make_error( 'Forbidden without nonce');
	if($nonce != $_SESSION['open-door-nonce']) return make_error( 'Invalid nonce');
	$_SESSION['open-door-nonce'] = NULL;
	$state = $values['state'];
	if($state == NULL) return make_error( 'Bad request' );
	$signature = $values['signature'];
	if($signature == NULL) return make_error( 'Unsigned content');
	
	$config = new Config_Open_Door();
	$key=$config->get_open_door_key();
	$raw = $nonce.$state.$key;
	$mysign = hash( "sha256" , $raw, false);
	if($signature != $mysign) return make_error( 'Integrity check failed!' ); 
	
	// Now really do the update
	write_log("Set Door State: ".$state);
	$oldstate= set_door_state($state);
	$config = new Config_Open_Door();

	$container = array( "open-door" => array(
		"state" => get_door_state(),
		"oldstate" => $oldstate,
		"timeout" => $config->get_open_door_timeout(),
		"lastseen" => get_door_last_seen_minutes(),	
		"debug" => $values,
	),	);
	return $container;
}




add_action('init', 'myStartSession', 1);
add_action('wp_logout', 'myEndSession');
add_action('wp_login', 'myEndSession');

function myStartSession() {
    if(!session_id()) {
        session_start();
    }
}

function myEndSession() {
    session_destroy ();
}

// Options
if ( is_admin() ){ // admin actions
  add_action( 'admin_menu', 'open_door_plugin_menu' );
  add_action( 'admin_init', 'register_open_door_settings' );
}

function register_open_door_settings() { // whitelist options
	register_setting( 'open_door_settings', 'open_door_settings', 'open_door_settings_validate' );
	add_settings_section('open_door_main', 'Open Door Settings', 'open_door_main_section_text', 'open_door');
	add_settings_field('open_door_preshared_key', 'Preshared Secret Key', 'open_door_settings_key', 'open_door', 'open_door_main');
}

function open_door_main_section_text() {
	echo '<p>Important settings</p>';
}

function open_door_settings_key() {
	$options = get_option('open_door_settings');
	echo "<input id='open_door_preshared_key' name='plugin_options[text_string]' size='40' type='text' value='{$options['text_string']}' />";
} 

function open_door_settings_validate($input) {
	$newinput['text_string'] = trim($input['text_string']);
	if(!preg_match('/^[a-z0-9]{32}$/i', $newinput['text_string'])) {
		$newinput['text_string'] = '';
	}
	return $newinput;
}

function open_door_plugin_menu() {
	add_options_page( 'Open Door Settings', 'Open Door', 'manage_options', 'open_door', 'open_door_plugin_options' );
}

function open_door_plugin_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	echo '<div class="wrap">';
	echo '<h1>Open Door Settings</h1>';
	echo '<form method="post" action="options.php">';
	settings_fields( 'open_door_settings' );
	do_settings_sections( 'open_door' );
	echo '<?php submit_button(); ?>';
	echo '</form>';
	echo '<h2>Log</h2>';
	echo '<textarea name="message" rows="25" cols="120">';

	$opendoorlog = plugin_dir_path( __FILE__ )."log.txt";
	if(file_exists($opendoorlog)){	
		$logfile = fopen($opendoorlog, "r");
		if($logfile){
			for($i=0; ($line = fgets($logfile)) !== false; $i++) {
				echo "[".str_pad($i, 7, ' ', STR_PAD_LEFT)."]:\t".$line;
   			}			
			fclose($logfile );
		} else {
			echo 'ERROR OPENING LOGFILE';
		}
	} else{
		echo ' NO LOGFILE ';
	}

	echo '</textarea>';
	echo '</div>';
}

?>
