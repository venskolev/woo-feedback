<?php
/**
 * Review moderation service for WooFeedback.
 *
 * Keeps WooCommerce/WordPress native review behavior,
 * but enforces moderation rules for reviews submitted
 * through the WooFeedback frontend layer.
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
 * Handles moderation logic for product reviews.
 */
final class ReviewModeration
{
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
     * Registers moderation-related filters.
     *
     * @return void
     */
    public function register_comment_filters(): void
    {
        add_filter('pre_comment_approved', [$this, 'filter_review_approval'], 10, 2);
        add_filter('preprocess_comment', [$this, 'normalize_review_comment_data'], 10, 1);
    }

    /**
     * Enforces approval status for WooFeedback product reviews.
     *
     * If moderation is enabled, every new product review created through the
     * frontend flow will be stored as pending.
     *
     * @param string|int $approved    Proposed approval status.
     * @param array      $commentdata Raw comment data.
     *
     * @return string|int
     */
    public function filter_review_approval(string|int $approved, array $commentdata): string|int
    {
        if (!$this->is_frontend_woo_feedback_submission()) {
            return $approved;
        }

        if (!$this->is_product_review_comment_data($commentdata)) {
            return $approved;
        }

        if ($this->settings->get('force_moderation', 'yes') !== 'yes') {
            return $approved;
        }

        return 0;
    }

    /**
     * Normalizes native WordPress comment payload for Woo product reviews.
     *
     * @param array<string, mixed> $commentdata Comment data.
     *
     * @return array<string, mixed>
     */
    public function normalize_review_comment_data(array $commentdata): array
    {
        if (!$this->is_frontend_woo_feedback_submission()) {
            return $commentdata;
        }

        if (!$this->is_product_review_comment_data($commentdata)) {
            return $commentdata;
        }

        $commentdata['comment_type'] = 'review';

        if ($this->settings->get('force_moderation', 'yes') === 'yes') {
            $commentdata['comment_approved'] = 0;
        }

        return $commentdata;
    }

    /**
     * Final safeguard after the comment is created.
     *
     * @param int               $comment_id       Comment ID.
     * @param string|int        $comment_approved Comment approval status.
     * @param array<string,mixed> $commentdata    Raw comment payload.
     *
     * @return void
     */
    public function flag_new_review_for_moderation(int $comment_id, string|int $comment_approved, array $commentdata): void
    {
        if ($comment_id < 1) {
            return;
        }

        if (!$this->is_product_review_comment_data($commentdata)) {
            return;
        }

        update_comment_meta($comment_id, 'woo_feedback_source', 'frontend_form');

        if ($this->settings->get('force_moderation', 'yes') !== 'yes') {
            return;
        }

        if ((string) $comment_approved === 'spam' || (string) $comment_approved === 'trash') {
            return;
        }

        $current_status = wp_get_comment_status($comment_id);

        if ($current_status === 'hold') {
            return;
        }

        wp_set_comment_status($comment_id, 'hold');

        /**
         * Fires when WooFeedback forces a newly created review into moderation.
         *
         * @param int                  $comment_id Comment ID.
         * @param array<string, mixed> $commentdata Comment data.
         */
        do_action('woo_feedback/review_marked_pending', $comment_id, $commentdata);
    }

    /**
     * Checks whether the current request comes from the WooFeedback frontend form.
     *
     * @return bool
     */
    private function is_frontend_woo_feedback_submission(): bool
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            return false;
        }

        if (!isset($_POST['woo_feedback_action'])) {
            return false;
        }

        $action = sanitize_key((string) wp_unslash($_POST['woo_feedback_action']));

        return $action === 'submit_review';
    }

    /**
     * Checks if the comment payload targets a WooCommerce product review.
     *
     * @param array<string, mixed> $commentdata Comment data.
     *
     * @return bool
     */
    private function is_product_review_comment_data(array $commentdata): bool
    {
        $post_id = isset($commentdata['comment_post_ID']) ? absint($commentdata['comment_post_ID']) : 0;

        if ($post_id < 1) {
            return false;
        }

        if (get_post_type($post_id) !== 'product') {
            return false;
        }

        $comment_type = isset($commentdata['comment_type']) && is_scalar($commentdata['comment_type'])
        ? (string) $commentdata['comment_type']
        : 'review';

        return $comment_type === '' || $comment_type === 'review';
    }
}
