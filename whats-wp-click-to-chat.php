<?php
/*
Plugin Name: WhatsWP - Click to Chat
Plugin URI: https://zulkaif.com/
Description: Display a floating WhatsApp chat button on your WordPress site.
Version: 1.0.1
Author: Zulkaif Riaz
Author URI: https://zulkaif.com/
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: whatswp
Domain Path: /languages
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Debugging mode - set to true to see error messages
define('WHATSWP_DEBUG', false);

class WhatsWP_Chat {

    public function __construct() {
        // Define constants
        $this->define_constants();
        
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init_plugin'));
        
        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
    }

    private function define_constants() {
        define('WHATSWP_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('WHATSWP_PLUGIN_URL', plugin_dir_url(__FILE__));
        define('WHATSWP_PLUGIN_BASENAME', plugin_basename(__FILE__));
    }

    public function activate() {
        // Set default options on activation
        if (false === get_option('whatswp_enabled')) {
            update_option('whatswp_enabled', '1');
            update_option('whatswp_location', 'right');
            update_option('whatswp_size', 'medium');
            update_option('whatswp_phone', '');
            update_option('whatswp_message', esc_html__('Hello, I have a question', 'whatswp'));
        }
    }

    public function init_plugin() {
        // Load text domain
        load_plugin_textdomain('whatswp', false, dirname(WHATSWP_PLUGIN_BASENAME) . '/languages/');

        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_footer', array($this, 'display_button'));

        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'register_settings'));
            add_filter('plugin_action_links_' . WHATSWP_PLUGIN_BASENAME, array($this, 'add_settings_link'));
        }

        // Debug info
        if (WHATSWP_DEBUG) {
            add_action('admin_notices', array($this, 'debug_info'));
        }
    }

    public function enqueue_assets() {
        // CSS
        $css_path = WHATSWP_PLUGIN_DIR . 'assets/css/whatswp.css';
        if (file_exists($css_path)) {
            wp_enqueue_style(
                'whatswp-style',
                WHATSWP_PLUGIN_URL . 'assets/css/whatswp.css',
                array(),
                filemtime($css_path)
            );
        } elseif (WHATSWP_DEBUG) {
            error_log('WhatsWP: CSS file not found at ' . $css_path);
        }

        // JS
        $js_path = WHATSWP_PLUGIN_DIR . 'assets/js/whatswp.js';
        if (file_exists($js_path)) {
            wp_enqueue_script(
                'whatswp-script',
                WHATSWP_PLUGIN_URL . 'assets/js/whatswp.js',
                array('jquery'),
                filemtime($js_path),
                true
            );
        } elseif (WHATSWP_DEBUG) {
            error_log('WhatsWP: JS file not found at ' . $js_path);
        }
    }

    public function display_button() {
        if (get_option('whatswp_enabled') !== '1') {
            if (WHATSWP_DEBUG) {
                error_log('WhatsWP: Plugin is disabled in settings');
            }
            return;
        }

        $phone = get_option('whatswp_phone');
        if (empty($phone)) {
            if (WHATSWP_DEBUG) {
                error_log('WhatsWP: Phone number is not set');
            }
            return;
        }

        $phone = preg_replace('/[^0-9]/', '', $phone);
        $message = urlencode(get_option('whatswp_message', esc_html__('Hello, I have a question', 'whatswp')));
        $location = get_option('whatswp_location', 'right');
        $size = get_option('whatswp_size', 'medium');

        $classes = array(
            'whatswp-button',
            'whatswp-' . esc_attr($location),
            'whatswp-' . esc_attr($size)
        );

        $whatsapp_url = 'https://wa.me/' . $phone . '?text=' . $message;

        echo '<div class="whatswp-container">';
        echo '<a href="' . esc_url($whatsapp_url) . '" class="' . implode(' ', $classes) . '" target="_blank" rel="noopener noreferrer">';
        echo '<img src="' . esc_url(WHATSWP_PLUGIN_URL . 'assets/images/whatsapp-icon.png') . '" alt="' . esc_attr__('Chat on WhatsApp', 'whatswp') . '">';
        echo '</a>';
        echo '</div>';

        // Inline styles if CSS fails to load
        echo '<style>
            .whatswp-container {
                position: fixed;
                z-index: 9999;
            }
            .whatswp-right {
                bottom: 20px;
                right: 20px;
            }
            .whatswp-left {
                bottom: 20px;
                left: 20px;
            }
            .whatswp-button img {
                width: 60px;
                height: 60px;
                transition: transform 0.3s;
            }
            .whatswp-button:hover img {
                transform: scale(1.1);
            }
        </style>';
    }

    // Admin functions
    public function add_admin_menu() {
        add_menu_page(
            esc_html__('WhatsWP Settings', 'whatswp'),
            'WhatsWP',
            'manage_options',
            'whatswp-settings',
            array($this, 'render_settings_page'),
            'dashicons-whatsapp',
            81
        );

        add_submenu_page(
            'whatswp-settings',
            esc_html__('Settings', 'whatswp'),
            esc_html__('Settings', 'whatswp'),
            'manage_options',
            'whatswp-settings',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'whatswp-settings',
            esc_html__('About', 'whatswp'),
            esc_html__('About', 'whatswp'),
            'manage_options',
            'whatswp-about',
            array($this, 'render_about_page')
        );
    }

    public function register_settings() {
        register_setting('whatswp_settings', 'whatswp_enabled');
        register_setting('whatswp_settings', 'whatswp_phone', array($this, 'sanitize_phone'));
        register_setting('whatswp_settings', 'whatswp_message');
        register_setting('whatswp_settings', 'whatswp_location');
        register_setting('whatswp_settings', 'whatswp_size');

        add_settings_section(
            'whatswp_main',
            esc_html__('WhatsApp Chat Settings', 'whatswp'),
            array($this, 'render_settings_section'),
            'whatswp-settings'
        );

        add_settings_field(
            'whatswp_enabled',
            esc_html__('Enable WhatsApp Button', 'whatswp'),
            array($this, 'render_enable_field'),
            'whatswp-settings',
            'whatswp_main'
        );

        add_settings_field(
            'whatswp_phone',
            esc_html__('WhatsApp Number', 'whatswp'),
            array($this, 'render_phone_field'),
            'whatswp-settings',
            'whatswp_main'
        );

        add_settings_field(
            'whatswp_message',
            esc_html__('Default Message', 'whatswp'),
            array($this, 'render_message_field'),
            'whatswp-settings',
            'whatswp_main'
        );

        add_settings_field(
            'whatswp_location',
            esc_html__('Button Position', 'whatswp'),
            array($this, 'render_location_field'),
            'whatswp-settings',
            'whatswp_main'
        );

        add_settings_field(
            'whatswp_size',
            esc_html__('Button Size', 'whatswp'),
            array($this, 'render_size_field'),
            'whatswp-settings',
            'whatswp_main'
        );
    }

    public function sanitize_phone($phone) {
        $sanitized = preg_replace('/[^0-9]/', '', $phone);
        if (empty($sanitized)) {
            add_settings_error(
                'whatswp_phone',
                'whatswp_phone_error',
                esc_html__('Please enter a valid WhatsApp number', 'whatswp'),
                'error'
            );
        }
        return $sanitized;
    }

    // Admin page rendering
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        settings_errors('whatswp_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('whatswp_settings');
                do_settings_sections('whatswp-settings');
                submit_button(esc_html__('Save Settings', 'whatswp'));
                ?>
            </form>
        </div>
        <?php
    }

    public function render_about_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('About WhatsWP', 'whatswp'); ?></h1>
            <div class="card">
                <h2><?php esc_html_e('WhatsWP - Click to Chat', 'whatswp'); ?></h2>
                <p><?php esc_html_e('Version: 1.0.1', 'whatswp'); ?></p>
                <p><?php esc_html_e('A simple plugin to add a WhatsApp chat button to your WordPress site.', 'whatswp'); ?></p>
                <p><?php esc_html_e('Developed by', 'whatswp'); ?> <a href="https://zulkaif.com" target="_blank">Zulkaif Riaz</a></p>
            </div>
        </div>
        <?php
    }

    // Settings field rendering
    public function render_settings_section() {
        echo '<p>' . esc_html__('Configure your WhatsApp chat button settings below.', 'whatswp') . '</p>';
    }

    public function render_enable_field() {
        $enabled = get_option('whatswp_enabled', '1');
        ?>
        <label>
            <input type="checkbox" name="whatswp_enabled" value="1" <?php checked('1', $enabled); ?>>
            <?php esc_html_e('Enable WhatsApp chat button', 'whatswp'); ?>
        </label>
        <?php
    }

    public function render_phone_field() {
        $phone = get_option('whatswp_phone');
        ?>
        <input type="text" name="whatswp_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text">
        <p class="description">
            <?php esc_html_e('Enter your WhatsApp number with country code (without + sign), e.g. 14151234567', 'whatswp'); ?>
        </p>
        <?php
    }

    public function render_message_field() {
        $message = get_option('whatswp_message', esc_html__('Hello, I have a question', 'whatswp'));
        ?>
        <textarea name="whatswp_message" rows="3" cols="50" class="large-text"><?php echo esc_textarea($message); ?></textarea>
        <p class="description">
            <?php esc_html_e('Default message that will be sent when users click the button', 'whatswp'); ?>
        </p>
        <?php
    }

    public function render_location_field() {
        $location = get_option('whatswp_location', 'right');
        ?>
        <select name="whatswp_location">
            <option value="right" <?php selected($location, 'right'); ?>>
                <?php esc_html_e('Bottom Right', 'whatswp'); ?>
            </option>
            <option value="left" <?php selected($location, 'left'); ?>>
                <?php esc_html_e('Bottom Left', 'whatswp'); ?>
            </option>
        </select>
        <?php
    }

    public function render_size_field() {
        $size = get_option('whatswp_size', 'medium');
        ?>
        <select name="whatswp_size">
            <option value="small" <?php selected($size, 'small'); ?>>
                <?php esc_html_e('Small (48px)', 'whatswp'); ?>
            </option>
            <option value="medium" <?php selected($size, 'medium'); ?>>
                <?php esc_html_e('Medium (60px)', 'whatswp'); ?>
            </option>
            <option value="large" <?php selected($size, 'large'); ?>>
                <?php esc_html_e('Large (72px)', 'whatswp'); ?>
            </option>
        </select>
        <?php
    }

    public function add_settings_link($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=whatswp-settings')),
            esc_html__('Settings', 'whatswp')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    public function debug_info() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $enabled = get_option('whatswp_enabled');
        $phone = get_option('whatswp_phone');
        $location = get_option('whatswp_location');
        $size = get_option('whatswp_size');

        echo '<div class="notice notice-info">';
        echo '<h3>WhatsWP Debug Information</h3>';
        echo '<ul>';
        echo '<li><strong>Enabled:</strong> ' . ($enabled ? 'Yes' : 'No') . '</li>';
        echo '<li><strong>Phone Number:</strong> ' . esc_html($phone) . '</li>';
        echo '<li><strong>Location:</strong> ' . esc_html($location) . '</li>';
        echo '<li><strong>Size:</strong> ' . esc_html($size) . '</li>';
        echo '</ul>';
        echo '</div>';
    }
}

// Initialize the plugin
new WhatsWP_Chat();