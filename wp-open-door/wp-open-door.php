<?php
/**
 * Plugin Name: Open Door
 * Plugin URI: http://verschwoerhaus.de/open-door/
 * Description: This plugin shows whether your door is currently locked or unlocked through the Opening Hours plugin
 * Version: 0.5.0
 * Author: Verschwoerhaus
 * Author URI: https://verschwoerhaus.de
 * License: GPL2
 */

define("OPEN_DOOR_OPTIONS_NAME", "open_door_options");
define("OPEN_DOOR_DOOR_STATE", "door_state");
define("OPEN_DOOR_DOOR_STATE_TIMESTAMP", "door_state_timestamp");
define("OPEN_DOOR_DEBUG_LOG", "debug_log");
define("OPEN_DOOR_DEMO_DOOR_STATE", "demo_door_state");
define('OPEN_DOOR_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once(OPEN_DOOR_PLUGIN_DIR . 'config.php');

function get_door_last_seen_minutes() {
  unset($options);
  $options= get_option(OPEN_DOOR_OPTIONS_NAME);
  $last_update= $options[OPEN_DOOR_DOOR_STATE_TIMESTAMP];
  if ($last_update == NULL) return 9999999999999999.0;
  $seconds_since_last_checkin = current_time('timestamp', 1) - $last_update;
  $minutes_since_last_checkin = $seconds_since_last_checkin/60;
  return $minutes_since_last_checkin;
}

function get_door_state() {
  unset($options);
  $options = get_option(OPEN_DOOR_OPTIONS_NAME);
  $state = $options[OPEN_DOOR_DOOR_STATE];
  $minutes = get_door_last_seen_minutes();
  $config = new Config_Open_Door();
  if ($minutes >= $config->get_open_door_timeout()) {
    return 'unknown';
  }
  return $state;
}

function get_door_state_bool() {
  if (get_door_state() == "open") return true;
  else if (get_door_state() == "closed") return false;
  else return false;
}

function set_door_state($newstate) {
  unset($options);
  $options= get_option(OPEN_DOOR_OPTIONS_NAME);
  $oldstate = $options[OPEN_DOOR_DOOR_STATE];
  $options[OPEN_DOOR_DOOR_STATE] = $newstate;
  $options[OPEN_DOOR_DOOR_STATE_TIMESTAMP] = current_time('timestamp', 1);
  update_option(OPEN_DOOR_OPTIONS_NAME, $options);
  return $oldstate;
}

function open_door_shortcode() {
  ob_start();
  change_door_state();
  return ob_get_clean();
}

function open_door_activated() {
  unset($options);
  $options = get_option(OPEN_DOOR_OPTIONS_NAME);
  if (false == $options || $options == "") {
    $options = array(OPEN_DOOR_DOOR_STATE => 'closed', OPEN_DOOR_DEMO_DOOR_STATE => "testing");
    update_option(OPEN_DOOR_OPTIONS_NAME, $options);
  }
}

function open_door_deactivated() {
  delete_option(OPEN_DOOR_OPTIONS_NAME);
}

register_activation_hook(__FILE__, "open_door_activated");
register_deactivation_hook(__FILE__, "open_door_deactivated");

add_shortcode('open_door_endpoint', 'open_door_shortcode');

add_action('rest_api_init', function () {
  register_rest_route('open-door', '/state', array(
    array(
      'methods' => 'GET',
      'callback' => 'jsonGetDoorState',
    ),
    array(
      'methods' => 'PUT,POST,PATCH',
      'callback' => 'jsonSetDoorState',
    )
  ));
  register_rest_route('open-door', '/log', array(
    array(
      'methods' => 'GET',
      'callback' => 'jsonGetLog',
    )
  ));
});


function get_user_ip() {
  if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    // check ip from share internet
    $ip = $_SERVER['HTTP_CLIENT_IP'];
  } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    // to check ip is pass from proxy
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
  } else {
    $ip = $_SERVER['REMOTE_ADDR'];
  }
  return apply_filters('wpb_get_ip', $ip);
}

function format_log_entry($entry) {
  return date('Y-m-d H:i:s') . " [" . get_user_ip() . "] : " . $entry."\n";
}

function write_log($entry) {
  $opendoorlog = plugin_dir_path(__FILE__ )."log.txt";
  $logfile = fopen($opendoorlog, "a");
  fwrite($logfile, format_log_entry($entry));
  fclose($logfile);
}

