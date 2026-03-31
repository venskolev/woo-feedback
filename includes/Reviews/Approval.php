<?php
/**
 * Review approval helpers for WooFeedback.
 *
 * Centralizes small moderation conveniences for native
 * WooCommerce product reviews in the standard comments screen.
 *
 * @package WooFeedback
 */

declare(strict_types=1);

namespace WDT\WooFeedback\Reviews;

use WP_Comment;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles quick moderation row actions for native review rows.
 */
final class Approval
{
    /**
     * Filters row actions on the native comments screen.
     *
     * @param array<string, string> $actions Existing row actions.
     * @param WP_Comment            $comment Current comment object.
     *
     * @return array<string, string>
     */
    public function filter_row_actions(array $actions, WP_Comment $comment): array
    {
        if (!is_admin()) {
            return $actions;
        }

        if (!$this->is_product_review($comment)) {
            return $actions;
        }

        $comment_id = (int) $comment->comment_ID;

        if ($comment_id < 1) {
            return $actions;
        }

        $custom = [];

        if ((string) $comment->comment_approved !== '1') {
            $approve_url = wp_nonce_url(
                add_query_arg(
                    [
                        'action' => 'approvecomment',
                        'c'      => $comment_id,
                    ],
                    admin_url('comment.php')
                ),
                "approve-comment_{$comment_id}"
            );

            $custom['woo_feedback_approve'] = sprintf(
                '<a href="%1$s" aria-label="%2$s">%3$s</a>',
                esc_url($approve_url),
                                                      esc_attr(sprintf(__('Одобряване на отзив #%d', 'woo-feedback'), $comment_id)),
                                                      esc_html__('Одобри', 'woo-feedback')
            );
        }

        if ((string) $comment->comment_approved !== 'trash') {
            $trash_url = wp_nonce_url(
                add_query_arg(
                    [
                        'action' => 'trashcomment',
                        'c'      => $comment_id,
                    ],
                    admin_url('comment.php')
                ),
                "delete-comment_{$comment_id}"
            );

            $custom['woo_feedback_trash'] = sprintf(
                '<a href="%1$s" aria-label="%2$s">%3$s</a>',
                esc_url($trash_url),
                                                    esc_attr(sprintf(__('Преместване на отзив #%d в кошчето', 'woo-feedback'), $comment_id)),
                                                    esc_html__('Кошче', 'woo-feedback')
            );
        }

        $delete_url = wp_nonce_url(
            add_query_arg(
                [
                    'action' => 'deletecomment',
                    'c'      => $comment_id,
                ],
                admin_url('comment.php')
            ),
            "delete-comment_{$comment_id}"
        );

        $custom['woo_feedback_delete'] = sprintf(
            '<a href="%1$s" aria-label="%2$s" style="color:#b32d2e;">%3$s</a>',
            esc_url($delete_url),
                                                 esc_attr(sprintf(__('Изтриване на отзив #%d', 'woo-feedback'), $comment_id)),
                                                 esc_html__('Изтрий', 'woo-feedback')
        );

        $ordered = [];

        if (isset($actions['approve'])) {
            unset($actions['approve']);
        }

        if (isset($actions['trash'])) {
            unset($actions['trash']);
        }

        if (isset($actions['delete'])) {
            unset($actions['delete']);
        }

        foreach ($custom as $key => $value) {
            $ordered[$key] = $value;
        }

        foreach ($actions as $key => $value) {
            $ordered[$key] = $value;
        }

        return $ordered;
    }

    /**
     * Checks whether the given comment is a WooCommerce product review.
     *
     * @param WP_Comment $comment Comment object.
     *
     * @return bool
     */
    private function is_product_review(WP_Comment $comment): bool
    {
        $post_id = (int) $comment->comment_post_ID;

        if ($post_id < 1) {
            return false;
        }

        if (get_post_type($post_id) !== 'product') {
            return false;
        }

        $comment_type = is_string($comment->comment_type) ? $comment->comment_type : '';

        return $comment_type === 'review' || $comment_type === '';
    }
}
