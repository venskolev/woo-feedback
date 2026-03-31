<?php
/**
 * Frontend form handler for WooFeedback.
 *
 * Uses the native WordPress/WooCommerce review model:
 * - comment on product post
 * - comment type: review
 * - rating stored in comment meta
 * - moderation via comment_approved = 0 / hold
 *
 * @package WooFeedback
 */

declare(strict_types=1);

namespace WDT\WooFeedback\Reviews;

use WDT\WooFeedback\Security\AntiSpamService;
use WDT\WooFeedback\Security\RateLimitService;
use WDT\WooFeedback\Security\TurnstileService;
use WDT\WooFeedback\Settings\Settings;
use WP_Error;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles frontend review form submissions.
 */
final class FormHandler
{
    /**
     * Frontend action key.
     */
    private const ACTION_KEY = 'submit_review';

    /**
     * AJAX action key.
     */
    private const AJAX_ACTION = 'woo_feedback_submit_review';

    /**
     * Nonce action.
     */
    private const NONCE_ACTION = 'woo_feedback_submit_review';

    /**
     * Nonce field name.
     */
    private const NONCE_FIELD = 'woo_feedback_nonce';

    /**
     * Flash state transient prefix.
     */
    private const FLASH_TRANSIENT_PREFIX = 'woo_feedback_form_state_';

    /**
     * Flash state lifetime in seconds.
     */
    private const FLASH_TTL = 300;

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
     * Anti-spam service.
     *
     * @var AntiSpamService
     */
    private AntiSpamService $anti_spam_service;

    /**
     * Rate limit service.
     *
     * @var RateLimitService
     */
    private RateLimitService $rate_limit_service;

    /**
     * Constructor.
     *
     * @param Settings         $settings           Settings service.
     * @param TurnstileService $turnstile_service  Turnstile service.
     * @param AntiSpamService  $anti_spam_service  Anti-spam service.
     * @param RateLimitService $rate_limit_service Rate limit service.
     */
    public function __construct(
        Settings $settings,
        TurnstileService $turnstile_service,
        AntiSpamService $anti_spam_service,
        RateLimitService $rate_limit_service
    ) {
        $this->settings           = $settings;
        $this->turnstile_service  = $turnstile_service;
        $this->anti_spam_service  = $anti_spam_service;
        $this->rate_limit_service = $rate_limit_service;
    }

    /**
     * Returns the AJAX action name.
     *
     * @return string
     */
    public static function get_ajax_action(): string
    {
        return self::AJAX_ACTION;
    }

    /**
     * Returns the internal submit action key used by the form payload.
     *
     * @return string
     */
    public static function get_submit_action_key(): string
    {
        return self::ACTION_KEY;
    }

    /**
     * Returns the current flash token from request, if present.
     *
     * @return string
     */
    public static function get_flash_token_from_request(): string
    {
        if (!isset($_GET['woo_feedback_form_state'])) {
            return '';
        }

        return sanitize_key((string) wp_unslash($_GET['woo_feedback_form_state']));
    }

    /**
     * Consumes flash form state for the current request token.
     *
     * @return array<string, string>
     */
    public static function consume_flash_state_from_request(): array
    {
        $token = self::get_flash_token_from_request();

        if ($token === '') {
            return [];
        }

        $transient_key = self::FLASH_TRANSIENT_PREFIX . $token;
        $state         = get_transient($transient_key);

        delete_transient($transient_key);

        if (!is_array($state)) {
            return [];
        }

        $normalized = [
            'woo_feedback_author'  => '',
            'woo_feedback_email'   => '',
            'woo_feedback_rating'  => '',
            'woo_feedback_comment' => '',
        ];

        foreach ($normalized as $key => $value) {
            if (isset($state[$key]) && is_scalar($state[$key])) {
                $normalized[$key] = (string) $state[$key];
            }
        }

        return $normalized;
    }

    /**
     * Handles incoming frontend form submission.
     *
     * @return void
     */
    public function handle_submission(): void
    {
        if (!$this->is_submission_request()) {
            return;
        }

        $result = $this->process_submission();

        if ($result['success']) {
            $this->redirect_with_status(
                (string) $result['redirect_url'],
                                        'success'
            );
        }

        $this->redirect_with_status(
            (string) $result['redirect_url'],
                                    'error',
                                    (string) $result['reason'],
                                    (array) $result['form_state']
        );
    }