function jsonGetLog($request) {
  $options = get_option(OPEN_DOOR_OPTIONS_NAME);
  $log = $options[OPEN_DOOR_DEBUG_LOG];
  $container = array("open-door" => array(
    "log" => $log
  ));
  return $container;
}

function jsonGetDoorState($request) {
  $state = get_door_state();
  $config = new Config_Open_Door();
  $container = array("open-door" => array(
    "state" => $state,
    "timeout" => $config->get_open_door_timeout(),
    "lastseen" => get_door_last_seen_minutes()
  ));
  return $container;
}

function make_error($error) {
  write_log("Error: " . $error);
  return array('error' => $error);
}

function getAuthorizationHeader(){
  $headers = null;
  if (isset($_SERVER['Authorization'])) {
    $headers = trim($_SERVER["Authorization"]);
  } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
  }
  return $headers;
}

function getBearerToken() {
  $headers = getAuthorizationHeader();
  if (empty($headers)) {
    return null;
  }
  if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
    return $matches[1];
  }
  return null;
}

function jsonSetDoorState($request) {
  $parameters = $request->get_json_params();
  $values = $parameters['open-door'];
  if ($values == NULL) return make_error('Unknown request');

  $config = new Config_Open_Door();

  $key = getBearerToken();
  if (empty($key)) {
    $key = $values['key'];
  }
  if ($key == NULL) return make_error('Forbidden without key');
  if ($key != $config->get_open_door_key()) return make_error('Invalid key');

  $state = $values['state'];
  if ($state == NULL) return make_error('Bad request');

  // Now really do the update
  write_log("Set Door State: " . $state);
  $oldstate = set_door_state($state);

  $container = array("open-door" => array(
    "state" => get_door_state(),
    "oldstate" => $oldstate,
    "timeout" => $config->get_open_door_timeout(),
    "lastseen" => get_door_last_seen_minutes(),
    "debug" => $values,
  ));
  return $container;
}


// Options
if (is_admin()) { // admin actions
  add_action('admin_menu', 'open_door_plugin_menu');
  add_action('admin_init', 'register_open_door_settings');
}

function register_open_door_settings() { // whitelist options
  register_setting('open_door_settings', 'open_door_settings', 'open_door_settings_validate' );
  add_settings_section('open_door_main', 'Open Door Settings', 'open_door_main_section_text', 'open_door');
  add_settings_field('open_door_preshared_key', 'Preshared Secret Key', 'open_door_settings_key', 'open_door', 'open_door_main');
}

function open_door_main_section_text() {
  echo '<p>Important settings</p>';
}

// FIXME: text_string?!
function open_door_settings_key() {
  $options = get_option('open_door_settings');
  echo "<input id='open_door_preshared_key' name='plugin_options[text_string]' size='40' type='text' value='{$options['text_string']}' />";
}

function open_door_settings_validate($input) {
  $newinput['text_string'] = trim($input['text_string']);
  if (!preg_match('/^[a-z0-9]{32}$/i', $newinput['text_string'])) {
    $newinput['text_string'] = '';
  }
  return $newinput;
}

function open_door_plugin_menu() {
  add_options_page('Open Door Settings', 'Open Door', 'manage_options', 'open_door', 'open_door_plugin_options');
}

function open_door_plugin_options() {
  if (!current_user_can('manage_options'))  {
    wp_die(__('You do not have sufficient permissions to access this page.'));
  }
  echo '<div class="wrap">';
  echo '<h1>Open Door Settings</h1>';
  echo '<form method="post" action="options.php">';
  settings_fields('open_door_settings');
  do_settings_sections('open_door');
  submit_button();
  echo '</form>';
  echo '<h2>Log</h2>';
  echo '<textarea name="message" rows="25" cols="120">';

  $opendoorlog = plugin_dir_path(__FILE__) . "log.txt";
  if (file_exists($opendoorlog)) {
    $logfile = fopen($opendoorlog, "r");
    if ($logfile) {
      for($i=0; ($line = fgets($logfile)) !== false; $i++) {
        echo "[" . str_pad($i, 7, ' ', STR_PAD_LEFT) . "]:\t" . $line;
         }
      fclose($logfile);
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
