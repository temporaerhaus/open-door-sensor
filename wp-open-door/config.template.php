<?
/**
 *	Opening Door Configuration
 *
 * 	RENAME this file to config.php and add your own secret key
 *
 *  TODO: Write admin settings page for these ... that's why everything has a function
 */
 
 /// The shared secret between automation board and Wordpress server 
 /// (in case of missing https support)
 $key="Some HEX encoded key shared with NodeMCU";
 
 /// The maximum interval in minutes that the automation board may NOT POST data
 ///  before switching to "unknown" state.
 $timeout = 10
 
 function get_open_door_key() { return $key; } 

 function get_open_door_timeout() { return $timeout; }
 
?>
