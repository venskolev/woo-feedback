<?php
/**
 * Plugin Name: WooFeedback
 * Plugin URI: https://webdigitech.de
 * Description: Управление и визуализация на WooCommerce отзиви за INRA.
 * Version: 1.2.1
 * Author: WebDigiTech | Ventsislav Kolev
 * Author URI: https://webdigitech.de
 * Text Domain: woo-feedback
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 *
 * @package WooFeedback
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WOO_FEEDBACK_VERSION')) {
    define('WOO_FEEDBACK_VERSION', '1.2.1');
}

if (!defined('WOO_FEEDBACK_FILE')) {
    define('WOO_FEEDBACK_FILE', __FILE__);
}

if (!defined('WOO_FEEDBACK_BASENAME')) {
    define('WOO_FEEDBACK_BASENAME', plugin_basename(__FILE__));
}

if (!defined('WOO_FEEDBACK_DIR')) {
    define('WOO_FEEDBACK_DIR', plugin_dir_path(__FILE__));
}

if (!defined('WOO_FEEDBACK_URL')) {
    define('WOO_FEEDBACK_URL', plugin_dir_url(__FILE__));
}

/**
 * PSR-4 style autoloader for WooFeedback classes.
 *
 * Namespace root:
 * WDT\WooFeedback\
 *
 * @param string $class Fully qualified class name.
 *
 * @return void
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'WDT\\WooFeedback\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $relative_path  = str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';
    $file           = WOO_FEEDBACK_DIR . 'includes/' . $relative_path;

    if (is_readable($file)) {
        require_once $file;
    }
});

add_action('before_woocommerce_init', static function (): void {
    if (!class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        return;
    }

    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
        'custom_order_tables',
        WOO_FEEDBACK_FILE,
        true
    );

    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
        'orders_cache',
        WOO_FEEDBACK_FILE,
        true
    );
});

register_activation_hook(__FILE__, static function (): void {
    if (!class_exists(\WDT\WooFeedback\Core\Activator::class)) {
        return;
    }

    \WDT\WooFeedback\Core\Activator::activate();
});

register_deactivation_hook(__FILE__, static function (): void {
    if (!class_exists(\WDT\WooFeedback\Core\Deactivator::class)) {
        return;
    }

    \WDT\WooFeedback\Core\Deactivator::deactivate();
});

/**
 * Boots the plugin after all plugins are loaded.
 *
 * @return void
 */
function woo_feedback_bootstrap(): void
{
    load_plugin_textdomain(
        'woo-feedback',
        false,
        dirname(WOO_FEEDBACK_BASENAME) . '/languages'
    );

    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', static function (): void {
            if (!current_user_can('activate_plugins')) {
                return;
            }

            echo '<div class="notice notice-error"><p>';
            echo esc_html__('WooFeedback изисква активиран WooCommerce.', 'woo-feedback');
            echo '</p></div>';
        });

        return;
    }

    if (!class_exists(\WDT\WooFeedback\Core\Plugin::class)) {
        add_action('admin_notices', static function (): void {
            if (!current_user_can('activate_plugins')) {
                return;
            }

            echo '<div class="notice notice-error"><p>';
            echo esc_html__('WooFeedback не успя да се инициализира коректно.', 'woo-feedback');
            echo '</p></div>';
        });

        return;
    }

    $plugin = new \WDT\WooFeedback\Core\Plugin();
    $plugin->boot();
}

add_action('plugins_loaded', 'woo_feedback_bootstrap');
