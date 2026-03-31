<?php
/**
 * Core plugin bootstrap for WooFeedback.
 *
 * @package WooFeedback
 */

declare(strict_types=1);

namespace WDT\WooFeedback\Core;

use WDT\WooFeedback\Admin\AdminMenu;
use WDT\WooFeedback\Admin\HelpPage;
use WDT\WooFeedback\Admin\ReviewsPage;
use WDT\WooFeedback\Admin\SecurityPage;
use WDT\WooFeedback\Admin\SettingsPage;
use WDT\WooFeedback\Frontend\Assets;
use WDT\WooFeedback\Frontend\Shortcodes;
use WDT\WooFeedback\Reviews\Approval;
use WDT\WooFeedback\Reviews\CommentTypes;
use WDT\WooFeedback\Reviews\FormHandler;
use WDT\WooFeedback\Reviews\ReviewColumns;
use WDT\WooFeedback\Reviews\ReviewModeration;
use WDT\WooFeedback\Reviews\ReviewQuery;
use WDT\WooFeedback\Security\AntiSpamService;
use WDT\WooFeedback\Security\RateLimitService;
use WDT\WooFeedback\Security\TurnstileService;
use WDT\WooFeedback\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin orchestrator.
 */
final class Plugin
{
    /**
     * Plugin settings service.
     *
     * @var Settings
     */
    private Settings $settings;

    /**
     * Frontend assets service.
     *
     * @var Assets
     */
    private Assets $assets;

    /**
     * Frontend shortcodes service.
     *
     * @var Shortcodes
     */
    private Shortcodes $shortcodes;

    /**
     * Frontend form handler service.
     *
     * @var FormHandler
     */
    private FormHandler $form_handler;

    /**
     * Admin menu service.
     *
     * @var AdminMenu
     */
    private AdminMenu $admin_menu;

    /**
     * Admin settings page service.
     *
     * @var SettingsPage
     */
    private SettingsPage $settings_page;

    /**
     * Admin security page service.
     *
     * @var SecurityPage
     */
    private SecurityPage $security_page;

    /**
     * Admin reviews page service.
     *
     * @var ReviewsPage
     */
    private ReviewsPage $reviews_page;

    /**
     * Admin help page service.
     *
     * @var HelpPage
     */
    private HelpPage $help_page;

    /**
     * Review moderation service.
     *
     * @var ReviewModeration
     */
    private ReviewModeration $review_moderation;

    /**
     * Review query service.
     *
     * @var ReviewQuery
     */
    private ReviewQuery $review_query;

    /**
     * Review columns integration.
     *
     * @var ReviewColumns
     */
    private ReviewColumns $review_columns;

    /**
     * Review comment type integration.
     *
     * @var CommentTypes
     */
    private CommentTypes $comment_types;

    /**
     * Review approval helper integration.
     *
     * @var Approval
     */
    private Approval $approval;

    /**
     * Turnstile security service.
     *
     * @var TurnstileService
     */
    private TurnstileService $turnstile_service;

    /**
     * Anti-spam security service.
     *
     * @var AntiSpamService
     */
    private AntiSpamService $anti_spam_service;

    /**
     * Rate limit security service.
     *
     * @var RateLimitService
     */
    private RateLimitService $rate_limit_service;

    /**
     * Bootstraps the full plugin lifecycle.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->register_services();

        /**
         * Defaults must exist immediately for both frontend and admin runtime.
         * This is cheap and safe because register_defaults() only writes when needed.
         */
        $this->settings->register_defaults();

