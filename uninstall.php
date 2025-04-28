<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package WhatsWP
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options from database
delete_option('whatswp_enabled');
delete_option('whatswp_location');
delete_option('whatswp_size');
delete_option('whatswp_phone');
delete_option('whatswp_message');
?>
