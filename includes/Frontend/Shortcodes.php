<?php
/**
 * Frontend shortcodes for WooFeedback.
 *
 * @package WooFeedback
 */

declare(strict_types=1);

namespace WDT\WooFeedback\Frontend;

use WDT\WooFeedback\Reviews\FormHandler;
use WDT\WooFeedback\Security\AntiSpamService;
use WDT\WooFeedback\Security\TurnstileService;
use WDT\WooFeedback\Settings\Settings;
use WP_Comment;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers and renders frontend shortcodes.
 */
final class Shortcodes
{
    /**
     * Main shortcode tag.
     */
    private const SHORTCODE_TAG = 'woo_feedback';

    /**
     * Default reviews per page.
     */
    private const DEFAULT_REVIEWS_PER_PAGE = 8;

    /**
     * Maximum allowed reviews per page from shortcode attribute.
     */
    private const MAX_REVIEWS_PER_PAGE = 50;

    /**
     * Cache group.
     */
    private const CACHE_GROUP = 'woo_feedback';

    /**
     * Cache key prefix for review count.
     */
    private const CACHE_KEY_COUNT_PREFIX = 'review_count_';

    /**
     * Cache key prefix for paginated review list.
     */
    private const CACHE_KEY_LIST_PREFIX = 'review_list_';

    /**
     * Cache key prefix for transient index.
     */
    private const CACHE_INDEX_PREFIX = 'review_cache_index_';

    /**
     * Cache TTL in seconds.
     */
    private const CACHE_TTL = 300;

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
     * Constructor.
     *
     * @param Settings         $settings          Settings service.
     * @param TurnstileService $turnstile_service Turnstile service.
     * @param AntiSpamService  $anti_spam_service Anti-spam service.
     */
    public function __construct(
        Settings $settings,
        TurnstileService $turnstile_service,
        AntiSpamService $anti_spam_service
    ) {
        $this->settings          = $settings;
        $this->turnstile_service = $turnstile_service;
        $this->anti_spam_service = $anti_spam_service;
    }

    /**
     * Registers supported shortcodes and frontend integrations.
     *
     * @return void
     */
    public function register(): void
    {
        if ($this->settings->get('enable_shortcode', 'yes') === 'yes') {
            add_shortcode(self::SHORTCODE_TAG, [$this, 'render_shortcode']);
        }

        if ($this->settings->get('auto_hide_woocommerce_tab', 'no') === 'yes') {
            add_filter('woocommerce_product_tabs', [$this, 'maybe_remove_default_reviews_tab'], 98);
        }

        add_action('comment_post', [$this, 'invalidate_review_cache_on_comment_change'], 20, 3);
        add_action('edit_comment', [$this, 'invalidate_review_cache_on_comment_update'], 20, 1);
        add_action('deleted_comment', [$this, 'invalidate_review_cache_on_comment_update'], 20, 1);
        add_action('trashed_comment', [$this, 'invalidate_review_cache_on_comment_update'], 20, 1);
        add_action('untrashed_comment', [$this, 'invalidate_review_cache_on_comment_update'], 20, 1);
        add_action('spam_comment', [$this, 'invalidate_review_cache_on_comment_update'], 20, 1);
        add_action('unspam_comment', [$this, 'invalidate_review_cache_on_comment_update'], 20, 1);
        add_action('wp_set_comment_status', [$this, 'invalidate_review_cache_on_status_change'], 20, 2);
        add_action('added_comment_meta', [$this, 'invalidate_review_cache_on_meta_change'], 20, 4);
        add_action('updated_comment_meta', [$this, 'invalidate_review_cache_on_meta_change'], 20, 4);
        add_action('deleted_comment_meta', [$this, 'invalidate_review_cache_on_meta_change'], 20, 4);
        add_action('woo_feedback/review_submitted', [$this, 'invalidate_review_cache_after_submit'], 20, 2);
    }

