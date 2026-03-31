<?php
/**
 * Assets registration for WooFeedback.
 *
 * @package WooFeedback
 */

declare(strict_types=1);

namespace WDT\WooFeedback\Frontend;

use WDT\WooFeedback\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers and prints lightweight frontend/admin assets.
 */
final class Assets
{
    /**
     * Frontend script handle.
     */
    private const FRONTEND_SCRIPT_HANDLE = 'woo-feedback-frontend';

    /**
     * Frontend style handle.
     */
    private const FRONTEND_STYLE_HANDLE = 'woo-feedback-frontend';

    /**
     * Admin style handle.
     */
    private const ADMIN_STYLE_HANDLE = 'woo-feedback-admin';

    /**
     * Settings service.
     *
     * @var Settings
     */
    private Settings $settings;

    /**
     * Constructor.
     *
     * @param Settings $settings Settings service.
     */
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Registers frontend assets.
     *
     * @return void
     */
    public function register_frontend_assets(): void
    {
        $has_shortcode = $this->current_request_likely_needs_frontend_assets();

        if (!$has_shortcode) {
            return;
        }

        wp_register_script(
            self::FRONTEND_SCRIPT_HANDLE,
            '',
            [],
            WOO_FEEDBACK_VERSION,
            true
        );

        wp_register_style(
            self::FRONTEND_STYLE_HANDLE,
            false,
            [],
            WOO_FEEDBACK_VERSION
        );

        wp_enqueue_script(self::FRONTEND_SCRIPT_HANDLE);
        wp_enqueue_style(self::FRONTEND_STYLE_HANDLE);

        wp_add_inline_script(
            self::FRONTEND_SCRIPT_HANDLE,
            $this->get_frontend_script()
        );

        wp_add_inline_style(
            self::FRONTEND_STYLE_HANDLE,
            $this->get_frontend_style()
        );
    }

    /**
     * Registers admin assets.
     *
     * @return void
     */
    public function register_admin_assets(): void
    {
        if (!$this->is_woo_feedback_admin_screen()) {
            return;
        }

        wp_register_style(
            self::ADMIN_STYLE_HANDLE,
            false,
            [],
            WOO_FEEDBACK_VERSION
        );

        wp_enqueue_style(self::ADMIN_STYLE_HANDLE);

        wp_add_inline_style(
            self::ADMIN_STYLE_HANDLE,
            $this->get_admin_style()
        );
    }

    /**
     * Detects whether the current frontend request likely contains the shortcode.
     *
     * @return bool
     */
    private function current_request_likely_needs_frontend_assets(): bool
    {
        if (is_admin()) {
            return false;
        }

        if (function_exists('is_product') && is_product()) {
            return true;
        }

        $post = get_post();

        if (!$post || !isset($post->post_content) || !is_string($post->post_content)) {
            return false;
        }

        return has_shortcode($post->post_content, 'woo_feedback');
    }

    /**
     * Checks whether the current admin screen belongs to the plugin.
     *
     * @return bool
     */
    private function is_woo_feedback_admin_screen(): bool
    {
        if (!is_admin()) {
            return false;
        }

        if (!function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();

        if (!$screen || !isset($screen->id) || !is_string($screen->id)) {
            return false;
        }

        return in_array($screen->id, ['toplevel_page_woo-feedback-reviews', 'woo-feedback_page_woo-feedback-settings'], true);
    }

    /**
     * Returns inline frontend JavaScript.
     *
     * @return string
     */
    private function get_frontend_script(): string
    {
        return <<<'JS'
        document.addEventListener('DOMContentLoaded', function () {
        const toggles = document.querySelectorAll('[data-woo-feedback-toggle]');

        if (!toggles.length) {
            return;
    }

    toggles.forEach(function (toggle) {
    toggle.addEventListener('click', function () {
    const targetId = toggle.getAttribute('data-target');

    if (!targetId) {
        return;
    }

    const content = document.getElementById(targetId);

    if (!content) {
        return;
    }

    const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
    const nextState = !isExpanded;

    toggle.setAttribute('aria-expanded', nextState ? 'true' : 'false');

    if (nextState) {
        content.hidden = false;
        content.classList.add('is-open');
    } else {
        content.hidden = true;
        content.classList.remove('is-open');
    }
    });
    });
    });
    JS;
    }

    /**
     * Returns inline frontend CSS.
     *
     * @return string
     */
    private function get_frontend_style(): string
    {
        return <<<'CSS'
        .woo-feedback-block {
            margin: 24px 0;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 14px;
            background: #ffffff;
            overflow: hidden;
        }

        .woo-feedback-toggle {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 18px;
            border: 0;
            background: #f7f7f7;
            color: #111111;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-align: left;
        }

        .woo-feedback-toggle:hover {
            background: #f1f1f1;
        }

        .woo-feedback-toggle__label {
            flex: 1 1 auto;
        }

        .woo-feedback-toggle__badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 28px;
            height: 28px;
            padding: 0 8px;
            border-radius: 999px;
            background: #111111;
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            line-height: 1;
        }

        .woo-feedback-toggle__icon {
            flex: 0 0 auto;
            font-size: 14px;
            transition: transform 0.2s ease;
        }

        .woo-feedback-toggle[aria-expanded="true"] .woo-feedback-toggle__icon {
            transform: rotate(180deg);
        }

        .woo-feedback-content {
            padding: 18px;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
        }

        .woo-feedback-content[hidden] {
            display: none !important;
        }

        .woo-feedback-title {
            margin: 0 0 16px;
            font-size: 20px;
            line-height: 1.3;
        }

        .woo-feedback-review-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 14px;
        }

