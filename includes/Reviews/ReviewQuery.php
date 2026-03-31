<?php
/**
 * Review query service for WooFeedback.
 *
 * Ensures the dedicated admin page only works with native
 * WooCommerce product reviews and never mixes unrelated comments.
 *
 * @package WooFeedback
 */

declare(strict_types=1);

namespace WDT\WooFeedback\Reviews;

use WDT\WooFeedback\Settings\Settings;
use WP_Comment_Query;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles admin-side review queries.
 */
final class ReviewQuery
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
     * Filters admin comment queries related to WooFeedback screens.
     *
     * @param WP_Comment_Query $query Comment query instance.
     *
     * @return void
     */
    public function filter_admin_comment_queries(WP_Comment_Query $query): void
    {
        if (!is_admin()) {
            return;
        }

        if (!$this->is_woo_feedback_request()) {
            return;
        }

        $query->query_vars['type'] = 'review';
        $query->query_vars['post_type'] = 'product';
        $query->query_vars['hierarchical'] = false;

        $status = $this->get_requested_status();
        if ($status !== 'all') {
            $query->query_vars['status'] = $this->map_status($status);
        }

        $search = $this->get_requested_search();
        if ($search !== '') {
            $query->query_vars['search'] = $search;
        }

        $orderby = $this->get_requested_orderby();
        $order   = $this->get_requested_order();

        $query->query_vars['orderby'] = $orderby;
        $query->query_vars['order']   = $order;
    }

    /**
     * Checks whether the current admin request belongs to WooFeedback.
     *
     * @return bool
     */
    private function is_woo_feedback_request(): bool
    {
        $page = isset($_REQUEST['page']) ? sanitize_key((string) wp_unslash($_REQUEST['page'])) : '';

        return in_array($page, ['woo-feedback-reviews', 'woo-feedback-settings'], true);
    }

    /**
     * Returns requested review status filter.
     *
     * @return string
     */
    private function get_requested_status(): string
    {
        $status = isset($_REQUEST['status']) ? sanitize_key((string) wp_unslash($_REQUEST['status'])) : 'all';

        if (!in_array($status, ['all', 'hold', 'approve', 'trash', 'spam'], true)) {
            return 'all';
        }

        return $status;
    }

    /**
     * Returns requested search value.
     *
     * @return string
     */
    private function get_requested_search(): string
    {
        $search = isset($_REQUEST['s']) ? sanitize_text_field((string) wp_unslash($_REQUEST['s'])) : '';

        return trim($search);
    }

    /**
     * Returns requested orderby value.
     *
     * @return string
     */
    private function get_requested_orderby(): string
    {
        $orderby = isset($_REQUEST['orderby']) ? sanitize_key((string) wp_unslash($_REQUEST['orderby'])) : 'comment_date_gmt';

        $allowed = [
            'comment_date',
            'comment_date_gmt',
            'comment_post_ID',
            'comment_author',
            'comment_approved',
        ];

        if (!in_array($orderby, $allowed, true)) {
            return 'comment_date_gmt';
        }

        return $orderby;
    }

    /**
     * Returns requested order direction.
     *
     * @return string
     */
    private function get_requested_order(): string
    {
        $order = isset($_REQUEST['order']) ? strtoupper(sanitize_text_field((string) wp_unslash($_REQUEST['order']))) : 'DESC';

        return $order === 'ASC' ? 'ASC' : 'DESC';
    }

    /**
     * Maps local admin filter status to WP comment query status.
     *
     * @param string $status Local status.
     *
     * @return string
     */
    private function map_status(string $status): string
    {
        return match ($status) {
            'hold'    => 'hold',
            'approve' => 'approve',
            'trash'   => 'trash',
            'spam'    => 'spam',
            default   => 'all',
        };
    }
}
