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
     * Runs the activation routine.
     *
     * @return void
     */
    public static function activate(): void
    {
        self::ensure_requirements();
        self::store_version();
        self::register_default_options();
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
            'enable_shortcode'            => 'yes',
            'show_review_form_default'    => 'no',
            'require_login_for_review'    => 'no',
            'force_moderation'            => 'yes',
            'auto_hide_woocommerce_tab'   => 'no',
            'default_shortcode_title'     => 'Отзиви',
            'empty_reviews_message'       => 'Все още няма отзиви.',
            'success_message'             => 'Вашият отзив беше изпратен и очаква одобрение от администратор.',
            'error_message'               => 'Възникна проблем при изпращането на отзива. Моля, опитайте отново.',
            'review_form_title'           => 'Добавете отзив',
            'submit_button_text'          => 'Изпрати отзив',
            'admin_items_per_page'        => 20,
            'enable_admin_dashboard_box'  => 'yes',
        ];

        $current = get_option('woo_feedback_settings', []);

        if (!is_array($current)) {
            $current = [];
        }

        update_option('woo_feedback_settings', array_merge($defaults, $current));
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
