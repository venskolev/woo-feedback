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
     * Settings submenu slug.
     */
    private const SETTINGS_SLUG = 'woo-feedback-settings';

    /**
     * Reviews submenu slug.
     */
    private const REVIEWS_SLUG = 'woo-feedback-reviews';

    /**
     * Help submenu slug.
     */
    private const HELP_SLUG = 'woo-feedback-help';

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
        if (!current_user_can($this->get_required_capability())) {
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
        if (!current_user_can($this->get_required_capability())) {
            wp_die(esc_html__('Нямате права за достъп до тази страница.', 'woo-feedback'));
        }

        /**
         * Fires before the WooFeedback settings admin page is rendered.
         */
        do_action('woo_feedback/admin/render_settings_page');
    }

    /**
     * Delegates rendering of the help page.
     *
     * @return void
     */
    public function render_help_page(): void
    {
        if (!current_user_can($this->get_required_capability())) {
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
     * @return string
     */
    private function get_required_capability(): string
    {
        return 'moderate_comments';
    }
}
