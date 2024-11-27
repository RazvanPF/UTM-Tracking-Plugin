<?php

// Add settings page to the WordPress menu
function utm_tracker_add_admin_menu() {
    add_options_page('UTM Tracker Settings', 'UTM Tracker', 'manage_options', 'utm-tracker', 'utm_tracker_options_page');
}
add_action('admin_menu', 'utm_tracker_add_admin_menu');

// Register settings
function utm_tracker_settings_init() {
    register_setting('utmTracker', 'utm_tracker_params');

    add_settings_section(
        'utm_tracker_section',
        __('UTM Parameters to Track', 'utm-tracker'),
        null,
        'utmTracker'
    );

    add_settings_field(
        'utm_tracker_params_field',
        __('UTM Parameters (comma-separated)', 'utm-tracker'),
        'utm_tracker_params_field_render',
        'utmTracker',
        'utm_tracker_section'
    );
}
add_action('admin_init', 'utm_tracker_settings_init');

// Render input field for parameters
function utm_tracker_params_field_render() {
    $params = get_option('utm_tracker_params', 'utm_campaign,utm_source,utm_medium,utm_content,utm_term');
    echo "<input type='text' name='utm_tracker_params' value='" . esc_attr($params) . "' />";
}

// Display settings page content
function utm_tracker_options_page() {
    ?>
    <div class="utm-tracker-settings-wrapper">
        <form action="options.php" method="post">
            <?php
            settings_fields('utmTracker');
            do_settings_sections('utmTracker');
            submit_button();
            ?>
        </form>
    </div>
    <style>
        .utm-tracker-settings-wrapper {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .utm-tracker-settings-wrapper input[type="text"] {
            width: 100%;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ccc;
            font-size: 14px;
        }
    </style>
    <?php
}

