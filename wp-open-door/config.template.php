<?php
/**
 *  Opening Door Configuration
 *
 *   RENAME this file to config.php and add your own secret key
 *
 *  TODO: Write admin settings page for these ... that's why everything has a function
 */

class Config_Open_Door {
  /** Constructor for the Settings_Features class */
  public function __construct() {
  }

  /// The shared secret between automation board and Wordpress server
  /// (in case of missing https support)
  public function get_open_door_key() {
    return "0123456789ABCDEF - Put our own key HERE";
  }

  /// The maximum interval in minutes that the automation board may NOT POST data
  ///  before switching to "unknown" state.
  public function get_open_door_timeout() {
    return 10; //change from 10 minutes, to something apropriate for your application
  }
}

?>
