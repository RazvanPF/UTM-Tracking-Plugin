<?php

// Add UTM Tracker as a top-level menu with custom icon
function utm_tracker_add_top_level_menu() {
    add_menu_page(
        'UTM Tracker Settings',             // Page Title
        'UTM Tracker',                      // Menu Title
        'manage_options',                   // Capability
        'utm-tracker',                      // Menu Slug
        'utm_tracker_options_page',         // Callback Function
        plugins_url('utmlogo.png', __FILE__), // Icon Path (in plugin root)
        81                                  // Position (adjust as needed)
    );

    // Optional styling for the icon size
    add_action('admin_head', function () {
        echo '<style>
            #toplevel_page_utm-tracker .wp-menu-image img {
                width: 26px !important;
                height: 26px !important;
                margin-top: -5px !important;
            }
        </style>';
    });
}
add_action('admin_menu', 'utm_tracker_add_top_level_menu');

// Register settings
function utm_tracker_settings_init() {
    register_setting('utmTracker', 'utm_tracker_params');
    register_setting('utmTracker', 'utm_tracker_hosts', [
        'type' => 'array',
        'default' => [],
        'sanitize_callback' => 'utm_tracker_sanitize_hosts'
    ]);
    register_setting('utmTracker', 'utm_tracker_debug_logs'); // Register debug log setting
	register_setting('utmTracker', 'utm_tracker_email_replacements', [
		'type' => 'array',
		'default' => [],
		'sanitize_callback' => 'utm_tracker_sanitize_email_replacements'
	]);

	// Add Hide UTM toggle
	register_setting('utmTracker', 'utm_tracker_hide_utm'); // Register setting
	
	// Register Bypass Cache Toggle
	register_setting('utmTracker', 'utm_tracker_bypass_cache');

    add_settings_section(
        'utm_tracker_section',
        __('UTM Tracker Settings', 'utm-tracker'),
        null,
        'utmTracker'
    );

    add_settings_field(
        'utm_tracker_params_field',
        __('Parameters (comma-separated)', 'utm-tracker'),
        'utm_tracker_params_field_render',
        'utmTracker',
        'utm_tracker_section'
    );

    add_settings_field(
        'utm_tracker_hosts_field',
        __('Host Propagation List:', 'utm-tracker'),
        'utm_tracker_hosts_field_render',
        'utmTracker',
        'utm_tracker_section'
    );
	
    // Add Email Replacement Rules
    add_settings_field(
        'utm_tracker_email_replacements',
        __('Replace Emails:', 'utm-tracker'),
        'utm_tracker_email_replacements_render',
        'utmTracker',
        'utm_tracker_section'
    );
	
	// Add Hide UTM toggle to the settings page
	add_settings_field(
		'utm_tracker_hide_utm',
		__('Hide UTM Parameters:', 'utm-tracker'),
		'utm_tracker_hide_utm_render',
		'utmTracker',
		'utm_tracker_section'
	);
	
	// Add Debug Logs Toggle
    add_settings_field(
        'utm_tracker_debug_logs',
        __('Enable Debug Logs:', 'utm-tracker'),
        'utm_tracker_debug_logs_render',
        'utmTracker',
        'utm_tracker_section'
    );
	
	// Add Bypass Cache Toggle Field
	add_settings_field(
		'utm_tracker_bypass_cache',
		__('Bypass Cache', 'utm-tracker') . ' <span class="tooltip-icon">ℹ<span class="tooltip-text">Enable this setting if a caching plugin interferes with UTM functionality.</span></span>',
		'utm_tracker_bypass_cache_render',
		'utmTracker',
		'utm_tracker_section'
	);
}
add_action('admin_init', 'utm_tracker_settings_init');

//Register License
register_setting('utmTracker', 'utm_tracker_license_key');

// Sanitize host list input
function utm_tracker_sanitize_hosts($input) {
    if (!is_array($input)) {
        return [];
    }
    return array_filter(array_map('sanitize_text_field', $input));
}

