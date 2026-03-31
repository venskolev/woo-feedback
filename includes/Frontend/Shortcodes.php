<?php
/**
 * Frontend shortcodes for WooFeedback.
 *
 * @package WooFeedback
 */

declare(strict_types=1);

namespace WDT\WooFeedback\Frontend;

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
            'title'         => (string) $this->settings->get('default_shortcode_title', 'Отзиви'),
            'show_form'     => (string) $this->settings->get('show_review_form_default', 'no'),
            'collapsed'     => 'yes',
            'show_count'    => 'yes',
            'button_text'   => (string) $this->settings->get('default_shortcode_title', 'Отзиви'),
            'empty_message' => (string) $this->settings->get('empty_reviews_message', 'Все още няма отзиви.'),
        ];

        $atts = shortcode_atts($defaults, $atts, self::SHORTCODE_TAG);

        $title         = sanitize_text_field((string) $atts['title']);
        $show_form     = $this->normalize_yes_no($atts['show_form']);
        $collapsed     = $this->normalize_yes_no($atts['collapsed']);
        $show_count    = $this->normalize_yes_no($atts['show_count']);
        $button_text   = sanitize_text_field((string) $atts['button_text']);
        $empty_message = sanitize_text_field((string) $atts['empty_message']);

        $reviews      = $this->get_product_reviews($product_id);
        $review_count = count($reviews);
        $wrapper_id   = 'woo-feedback-' . $product_id . '-' . wp_rand(1000, 999999);
        $content_id   = $wrapper_id . '-content';
        $is_expanded  = $collapsed === 'no';

        ob_start();
        ?>
        <div
        class="woo-feedback-block"
        id="<?php echo esc_attr($wrapper_id); ?>"
        data-product-id="<?php echo esc_attr((string) $product_id); ?>"
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
        <?php else : ?>
        <p class="woo-feedback-empty-message">
        <?php echo esc_html($empty_message); ?>
        </p>
        <?php endif; ?>
        </div>

        <?php if ($show_form === 'yes') : ?>
        <div class="woo-feedback-form-wrap">
        <?php echo $this->render_form($product_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
     * @param int $product_id Product ID.
     *
     * @return string
     */
    private function render_form(int $product_id): string
    {
        if ($this->settings->get('require_login_for_review', 'no') === 'yes' && !is_user_logged_in()) {
            return '<p class="woo-feedback-login-required">' . esc_html__('Трябва да сте влезли в профила си, за да оставите отзив.', 'woo-feedback') . '</p>';
        }

        $form_title  = (string) $this->settings->get('review_form_title', 'Добавете отзив');
        $button_text = (string) $this->settings->get('submit_button_text', 'Изпрати отзив');
        $commenter   = wp_get_current_commenter();

        ob_start();
        ?>
        <div class="woo-feedback-form">
        <?php if ($form_title !== '') : ?>
        <h4 class="woo-feedback-form-title"><?php echo esc_html($form_title); ?></h4>
        <?php endif; ?>

        <?php $this->render_frontend_messages(); ?>

        <form method="post" action="">
        <?php wp_nonce_field('woo_feedback_submit_review', 'woo_feedback_nonce'); ?>
        <input type="hidden" name="woo_feedback_action" value="submit_review" />
        <input type="hidden" name="woo_feedback_product_id" value="<?php echo esc_attr((string) $product_id); ?>" />
        <input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr($this->get_current_page_url()); ?>" />

        <?php if (!is_user_logged_in()) : ?>
        <p class="woo-feedback-field">
        <label for="woo-feedback-author-<?php echo esc_attr((string) $product_id); ?>">
        <?php echo esc_html__('Име', 'woo-feedback'); ?>
        </label>
        <input
        type="text"
        id="woo-feedback-author-<?php echo esc_attr((string) $product_id); ?>"
        name="woo_feedback_author"
        value="<?php echo esc_attr($commenter['comment_author'] ?? ''); ?>"
        required
        />
        </p>

        <p class="woo-feedback-field">
        <label for="woo-feedback-email-<?php echo esc_attr((string) $product_id); ?>">
        <?php echo esc_html__('Имейл', 'woo-feedback'); ?>
        </label>
        <input
        type="email"
        id="woo-feedback-email-<?php echo esc_attr((string) $product_id); ?>"
        name="woo_feedback_email"
        value="<?php echo esc_attr($commenter['comment_author_email'] ?? ''); ?>"
        required
        />
        </p>
        <?php endif; ?>

        <p class="woo-feedback-field">
        <label for="woo-feedback-rating-<?php echo esc_attr((string) $product_id); ?>">
        <?php echo esc_html__('Оценка', 'woo-feedback'); ?>
        </label>
        <select
        id="woo-feedback-rating-<?php echo esc_attr((string) $product_id); ?>"
        name="woo_feedback_rating"
        required
        >
        <option value=""><?php echo esc_html__('Изберете оценка', 'woo-feedback'); ?></option>
        <option value="5">5 - <?php echo esc_html__('Отлично', 'woo-feedback'); ?></option>
        <option value="4">4 - <?php echo esc_html__('Много добро', 'woo-feedback'); ?></option>
        <option value="3">3 - <?php echo esc_html__('Добро', 'woo-feedback'); ?></option>
        <option value="2">2 - <?php echo esc_html__('Слабо', 'woo-feedback'); ?></option>
        <option value="1">1 - <?php echo esc_html__('Много слабо', 'woo-feedback'); ?></option>
        </select>
        </p>

        <p class="woo-feedback-field">
        <label for="woo-feedback-comment-<?php echo esc_attr((string) $product_id); ?>">
        <?php echo esc_html__('Вашият отзив', 'woo-feedback'); ?>
        </label>
        <textarea
        id="woo-feedback-comment-<?php echo esc_attr((string) $product_id); ?>"
        name="woo_feedback_comment"
        rows="6"
        required
        ></textarea>
        </p>

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
        $status = isset($_GET['woo_feedback_status']) ? sanitize_key((string) wp_unslash($_GET['woo_feedback_status'])) : '';

        if ($status === '') {
            return;
        }

        if ($status === 'success') {
            echo '<div class="woo-feedback-message woo-feedback-message--success">';
            echo esc_html((string) $this->settings->get('success_message', 'Вашият отзив беше изпратен и очаква одобрение от администратор.'));
            echo '</div>';

            return;
        }

        if ($status === 'error') {
            echo '<div class="woo-feedback-message woo-feedback-message--error">';
            echo esc_html((string) $this->settings->get('error_message', 'Възникна проблем при изпращането на отзива. Моля, опитайте отново.'));
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
     * Returns approved reviews for a WooCommerce product.
     *
     * @param int $product_id Product ID.
     *
     * @return array<int, WP_Comment>
     */
    private function get_product_reviews(int $product_id): array
    {
        $reviews = get_comments([
            'post_id'      => $product_id,
            'status'       => 'approve',
            'type'         => 'review',
            'orderby'      => 'comment_date_gmt',
            'order'        => 'DESC',
            'hierarchical' => false,
        ]);

        if (!is_array($reviews)) {
            return [];
        }

        return array_values(array_filter($reviews, static fn ($item): bool => $item instanceof WP_Comment));
    }

    /**
     * Returns the current page URL for safe redirect back after submit.
     *
     * @return string
     */
    private function get_current_page_url(): string
    {
        global $wp;

        if (isset($wp) && is_object($wp) && method_exists($wp, 'parse_request')) {
            $url = home_url(add_query_arg([], $wp->request ?? ''));

            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';

        return home_url($request_uri);
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
}