        $this->register_hooks();
    }

    /**
     * Instantiates all required services.
     *
     * @return void
     */
    private function register_services(): void
    {
        $this->settings           = new Settings();
        $this->turnstile_service  = new TurnstileService($this->settings);
        $this->anti_spam_service  = new AntiSpamService($this->settings);
        $this->rate_limit_service = new RateLimitService($this->settings);

        $this->assets       = new Assets($this->settings, $this->turnstile_service);
        $this->shortcodes   = new Shortcodes(
            $this->settings,
            $this->turnstile_service,
            $this->anti_spam_service
        );
        $this->form_handler = new FormHandler(
            $this->settings,
            $this->turnstile_service,
            $this->anti_spam_service,
            $this->rate_limit_service
        );

        $this->admin_menu    = new AdminMenu();
        $this->settings_page = new SettingsPage($this->settings);
        $this->security_page = new SecurityPage($this->settings);
        $this->reviews_page  = new ReviewsPage($this->settings);
        $this->help_page     = new HelpPage();

        $this->review_moderation = new ReviewModeration($this->settings);
        $this->review_query      = new ReviewQuery($this->settings);
        $this->review_columns    = new ReviewColumns();
        $this->comment_types     = new CommentTypes();
        $this->approval          = new Approval();
    }

    /**
     * Registers the plugin hooks.
     *
     * @return void
     */
    private function register_hooks(): void
    {
        add_action('init', [$this, 'register_runtime']);
        add_action('init', [$this->shortcodes, 'register']);
        add_action('init', [$this->comment_types, 'register']);

        /**
         * Version upgrades do not need to run on every public runtime path.
         * Admin init is the cleanest lifecycle point for settings migrations.
         */
        add_action('admin_init', [$this, 'maybe_upgrade']);

        add_action('template_redirect', [$this->form_handler, 'handle_submission']);

        add_action(
            'wp_ajax_' . FormHandler::get_ajax_action(),
                   [$this->form_handler, 'handle_ajax_submission']
        );
        add_action(
            'wp_ajax_nopriv_' . FormHandler::get_ajax_action(),
                   [$this->form_handler, 'handle_ajax_submission']
        );

        add_action('wp_enqueue_scripts', [$this->assets, 'register_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this->assets, 'register_admin_assets']);

        add_action('admin_menu', [$this->admin_menu, 'register']);
        add_action('admin_init', [$this->settings_page, 'register_settings']);
        add_action('admin_init', [$this->security_page, 'register_settings']);
        add_action('current_screen', [$this->reviews_page, 'handle_actions']);

        add_action('woo_feedback/admin/render_reviews_page', [$this->reviews_page, 'render_page']);
        add_action('woo_feedback/admin/render_settings_page', [$this->settings_page, 'render_page']);
        add_action('woo_feedback/admin/render_security_page', [$this->security_page, 'render_page']);
        add_action('woo_feedback/admin/render_help_page', [$this->help_page, 'render_page']);

        add_action('comment_post', [$this->review_moderation, 'flag_new_review_for_moderation'], 10, 3);

        add_filter('plugin_action_links_' . WOO_FEEDBACK_BASENAME, [$this, 'add_plugin_action_links']);
        add_filter('manage_edit-comments_columns', [$this->review_columns, 'register_columns']);
        add_action('manage_comments_custom_column', [$this->review_columns, 'render_column'], 10, 2);
        add_filter('comment_row_actions', [$this->approval, 'filter_row_actions'], 10, 2);
        add_action('pre_get_comments', [$this->review_query, 'filter_admin_comment_queries']);
    }

    /**
     * Registers runtime integrations that must exist after init.
     *
     * @return void
     */
    public function register_runtime(): void
    {
        $this->review_moderation->register_comment_filters();
    }

    /**
     * Runs version-based settings upgrade only when needed.
     *
     * @return void
     */
    public function maybe_upgrade(): void
    {
        $this->settings->maybe_upgrade();
    }

    /**
     * Adds quick access links on the plugins list screen.
     *
     * @param array<int, string> $links Existing action links.
     *
     * @return array<int, string>
     */
    public function add_plugin_action_links(array $links): array
    {
        $settings_url = admin_url('admin.php?page=woo-feedback-settings');
        $security_url = admin_url('admin.php?page=woo-feedback-security');
        $reviews_url  = admin_url('admin.php?page=woo-feedback-reviews');
        $help_url     = admin_url('admin.php?page=woo-feedback-help');

        $custom_links = [
            sprintf(
                '<a href="%1$s">%2$s</a>',
                esc_url($reviews_url),
                    esc_html__('Отзиви', 'woo-feedback')
            ),
            sprintf(
                '<a href="%1$s">%2$s</a>',
                esc_url($settings_url),
                    esc_html__('Настройки', 'woo-feedback')
            ),
            sprintf(
                '<a href="%1$s">%2$s</a>',
                esc_url($security_url),
                    esc_html__('Сигурност', 'woo-feedback')
            ),
            sprintf(
                '<a href="%1$s">%2$s</a>',
                esc_url($help_url),
                    esc_html__('Помощ', 'woo-feedback')
            ),
        ];

        return array_merge($custom_links, $links);
    }
}
