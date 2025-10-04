<?php
/**
 * Uninstall script for BetterFeed
 * 
 * This file is executed when the plugin is deleted from the WordPress admin.
 *
 * @package BetterFeed
 * @since 1.0.0
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load plugin constants
if (!defined('BF_PLUGIN_DIR')) {
    define('BF_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

// Load uninstaller class
require_once BF_PLUGIN_DIR . 'includes/class-bf-uninstaller.php';

// Run uninstall process
BF_Uninstaller::uninstall();