// Sanitize email replacements input to ensure multiple rules are saved correctly
function utm_tracker_sanitize_email_replacements($input) {
    if (!is_array($input)) {
        error_log("❌ utm_tracker_sanitize_email_replacements received non-array data!");
        return [];
    }

    error_log("🔎 RAW EMAIL INPUT DATA: " . print_r($input, true));

    $sanitized = [];
    $errors = []; // Array to store error messages

    foreach ($input as $index => $rule) {
        if (!empty($rule['original']) && !empty($rule['replacement'])) {
            $original = trim($rule['original']);
            $replacement = trim($rule['replacement']);

            // **Allow only valid email-like strings**
            if (filter_var($original, FILTER_VALIDATE_EMAIL) || strpos($original, '@') !== false) {
                $original = sanitize_text_field($original);
            } else {
                $errors[] = "⚠️ Invalid email format skipped: <strong>$original</strong>"; // Store error
                continue;
            }

            if (filter_var($replacement, FILTER_VALIDATE_EMAIL) || strpos($replacement, '@') !== false) {
                $replacement = sanitize_text_field($replacement);
            } else {
                $errors[] = "⚠️ Invalid email format skipped: <strong>$replacement</strong>"; // Store error
                continue;
            }

            $sanitized[$index] = [
                'original' => $original,
                'replacement' => $replacement
            ];
        }
    }

    // Store errors in session so we can display them
    if (!empty($errors)) {
        $_SESSION['utm_tracker_email_errors'] = $errors;
    }

    error_log("✅ FINAL Sanitized Email Replacements: " . print_r($sanitized, true));
    return $sanitized;
}

// Render input field for parameters
function utm_tracker_params_field_render() {
    $params = get_option('utm_tracker_params', 'utm_campaign,utm_source,utm_medium,utm_content,utm_term');
    echo "<input type='text' name='utm_tracker_params' value='" . esc_attr($params) . "' />";
}

// Render input fields for hosts
function utm_tracker_hosts_field_render() {
    $hosts = get_option('utm_tracker_hosts', []);
    if (!is_array($hosts)) {
        $hosts = [];
    }
    echo '<div id="utm-tracker-hosts-container">';
    foreach ($hosts as $host) {
        echo '<div class="utm-tracker-host">
                <span class="protocol-label">https://</span>
                <input type="text" name="utm_tracker_hosts[]" value="' . esc_attr($host) . '" />
                <button type="button" class="remove-host">Remove</button>
              </div>';
    }
    echo '</div>';
    echo '<button type="button" id="add-host">+ Add Host</button>';
    echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                let addButton = document.getElementById("add-host");
                let container = document.getElementById("utm-tracker-hosts-container");
                if (!addButton || !container) return;
                
                addButton.addEventListener("click", function() {
                    let div = document.createElement("div");
                    div.classList.add("utm-tracker-host");
                    div.innerHTML = `<span class="protocol-label">https://</span>
                                     <input type="text" name="utm_tracker_hosts[]" /> 
                                     <button type="button" class="remove-host">Remove</button>`;
                    container.appendChild(div);
                });

                container.addEventListener("click", function(e) {
                    if (e.target.classList.contains("remove-host")) {
                        e.target.parentElement.remove();
                    }
                });
            });
          </script>';
}

// Render the hide utm toggle
function utm_tracker_hide_utm_render() {
    $hide_utm = get_option('utm_tracker_hide_utm', 'off'); // Default is off
    ?>
    <label class="switch">
        <input type="checkbox" name="utm_tracker_hide_utm" value="on" <?php checked($hide_utm, 'on'); ?>>
        <span class="slider round"></span>
    </label>
    <?php
}

// Render Bypass Cache Toggle with Tooltip
function utm_tracker_bypass_cache_render() {
    $bypass_cache = get_option('utm_tracker_bypass_cache', 'off'); // Default off
    ?>
    <label class="switch">
        <input type="checkbox" name="utm_tracker_bypass_cache" value="on" <?php checked($bypass_cache, 'on'); ?>>
        <span class="slider round"></span>
    </label>

    <style>
        .tooltip-icon {
            display: inline-block;
            cursor: pointer;
            font-weight: bold;
            color: #0073aa;
            font-size: 14px;
            position: relative;
            margin-left: 5px; 
        }

        .tooltip-text {
            display: none;
            position: absolute;
            background: #333;
            color: #fff;
            padding: 6px 10px;
            border-radius: 5px;
            font-size: 12px;
            max-width: 520px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            white-space: nowrap;
            left: 0;
            top: 22px;
        }

        .tooltip-icon:hover .tooltip-text {
            display: block;
        }
    </style>
    <?php
}

// Render Debug Logs Toggle
function utm_tracker_debug_logs_render() {
    $debug_logs = get_option('utm_tracker_debug_logs', 'off'); 
    ?>
    <label class="switch">
        <input type="checkbox" name="utm_tracker_debug_logs" value="on" <?php checked($debug_logs, 'on'); ?>>
        <span class="slider round"></span>
    </label>
    <style>
		/* Wrapper for the switch */
		.switch {
			position: relative;
			display: inline-block;
			width: 40px; 
			height: 22px; 
		}

		/* Hide Default Checkbox */
		.switch input {
			opacity: 0;
			width: 0;
			height: 0;
		}

		/* Toggle Background */
		.slider {
			position: absolute;
			cursor: pointer;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background-color: #ccc;
			transition: .4s;
			border-radius: 22px; 
		}

		/* Small Circle Inside Toggle */
		.slider:before {
			position: absolute;
			content: "";
			height: 16px; 
			width: 16px; 
			left: 3px; 
			bottom: 3px;
			background-color: white;
			transition: .4s;
			border-radius: 50%;
		}

		/* When Checkbox is Checked (Active State) */
		input:checked + .slider {
			background-color: #4CAF50;
		}

		/* Move the Small Circle Right */
		input:checked + .slider:before {
			transform: translateX(18px) !important;
		}

		/* Add a Glowing Effect When Active */
		input:checked + .slider {
			box-shadow: 0 0 8px rgba(76, 175, 80, 0.6);
		}

    </style>
    <?php
}

