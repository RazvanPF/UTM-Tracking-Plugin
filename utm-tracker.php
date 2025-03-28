<?php
/*
Plugin Name: UTM Tracker
Plugin URI: https://yourwebsite.com/
Description: UTM tracking solution that stores UTM parameters, dynamically appends them to links, replaces email addresses based on UTM presence, removes UTM parameters from URLs for cleaner links, and includes caching compatibility options. Perfect for campaign tracking, lead attribution, and optimizing marketing performance.
Version: 1.4
Author: WEB RUNNER
*/

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Start session if not already started
function utm_tracker_start_session() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}
add_action('init', 'utm_tracker_start_session', 1);

// Store UTM parameters in session and optionally hide them
function utm_tracker_store_utm_params() {
    if (!session_id()) {
        session_start();
    }

    $hide_utm = get_option('utm_tracker_hide_utm', 'off'); // Check if hiding UTM parameters is enabled
    $utm_params = get_option('utm_tracker_params', 'utm_campaign,utm_source,utm_medium,utm_content,utm_term');
    $utm_params_array = array_map('trim', explode(',', $utm_params));

    if (!isset($_SESSION['utm_tracker'])) {
        $_SESSION['utm_tracker'] = [];
    }

    $has_utm = false;
    foreach ($utm_params_array as $param) {
        if (isset($_GET[$param])) {
            $_SESSION['utm_tracker'][$param] = sanitize_text_field($_GET[$param]);
            $has_utm = true;
        }
    }

    // If "Hide UTM Parameters" is enabled and we found UTM params, remove them from the URL
    if ($hide_utm === 'on' && $has_utm) {
        $clean_url = strtok($_SERVER["REQUEST_URI"], '?'); // Remove query parameters
        wp_redirect($clean_url);
        exit;
    }
}
add_action('init', 'utm_tracker_store_utm_params', 1);

// Retrieve stored UTM parameters
function utm_tracker_get_session_utms() {
    return isset($_SESSION['utm_tracker']) ? $_SESSION['utm_tracker'] : [];
}

// Retrieve allowed hosts from settings
function utm_tracker_get_allowed_hosts() {
    $hosts = get_option('utm_tracker_hosts', []);
    return is_array($hosts) ? array_map('trim', $hosts) : [];
}

// Modify external and internal links early in WordPress execution
function utm_tracker_modify_links() {
    ob_start(function ($buffer) {
        $utms = utm_tracker_get_session_utms();
        if (empty($utms)) return $buffer;

        $hosts = utm_tracker_get_allowed_hosts();
        if (empty($hosts)) return $buffer; // ðŸš€ **If no hosts are set, DO NOT modify links**

        preg_match_all('/<a[^>]*href=["\']([^"\']+)["\']/i', $buffer, $matches);
        
        foreach ($matches[1] as $match) {
            $parsed_url = parse_url($match);
            if (!isset($parsed_url['host'])) continue;

            $is_allowed = false;
            foreach ($hosts as $host) {
                if (strpos($parsed_url['host'], $host) !== false) {
                    $is_allowed = true;
                    break;
                }
            }

            // Apply UTM parameters only if the host is in the allowed list
            if ($is_allowed) {
                $utm_query = http_build_query($utms);
                $new_url = $match . (strpos($match, '?') !== false ? '&' : '?') . $utm_query;

                // Ensure replacement affects correct parts of HTML
                $buffer = preg_replace('/(<a[^>]*href=["\'])' . preg_quote($match, '/') . '(["\'])/i', '$1' . $new_url . '$2', $buffer);
            }
        }
        return $buffer;
    });
}
add_action('template_redirect', 'utm_tracker_modify_links', 1);

// Enqueue JavaScript with Debug and Email Replacement Rules
function utm_tracker_enqueue_script() {
    $utm_params = get_option('utm_tracker_params', 'utm_campaign,utm_source,utm_medium,utm_content,utm_term');
    $utm_params_array = array_map('trim', explode(',', $utm_params));
    $allowed_hosts = get_option('utm_tracker_hosts', []);
    $debug_logs = get_option('utm_tracker_debug_logs', 'off'); // Fetch debug log setting
    $email_replacements = get_option('utm_tracker_email_replacements', []);

    // ðŸ”¥ Ensure email replacements are re-indexed before passing to JS
    if (!empty($email_replacements) && is_array($email_replacements)) {
        $email_replacements = array_values($email_replacements); // Remove unique keys
    }

    // Debugging: Log to see if email replacements are now an indexed array
    error_log("Final Email Replacements Sent to JS: " . print_r($email_replacements, true));

    wp_enqueue_script('utm-tracker-script', plugin_dir_url(__FILE__) . 'utm-tracker.js', [], '2.4', true);
    
    wp_localize_script('utm-tracker-script', 'utmTrackerData', [
        'params' => $utm_params_array,
        'session_utms' => utm_tracker_get_session_utms(),
        'allowed_hosts' => $allowed_hosts,
        'debug' => ($debug_logs === 'on') ? true : false, // Convert to boolean
        'email_replacements' => $email_replacements // Pass corrected email replacements
    ]);
}
add_action('wp_enqueue_scripts', 'utm_tracker_enqueue_script');

// Add settings link on the plugin page
function utm_tracker_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=utm-tracker">Settings</a>';
    array_push($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'utm_tracker_settings_link');

// Include settings page
if (!function_exists('utm_tracker_options_page')) {
    include_once plugin_dir_path(__FILE__) . 'utm-tracker-settings.php';
}

function utm_tracker_disable_cache() {
    $bypass_cache = get_option('utm_tracker_bypass_cache', 'off');

// Only disable cache if the setting is ON and UTM parameters exist
    if ($bypass_cache === 'on' && (!empty($_GET['utm_campaign']) || !empty($_GET['utm_source']) || !empty($_GET['utm_medium']) || !empty($_GET['utm_content']) || !empty($_GET['utm_term']))) {
        define('DONOTCACHEPAGE', true);
        define('DONOTCACHEOBJECT', true);
        define('DONOTCACHEDB', true);
        header("Cache-Control: no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");
    }
}
add_action('template_redirect', 'utm_tracker_disable_cache');

// Detect changes and set transient to show admin notice
function utm_tracker_detect_changes($option, $old_value, $new_value) {
    if ($old_value !== $new_value) {
        set_transient('utm_tracker_changes_made', true, 30); // Show message for 30s
    }
}

// Attach change detection to relevant settings
add_action('updated_option', function($option, $old_value, $new_value) {
    if (strpos($option, 'utm_tracker_') === 0) { // Only track our plugin's settings
        utm_tracker_detect_changes($option, $old_value, $new_value);
    }
}, 10, 3);