        .woo-feedback-review-item {
            padding: 16px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            background: #ffffff;
        }

        .woo-feedback-review-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .woo-feedback-review-author {
            font-size: 15px;
            font-weight: 700;
            color: #111111;
        }

        .woo-feedback-review-meta {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            font-size: 13px;
            color: #666666;
        }

        .woo-feedback-review-rating {
            font-size: 14px;
            line-height: 1;
            letter-spacing: 1px;
        }

        .woo-feedback-review-text {
            font-size: 15px;
            line-height: 1.65;
            color: #222222;
        }

        .woo-feedback-review-text p:last-child {
            margin-bottom: 0;
        }

        .woo-feedback-empty-message,
        .woo-feedback-login-required,
        .woo-feedback-message {
            padding: 14px 16px;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.5;
        }

        .woo-feedback-empty-message,
        .woo-feedback-login-required {
            background: #f7f7f7;
            color: #444444;
        }

        .woo-feedback-message {
            margin-bottom: 16px;
        }

        .woo-feedback-message--success {
            background: #eef8ef;
            color: #1d5b28;
            border: 1px solid #cce7d1;
        }

        .woo-feedback-message--error {
            background: #fff2f2;
            color: #8a1f1f;
            border: 1px solid #f2caca;
        }

        .woo-feedback-form-wrap {
            margin-top: 18px;
        }

        .woo-feedback-form-title {
            margin: 0 0 14px;
            font-size: 18px;
            line-height: 1.3;
        }

        .woo-feedback-field {
            margin: 0 0 14px;
        }

        .woo-feedback-field label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 600;
            color: #111111;
        }

        .woo-feedback-field input,
        .woo-feedback-field select,
        .woo-feedback-field textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid rgba(0, 0, 0, 0.14);
            border-radius: 10px;
            background: #ffffff;
            color: #111111;
            font-size: 15px;
            line-height: 1.4;
            box-sizing: border-box;
        }

        .woo-feedback-field textarea {
            min-height: 140px;
            resize: vertical;
        }

        .woo-feedback-actions {
            margin: 0;
        }

        .woo-feedback-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 18px;
            border: 0;
            border-radius: 10px;
            background: #111111;
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .woo-feedback-submit:hover {
            opacity: 0.92;
        }

        @media (max-width: 767px) {
            .woo-feedback-toggle {
                padding: 14px 16px;
                font-size: 15px;
            }

            .woo-feedback-content {
                padding: 16px;
            }

            .woo-feedback-review-head {
                align-items: flex-start;
                flex-direction: column;
            }
        }
        CSS;
    }

    /**
     * Returns inline admin CSS.
     *
     * @return string
     */
    private function get_admin_style(): string
    {
        return <<<'CSS'
        .woo-feedback-admin-page .form-table th {
            width: 320px;
        }

        .woo-feedback-admin-page .description {
            max-width: 760px;
        }

        .woo-feedback-admin-page table.widefat td,
        .woo-feedback-admin-page table.widefat th {
            vertical-align: top;
        }

        .woo-feedback-admin-page .tablenav-pages .page-numbers {
            display: inline-block;
            margin-right: 4px;
            padding: 6px 10px;
            border: 1px solid #dcdcde;
            border-radius: 6px;
            text-decoration: none;
        }

        .woo-feedback-admin-page .tablenav-pages .page-numbers.current {
            background: #2271b1;
            border-color: #2271b1;
            color: #ffffff;
        }
        CSS;
    }
}