// Render Multiple Email Replacement Rules
function utm_tracker_email_replacements_render() {
    $email_replacements = get_option('utm_tracker_email_replacements', []);
    if (!is_array($email_replacements)) {
        $email_replacements = [];
    }

    echo '<div id="utm-tracker-emails-container">';
    foreach ($email_replacements as $index => $pair) {
        echo '<div class="utm-tracker-email">
                <input type="text" name="utm_tracker_email_replacements[' . esc_attr($index) . '][original]" value="' . esc_attr($pair['original']) . '" placeholder="Original Email" />
                <span class="to-label">To</span>
                <input type="text" name="utm_tracker_email_replacements[' . esc_attr($index) . '][replacement]" value="' . esc_attr($pair['replacement']) . '" placeholder="UTM Email" />
                <button type="button" class="remove-email">Remove</button>
              </div>';
    }
    echo '</div>';
    echo '<button type="button" id="add-email">+ Add Email Rule</button>';

    echo '<script>
            document.addEventListener("DOMContentLoaded", function () {
                let addEmailButton = document.getElementById("add-email");
                let emailContainer = document.getElementById("utm-tracker-emails-container");
                if (!addEmailButton || !emailContainer) return;

                addEmailButton.addEventListener("click", function () {
                    let index = Date.now(); // Generates a unique index for each new entry
                    let div = document.createElement("div");
                    div.classList.add("utm-tracker-email");

                    div.innerHTML = `<input type="text" name="utm_tracker_email_replacements[\${index}][original]" placeholder="Original Email" />
                                     <span class="to-label">To</span>
                                     <input type="text" name="utm_tracker_email_replacements[\${index}][replacement]" placeholder="UTM Email" />
                                     <button type="button" class="remove-email">Remove</button>`;

                    // Set real unique index (Fix: Now setting the correct name attributes)
                    div.innerHTML = div.innerHTML.replace(/\$\{index\}/g, index);

                    emailContainer.appendChild(div);
                });

                emailContainer.addEventListener("click", function (e) {
                    if (e.target.classList.contains("remove-email")) {
                        e.target.parentElement.remove();
                    }
                });
            });
          </script>';
}

// Display admin notice if settings were changed
function utm_tracker_admin_notice() {
    if (get_transient('utm_tracker_changes_made')) {
        echo '<div class="notice notice-warning is-dismissible">
                <p>⚠️ <strong>Changes saved successfully.</strong> If you are using a caching plugin, please clear your cache to apply the latest changes.</p>
              </div>';
        delete_transient('utm_tracker_changes_made'); // Remove flag after showing message
    }
}
add_action('admin_notices', 'utm_tracker_admin_notice');

// Hide all WP/plugin notices EXCEPT our custom notices on the UTM Tracker settings page
add_action('admin_head', function () {
    if (isset($_GET['page']) && $_GET['page'] === 'utm-tracker') {
        global $wp_filter;

        // Backup existing admin_notices hooks
        $saved_admin_notices = $wp_filter['admin_notices'] ?? [];

        // Remove all admin notices
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');

        // Re-add our custom notice only
        add_action('admin_notices', 'utm_tracker_admin_notice');

        // Restore WP notices after our page to avoid affecting other pages
        add_action('shutdown', function () use ($saved_admin_notices) {
            global $wp_filter;
            $wp_filter['admin_notices'] = $saved_admin_notices;
        });
    }
});

