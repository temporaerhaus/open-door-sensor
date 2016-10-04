<?php

// allow to use `is_plugin_active` function
include_once(ABSPATH.'wp-admin/includes/plugin.php');

/**
 * Get the Space Open/Close state from external sources
 *
 * For now, only get state from the Wordpress Opening Hours plugin
 *
 * @since 0.3
 */
class Space_State
{
    /**
     *  Retrive the opening state of the hackerspace
     *
     * @retun boll The open/close state
     */
    public function is_open()
    {
        if (is_plugin_active('wp-opening-hours/wp-opening-hours.php')) {
            return $this->get_opening_hours_state();
        }
    }

    /**
     * Get the open/close status from the Opening Hours plugin
     *
     * @return bool $is_open
     */
    private function get_opening_hours_state()
    {
        if (function_exists('is_open')){ // plugin version 1.2
            return is_open();
        }
        else {
            return null;
        }
    }
}