    /**
     * Renders the main reviews shortcode.
     *
     * Supported attributes:
     * - product_id / id
     * - title
     * - show_form: yes|no
     * - collapsed: yes|no
     * - show_count: yes|no
     * - button_text
     * - empty_message
     * - reviews_per_page
     *
     * @param array<string, mixed> $atts Shortcode attributes.
     *
     * @return string
     */
    public function render_shortcode(array $atts = []): string
    {
        $product_id = $this->resolve_product_id($atts);

        if ($product_id < 1) {
            return '';
        }

        $defaults = [
            'title'            => (string) $this->settings->get('default_shortcode_title', 'Отзиви'),
            'show_form'        => (string) $this->settings->get('show_review_form_default', 'no'),
            'collapsed'        => 'yes',
            'show_count'       => 'yes',
            'button_text'      => (string) $this->settings->get('default_shortcode_title', 'Отзиви'),
            'empty_message'    => (string) $this->settings->get('empty_reviews_message', 'Все още няма отзиви.'),
            'reviews_per_page' => (string) self::DEFAULT_REVIEWS_PER_PAGE,
        ];

        $atts = shortcode_atts($defaults, $atts, self::SHORTCODE_TAG);

        $title                = sanitize_text_field((string) $atts['title']);
        $show_form            = $this->normalize_yes_no($atts['show_form']);
        $collapsed            = $this->normalize_yes_no($atts['collapsed']);
        $show_count           = $this->normalize_yes_no($atts['show_count']);
        $button_text          = sanitize_text_field((string) $atts['button_text']);
        $empty_message        = sanitize_text_field((string) $atts['empty_message']);
        $reviews_per_page     = $this->normalize_reviews_per_page($atts['reviews_per_page']);
        $wrapper_id           = $this->get_wrapper_id($product_id);
        $content_id           = $wrapper_id . '-content';
        $message_state        = $this->get_frontend_message_state();
        $has_frontend_message = $message_state['status'] !== '';
        $is_expanded          = $collapsed === 'no' || $has_frontend_message;
        $reviews_are_allowed  = $this->reviews_are_allowed($product_id);
        $review_count         = $this->get_product_review_count($product_id);
        $current_page         = $this->get_current_reviews_page($product_id);
        $total_pages          = $review_count > 0 ? (int) ceil($review_count / $reviews_per_page) : 1;
        $current_page         = max(1, min($current_page, $total_pages));
        $reviews              = $this->get_product_reviews($product_id, $current_page, $reviews_per_page);

        ob_start();
        ?>
        <div
        class="woo-feedback-block"
        id="<?php echo esc_attr($wrapper_id); ?>"
        data-product-id="<?php echo esc_attr((string) $product_id); ?>"
        data-woo-feedback-anchor="<?php echo esc_attr('#' . $wrapper_id); ?>"
        >
        <button
        type="button"
        class="woo-feedback-toggle"
        data-woo-feedback-toggle
        data-target="<?php echo esc_attr($content_id); ?>"
        aria-controls="<?php echo esc_attr($content_id); ?>"
        aria-expanded="<?php echo $is_expanded ? 'true' : 'false'; ?>"
        >
        <span class="woo-feedback-toggle__main">
        <span class="woo-feedback-toggle__label">
        <?php echo esc_html($button_text !== '' ? $button_text : 'Отзиви'); ?>
        </span>

        <?php if ($show_count === 'yes') : ?>
        <span class="woo-feedback-toggle__badge">
        <?php echo esc_html((string) $review_count); ?>
        </span>
        <?php endif; ?>
        </span>

        <span class="woo-feedback-toggle__icon" aria-hidden="true">▾</span>
        </button>

        <div
        id="<?php echo esc_attr($content_id); ?>"
        class="woo-feedback-content<?php echo $is_expanded ? ' is-open' : ''; ?>"
        <?php echo $is_expanded ? '' : 'hidden'; ?>
        >
        <?php if ($title !== '') : ?>
        <h3 class="woo-feedback-title"><?php echo esc_html($title); ?></h3>
        <?php endif; ?>

        <div class="woo-feedback-reviews">
        <?php if (!empty($reviews)) : ?>
        <ul class="woo-feedback-review-list">
        <?php foreach ($reviews as $review) : ?>
        <?php $this->render_single_review($review); ?>
        <?php endforeach; ?>
        </ul>

        <?php
        echo $this->render_reviews_pagination(
            $product_id,
            $current_page,
            $total_pages,
            $reviews_per_page,
            $review_count,
            $wrapper_id
        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
        <?php else : ?>
        <p class="woo-feedback-empty-message">
        <?php echo esc_html($empty_message); ?>
        </p>
        <?php endif; ?>
        </div>

        <?php if ($show_form === 'yes') : ?>
        <div class="woo-feedback-form-wrap">
        <?php echo $this->render_form($product_id, $reviews_are_allowed, $wrapper_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <?php endif; ?>
        </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Optionally removes the default WooCommerce reviews tab.
     *
     * @param array<string, mixed> $tabs Product tabs.
     *
     * @return array<string, mixed>
     */
    public function maybe_remove_default_reviews_tab(array $tabs): array
    {
        if (isset($tabs['reviews'])) {
            unset($tabs['reviews']);
        }

        return $tabs;
    }

    /**
     * Invalidates product review cache after frontend submit.
     *
     * @param int $comment_id Created comment ID.
     * @param int $product_id Product ID.
     *
     * @return void
     */
    public function invalidate_review_cache_after_submit(int $comment_id, int $product_id): void
    {
        unset($comment_id);

        if ($product_id < 1) {
            return;
        }

        $this->invalidate_product_review_cache($product_id);
    }

    /**
     * Invalidates review cache when a comment is created.
     *
     * @param int        $comment_id       Comment ID.
     * @param int|string $comment_approved Approval state.
     * @param array      $commentdata      Comment payload.
     *
     * @return void
     */
    public function invalidate_review_cache_on_comment_change(int $comment_id, $comment_approved, array $commentdata): void
    {
        unset($comment_approved);

        $product_id = isset($commentdata['comment_post_ID']) ? absint($commentdata['comment_post_ID']) : 0;

        if ($product_id < 1) {
            $product_id = $this->get_product_id_from_comment_id($comment_id);
        }

        if ($product_id < 1) {
            return;
        }

        $this->invalidate_product_review_cache($product_id);
    }

    /**
     * Invalidates review cache when a comment is updated or removed.
     *
     * @param int $comment_id Comment ID.
     *
     * @return void
     */
    public function invalidate_review_cache_on_comment_update(int $comment_id): void
    {
        $product_id = $this->get_product_id_from_comment_id($comment_id);

        if ($product_id < 1) {
            return;
        }

        $this->invalidate_product_review_cache($product_id);
    }

    /**
     * Invalidates review cache when a comment status changes.
     *
     * @param int    $comment_id Comment ID.
     * @param string $status     New status.
     *
     * @return void
     */
    public function invalidate_review_cache_on_status_change(int $comment_id, string $status): void
    {
        unset($status);

        $product_id = $this->get_product_id_from_comment_id($comment_id);

        if ($product_id < 1) {
            return;
        }

        $this->invalidate_product_review_cache($product_id);
    }

    /**
     * Invalidates review cache when rating or relevant meta changes.
     *
     * @param int    $meta_id     Meta ID.
     * @param int    $comment_id  Comment ID.
     * @param string $meta_key    Meta key.
     * @param mixed  $meta_value  Meta value.
     *
     * @return void
     */
    public function invalidate_review_cache_on_meta_change(int $meta_id, int $comment_id, string $meta_key, $meta_value): void
    {
        unset($meta_id, $meta_value);

        if ($meta_key !== 'rating' && $meta_key !== 'verified') {
            return;
        }

        $product_id = $this->get_product_id_from_comment_id($comment_id);

        if ($product_id < 1) {
            return;
        }

        $this->invalidate_product_review_cache($product_id);
    }

    /**
     * Renders a single review list item.
     *
     * @param WP_Comment $review Review object.
     *
     * @return void
     */
    private function render_single_review(WP_Comment $review): void
    {
        $author      = $review->comment_author !== '' ? $review->comment_author : __('Анонимен', 'woo-feedback');
        $date        = mysql2date(get_option('date_format'), $review->comment_date);
        $rating_meta = get_comment_meta($review->comment_ID, 'rating', true);
        $rating      = is_scalar($rating_meta) ? max(0, min(5, (int) $rating_meta)) : 0;
        ?>
        <li class="woo-feedback-review-item">
        <div class="woo-feedback-review-head">
        <div class="woo-feedback-review-author">
        <?php echo esc_html($author); ?>
        </div>

        <div class="woo-feedback-review-meta">
        <?php if ($rating > 0) : ?>
        <span class="woo-feedback-review-rating" aria-label="<?php echo esc_attr(sprintf(__('Оценка %d от 5', 'woo-feedback'), $rating)); ?>">
        <?php echo esc_html(str_repeat('★', $rating) . str_repeat('☆', 5 - $rating)); ?>
        </span>
        <?php endif; ?>

        <span class="woo-feedback-review-date">
        <?php echo esc_html($date); ?>
        </span>
        </div>
        </div>

        <div class="woo-feedback-review-text">
        <?php
        echo wp_kses_post(
            wpautop(
                esc_html($review->comment_content)
            )
        );
        ?>
        </div>
        </li>
        <?php
    }

    /**
     * Renders the review submission form.
     *
     * @param int    $product_id          Product ID.
     * @param bool   $reviews_are_allowed Whether reviews are allowed.
     * @param string $wrapper_id          Wrapper ID for anchor return.
     *
     * @return string
     */
    private function render_form(int $product_id, bool $reviews_are_allowed, string $wrapper_id): string
    {
        if (!$reviews_are_allowed) {
            return '<p class="woo-feedback-reviews-disabled">' . esc_html__('Отзивите за този продукт в момента са изключени.', 'woo-feedback') . '</p>';
        }

        if ($this->settings->get('require_login_for_review', 'no') === 'yes' && !is_user_logged_in()) {
            return '<p class="woo-feedback-login-required">' . esc_html__('Трябва да сте влезли в профила си, за да оставите отзив.', 'woo-feedback') . '</p>';
        }

        $form_title     = (string) $this->settings->get('review_form_title', 'Добавете отзив');
        $button_text    = (string) $this->settings->get('submit_button_text', 'Изпрати отзив');
        $commenter      = wp_get_current_commenter();
        $started_at     = time();
        $turnstile_html   = $this->render_turnstile_widget();
        $flash_state      = $this->get_flash_form_state();
        $field_id_prefix  = $wrapper_id . '-field';
        $author_field_id  = $field_id_prefix . '-author';
        $email_field_id   = $field_id_prefix . '-email';
        $rating_field_id  = $field_id_prefix . '-rating';
        $comment_field_id = $field_id_prefix . '-comment';

        $author_value  = $this->get_field_value(
            'woo_feedback_author',
            (string) ($commenter['comment_author'] ?? ''),
                                                $flash_state
        );
        $email_value   = $this->get_field_value(
            'woo_feedback_email',
            (string) ($commenter['comment_author_email'] ?? ''),
                                                $flash_state
        );
        $rating_value  = $this->get_field_value('woo_feedback_rating', '', $flash_state);
        $comment_value = $this->get_field_value('woo_feedback_comment', '', $flash_state);

        ob_start();
        ?>
        <div class="woo-feedback-form">
        <?php if ($form_title !== '') : ?>
        <h4 class="woo-feedback-form-title"><?php echo esc_html($form_title); ?></h4>
        <?php endif; ?>

        <?php $this->render_frontend_messages(); ?>

        <form method="post" action="" class="woo-feedback-submit-form" novalidate>
        <?php wp_nonce_field('woo_feedback_submit_review', 'woo_feedback_nonce'); ?>
        <input type="hidden" name="woo_feedback_action" value="submit_review" />
        <input type="hidden" name="woo_feedback_product_id" value="<?php echo esc_attr((string) $product_id); ?>" />
        <input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr($this->get_current_page_url('#' . $wrapper_id)); ?>" />
        <input type="hidden" name="<?php echo esc_attr($this->anti_spam_service->get_started_at_field_name()); ?>" value="<?php echo esc_attr((string) $started_at); ?>" />

        <?php $this->render_honeypot_field(); ?>

        <?php if (!is_user_logged_in()) : ?>
        <p class="woo-feedback-field">
        <label for="<?php echo esc_attr($author_field_id); ?>">
        <?php echo esc_html__('Име', 'woo-feedback'); ?>
        </label>
        <input
        type="text"
        id="<?php echo esc_attr($author_field_id); ?>"
        name="woo_feedback_author"
        value="<?php echo esc_attr($author_value); ?>"
        required
        />
        </p>

        <p class="woo-feedback-field">
        <label for="<?php echo esc_attr($email_field_id); ?>">
        <?php echo esc_html__('Имейл', 'woo-feedback'); ?>
        </label>
        <input
        type="email"
        id="<?php echo esc_attr($email_field_id); ?>"
        name="woo_feedback_email"
        value="<?php echo esc_attr($email_value); ?>"
        required
        />
        </p>
        <?php endif; ?>

        <p class="woo-feedback-field">
        <label for="<?php echo esc_attr($rating_field_id); ?>">
        <?php echo esc_html__('Оценка', 'woo-feedback'); ?>
        </label>
        <select
        id="<?php echo esc_attr($rating_field_id); ?>"
        name="woo_feedback_rating"
        required
        >
        <option value=""><?php echo esc_html__('Изберете оценка', 'woo-feedback'); ?></option>
        <option value="5" <?php selected($rating_value, '5'); ?>>5 - <?php echo esc_html__('Отлично', 'woo-feedback'); ?></option>
        <option value="4" <?php selected($rating_value, '4'); ?>>4 - <?php echo esc_html__('Много добро', 'woo-feedback'); ?></option>
        <option value="3" <?php selected($rating_value, '3'); ?>>3 - <?php echo esc_html__('Добро', 'woo-feedback'); ?></option>
        <option value="2" <?php selected($rating_value, '2'); ?>>2 - <?php echo esc_html__('Слабо', 'woo-feedback'); ?></option>
        <option value="1" <?php selected($rating_value, '1'); ?>>1 - <?php echo esc_html__('Много слабо', 'woo-feedback'); ?></option>
        </select>
        </p>

        <p class="woo-feedback-field">
        <label for="<?php echo esc_attr($comment_field_id); ?>">
        <?php echo esc_html__('Вашият отзив', 'woo-feedback'); ?>
        </label>
        <textarea
        id="<?php echo esc_attr($comment_field_id); ?>"
        name="woo_feedback_comment"
        rows="6"
        required
        ><?php echo esc_textarea($comment_value); ?></textarea>
        </p>

        <?php if ($turnstile_html !== '') : ?>
        <div class="woo-feedback-field woo-feedback-field--turnstile">
        <?php echo $turnstile_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <?php endif; ?>

        <p class="woo-feedback-actions">
        <button type="submit" class="woo-feedback-submit">
        <?php echo esc_html($button_text); ?>
        </button>
        </p>
        </form>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Renders frontend success or error messages from query vars.
     *
     * @return void
     */
    private function render_frontend_messages(): void
    {
        $state = $this->get_frontend_message_state();

        if ($state['status'] === '') {
            return;
        }

        if ($state['status'] === 'success') {
            echo '<div class="woo-feedback-message woo-feedback-message--success">';
            echo esc_html((string) $this->settings->get('success_message', 'Вашият отзив беше изпратен и очаква одобрение от администратор.'));
            echo '</div>';

            return;
        }

        if ($state['status'] === 'error') {
            echo '<div class="woo-feedback-message woo-feedback-message--error">';
            echo esc_html($this->get_error_message_by_reason($state['reason']));
            echo '</div>';
        }
    }

    /**
     * Resolves the product ID from shortcode attributes or current product context.
     *
     * @param array<string, mixed> $atts Shortcode attributes.
     *
     * @return int
     */
    private function resolve_product_id(array $atts): int
    {
        $product_id = 0;

        if (isset($atts['product_id'])) {
            $product_id = absint($atts['product_id']);
        } elseif (isset($atts['id'])) {
            $product_id = absint($atts['id']);
        } elseif (function_exists('is_product') && is_product()) {
            $product_id = get_the_ID();
        }

        if ($product_id < 1) {
            return 0;
        }

        $post_type = get_post_type($product_id);

        if ($post_type !== 'product') {
            return 0;
        }

        return $product_id;
    }

    /**
     * Returns the total approved reviews count for a WooCommerce product.
     *
     * @param int $product_id Product ID.
     *
     * @return int
     */
    private function get_product_review_count(int $product_id): int
    {
        $cache_key = $this->get_review_count_cache_key($product_id);
        $cached    = $this->get_cached_value($product_id, $cache_key);

        if (is_int($cached) || is_numeric($cached)) {
            return max(0, (int) $cached);
        }

        $count = get_comments([
            'post_id' => $product_id,
            'status'  => 'approve',
            'type'    => 'review',
            'count'   => true,
        ]);

        $normalized = is_numeric($count) ? max(0, (int) $count) : 0;

        $this->set_cached_value($product_id, $cache_key, $normalized);

        return $normalized;
    }

    /**
     * Returns approved reviews for a WooCommerce product page.
     *
     * @param int $product_id       Product ID.
     * @param int $page             Current page.
     * @param int $reviews_per_page Reviews per page.
     *
     * @return array<int, WP_Comment>
     */
    private function get_product_reviews(int $product_id, int $page, int $reviews_per_page): array
    {
        $page             = max(1, $page);
        $reviews_per_page = max(1, $reviews_per_page);
        $offset           = ($page - 1) * $reviews_per_page;
        $cache_key        = $this->get_review_list_cache_key($product_id, $page, $reviews_per_page);
        $cached           = $this->get_cached_value($product_id, $cache_key);

        if (is_array($cached)) {
            return array_values(array_filter($cached, static fn ($item): bool => $item instanceof WP_Comment));
        }

        $reviews = get_comments([
            'post_id'      => $product_id,
            'status'       => 'approve',
            'type'         => 'review',
            'orderby'      => 'comment_date_gmt',
            'order'        => 'DESC',
            'hierarchical' => false,
            'number'       => $reviews_per_page,
            'offset'       => $offset,
        ]);

        if (!is_array($reviews)) {
            $reviews = [];
        }

        $normalized = array_values(array_filter($reviews, static fn ($item): bool => $item instanceof WP_Comment));

        $this->set_cached_value($product_id, $cache_key, $normalized);

        return $normalized;
    }

    /**
     * Renders pagination links for the reviews block.
     *
     * @param int    $product_id       Product ID.
     * @param int    $current_page     Current page.
     * @param int    $total_pages      Total pages.
     * @param int    $reviews_per_page Reviews per page.
     * @param int    $review_count     Total review count.
     * @param string $wrapper_id       Wrapper ID.
     *
     * @return string
     */
    private function render_reviews_pagination(
        int $product_id,
        int $current_page,
        int $total_pages,
        int $reviews_per_page,
        int $review_count,
        string $wrapper_id
    ): string {
        if ($review_count <= $reviews_per_page || $total_pages <= 1) {
            return '';
        }

        $base_url  = $this->get_current_page_url('#' . $wrapper_id);
        $query_key = $this->get_reviews_page_query_key($product_id);

        ob_start();
        ?>
        <nav class="woo-feedback-pagination" aria-label="<?php echo esc_attr__('Навигация за отзиви', 'woo-feedback'); ?>">
        <div class="woo-feedback-pagination__summary">
        <?php
        echo esc_html(
            sprintf(
                __('Страница %1$d от %2$d · общо %3$d отзива', 'woo-feedback'),
                    $current_page,
                    $total_pages,
                    $review_count
            )
        );
        ?>
        </div>

        <div class="woo-feedback-pagination__links">
        <?php if ($current_page > 1) : ?>
        <a
        class="woo-feedback-pagination__link woo-feedback-pagination__link--prev"
        href="<?php echo esc_url($this->build_reviews_page_url($base_url, $query_key, $current_page - 1)); ?>"
        >
        <?php echo esc_html__('Предишна', 'woo-feedback'); ?>
        </a>
        <?php endif; ?>

        <?php foreach ($this->get_reviews_page_numbers($current_page, $total_pages) as $page_number) : ?>
        <?php if ($page_number === 0) : ?>
        <span class="woo-feedback-pagination__dots" aria-hidden="true">…</span>
        <?php continue; ?>
        <?php endif; ?>

        <?php if ($page_number === $current_page) : ?>
        <span class="woo-feedback-pagination__link is-current" aria-current="page">
        <?php echo esc_html((string) $page_number); ?>
        </span>
        <?php else : ?>
        <a
        class="woo-feedback-pagination__link"
        href="<?php echo esc_url($this->build_reviews_page_url($base_url, $query_key, $page_number)); ?>"
        >
        <?php echo esc_html((string) $page_number); ?>
        </a>
        <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($current_page < $total_pages) : ?>
        <a
        class="woo-feedback-pagination__link woo-feedback-pagination__link--next"
        href="<?php echo esc_url($this->build_reviews_page_url($base_url, $query_key, $current_page + 1)); ?>"
        >
        <?php echo esc_html__('Следваща', 'woo-feedback'); ?>
        </a>
        <?php endif; ?>
        </div>
        </nav>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Returns compact page numbers with ellipsis markers as 0.
     *
     * @param int $current_page Current page.
     * @param int $total_pages  Total pages.
     *
     * @return array<int, int>
     */
    private function get_reviews_page_numbers(int $current_page, int $total_pages): array
    {
        if ($total_pages <= 7) {
            return range(1, $total_pages);
        }

        $pages = [1];

        if ($current_page > 3) {
            $pages[] = 0;
        }

        for ($page = max(2, $current_page - 1); $page <= min($total_pages - 1, $current_page + 1); $page++) {
            $pages[] = $page;
        }

        if ($current_page < $total_pages - 2) {
            $pages[] = 0;
        }

        $pages[] = $total_pages;

        return array_values(array_unique($pages));
    }

    /**
     * Returns the current page URL for safe redirect back after submit.
     *
     * @param string $anchor Optional URL fragment.
     *
     * @return string
     */
    private function get_current_page_url(string $anchor = ''): string
    {
        global $wp;

        $url = '';

        if (isset($wp) && is_object($wp) && method_exists($wp, 'parse_request')) {
            $resolved = home_url(add_query_arg([], $wp->request ?? ''));

            if (is_string($resolved) && $resolved !== '') {
                $url = $resolved;
            }
        }

        if ($url === '') {
            $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';
            $url         = home_url($request_uri);
        }

        $url = remove_query_arg(
            [
                'woo_feedback_status',
                'woo_feedback_reason',
                'woo_feedback_form_state',
            ],
            $url
        );

        if ($anchor !== '') {
            $url .= $anchor;
        }

        return $url;
    }

    /**
     * Normalizes a yes/no value.
     *
     * @param mixed $value Raw value.
     *
     * @return string
     */
    private function normalize_yes_no(mixed $value): string
    {
        return $value === 'no' ? 'no' : 'yes';
    }

    /**
     * Normalizes reviews_per_page shortcode value.
     *
     * @param mixed $value Raw value.
     *
     * @return int
     */
    private function normalize_reviews_per_page(mixed $value): int
    {
        $normalized = absint((string) $value);

        if ($normalized < 1) {
            return self::DEFAULT_REVIEWS_PER_PAGE;
        }

        return min($normalized, self::MAX_REVIEWS_PER_PAGE);
    }

    /**
     * Returns the product-specific pagination query key.
     *
     * @param int $product_id Product ID.
     *
     * @return string
     */
    private function get_reviews_page_query_key(int $product_id): string
    {
        return 'woo_feedback_page_' . $product_id;
    }

    /**
     * Returns the current page for a product review list.
     *
     * @param int $product_id Product ID.
     *
     * @return int
     */
    private function get_current_reviews_page(int $product_id): int
    {
        $query_key = $this->get_reviews_page_query_key($product_id);

        if (!isset($_GET[$query_key])) {
            return 1;
        }

        return max(1, absint((string) wp_unslash($_GET[$query_key])));
    }

    /**
     * Builds a pagination URL for the reviews block.
     *
     * @param string $base_url  Base URL.
     * @param string $query_key Query key.
     * @param int    $page      Target page.
     *
     * @return string
     */
    private function build_reviews_page_url(string $base_url, string $query_key, int $page): string
    {
        $page = max(1, $page);

        if ($page === 1) {
            return remove_query_arg($query_key, $base_url);
        }

        return add_query_arg($query_key, $page, $base_url);
    }

    /**
     * Returns whether reviews are currently allowed for the product.
     *
     * Must stay in sync with FormHandler::reviews_are_allowed().
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
     * Renders the hidden honeypot field when enabled.
     *
     * @return void
     */
    private function render_honeypot_field(): void
    {
        if (!$this->anti_spam_service->is_honeypot_enabled()) {
            return;
        }

        ?>
        <div class="woo-feedback-honeypot" style="position:absolute !important; left:-9999px !important; width:1px !important; height:1px !important; overflow:hidden !important;" aria-hidden="true">
        <label for="woo-feedback-hp-field"><?php echo esc_html__('Оставете това поле празно', 'woo-feedback'); ?></label>
        <input
        type="text"
        id="woo-feedback-hp-field"
        name="<?php echo esc_attr($this->anti_spam_service->get_honeypot_field_name()); ?>"
        value=""
        tabindex="-1"
        autocomplete="off"
        />
        </div>
        <?php
    }

    /**
     * Renders the Turnstile widget markup when enabled and configured.
     *
     * @return string
     */
    private function render_turnstile_widget(): string
    {
        if (!$this->turnstile_service->should_render_widget()) {
            return '';
        }

        $site_key = $this->turnstile_service->get_site_key();

        if ($site_key === '') {
            return '';
        }

        ob_start();
        ?>
        <div
        class="cf-turnstile"
        data-sitekey="<?php echo esc_attr($site_key); ?>"
        data-theme="auto"
        data-response-field-name="<?php echo esc_attr($this->turnstile_service->get_response_field_name()); ?>"
        ></div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Returns a user-facing message for a known submit error reason.
     *
     * @param string $reason Error reason code.
     *
     * @return string
     */
    private function get_error_message_by_reason(string $reason): string
    {
        return match ($reason) {
            'reviews_disabled' => __('Отзивите за този продукт в момента са изключени.', 'woo-feedback'),
            'invalid_nonce' => __('Сесията на формата е изтекла. Моля, презаредете страницата и опитайте отново.', 'woo-feedback'),
            'invalid_product' => __('Невалиден продукт за отзив.', 'woo-feedback'),
            'login_required' => __('Трябва да влезете в профила си, за да изпратите отзив.', 'woo-feedback'),
            'honeypot_rejected',
            'honeypot_filled' => __('Изпращането беше отказано от защитата срещу автоматизирани заявки.', 'woo-feedback'),
            'missing_started_at',
            'missing_submit_timestamp' => __('Липсва информация за защитата на формата. Моля, презаредете страницата и опитайте отново.', 'woo-feedback'),
            'invalid_started_at',
            'invalid_submit_timestamp' => __('Невалидни данни за защитата на формата. Моля, презаредете страницата и опитайте отново.', 'woo-feedback'),
            'submit_too_fast' => __('Формата беше изпратена твърде бързо. Моля, опитайте отново.', 'woo-feedback'),
            'missing_turnstile_token',
            'missing_token',
            'captcha_required' => __('Моля, потвърдете проверката за сигурност.', 'woo-feedback'),
            'turnstile_failed',
            'invalid_token',
            'turnstile_verification_failed',
            'turnstile_not_configured_fail_closed',
            'turnstile_request_error_fail_closed',
            'turnstile_invalid_http_response_fail_closed',
            'turnstile_invalid_json_fail_closed',
            'turnstile_hostname_mismatch' => __('Възникна проблем при потвърждаването на защитната проверка. Моля, презаредете страницата и опитайте отново.', 'woo-feedback'),
            'rate_limited',
            'rate_limit_exceeded' => __('Направени са твърде много опити за изпращане. Моля, изчакайте малко и опитайте отново.', 'woo-feedback'),
            'duplicate_review' => __('Изглежда вече сте изпратили същия отзив за този продукт.', 'woo-feedback'),
            'invalid_payload' => __('Моля, попълнете всички задължителни полета коректно.', 'woo-feedback'),
            'comment_insert_failed' => __('Неуспешно записване на отзива. Моля, опитайте отново.', 'woo-feedback'),
            default => (string) $this->settings->get('error_message', 'Възникна проблем при изпращането на отзива. Моля, опитайте отново.'),
        };
    }

    /**
     * Returns normalized frontend message state from query args.
     *
     * @return array{status:string,reason:string}
     */
    private function get_frontend_message_state(): array
    {
        $status = isset($_GET['woo_feedback_status']) ? sanitize_key((string) wp_unslash($_GET['woo_feedback_status'])) : '';
        $reason = isset($_GET['woo_feedback_reason']) ? sanitize_key((string) wp_unslash($_GET['woo_feedback_reason'])) : '';

        if ($status !== 'success' && $status !== 'error') {
            $status = '';
        }

        return [
            'status' => $status,
            'reason' => $reason,
        ];
    }

    /**
     * Returns a request-stable unique wrapper ID for one rendered product block.
     *
     * This prevents duplicate IDs when the same product feedback shortcode is
     * rendered multiple times on one page, for example in separate desktop and
     * mobile sections created by the active theme or page builder.
     *
     * @param int $product_id Product ID.
     *
     * @return string
     */
    private function get_wrapper_id(int $product_id): string
    {
        $prefix = 'woo-feedback-' . $product_id . '-';

        if (function_exists('wp_unique_id')) {
            return wp_unique_id($prefix);
        }

        static $instance = 0;
        $instance++;

        return $prefix . (string) $instance;
    }

    /**
     * Returns cached flash form state from the previous redirect, if available.
     *
     * @return array<string, string>
     */
    private function get_flash_form_state(): array
    {
        static $flash_state = null;

        if (is_array($flash_state)) {
            return $flash_state;
        }

        $flash_state = FormHandler::consume_flash_state_from_request();

        return $flash_state;
    }

    /**
     * Returns the field value from POST, flash state, or fallback default.
     *
     * AJAX errors keep the browser field values automatically.
     * POST fallback after redirect restores values from flash state.
     *
     * @param string               $key         Input key.
     * @param string               $default     Default value.
     * @param array<string,string> $flash_state Flash state payload.
     *
     * @return string
     */
    private function get_field_value(string $key, string $default, array $flash_state): string
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST' && isset($_POST[$key])) {
            return trim((string) wp_unslash($_POST[$key]));
        }

        if (isset($flash_state[$key]) && is_string($flash_state[$key])) {
            return $flash_state[$key];
        }

        return $default;
    }

    /**
     * Returns product ID for a comment when it is a product review comment.
     *
     * @param int $comment_id Comment ID.
     *
     * @return int
     */
    private function get_product_id_from_comment_id(int $comment_id): int
    {
        $comment = get_comment($comment_id);

        if (!$comment instanceof WP_Comment) {
            return 0;
        }

        $product_id = absint($comment->comment_post_ID);

        if ($product_id < 1 || get_post_type($product_id) !== 'product') {
            return 0;
        }

        return $product_id;
    }

    /**
     * Invalidates all cache entries for one product reviews block.
     *
     * @param int $product_id Product ID.
     *
     * @return void
     */
    private function invalidate_product_review_cache(int $product_id): void
    {
        $cache_keys = $this->get_product_cache_index($product_id);

        foreach ($cache_keys as $cache_key) {
            wp_cache_delete($cache_key, self::CACHE_GROUP);
            delete_transient($this->get_transient_key($cache_key));
        }

        delete_transient($this->get_cache_index_transient_key($product_id));
    }

    /**
     * Returns cache key for review count.
     *
     * @param int $product_id Product ID.
     *
     * @return string
     */
    private function get_review_count_cache_key(int $product_id): string
    {
        return self::CACHE_KEY_COUNT_PREFIX . $product_id;
    }

    /**
     * Returns cache key for one paginated review list.
     *
     * @param int $product_id Product ID.
     * @param int $page       Page number.
     * @param int $per_page   Reviews per page.
     *
     * @return string
     */
    private function get_review_list_cache_key(int $product_id, int $page, int $per_page): string
    {
        return self::CACHE_KEY_LIST_PREFIX . $product_id . '_' . $page . '_' . $per_page;
    }

    /**
     * Returns one cached value from runtime cache or persistent transient cache.
     *
     * @param int    $product_id Product ID.
     * @param string $cache_key  Cache key.
     *
     * @return mixed
     */
    private function get_cached_value(int $product_id, string $cache_key): mixed
    {
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $transient_key = $this->get_transient_key($cache_key);
        $cached        = get_transient($transient_key);

        if ($cached === false) {
            return false;
        }

        wp_cache_set($cache_key, $cached, self::CACHE_GROUP, self::CACHE_TTL);
        $this->register_product_cache_key($product_id, $cache_key);

        return $cached;
    }

    /**
     * Stores one value in runtime cache and transient cache.
     *
     * @param int    $product_id Product ID.
     * @param string $cache_key  Cache key.
     * @param mixed  $value      Cache value.
     *
     * @return void
     */
    private function set_cached_value(int $product_id, string $cache_key, mixed $value): void
    {
        wp_cache_set($cache_key, $value, self::CACHE_GROUP, self::CACHE_TTL);
        set_transient($this->get_transient_key($cache_key), $value, self::CACHE_TTL);
        $this->register_product_cache_key($product_id, $cache_key);
    }

    /**
     * Registers one cache key in the product cache index.
     *
     * @param int    $product_id Product ID.
     * @param string $cache_key  Cache key.
     *
     * @return void
     */
    private function register_product_cache_key(int $product_id, string $cache_key): void
    {
        $index_key = $this->get_cache_index_transient_key($product_id);
        $index     = get_transient($index_key);

        if (!is_array($index)) {
            $index = [];
        }

        $index[] = $cache_key;
        $index   = array_values(array_unique(array_filter($index, static fn ($item): bool => is_string($item) && $item !== '')));

        set_transient($index_key, $index, self::CACHE_TTL);
    }

    /**
     * Returns the product cache index.
     *
     * @param int $product_id Product ID.
     *
     * @return array<int, string>
     */
    private function get_product_cache_index(int $product_id): array
    {
        $index = get_transient($this->get_cache_index_transient_key($product_id));

        if (!is_array($index)) {
            return [];
        }

        return array_values(array_filter($index, static fn ($item): bool => is_string($item) && $item !== ''));
    }

    /**
     * Returns the transient key for one cache entry.
     *
     * @param string $cache_key Cache key.
     *
     * @return string
     */
    private function get_transient_key(string $cache_key): string
    {
        return 'woo_feedback_' . md5($cache_key);
    }

    /**
     * Returns the transient key for the product cache index.
     *
     * @param int $product_id Product ID.
     *
     * @return string
     */
    private function get_cache_index_transient_key(int $product_id): string
    {
        return self::CACHE_INDEX_PREFIX . $product_id;
    }
}
