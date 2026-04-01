<?php
/**
 * Plugin activator for WooFeedback.
 *
 * @package WooFeedback
 */

declare(strict_types=1);

namespace WDT\WooFeedback\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles plugin activation tasks.
 */
final class Activator
{
    /**
     * Plugin-specific admin capability.
     */
    private const PLUGIN_CAPABILITY = 'manage_woo_feedback';

    /**
     * Runs the activation routine.
     *
     * @return void
     */
    public static function activate(): void
    {
        self::ensure_requirements();
        self::store_version();
        self::register_default_options();
        self::register_capabilities();
        self::flush_rewrite_rules_safe();
    }

    /**
     * Validates environment requirements before activation completes.
     *
     * @return void
     */
    private static function ensure_requirements(): void
    {
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(WOO_FEEDBACK_BASENAME);

            wp_die(
                esc_html__('WooFeedback изисква активиран WooCommerce.', 'woo-feedback'),
                   esc_html__('Грешка при активиране', 'woo-feedback'),
                   [
                       'back_link' => true,
                   ]
            );
        }

        if (version_compare(PHP_VERSION, '8.0', '<')) {
            deactivate_plugins(WOO_FEEDBACK_BASENAME);

            wp_die(
                esc_html__('WooFeedback изисква PHP версия 8.0 или по-нова.', 'woo-feedback'),
                   esc_html__('Грешка при активиране', 'woo-feedback'),
                   [
                       'back_link' => true,
                   ]
            );
        }
    }

    /**
     * Stores plugin version metadata.
     *
     * @return void
     */
    private static function store_version(): void
    {
        update_option('woo_feedback_version', WOO_FEEDBACK_VERSION);
        update_option('woo_feedback_installed_at', (string) time());
    }

    /**
     * Registers default plugin options.
     *
     * @return void
     */
    private static function register_default_options(): void
    {
        $defaults = [
            'enable_shortcode'                 => 'yes',
            'show_review_form_default'         => 'no',
            'require_login_for_review'         => 'no',
            'force_moderation'                 => 'yes',
            'auto_hide_woocommerce_tab'        => 'no',
            'default_shortcode_title'          => 'Отзиви',
            'empty_reviews_message'            => 'Все още няма отзиви.',
            'success_message'                  => 'Вашият отзив беше изпратен и очаква одобрение от администратор.',
            'error_message'                    => 'Възникна проблем при изпращането на отзива. Моля, опитайте отново.',
            'review_form_title'                => 'Добавете отзив',
            'submit_button_text'               => 'Изпрати отзив',
            'admin_items_per_page'             => 20,
            'enable_admin_dashboard_box'       => 'yes',

            // Security settings.
            'security_enable_protection'       => 'yes',
            'security_enable_turnstile'        => 'no',
            'security_turnstile_site_key'      => '',
            'security_turnstile_secret_key'    => '',
            'security_enable_honeypot'         => 'yes',
            'security_enable_time_check'       => 'yes',
            'security_min_submit_time'         => 4,
            'security_enable_rate_limit'       => 'yes',
            'security_rate_limit_window'       => 10,
            'security_rate_limit_max_attempts' => 5,
            'security_enable_duplicate_check'  => 'yes',
            'security_duplicate_window'        => 15,
            'security_whitelist_ips'           => '',
            'security_failsafe_mode'           => 'open',
        ];

        $current = get_option('woo_feedback_settings', []);

        if (!is_array($current)) {
            $current = [];
        }

        update_option('woo_feedback_settings', array_merge($defaults, $current));
    }

    /**
     * Registers plugin-specific capabilities for administrator roles.
     *
     * Backward compatibility is preserved because runtime still falls back
     * to moderate_comments if the capability is not present yet.
     *
     * @return void
     */
    private static function register_capabilities(): void
    {
        $roles = [
            'administrator',
            'shop_manager',
        ];

        foreach ($roles as $role_name) {
            $role = get_role($role_name);

            if ($role === null) {
                continue;
            }

            $role->add_cap(self::PLUGIN_CAPABILITY);
        }
    }

    /**
     * Flushes rewrite rules in a safe and lightweight way.
     *
     * @return void
     */
    private static function flush_rewrite_rules_safe(): void
    {
        flush_rewrite_rules(false);
    }
}
