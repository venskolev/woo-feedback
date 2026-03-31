<?php
/**
 * Assets registration for WooFeedback.
 *
 * @package WooFeedback
 */

declare(strict_types=1);

namespace WDT\WooFeedback\Frontend;

use WDT\WooFeedback\Reviews\FormHandler;
use WDT\WooFeedback\Security\TurnstileService;
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
     * Frontend Turnstile script handle.
     */
    private const TURNSTILE_SCRIPT_HANDLE = 'woo-feedback-turnstile';

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
     * Turnstile service.
     *
     * @var TurnstileService
     */
    private TurnstileService $turnstile_service;

    /**
     * Constructor.
     *
     * @param Settings         $settings          Settings service.
     * @param TurnstileService $turnstile_service Turnstile service.
     */
    public function __construct(Settings $settings, TurnstileService $turnstile_service)
    {
        $this->settings          = $settings;
        $this->turnstile_service = $turnstile_service;
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

        if ($this->turnstile_service->should_render_widget()) {
            wp_register_script(
                self::TURNSTILE_SCRIPT_HANDLE,
                $this->turnstile_service->get_api_script_url(),
                               [],
                               null,
                               true
            );

            wp_enqueue_script(self::TURNSTILE_SCRIPT_HANDLE);
        }

        wp_localize_script(
            self::FRONTEND_SCRIPT_HANDLE,
            'wooFeedbackFrontend',
            $this->get_frontend_script_config()
        );

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

        return in_array(
            $screen->id,
            [
                'toplevel_page_woo-feedback-reviews',
                'woo-feedback_page_woo-feedback-settings',
                'woo-feedback_page_woo-feedback-security',
                'woo-feedback_page_woo-feedback-help',
            ],
            true
        );
    }

    /**
     * Returns frontend runtime configuration.
     *
     * @return array<string, mixed>
     */
    private function get_frontend_script_config(): array
    {
        return [
            'ajaxUrl'                  => admin_url('admin-ajax.php'),
            'ajaxAction'               => FormHandler::get_ajax_action(),
            'turnstileResponseField'   => $this->turnstile_service->get_response_field_name(),
            'selectors'                => [
                'block'        => '.woo-feedback-block',
                'toggle'       => '[data-woo-feedback-toggle]',
                'content'      => '.woo-feedback-content',
                'form'         => '.woo-feedback-submit-form',
                'message'      => '.woo-feedback-message',
                'submitButton' => '.woo-feedback-submit',
                'reviewList'   => '.woo-feedback-review-list',
                'emptyMessage' => '.woo-feedback-empty-message',
            ],
            'messages'                 => [
                'networkError' => __('Възникна технически проблем при изпращането. Моля, опитайте отново.', 'woo-feedback'),
                'submitting'   => __('Изпращане...', 'woo-feedback'),
            ],
            'successMessage'           => (string) $this->settings->get(
                'success_message',
                'Вашият отзив беше изпратен и очаква одобрение от администратор.'
            ),
            'errorMessage'             => (string) $this->settings->get(
                'error_message',
                'Възникна проблем при изпращането на отзива. Моля, опитайте отново.'
            ),
            'ajaxEnabled'              => true,
            'turnstileWidgetAvailable' => $this->turnstile_service->should_render_widget(),
        ];
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
        const config = window.wooFeedbackFrontend || {};
        const selectors = config.selectors || {};
        const blocks = document.querySelectorAll(selectors.block || '.woo-feedback-block');

        function getPrefersReducedMotion() {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    function scrollBlockIntoView(block) {
    if (!block || typeof block.scrollIntoView !== 'function') {
        return;
    }

    block.scrollIntoView({
    behavior: getPrefersReducedMotion() ? 'auto' : 'smooth',
    block: 'start'
    });
    }

    function setExpandedState(toggle, content, expanded) {
    if (!toggle || !content) {
        return;
    }

    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');

    if (expanded) {
        content.hidden = false;
        content.classList.add('is-open');
        return;
    }

    content.hidden = true;
    content.classList.remove('is-open');
    }

    function ensureBlockOpen(block) {
    if (!block) {
        return;
    }

    const toggle = block.querySelector(selectors.toggle || '[data-woo-feedback-toggle]');
    const content = block.querySelector(selectors.content || '.woo-feedback-content');

    if (!toggle || !content) {
        return;
    }

    const isExpanded = toggle.getAttribute('aria-expanded') === 'true';

    if (!isExpanded) {
        setExpandedState(toggle, content, true);
    }
    }

    function removeDynamicMessages(scope) {
    if (!scope) {
        return;
    }

    scope.querySelectorAll('.woo-feedback-message.is-dynamic').forEach(function (node) {
    node.remove();
    });
    }

    function renderMessage(target, type, text) {
    if (!target || !text) {
        return null;
    }

    removeDynamicMessages(target);

    const message = document.createElement('div');
    message.className = 'woo-feedback-message woo-feedback-message--' + type + ' is-dynamic';
    message.setAttribute('role', type === 'error' ? 'alert' : 'status');
    message.textContent = text;

    target.insertBefore(message, target.firstChild);

    return message;
    }

    function setSubmittingState(form, isSubmitting) {
    if (!form) {
        return;
    }

    const submitButton = form.querySelector(selectors.submitButton || '.woo-feedback-submit');

    form.classList.toggle('is-submitting', !!isSubmitting);

    if (!submitButton) {
        return;
    }

    if (!submitButton.dataset.originalText) {
        submitButton.dataset.originalText = submitButton.textContent || '';
    }

    submitButton.disabled = !!isSubmitting;
    submitButton.setAttribute('aria-disabled', isSubmitting ? 'true' : 'false');
    submitButton.textContent = isSubmitting
    ? (config.messages && config.messages.submitting ? config.messages.submitting : 'Изпращане...')
    : (submitButton.dataset.originalText || submitButton.textContent || '');
    }

    function resetTurnstile(form) {
    if (!form || !window.turnstile || typeof window.turnstile.reset !== 'function') {
        return;
    }

    const widget = form.querySelector('.cf-turnstile');

    if (!widget) {
        return;
    }

    try {
    window.turnstile.reset(widget);
    } catch (error) {
    // noop
    }
    }

    function clearReviewForm(form) {
    if (!form) {
        return;
    }

    form.reset();
    resetTurnstile(form);
    }

    function handleSuccess(block, form, responseData) {
    const formWrap = form.closest('.woo-feedback-form') || form.parentElement || form;
    const message = responseData && responseData.message
    ? responseData.message
    : (config.successMessage || '');

    renderMessage(formWrap, 'success', message);
    ensureBlockOpen(block);
    clearReviewForm(form);
    scrollBlockIntoView(block);
    }

    function handleError(block, form, responseData) {
    const formWrap = form.closest('.woo-feedback-form') || form.parentElement || form;
    const message = responseData && responseData.message
    ? responseData.message
    : (config.errorMessage || (config.messages && config.messages.networkError ? config.messages.networkError : ''));

    renderMessage(formWrap, 'error', message);
    ensureBlockOpen(block);
    resetTurnstile(form);
    scrollBlockIntoView(block);
    }

    function submitReviewForm(block, form) {
    if (!config.ajaxEnabled || !config.ajaxUrl || !config.ajaxAction || !window.fetch || !window.FormData) {
        return;
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        const formData = new FormData(form);
        formData.set('action', config.ajaxAction);

        setSubmittingState(form, true);

        fetch(config.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    })
    .then(function (response) {
    return response.json().catch(function () {
    return null;
    }).then(function (data) {
    return {
    ok: response.ok,
    status: response.status,
    data: data
    };
    });
    })
    .then(function (result) {
    const payload = result && result.data && typeof result.data === 'object'
    ? result.data
    : null;

    const responseData = payload && payload.data && typeof payload.data === 'object'
    ? payload.data
    : {};

    if (result && result.ok && payload && payload.success) {
        handleSuccess(block, form, responseData);
        return;
    }

    handleError(block, form, responseData);
    })
    .catch(function () {
    handleError(block, form, {
    message: config.messages && config.messages.networkError
    ? config.messages.networkError
    : ''
    });
    })
    .finally(function () {
    setSubmittingState(form, false);
    });
    });
    }

    document.querySelectorAll(selectors.toggle || '[data-woo-feedback-toggle]').forEach(function (toggle) {
    const targetId = toggle.getAttribute('data-target');

    if (!targetId) {
        return;
    }

    const content = document.getElementById(targetId);

    if (!content) {
        return;
    }

    toggle.addEventListener('click', function () {
    const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
    setExpandedState(toggle, content, !isExpanded);
    });
    });

    blocks.forEach(function (block) {
    const message = block.querySelector(selectors.message || '.woo-feedback-message');
    const form = block.querySelector(selectors.form || '.woo-feedback-submit-form');

    if (message) {
        ensureBlockOpen(block);
        scrollBlockIntoView(block);
    }

    if (form) {
        submitReviewForm(block, form);
    }
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
            scroll-margin-top: 24px;
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

        .woo-feedback-toggle__main {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
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

        .woo-feedback-pagination {
            display: grid;
            gap: 12px;
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
        }

        .woo-feedback-pagination__summary {
            font-size: 13px;
            line-height: 1.5;
            color: #666666;
        }

        .woo-feedback-pagination__links {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }

        .woo-feedback-pagination__link,
        .woo-feedback-pagination__dots {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 12px;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1;
            text-decoration: none;
        }

        .woo-feedback-pagination__link {
            border: 1px solid rgba(0, 0, 0, 0.12);
            background: #ffffff;
            color: #111111;
            transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        }

        .woo-feedback-pagination__link:hover {
            background: #f5f5f5;
            border-color: rgba(0, 0, 0, 0.18);
        }

        .woo-feedback-pagination__link.is-current {
            background: #111111;
            border-color: #111111;
            color: #ffffff;
            pointer-events: none;
        }

        .woo-feedback-pagination__link--prev,
        .woo-feedback-pagination__link--next {
            padding: 0 14px;
        }

        .woo-feedback-pagination__dots {
            color: #666666;
        }

        .woo-feedback-empty-message,
        .woo-feedback-login-required,
        .woo-feedback-reviews-disabled,
        .woo-feedback-message {
            padding: 14px 16px;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.5;
        }

        .woo-feedback-empty-message,
        .woo-feedback-login-required,
        .woo-feedback-reviews-disabled {
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

        .woo-feedback-form.is-submitting {
            pointer-events: none;
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

        .woo-feedback-field--turnstile {
            overflow: hidden;
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

        .woo-feedback-submit[disabled],
        .woo-feedback-submit[aria-disabled="true"] {
            opacity: 0.72;
            cursor: wait;
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

            .woo-feedback-pagination__summary {
                font-size: 12px;
            }

            .woo-feedback-pagination__links {
                gap: 6px;
            }

            .woo-feedback-pagination__link,
            .woo-feedback-pagination__dots {
                min-width: 36px;
                height: 36px;
                padding: 0 10px;
                font-size: 13px;
            }

            .woo-feedback-pagination__link--prev,
            .woo-feedback-pagination__link--next {
                padding: 0 12px;
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