// Display settings page content
function utm_tracker_options_page() {
    ?>
    <div class="utm-tracker-settings-wrapper">
        <?php
        // Display admin notice if email sanitization failed
        if (!empty($_SESSION['utm_tracker_email_errors'])) {
            echo '<div class="notice notice-error">';
            foreach ($_SESSION['utm_tracker_email_errors'] as $error) {
                echo "<p>$error</p>";
            }
            echo '</div>';
            unset($_SESSION['utm_tracker_email_errors']); // Clear errors after displaying
        }
        ?>
        
        <form action="options.php" method="post">
            <?php
            settings_fields('utmTracker');
            do_settings_sections('utmTracker');
            submit_button();
            ?>
        </form>

        <!-- License Activation Box -->
        <div class="license-key-container">
            <label class="license-key-label">License Key</label>
            <input type="text" id="utm-license-key-input" 
                placeholder="Enter your license key" 
                value="<?php echo esc_attr(get_option('utm_tracker_license_key', '')); ?>" />
            <button type="button" id="utm-license-activate-btn" disabled>Activate License</button>
        </div>

        <div id="utm-license-message"></div>

        <style>
        .license-key-container {
            display: flex;
            align-items: center;
            background: #f8f9fa;
            padding: 12px 18px;
            border-radius: 6px;
            margin: 30px 0 0;
            border: 1px solid #ddd;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            width: 60%;
        }
        .license-key-label {
            font-weight: bold;
            font-size: 14px;
            color: #333;
            margin-right: 15px;
            white-space: nowrap;
        }
        #utm-license-key-input {
            flex: 1;
            padding: 10px 15px;
            font-size: 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #fff;
            transition: all 0.2s ease-in-out;
        }
        #utm-license-key-input:focus {
            border-color: #007cba;
            box-shadow: 0 0 8px rgba(0, 124, 186, 0.3);
            outline: none;
        }
        #utm-license-activate-btn {
            background: #007cba;
            color: white;
            border: none;
            padding: 10px 15px;
            font-size: 14px;
            border-radius: 6px;
            margin-left: 12px;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
        }
        #utm-license-activate-btn:hover {
            background: #005a9e;
        }
        #utm-license-activate-btn:disabled {
            background: #cccccc;
            cursor: not-allowed;
            opacity: 0.6;
        }
        #utm-license-message {
            margin-top: 10px;
            font-weight: bold;
            color: #007cba;
        }
        </style>

        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const keyInput = document.getElementById("utm-license-key-input");
            const activateBtn = document.getElementById("utm-license-activate-btn");
            const messageBox = document.getElementById("utm-license-message");

            keyInput.addEventListener("input", function() {
                activateBtn.disabled = keyInput.value.trim() === "";
            });

            activateBtn.addEventListener("click", function() {
                const key = keyInput.value.trim();
                messageBox.innerHTML = "🔄 Verifying license...";

                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'utm_activate_license',
                        license_key: key,
                        _ajax_nonce: '<?php echo wp_create_nonce("utm_license_nonce"); ?>'
                    })
                })
                .then(res => res.json())
                .then(data => {
                    messageBox.innerHTML = data.message;
                })
                .catch(() => {
                    messageBox.innerHTML = "❌ Something went wrong.";
                });
            });
        });
        </script>

    </div>

    <style>
        .utm-tracker-settings-wrapper {
			max-width: 90%;
			margin: 20px auto;
			padding: 25px;
			background: #ffffff;
			border: 1px solid #ddd;
			border-radius: 8px;
			box-shadow: 0px 3px 10px rgba(0, 0, 0, 0.1);
        }

        .utm-tracker-settings-wrapper input[type="text"] {
            width: 100%;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ccc;
            font-size: 14px;
        }

        .utm-tracker-host,
        .utm-tracker-email {
            display: flex;
            gap: 10px;
            margin-bottom: 5px;
        }

        .remove-host,
        .remove-email {
            background-color: #ff4d4d;
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            border-radius: 4px;
        }
		
		.remove-email {
			max-height: 35px;
    		margin-top: 5px;
		}

        #add-host,
        #add-email {
            margin-top: 10px;
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 12px;
            cursor: pointer;
            border-radius: 4px;
			min-width: 140px;
        }
		
		.protocol-label {
			margin-right: 5px;
			color: #555;
			font-size: 14px;
			display: inline-block;
		}
		.utm-tracker-host {
			display: flex;
			align-items: center;
			gap: 10px;
			margin-bottom: 5px;
		}
		
		.to-label {
			font-size: 14px;
			color: #555;
			margin: 0 8px;
			display: flex;
			align-items: center; 
			justify-content: center;
			height: 100% !important; 
			white-space: nowrap; 
			padding-top: 15px;
        }
		
		h2 {
			font-size: 1.8em;
		}
    </style>
    <?php
}

add_action('wp_ajax_utm_activate_license', function () {
    check_ajax_referer('utm_license_nonce');

    $key = sanitize_text_field($_POST['license_key'] ?? '');

    if (!$key) {
        wp_send_json_error(['message' => '❌ License key is empty.']);
    }

    // TODO: Replace with actual license validation against remote server if needed.
    // For now, we'll just store it.
    update_option('utm_tracker_license_key', $key);

    wp_send_json_success(['message' => '✅ License activated and saved.']);
});
