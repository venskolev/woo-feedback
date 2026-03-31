<?php
/**
 * Review columns integration for WooFeedback.
 *
 * Enhances the native WordPress comments list with
 * clearer product review context while staying fully
 * compatible with the standard WooCommerce review model.
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
 * Handles additional columns for native review rows.
 */
final class ReviewColumns
{
    /**
     * Registers custom columns for the comments list table.
     *
     * @param array<string, string> $columns Existing columns.
     *
     * @return array<string, string>
     */
    public function register_columns(array $columns): array
    {
        if (!$this->is_comments_admin_screen()) {
            return $columns;
        }

        $normalized = [];

        foreach ($columns as $key => $label) {
            $normalized[$key] = $label;

            if ($key === 'comment') {
                $normalized['woo_feedback_product'] = __('Продукт', 'woo-feedback');
                $normalized['woo_feedback_rating']  = __('Оценка', 'woo-feedback');
                $normalized['woo_feedback_status']  = __('Статус', 'woo-feedback');
            }
        }

        if (!isset($normalized['woo_feedback_product'])) {
            $normalized['woo_feedback_product'] = __('Продукт', 'woo-feedback');
        }

        if (!isset($normalized['woo_feedback_rating'])) {
            $normalized['woo_feedback_rating'] = __('Оценка', 'woo-feedback');
        }

        if (!isset($normalized['woo_feedback_status'])) {
            $normalized['woo_feedback_status'] = __('Статус', 'woo-feedback');
        }

        return $normalized;
    }

    /**
     * Renders the custom column value.
     *
     * @param string $column  Column name.
     * @param int    $comment_id Comment ID.
     *
     * @return void
     */
    public function render_column(string $column, int $comment_id): void
    {
        if (!$this->is_comments_admin_screen()) {
            return;
        }

        if (!in_array($column, ['woo_feedback_product', 'woo_feedback_rating', 'woo_feedback_status'], true)) {
            return;
        }

        $comment = get_comment($comment_id);

        if (!$comment instanceof WP_Comment) {
            echo '—';
            return;
        }

        if (!$this->is_product_review($comment)) {
            echo '—';
            return;
        }

        switch ($column) {
            case 'woo_feedback_product':
                $this->render_product_column($comment);
                break;

            case 'woo_feedback_rating':
                $this->render_rating_column($comment);
                break;

            case 'woo_feedback_status':
                $this->render_status_column($comment);
                break;
        }
    }

    /**
     * Renders the product column.
     *
     * @param WP_Comment $comment Review comment.
     *
     * @return void
     */
    private function render_product_column(WP_Comment $comment): void
    {
        $product_id    = (int) $comment->comment_post_ID;
        $product_title = get_the_title($product_id);
        $edit_link     = get_edit_post_link($product_id);

        if ($product_title === '') {
            echo esc_html__('Непознат продукт', 'woo-feedback');
            return;
        }

        if (is_string($edit_link) && $edit_link !== '') {
            printf(
                '<a href="%1$s">%2$s</a>',
                esc_url($edit_link),
                   esc_html($product_title)
            );
            return;
        }

        echo esc_html($product_title);
    }

    /**
     * Renders the rating column.
     *
     * @param WP_Comment $comment Review comment.
     *
     * @return void
     */
    private function render_rating_column(WP_Comment $comment): void
    {
        $rating_meta = get_comment_meta($comment->comment_ID, 'rating', true);
        $rating      = is_scalar($rating_meta) ? (int) $rating_meta : 0;

        if ($rating < 1 || $rating > 5) {
            echo '—';
            return;
        }

        $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);

        printf(
            '<span aria-label="%1$s">%2$s</span>',
            esc_attr(sprintf(__('Оценка %d от 5', 'woo-feedback'), $rating)),
               esc_html($stars)
        );
    }

    /**
     * Renders the status column.
     *
     * @param WP_Comment $comment Review comment.
     *
     * @return void
     */
    private function render_status_column(WP_Comment $comment): void
    {
        $status = $this->get_status_label((string) $comment->comment_approved);

        echo esc_html($status);
    }

    /**
     * Returns a human-readable status label.
     *
     * @param string $status Raw approval status.
     *
     * @return string
     */
    private function get_status_label(string $status): string
    {
        return match ($status) {
            '1'       => __('Одобрен', 'woo-feedback'),
            '0',
            'hold'    => __('Чака одобрение', 'woo-feedback'),
            'trash'   => __('В кошчето', 'woo-feedback'),
            'spam'    => __('Спам', 'woo-feedback'),
            default   => __('Неизвестен', 'woo-feedback'),
        };
    }

    /**
     * Checks whether the current admin page is the native comments screen.
     *
     * @return bool
     */
    private function is_comments_admin_screen(): bool
    {
        if (!is_admin()) {
            return false;
        }

        global $pagenow;

        return is_string($pagenow) && $pagenow === 'edit-comments.php';
    }

    /**
     * Checks if the given comment is a WooCommerce product review.
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
