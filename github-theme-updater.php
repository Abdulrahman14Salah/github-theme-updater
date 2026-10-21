<?php

/**
 * Plugin Name:       GitHub Theme Updater
 * Plugin URI:        https://a-salah.dev
 * Description:       Checks a GitHub repository's Releases for theme updates and auto-updates the configured theme on this site — works with public or private repos. Configure under Settings → GitHub Theme Updater.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Abdulrahman Salah
 * Author URI:        https://a-salah.dev
 * License:           MIT
 * Text Domain:       github-theme-updater
 */

if (! defined('ABSPATH')) {
    exit;
}

define('GTU_VERSION', '1.0.0');
define('GTU_DIR', plugin_dir_path(__FILE__));
define('GTU_URL', plugin_dir_url(__FILE__));
define('GTU_OPTION_KEY', 'gtu_settings');

require_once GTU_DIR . 'includes/class-settings.php';
require_once GTU_DIR . 'includes/class-updater.php';

add_action('plugins_loaded', function () {

    new GTU_Settings();

    $settings = get_option(GTU_OPTION_KEY, []);

    // Only run the update checker once the essentials are filled in.
    if (! empty($settings['theme_slug']) && ! empty($settings['github_user']) && ! empty($settings['github_repo'])) {
        new GTU_Updater($settings);
    }
});
