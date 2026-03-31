<?php
/**
 * Admin menu registration for WooFeedback.
 *
 * @package WooFeedback
 */

declare(strict_types=1);

namespace WDT\WooFeedback\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers the WooFeedback admin menu structure.
 */
final class AdminMenu
{
    /**
     * Main menu slug.
     */
    private const MENU_SLUG = 'woo-feedback-reviews';

    /**
     * Reviews submenu slug.
     */
    private const REVIEWS_SLUG = 'woo-feedback-reviews';

    /**
     * Settings submenu slug.
     */
    private const SETTINGS_SLUG = 'woo-feedback-settings';

    /**
     * Security submenu slug.
     */
    private const SECURITY_SLUG = 'woo-feedback-security';

    /**
     * Help submenu slug.
     */
    private const HELP_SLUG = 'woo-feedback-help';

    /**
     * Plugin-specific capability.
     */
    private const PLUGIN_CAPABILITY = 'manage_woo_feedback';

    /**
     * Fallback legacy capability.
     */
    private const LEGACY_CAPABILITY = 'moderate_comments';

    /**
     * Registers all admin menu pages.
     *
     * @return void
     */
    public function register(): void
    {
        $capability = $this->get_required_capability();

        add_menu_page(
            __('WooFeedback', 'woo-feedback'),
                      __('WooFeedback', 'woo-feedback'),
                      $capability,
                      self::MENU_SLUG,
                      [$this, 'render_reviews_page'],
                      'dashicons-testimonial',
                      56
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Преглед на отзивите', 'woo-feedback'),
                         __('Отзиви', 'woo-feedback'),
                         $capability,
                         self::REVIEWS_SLUG,
                         [$this, 'render_reviews_page']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Настройки на WooFeedback', 'woo-feedback'),
                         __('Настройки', 'woo-feedback'),
                         $capability,
                         self::SETTINGS_SLUG,
                         [$this, 'render_settings_page']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Настройки за сигурност на WooFeedback', 'woo-feedback'),
                         __('Сигурност', 'woo-feedback'),
                         $capability,
                         self::SECURITY_SLUG,
                         [$this, 'render_security_page']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Помощ за WooFeedback', 'woo-feedback'),
                         __('Помощ', 'woo-feedback'),
                         $capability,
                         self::HELP_SLUG,
                         [$this, 'render_help_page']
        );
    }

    /**
     * Delegates rendering of the reviews page.
     *
     * @return void
     */
    public function render_reviews_page(): void
    {
        if (!$this->current_user_can_access_admin()) {
            wp_die(esc_html__('Нямате права за достъп до тази страница.', 'woo-feedback'));
        }

        /**
         * Fires before the WooFeedback reviews admin page is rendered.
         */
        do_action('woo_feedback/admin/render_reviews_page');
    }

    /**
     * Delegates rendering of the settings page.
     *
     * @return void
     */
    public function render_settings_page(): void
    {
        if (!$this->current_user_can_access_admin()) {
            wp_die(esc_html__('Нямате права за достъп до тази страница.', 'woo-feedback'));
        }

        /**
         * Fires before the WooFeedback settings admin page is rendered.
         */
        do_action('woo_feedback/admin/render_settings_page');
    }

    /**
     * Delegates rendering of the security page.
     *
     * @return void
     */
    public function render_security_page(): void
    {
        if (!$this->current_user_can_access_admin()) {
            wp_die(esc_html__('Нямате права за достъп до тази страница.', 'woo-feedback'));
        }

        /**
         * Fires before the WooFeedback security admin page is rendered.
         */
        do_action('woo_feedback/admin/render_security_page');
    }

    /**
     * Delegates rendering of the help page.
     *
     * @return void
     */
    public function render_help_page(): void
    {
        if (!$this->current_user_can_access_admin()) {
            wp_die(esc_html__('Нямате права за достъп до тази страница.', 'woo-feedback'));
        }

        /**
         * Fires before the WooFeedback help admin page is rendered.
         */
        do_action('woo_feedback/admin/render_help_page');
    }

    /**
     * Returns the required admin capability.
     *
     * Uses a plugin-owned capability with backward-compatible fallback.
     *
     * @return string
     */
    private function get_required_capability(): string
    {
        $capability = self::PLUGIN_CAPABILITY;

        if (!current_user_can(self::PLUGIN_CAPABILITY) && current_user_can(self::LEGACY_CAPABILITY)) {
            $capability = self::LEGACY_CAPABILITY;
        }

        /**
         * Filters the capability required for WooFeedback admin access.
         *
         * @param string $capability Resolved capability.
         */
        $capability = apply_filters('woo_feedback/admin/capability', $capability);

        if (!is_string($capability) || $capability === '') {
            return self::LEGACY_CAPABILITY;
        }

        return $capability;
    }

    /**
     * Returns whether the current user can access WooFeedback admin pages.
     *
     * @return bool
     */
    private function current_user_can_access_admin(): bool
    {
        if (current_user_can(self::PLUGIN_CAPABILITY)) {
            return true;
        }

        if (current_user_can(self::LEGACY_CAPABILITY)) {
            return true;
        }

        $filtered_capability = apply_filters('woo_feedback/admin/capability', self::PLUGIN_CAPABILITY);

        if (!is_string($filtered_capability) || $filtered_capability === '') {
            return false;
        }

        return current_user_can($filtered_capability);
    }
}
