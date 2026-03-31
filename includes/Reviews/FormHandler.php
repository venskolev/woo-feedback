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

use WDT\WooFeedback\Settings\Settings;

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
     * Nonce action.
     */
    private const NONCE_ACTION = 'woo_feedback_submit_review';

    /**
     * Nonce field name.
     */
    private const NONCE_FIELD = 'woo_feedback_nonce';

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
     * Handles incoming frontend form submission.
     *
     * @return void
     */
    public function handle_submission(): void
    {
        if (!$this->is_submission_request()) {
            return;
        }

        $redirect_url = $this->get_redirect_url();

        if (!$this->verify_nonce()) {
            $this->redirect_with_status($redirect_url, 'error');
        }

        $product_id = isset($_POST['woo_feedback_product_id']) ? absint($_POST['woo_feedback_product_id']) : 0;

        if ($product_id < 1 || get_post_type($product_id) !== 'product') {
            $this->redirect_with_status($redirect_url, 'error');
        }

        if ($this->settings->get('require_login_for_review', 'no') === 'yes' && !is_user_logged_in()) {
            $this->redirect_with_status($redirect_url, 'error');
        }

        $payload = $this->build_comment_payload($product_id);

        if ($payload === null) {
            $this->redirect_with_status($redirect_url, 'error');
        }

        $comment_id = wp_new_comment($payload, true);

        if (is_wp_error($comment_id) || !is_numeric($comment_id)) {
            $this->redirect_with_status($redirect_url, 'error');
        }

        $comment_id = (int) $comment_id;

        $rating = isset($_POST['woo_feedback_rating']) ? absint($_POST['woo_feedback_rating']) : 0;

        if ($rating >= 1 && $rating <= 5) {
            update_comment_meta($comment_id, 'rating', $rating);
        }

        update_comment_meta($comment_id, 'verified', 0);

        /**
         * Fires after a WooFeedback review has been created.
         *
         * @param int   $comment_id Created comment ID.
         * @param int   $product_id Product ID.
         * @param array $payload    Inserted comment payload.
         */
        do_action('woo_feedback/review_submitted', $comment_id, $product_id, $payload);

        $this->redirect_with_status($redirect_url, 'success');
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

        return wp_verify_nonce($nonce, self::NONCE_ACTION) === 1;
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

        $rating = isset($_POST['woo_feedback_rating']) ? absint($_POST['woo_feedback_rating']) : 0;

        if ($rating < 1 || $rating > 5) {
            return null;
        }

        $current_user = wp_get_current_user();
        $user_id      = get_current_user_id();

        $author_name  = '';
        $author_email = '';

        if ($user_id > 0 && $current_user instanceof \WP_User && $current_user->exists()) {
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
            'comment_author_IP'    => $this->get_user_ip(),
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
        $fallback = wp_get_referer();

        if (!is_string($fallback) || $fallback === '') {
            $fallback = home_url('/');
        }

        $current_url = $fallback;

        if (isset($_POST['_wp_http_referer'])) {
            $posted_referrer = wp_unslash($_POST['_wp_http_referer']);

            if (is_string($posted_referrer) && $posted_referrer !== '') {
                $current_url = $posted_referrer;
            }
        }

        $current_url = remove_query_arg(
            [
                'woo_feedback_status',
            ],
            $current_url
        );

        return wp_validate_redirect($current_url, home_url('/'));
    }

    /**
     * Redirects back with a status query arg.
     *
     * @param string $url    Base redirect URL.
     * @param string $status success|error
     *
     * @return void
     */
    private function redirect_with_status(string $url, string $status): void
    {
        $location = add_query_arg(
            [
                'woo_feedback_status' => $status === 'success' ? 'success' : 'error',
            ],
            $url
        );

        wp_safe_redirect($location);
        exit;
    }

    /**
     * Returns the current visitor IP.
     *
     * @return string
     */
    private function get_user_ip(): string
    {
        $ip = wp_privacy_anonymize_ip((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

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
}