    /**
     * Handles AJAX review submission.
     *
     * @return void
     */
    public function handle_ajax_submission(): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            wp_send_json_error(
                [
                    'status'       => 'error',
                    'reason'       => 'invalid_method',
                    'message'      => $this->get_error_message_for_reason('invalid_method'),
                               'redirect_url' => '',
                               'product_id'   => 0,
                               'comment_id'   => 0,
                               'form_state'   => [],
                ],
                405
            );
        }

        $ajax_action = isset($_POST['action'])
        ? sanitize_key((string) wp_unslash($_POST['action']))
        : '';

        if ($ajax_action !== self::AJAX_ACTION) {
            wp_send_json_error(
                [
                    'status'       => 'error',
                    'reason'       => 'invalid_action',
                    'message'      => $this->get_error_message_for_reason('invalid_action'),
                               'redirect_url' => '',
                               'product_id'   => 0,
                               'comment_id'   => 0,
                               'form_state'   => [],
                ],
                400
            );
        }

        $result = $this->process_submission();

        if ($result['success']) {
            wp_send_json_success(
                [
                    'status'       => 'success',
                    'reason'       => '',
                    'message'      => (string) $result['message'],
                                 'redirect_url' => (string) $result['redirect_url'],
                                 'product_id'   => (int) $result['product_id'],
                                 'comment_id'   => (int) $result['comment_id'],
                                 'form_state'   => [],
                ]
            );
        }

        wp_send_json_error(
            [
                'status'       => 'error',
                'reason'       => (string) $result['reason'],
                           'message'      => (string) $result['message'],
                           'redirect_url' => (string) $result['redirect_url'],
                           'product_id'   => (int) $result['product_id'],
                           'comment_id'   => 0,
                           'form_state'   => (array) $result['form_state'],
            ],
            400
        );
    }

    /**
     * Processes the full review submission flow and returns a normalized result.
     *
     * @return array<string, mixed>
     */
    private function process_submission(): array
    {
        $redirect_url = $this->get_redirect_url();
        $product_id   = $this->get_submitted_product_id();
        $form_state   = $this->build_form_state();

        if (!$this->verify_nonce()) {
            return $this->build_error_result($redirect_url, 'invalid_nonce', $product_id, $form_state);
        }

        if ($product_id < 1 || get_post_type($product_id) !== 'product') {
            return $this->build_error_result($redirect_url, 'invalid_product', $product_id, $form_state);
        }

        if (!$this->reviews_are_allowed($product_id)) {
            return $this->build_error_result($redirect_url, 'reviews_disabled', $product_id, $form_state);
        }

        if ($this->settings->get('require_login_for_review', 'no') === 'yes' && !is_user_logged_in()) {
            return $this->build_error_result($redirect_url, 'login_required', $product_id, $form_state);
        }

        $remote_ip = $this->get_remote_ip();
        $email     = $this->resolve_submitted_email();

        $honeypot_result = $this->anti_spam_service->validate_honeypot(
            $_POST[$this->anti_spam_service->get_honeypot_field_name()] ?? '',
                                                                       $remote_ip
        );

        if (!$this->did_security_check_pass($honeypot_result)) {
            return $this->build_error_result(
                $redirect_url,
                $this->extract_security_reason($honeypot_result, 'honeypot_rejected'),
                                             $product_id,
                                             $form_state
            );
        }

        $time_result = $this->anti_spam_service->validate_submit_time(
            $_POST[$this->anti_spam_service->get_started_at_field_name()] ?? '',
                                                                      $remote_ip
        );

        if (!$this->did_security_check_pass($time_result)) {
            return $this->build_error_result(
                $redirect_url,
                $this->extract_security_reason($time_result, 'submit_too_fast'),
                                             $product_id,
                                             $form_state
            );
        }

        $rate_limit_result = $this->rate_limit_service->is_allowed($remote_ip, $email, $product_id);

        if (!$this->did_security_check_pass($rate_limit_result)) {
            return $this->build_error_result(
                $redirect_url,
                $this->extract_security_reason($rate_limit_result, 'rate_limited'),
                                             $product_id,
                                             $form_state
            );
        }

        $turnstile_token = isset($_POST[$this->turnstile_service->get_response_field_name()])
        ? (string) wp_unslash($_POST[$this->turnstile_service->get_response_field_name()])
        : '';

        $turnstile_result = $this->turnstile_service->verify_token($turnstile_token, $remote_ip);

        if (!$this->did_security_check_pass($turnstile_result)) {
            return $this->build_error_result(
                $redirect_url,
                $this->extract_security_reason($turnstile_result, 'turnstile_failed'),
                                             $product_id,
                                             $form_state
            );
        }

        $payload = $this->build_comment_payload($product_id);

        if ($payload === null) {
            return $this->build_error_result($redirect_url, 'invalid_payload', $product_id, $form_state);
        }

        $duplicate_review_id = $this->anti_spam_service->find_recent_duplicate_review_id(
            $product_id,
            (string) ($payload['comment_author'] ?? ''),
                                                                                         (string) ($payload['comment_author_email'] ?? ''),
                                                                                         (string) ($payload['comment_content'] ?? '')
        );

        if ($duplicate_review_id > 0) {
            $this->rate_limit_service->record_attempt(
                $remote_ip,
                (string) ($payload['comment_author_email'] ?? ''),
                                                      $product_id
            );

            return $this->build_error_result($redirect_url, 'duplicate_review', $product_id, $form_state);
        }

        $comment_id = wp_new_comment($payload, true);

        if ($comment_id instanceof WP_Error || !is_numeric($comment_id)) {
            $this->rate_limit_service->record_attempt(
                $remote_ip,
                (string) ($payload['comment_author_email'] ?? ''),
                                                      $product_id
            );

            return $this->build_error_result($redirect_url, 'comment_insert_failed', $product_id, $form_state);
        }

        $comment_id = (int) $comment_id;
        $rating     = isset($_POST['woo_feedback_rating'])
        ? absint(wp_unslash($_POST['woo_feedback_rating']))
        : 0;

        if ($rating >= 1 && $rating <= 5) {
            update_comment_meta($comment_id, 'rating', $rating);
        }

        update_comment_meta($comment_id, 'verified', 0);

        $this->anti_spam_service->store_duplicate_hash(
            $comment_id,
            $product_id,
            (string) ($payload['comment_author'] ?? ''),
                                                       (string) ($payload['comment_author_email'] ?? ''),
                                                       (string) ($payload['comment_content'] ?? '')
        );

        $this->rate_limit_service->record_attempt(
            $remote_ip,
            (string) ($payload['comment_author_email'] ?? ''),
                                                  $product_id
        );

        /**
         * Fires after a WooFeedback review has been created.
         *
         * @param int   $comment_id Created comment ID.
         * @param int   $product_id Product ID.
         * @param array $payload    Inserted comment payload.
         */
        do_action('woo_feedback/review_submitted', $comment_id, $product_id, $payload);

        return [
            'success'      => true,
            'status'       => 'success',
            'reason'       => '',
            'message'      => $this->get_success_message(),
            'redirect_url' => $redirect_url,
            'product_id'   => $product_id,
            'comment_id'   => $comment_id,
            'form_state'   => [],
        ];
    }

    /**
     * Checks whether the current request is a review submission.
     *
     * @return bool
     */
    private function is_submission_request(): bool
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            return false;
        }

        if (!isset($_POST['woo_feedback_action'])) {
            return false;
        }

        $action = sanitize_key((string) wp_unslash($_POST['woo_feedback_action']));

        return $action === self::ACTION_KEY;
    }

    /**
     * Verifies request nonce.
     *
     * @return bool
     */
    private function verify_nonce(): bool
    {
        if (!isset($_POST[self::NONCE_FIELD])) {
            return false;
        }

        $nonce = (string) wp_unslash($_POST[self::NONCE_FIELD]);

        return wp_verify_nonce($nonce, self::NONCE_ACTION) !== false;
    }

    /**
     * Returns the submitted product ID.
     *
     * @return int
     */
    private function get_submitted_product_id(): int
    {
        return isset($_POST['woo_feedback_product_id'])
        ? absint(wp_unslash($_POST['woo_feedback_product_id']))
        : 0;
    }

    /**
     * Builds a sanitized flash form state.
     *
     * @return array<string, string>
     */
    private function build_form_state(): array
    {
        $state = [
            'woo_feedback_author'  => '',
            'woo_feedback_email'   => '',
            'woo_feedback_rating'  => '',
            'woo_feedback_comment' => '',
        ];

        if (!is_user_logged_in()) {
            $state['woo_feedback_author'] = isset($_POST['woo_feedback_author'])
            ? sanitize_text_field((string) wp_unslash($_POST['woo_feedback_author']))
            : '';

            $state['woo_feedback_email'] = isset($_POST['woo_feedback_email'])
            ? sanitize_email((string) wp_unslash($_POST['woo_feedback_email']))
            : '';
        }

        $rating = isset($_POST['woo_feedback_rating'])
        ? absint(wp_unslash($_POST['woo_feedback_rating']))
        : 0;

        if ($rating >= 1 && $rating <= 5) {
            $state['woo_feedback_rating'] = (string) $rating;
        }

        $state['woo_feedback_comment'] = isset($_POST['woo_feedback_comment'])
        ? trim((string) wp_unslash($_POST['woo_feedback_comment']))
        : '';

        return $state;
    }

    /**
     * Stores flash form state and returns the token.
     *
     * @param array<string, string> $form_state Sanitized form state.
     *
     * @return string
     */
    private function store_flash_state(array $form_state): string
    {
        $filtered_state = array_filter(
            $form_state,
            static fn (mixed $value): bool => is_string($value) && $value !== ''
        );

        if ($filtered_state === []) {
            return '';
        }

        $token = wp_generate_password(20, false, false);

        set_transient(
            self::FLASH_TRANSIENT_PREFIX . $token,
            $filtered_state,
            self::FLASH_TTL
        );

        return sanitize_key($token);
    }

    /**
     * Builds the native WordPress comment payload for a Woo product review.
     *
     * @param int $product_id Product ID.
     *
     * @return array<string, mixed>|null
     */
    private function build_comment_payload(int $product_id): ?array
    {
        $comment_content = isset($_POST['woo_feedback_comment'])
        ? trim((string) wp_unslash($_POST['woo_feedback_comment']))
        : '';

        if ($comment_content === '') {
            return null;
        }

        $rating = isset($_POST['woo_feedback_rating'])
        ? absint(wp_unslash($_POST['woo_feedback_rating']))
        : 0;

        if ($rating < 1 || $rating > 5) {
            return null;
        }

        $current_user = wp_get_current_user();
        $user_id      = get_current_user_id();

        $author_name  = '';
        $author_email = '';

        if ($user_id > 0 && $current_user instanceof WP_User && $current_user->exists()) {
            $author_name  = $current_user->display_name !== '' ? $current_user->display_name : $current_user->user_login;
            $author_email = (string) $current_user->user_email;
        } else {
            $author_name = isset($_POST['woo_feedback_author'])
            ? sanitize_text_field((string) wp_unslash($_POST['woo_feedback_author']))
            : '';

            $author_email = isset($_POST['woo_feedback_email'])
            ? sanitize_email((string) wp_unslash($_POST['woo_feedback_email']))
            : '';
        }

        if ($author_name === '' || $author_email === '' || !is_email($author_email)) {
            return null;
        }

        $approval_status = $this->settings->get('force_moderation', 'yes') === 'yes' ? 0 : 1;

        $payload = [
            'comment_post_ID'      => $product_id,
            'comment_author'       => $author_name,
            'comment_author_email' => $author_email,
            'comment_author_url'   => '',
            'comment_content'      => wp_kses_post($comment_content),
            'comment_type'         => 'review',
            'user_id'              => $user_id > 0 ? $user_id : 0,
            'comment_parent'       => 0,
            'comment_author_IP'    => $this->get_comment_author_ip(),
            'comment_agent'        => $this->get_user_agent(),
            'comment_approved'     => $approval_status,
        ];

        /**
         * Filters the final review payload before insert.
         *
         * @param array<string, mixed> $payload    Comment payload.
         * @param int                  $product_id Product ID.
         */
        $payload = apply_filters('woo_feedback/review_payload', $payload, $product_id);

        return is_array($payload) ? $payload : null;
    }

    /**
     * Returns a safe redirect URL back to the current page.
     *
     * @return string
     */
    private function get_redirect_url(): string
    {
        $product_id = $this->get_submitted_product_id();

        $fallback = $product_id > 0 ? get_permalink($product_id) : '';

        if (!is_string($fallback) || $fallback === '') {
            $fallback = wp_get_referer();
        }

        if (!is_string($fallback) || $fallback === '') {
            $fallback = home_url('/');
        }

        $current_url = $fallback;

        if (isset($_POST['_wp_http_referer'])) {
            $posted_referrer = (string) wp_unslash($_POST['_wp_http_referer']);

            if ($posted_referrer !== '') {
                $current_url = $posted_referrer;
            }
        }

        $current_url = remove_query_arg(
            [
                'woo_feedback_status',
                'woo_feedback_reason',
                'woo_feedback_form_state',
            ],
            $current_url
        );

        return wp_validate_redirect($current_url, $fallback);
    }

    /**
     * Redirects back with a status query arg.
     *
     * @param string               $url        Base redirect URL.
     * @param string               $status     success|error.
     * @param string               $reason     Optional error reason code.
     * @param array<string,string> $form_state Optional form state for POST fallback.
     *
     * @return void
     */
    private function redirect_with_status(
        string $url,
        string $status,
        string $reason = '',
        array $form_state = []
    ): void {
        $args = [
            'woo_feedback_status' => $status === 'success' ? 'success' : 'error',
        ];

        if ($status !== 'success' && $reason !== '') {
            $args['woo_feedback_reason'] = sanitize_key($reason);
        }

        if ($status !== 'success') {
            $flash_token = $this->store_flash_state($form_state);

            if ($flash_token !== '') {
                $args['woo_feedback_form_state'] = $flash_token;
            }
        }

        $location = add_query_arg($args, $url);

        wp_safe_redirect($location);
        exit;
    }

    /**
     * Returns whether reviews are currently allowed for the product.
     *
     * @param int $product_id Product ID.
     *
     * @return bool
     */
    private function reviews_are_allowed(int $product_id): bool
    {
        if ($product_id < 1 || get_post_type($product_id) !== 'product') {
            return false;
        }

        if (function_exists('wc_review_ratings_enabled')) {
            $global_reviews_enabled = get_option('woocommerce_enable_reviews', 'yes');

            if ($global_reviews_enabled !== 'yes') {
                return false;
            }
        }

        return comments_open($product_id);
    }

    /**
     * Resolves the submitted email value before the final payload is built.
     *
     * @return string
     */
    private function resolve_submitted_email(): string
    {
        $user_id      = get_current_user_id();
        $current_user = wp_get_current_user();

        if ($user_id > 0 && $current_user instanceof WP_User && $current_user->exists()) {
            return sanitize_email((string) $current_user->user_email);
        }

        if (!isset($_POST['woo_feedback_email'])) {
            return '';
        }

        return sanitize_email((string) wp_unslash($_POST['woo_feedback_email']));
    }

    /**
     * Returns the current visitor IP.
     *
     * @return string
     */
    private function get_remote_ip(): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';

        if ($ip === '') {
            return '';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return '';
        }

        return $ip;
    }

    /**
     * Returns the comment IP value stored with the review.
     *
     * @return string
     */
    private function get_comment_author_ip(): string
    {
        $ip = wp_privacy_anonymize_ip($this->get_remote_ip());

        return is_string($ip) ? $ip : '';
    }

    /**
     * Returns the current user agent.
     *
     * @return string
     */
    private function get_user_agent(): string
    {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT'])
        ? (string) wp_unslash($_SERVER['HTTP_USER_AGENT'])
        : '';

        $user_agent = sanitize_text_field($user_agent);

        return mb_substr($user_agent, 0, 254);
    }

    /**
     * Returns whether a security/service check result passed.
     *
     * @param mixed $result Security check result.
     *
     * @return bool
     */
    private function did_security_check_pass(mixed $result): bool
    {
        return is_array($result) && !empty($result['passed']);
    }

    /**
     * Extracts a normalized reason from a service result.
     *
     * @param mixed  $result  Security/service result.
     * @param string $default Default reason.
     *
     * @return string
     */
    private function extract_security_reason(mixed $result, string $default): string
    {
        if (!is_array($result)) {
            return $default;
        }

        $reason = isset($result['reason']) ? sanitize_key((string) $result['reason']) : '';

        return $reason !== '' ? $reason : $default;
    }

    /**
     * Builds a normalized error result array.
     *
     * @param string               $redirect_url Redirect URL.
     * @param string               $reason       Error reason code.
     * @param int                  $product_id   Product ID.
     * @param array<string,string> $form_state   Current form state.
     *
     * @return array<string, mixed>
     */
    private function build_error_result(
        string $redirect_url,
        string $reason,
        int $product_id = 0,
        array $form_state = []
    ): array {
        $reason = sanitize_key($reason);

        return [
            'success'      => false,
            'status'       => 'error',
            'reason'       => $reason,
            'message'      => $this->get_error_message_for_reason($reason),
            'redirect_url' => $redirect_url,
            'product_id'   => $product_id,
            'comment_id'   => 0,
            'form_state'   => $form_state,
        ];
    }

    /**
     * Returns the success message configured for the plugin.
     *
     * @return string
     */
    private function get_success_message(): string
    {
        $message = (string) $this->settings->get(
            'success_message',
            'Вашият отзив беше изпратен и очаква одобрение от администратор.'
        );

        return $message !== ''
        ? $message
        : 'Вашият отзив беше изпратен и очаква одобрение от администратор.';
    }

    /**
     * Returns a user-facing error message for a specific reason.
     *
     * @param string $reason Error reason code.
     *
     * @return string
     */
    private function get_error_message_for_reason(string $reason): string
    {
        $default_message = (string) $this->settings->get(
            'error_message',
            'Възникна проблем при изпращането на отзива. Моля, опитайте отново.'
        );

        $messages = [
            'invalid_method'        => 'Невалиден метод на заявка.',
            'invalid_action'        => 'Невалидно действие за изпращане.',
            'invalid_nonce'         => 'Сесията на формата е изтекла. Моля, презаредете страницата и опитайте отново.',
            'invalid_product'       => 'Невалиден продукт за отзив.',
            'reviews_disabled'      => 'Отзивите за този продукт не са разрешени.',
            'login_required'        => 'Трябва да влезете в профила си, за да изпратите отзив.',
            'honeypot_rejected'     => 'Изпращането беше блокирано от защитата срещу автоматични заявки.',
            'missing_started_at'    => 'Липсва информация за защитата на формата. Моля, презаредете страницата и опитайте отново.',
            'invalid_started_at'    => 'Невалидни данни за защитата на формата. Моля, презаредете страницата и опитайте отново.',
            'submit_too_fast'       => 'Формата беше изпратена твърде бързо. Моля, опитайте отново след момент.',
            'rate_limited'          => 'Направени са твърде много опити. Моля, изчакайте малко и опитайте отново.',
            'turnstile_failed'      => 'Проверката за сигурност не беше успешна. Моля, опитайте отново.',
            'missing_token'         => 'Моля, потвърдете проверката за сигурност.',
            'invalid_token'         => 'Невалиден токен за сигурност. Моля, опитайте отново.',
            'captcha_required'      => 'Моля, потвърдете проверката за сигурност.',
            'duplicate_review'      => 'Този отзив вече е изпратен наскоро.',
            'invalid_payload'       => 'Моля, попълнете всички задължителни полета коректно.',
            'comment_insert_failed' => 'Неуспешно записване на отзива. Моля, опитайте отново.',
        ];

        if (isset($messages[$reason]) && $messages[$reason] !== '') {
            return $messages[$reason];
        }

        return $default_message !== ''
        ? $default_message
        : 'Възникна проблем при изпращането на отзива. Моля, опитайте отново.';
    }
}
