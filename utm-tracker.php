<?php
/*
Plugin Name: UTM Tracker
Description: Tracks UTM parameters, stores them in local storage, appends them to links, and populates form fields.
Version: 1.2
Author: Razvan Faraon
*/

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Include settings page
include_once plugin_dir_path(__FILE__) . 'utm-tracker-settings.php';

// Enqueue JavaScript on the frontend
function utm_tracker_enqueue_script() {
    $utm_params = get_option('utm_tracker_params', 'utm_campaign,utm_source,utm_medium,utm_content,utm_term');
    $utm_params_array = array_map('trim', explode(',', $utm_params)); // Ensure it is an array

    wp_enqueue_script('utm-tracker-script', plugin_dir_url(__FILE__) . 'utm-tracker.js', [], '1.2', true);
    wp_localize_script('utm-tracker-script', 'utmTrackerParams', $utm_params_array);
}
add_action('wp_enqueue_scripts', 'utm_tracker_enqueue_script');

// Add settings link on the plugin page
function utm_tracker_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=utm-tracker">Settings</a>';
    array_push($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'utm_tracker_settings_link');
