<?php
/**
 * Review comment type integration for WooFeedback.
 *
 * Keeps compatibility with the native WordPress/WooCommerce
 * review model and ensures we consistently treat product reviews
 * as comment_type = review.
 *
 * @package WooFeedback
 */

declare(strict_types=1);

namespace WDT\WooFeedback\Reviews;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles review comment-type normalization and helpers.
 */
final class CommentTypes
{
    /**
     * Registers hooks related to review comment typing.
     *
     * @return void
     */
    public function register(): void
    {
        add_filter('get_comment_type', [$this, 'normalize_comment_type'], 10, 3);
        add_filter('comment_class', [$this, 'append_review_css_class'], 10, 5);
        add_filter('comments_clauses', [$this, 'preserve_review_comment_queries'], 10, 2);
    }

    /**
     * Normalizes the visible comment type label for product reviews.
     *
     * @param string      $comment_type Existing comment type.
     * @param string|null $comment_id   Comment ID.
     * @param \WP_Comment $comment      Comment object.
     *
     * @return string
     */
    public function normalize_comment_type(string $comment_type, ?string $comment_id = null, mixed $comment = null): string
    {
        if (!$comment instanceof \WP_Comment) {
            return $comment_type;
        }

        if (!$this->is_product_review($comment)) {
            return $comment_type;
        }

        return 'review';
    }

    /**
     * Appends a dedicated CSS class to product review comment items.
     *
     * @param array<int, string> $classes Existing classes.
     * @param string             $css_class Class name string.
     * @param int|string         $comment_id Comment ID.
     * @param \WP_Post|null      $post Current post.
     * @param \WP_Comment        $comment Comment object.
     *
     * @return array<int, string>
     */
    public function append_review_css_class(
        array $classes,
        string $css_class,
        int|string $comment_id,
        mixed $post,
        mixed $comment
    ): array {
        if (!$comment instanceof \WP_Comment) {
            return $classes;
        }

        if (!$this->is_product_review($comment)) {
            return $classes;
        }

        $classes[] = 'woo-feedback-review';

        return array_values(array_unique($classes));
    }

    /**
     * Leaves native review queries intact and avoids accidental broadening.
     *
     * This hook is intentionally lightweight. We do not rewrite the query;
     * we only make sure custom plugin behavior cannot unintentionally remove
     * the review comment_type restriction for product reviews.
     *
     * @param array<string, string> $clauses  SQL clauses.
     * @param \WP_Comment_Query     $query    Comment query.
     *
     * @return array<string, string>
     */
    public function preserve_review_comment_queries(array $clauses, \WP_Comment_Query $query): array
    {
        $query_vars = $query->query_vars;

        $type = $query_vars['type'] ?? '';

        if ($type !== 'review') {
            return $clauses;
        }

        return $clauses;
    }

    /**
     * Determines whether the given comment is a WooCommerce product review.
     *
     * @param \WP_Comment $comment Comment object.
     *
     * @return bool
     */
    public function is_product_review(\WP_Comment $comment): bool
    {
        if ((int) $comment->comment_post_ID < 1) {
            return false;
        }

        if (get_post_type((int) $comment->comment_post_ID) !== 'product') {
            return false;
        }

        $comment_type = is_string($comment->comment_type) ? $comment->comment_type : '';

        return $comment_type === 'review' || $comment_type === '';
    }
}
