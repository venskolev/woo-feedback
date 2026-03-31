<?php
/**
 * Reviews admin page for WooFeedback.
 *
 * @package WooFeedback
 */

declare(strict_types=1);

namespace WDT\WooFeedback\Admin;

use WDT\WooFeedback\Settings\Settings;
use WP_Comment;
use WP_Screen;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the dedicated admin page for review moderation and quick actions.
 */
final class ReviewsPage
{
    /**
     * Page slug.
     */
    private const PAGE_SLUG = 'woo-feedback-reviews';

    /**
     * Approve action key.
     */
    private const ACTION_APPROVE = 'woo_feedback_approve';

    /**
     * Trash action key.
     */
    private const ACTION_TRASH = 'woo_feedback_trash';

    /**
     * Delete action key.
     */
    private const ACTION_DELETE = 'woo_feedback_delete';

    /**
     * Bulk approve action key.
     */
    private const BULK_ACTION_APPROVE = 'woo_feedback_bulk_approve';

    /**
     * Bulk trash action key.
     */
    private const BULK_ACTION_TRASH = 'woo_feedback_bulk_trash';

    /**
     * Plugin-specific capability.
     */
    private const PLUGIN_CAPABILITY = 'manage_woo_feedback';

    /**
     * Legacy fallback capability.
     */
    private const LEGACY_CAPABILITY = 'moderate_comments';

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
     * Handles page actions when the current admin screen matches the reviews page.
     *
     * @param WP_Screen $screen Current screen object.
     *
     * @return void
     */
    public function handle_actions(WP_Screen $screen): void
    {
        if (!$this->is_reviews_screen($screen)) {
            return;
        }

        if (!$this->current_user_can_access_admin()) {
            return;
        }

        $this->maybe_handle_single_action();
        $this->maybe_handle_bulk_action();
    }

    /**
     * Renders the reviews admin page.
     *
     * @return void
     */
    public function render_page(): void
    {
        if (!$this->current_user_can_access_admin()) {
            wp_die(esc_html__('Нямате права за достъп до тази страница.', 'woo-feedback'));
        }

        $search   = $this->get_search_query();
        $status   = $this->get_status_filter();
        $paged    = $this->get_current_page();
        $per_page = (int) $this->settings->get('admin_items_per_page', 20);

        $result       = $this->get_reviews($status, $search, $paged, $per_page);
        $reviews      = $result['reviews'];
        $total_items  = $result['total'];
        $total_pages  = $per_page > 0 ? (int) ceil($total_items / $per_page) : 1;
        $status_count = $this->get_status_counts($search);

        ?>
        <div class="wrap woo-feedback-admin-page">
        <h1><?php echo esc_html__('WooFeedback – Отзиви', 'woo-feedback'); ?></h1>

        <p>
        <?php echo esc_html__('Тук следите всички отзиви, чакате за нови одобрения и имате бърз достъп до премахване или изтриване.', 'woo-feedback'); ?>
        </p>

        <?php $this->render_notices(); ?>

        <?php $this->render_summary_cards($status_count); ?>

        <hr style="margin: 20px 0;" />

        <form method="get" action="">
        <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>" />

        <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:16px;">
        <label for="woo-feedback-status-filter">
        <strong><?php echo esc_html__('Статус:', 'woo-feedback'); ?></strong>
        </label>

        <select name="status" id="woo-feedback-status-filter">
        <option value="all" <?php selected($status, 'all'); ?>>
        <?php echo esc_html(sprintf(__('Всички (%d)', 'woo-feedback'), $status_count['all'])); ?>
        </option>
        <option value="hold" <?php selected($status, 'hold'); ?>>
        <?php echo esc_html(sprintf(__('Чакащи (%d)', 'woo-feedback'), $status_count['hold'])); ?>
        </option>
        <option value="approve" <?php selected($status, 'approve'); ?>>
        <?php echo esc_html(sprintf(__('Одобрени (%d)', 'woo-feedback'), $status_count['approve'])); ?>
        </option>
        <option value="trash" <?php selected($status, 'trash'); ?>>
        <?php echo esc_html(sprintf(__('Кошче (%d)', 'woo-feedback'), $status_count['trash'])); ?>
        </option>
        </select>

        <label for="woo-feedback-search">
        <strong><?php echo esc_html__('Търсене:', 'woo-feedback'); ?></strong>
        </label>

        <input
        type="search"
        name="s"
        id="woo-feedback-search"
        value="<?php echo esc_attr($search); ?>"
        placeholder="<?php echo esc_attr__('Име, имейл, текст, продукт', 'woo-feedback'); ?>"
        style="min-width:280px;"
        />

        <?php submit_button(__('Филтрирай', 'woo-feedback'), 'secondary', '', false); ?>

        <?php if ($search !== '' || $status !== 'all') : ?>
        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)); ?>">
        <?php echo esc_html__('Изчисти', 'woo-feedback'); ?>
        </a>
        <?php endif; ?>
        </div>
        </form>

        <form method="post" action="">
        <?php wp_nonce_field('woo_feedback_bulk_reviews_action', 'woo_feedback_bulk_reviews_nonce'); ?>
        <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>" />
        <input type="hidden" name="status" value="<?php echo esc_attr($status); ?>" />
        <input type="hidden" name="s" value="<?php echo esc_attr($search); ?>" />
        <input type="hidden" name="paged" value="<?php echo esc_attr((string) $paged); ?>" />

        <div style="display:flex; gap:10px; align-items:center; margin-bottom:16px;">
        <select name="bulk_action">
        <option value=""><?php echo esc_html__('Масово действие', 'woo-feedback'); ?></option>
        <option value="<?php echo esc_attr(self::BULK_ACTION_APPROVE); ?>">
        <?php echo esc_html__('Одобри избраните', 'woo-feedback'); ?>
        </option>
        <option value="<?php echo esc_attr(self::BULK_ACTION_TRASH); ?>">
        <?php echo esc_html__('Премести избраните в кошчето', 'woo-feedback'); ?>
        </option>
        </select>

        <?php submit_button(__('Изпълни', 'woo-feedback'), 'secondary', 'woo_feedback_apply_bulk_action', false); ?>
        </div>

        <table class="widefat fixed striped">
        <thead>
        <tr>
        <td class="manage-column check-column">
        <input type="checkbox" id="woo-feedback-select-all" />
        </td>
        <th scope="col"><?php echo esc_html__('Автор', 'woo-feedback'); ?></th>
        <th scope="col"><?php echo esc_html__('Продукт', 'woo-feedback'); ?></th>
        <th scope="col"><?php echo esc_html__('Отзив', 'woo-feedback'); ?></th>
        <th scope="col"><?php echo esc_html__('Оценка', 'woo-feedback'); ?></th>
        <th scope="col"><?php echo esc_html__('Статус', 'woo-feedback'); ?></th>
        <th scope="col"><?php echo esc_html__('Дата', 'woo-feedback'); ?></th>
        <th scope="col"><?php echo esc_html__('Действия', 'woo-feedback'); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php if (!empty($reviews)) : ?>
        <?php foreach ($reviews as $review) : ?>
        <?php $this->render_review_row($review, $status, $search, $paged); ?>
        <?php endforeach; ?>
        <?php else : ?>
        <tr>
        <td colspan="8">
        <?php echo esc_html__('Няма намерени отзиви по зададените критерии.', 'woo-feedback'); ?>
        </td>
        </tr>
        <?php endif; ?>
        </tbody>
        </table>
        </form>

        <?php if ($total_pages > 1) : ?>
        <div class="tablenav" style="margin-top:16px;">
        <div class="tablenav-pages">
        <?php
        echo wp_kses_post(
            paginate_links([
                'base'      => add_query_arg([
                    'page'   => self::PAGE_SLUG,
                    'status' => $status,
                    's'      => $search,
                    'paged'  => '%#%',
                ], admin_url('admin.php')),
                           'format'    => '',
                           'current'   => $paged,
                           'total'     => max(1, $total_pages),
                           'prev_text' => __('« Назад', 'woo-feedback'),
                           'next_text' => __('Напред »', 'woo-feedback'),
            ])
        );
        ?>
        </div>
        </div>
        <?php endif; ?>

        <hr style="margin-top: 40px; margin-bottom: 20px;" />

        <div style="text-align: center; font-size: 13px; color: #777;">
        <p style="margin-bottom: 6px;">
        <?php
        echo wp_kses_post(
            sprintf(
                __('© %1$s Всички права запазени. | Разработено от %2$s', 'woo-feedback'),
                    esc_html(wp_date('Y')),
                    '<a href="https://webdigitech.de" target="_blank" rel="noopener noreferrer" style="color:#0073aa;text-decoration:none;">Ventsislav Kolev | WebDigiTech</a>'
            )
        );
        ?>
        </p>

        <p style="margin-top: 0;">
        <?php
        echo esc_html(
            sprintf(
                __('Версия на плъгина: %s', 'woo-feedback'),
                    defined('WOO_FEEDBACK_VERSION') ? WOO_FEEDBACK_VERSION : '1.0.0'
            )
        );
        ?>
        </p>
        </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectAll = document.getElementById('woo-feedback-select-all');
            if (!selectAll) {
                return;
            }

            selectAll.addEventListener('change', function () {
                document.querySelectorAll('input[name="review_ids[]"]').forEach(function (checkbox) {
                    checkbox.checked = selectAll.checked;
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Renders the summary cards section.
     *
     * @param array<string, int> $counts Status counts.
     *
     * @return void
     */
    private function render_summary_cards(array $counts): void
    {
        $cards = [
            [
                'label' => __('Общо отзиви', 'woo-feedback'),
                'value' => $counts['all'] ?? 0,
            ],
            [
                'label' => __('Чакащи одобрение', 'woo-feedback'),
                'value' => $counts['hold'] ?? 0,
            ],
            [
                'label' => __('Одобрени', 'woo-feedback'),
                'value' => $counts['approve'] ?? 0,
            ],
            [
                'label' => __('В кошчето', 'woo-feedback'),
                'value' => $counts['trash'] ?? 0,
            ],
        ];

        echo '<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px;">';

        foreach ($cards as $card) {
            echo '<div style="background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:16px;">';
            echo '<div style="font-size:13px; color:#646970; margin-bottom:8px;">' . esc_html((string) $card['label']) . '</div>';
            echo '<div style="font-size:28px; font-weight:700; line-height:1;">' . esc_html((string) $card['value']) . '</div>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Renders a single review row.
     *
     * @param WP_Comment $review Review object.
     * @param string     $status Current status filter.
     * @param string     $search Current search query.
     * @param int        $paged  Current page.
     *
     * @return void
     */
    private function render_review_row(WP_Comment $review, string $status, string $search, int $paged): void
    {
        $product_id    = (int) $review->comment_post_ID;
        $product_title = get_the_title($product_id);
        $product_link  = get_edit_post_link($product_id);
        $rating        = get_comment_meta($review->comment_ID, 'rating', true);
        $rating        = is_scalar($rating) ? (string) $rating : '';
        $status_label  = $this->get_status_label((string) $review->comment_approved);
        $date_label    = mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $review->comment_date);

        $actions = $this->get_row_actions($review, $status, $search, $paged);

        ?>
        <tr>
        <th scope="row" class="check-column">
        <input type="checkbox" name="review_ids[]" value="<?php echo esc_attr((string) $review->comment_ID); ?>" />
        </th>

        <td>
        <strong><?php echo esc_html($review->comment_author ?: __('Без име', 'woo-feedback')); ?></strong><br />
        <a href="mailto:<?php echo esc_attr($review->comment_author_email); ?>">
        <?php echo esc_html($review->comment_author_email ?: __('Няма имейл', 'woo-feedback')); ?>
        </a>
        </td>

        <td>
        <?php if ($product_link) : ?>
        <a href="<?php echo esc_url($product_link); ?>" target="_blank" rel="noopener noreferrer">
        <?php echo esc_html($product_title ?: __('Непознат продукт', 'woo-feedback')); ?>
        </a>
        <?php else : ?>
        <?php echo esc_html($product_title ?: __('Непознат продукт', 'woo-feedback')); ?>
        <?php endif; ?>
        </td>

        <td>
        <div style="max-width:420px;">
        <?php echo esc_html(wp_trim_words($review->comment_content, 28, '...')); ?>
        </div>
        </td>

        <td>
        <?php echo esc_html($rating !== '' ? $rating . '/5' : '—'); ?>
        </td>

        <td>
        <?php echo esc_html($status_label); ?>
        </td>

        <td>
        <?php echo esc_html($date_label); ?>
        </td>

        <td>
        <?php if (!empty($actions)) : ?>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <?php foreach ($actions as $action) : ?>
        <a class="button button-small" href="<?php echo esc_url($action['url']); ?>">
        <?php echo esc_html($action['label']); ?>
        </a>
        <?php endforeach; ?>
        </div>
        <?php else : ?>
        —
        <?php endif; ?>
        </td>
        </tr>
        <?php
    }

    /**
     * Returns row actions for a given review.
     *
     * @param WP_Comment $review Review object.
     * @param string     $status Current filter status.
     * @param string     $search Current search query.
     * @param int        $paged  Current page.
     *
     * @return array<int, array<string, string>>
     */
    private function get_row_actions(WP_Comment $review, string $status, string $search, int $paged): array
    {
        $actions = [];
        $id      = (int) $review->comment_ID;

        if ((string) $review->comment_approved !== '1') {
            $actions[] = [
                'label' => __('Одобри', 'woo-feedback'),
                'url'   => $this->build_action_url(self::ACTION_APPROVE, $id, $status, $search, $paged),
            ];
        }

        if ((string) $review->comment_approved !== 'trash') {
            $actions[] = [
                'label' => __('Кошче', 'woo-feedback'),
                'url'   => $this->build_action_url(self::ACTION_TRASH, $id, $status, $search, $paged),
            ];
        }

        $actions[] = [
            'label' => __('Изтрий', 'woo-feedback'),
            'url'   => $this->build_action_url(self::ACTION_DELETE, $id, $status, $search, $paged),
        ];

        return $actions;
    }

    /**
     * Builds a signed admin action URL.
     *
     * @param string $action Action name.
     * @param int    $id     Review ID.
     * @param string $status Current filter.
     * @param string $search Search query.
     * @param int    $paged  Current page.
     *
     * @return string
     */
    private function build_action_url(string $action, int $id, string $status, string $search, int $paged): string
    {
        $url = add_query_arg([
            'page'      => self::PAGE_SLUG,
            'action'    => $action,
            'review_id' => $id,
            'status'    => $status,
            's'         => $search,
            'paged'     => $paged,
        ], admin_url('admin.php'));

        return wp_nonce_url($url, 'woo_feedback_review_action_' . $action . '_' . $id);
    }

    /**
     * Handles single review actions.
     *
     * @return void
     */
    private function maybe_handle_single_action(): void
    {
        $action = isset($_GET['action']) ? sanitize_key((string) wp_unslash($_GET['action'])) : '';

        if (!in_array($action, [self::ACTION_APPROVE, self::ACTION_TRASH, self::ACTION_DELETE], true)) {
            return;
        }

        $review_id = isset($_GET['review_id']) ? absint($_GET['review_id']) : 0;

        if ($review_id < 1) {
            return;
        }

        check_admin_referer('woo_feedback_review_action_' . $action . '_' . $review_id);

        switch ($action) {
            case self::ACTION_APPROVE:
                wp_set_comment_status($review_id, 'approve');
                $this->redirect_with_notice('approved');
                break;

            case self::ACTION_TRASH:
                wp_trash_comment($review_id);
                $this->redirect_with_notice('trashed');
                break;

            case self::ACTION_DELETE:
                wp_delete_comment($review_id, true);
                $this->redirect_with_notice('deleted');
                break;
        }
    }

    /**
     * Handles bulk review actions.
     *
     * @return void
     */
    private function maybe_handle_bulk_action(): void
    {
        if (!isset($_POST['woo_feedback_apply_bulk_action'])) {
            return;
        }

        check_admin_referer('woo_feedback_bulk_reviews_action', 'woo_feedback_bulk_reviews_nonce');

        $action = isset($_POST['bulk_action']) ? sanitize_key((string) wp_unslash($_POST['bulk_action'])) : '';
        $ids    = isset($_POST['review_ids']) ? wp_unslash($_POST['review_ids']) : [];

        if (!is_array($ids) || empty($ids)) {
            $this->redirect_with_notice('no_selection');
        }

        $review_ids = array_filter(array_map('absint', $ids));

        if (empty($review_ids)) {
            $this->redirect_with_notice('no_selection');
        }

        $processed = 0;

        if ($action === self::BULK_ACTION_APPROVE) {
            foreach ($review_ids as $review_id) {
                wp_set_comment_status($review_id, 'approve');
                $processed++;
            }

            $this->redirect_with_notice('bulk_approved', $processed);
        }

        if ($action === self::BULK_ACTION_TRASH) {
            foreach ($review_ids as $review_id) {
                wp_trash_comment($review_id);
                $processed++;
            }

            $this->redirect_with_notice('bulk_trashed', $processed);
        }
    }

    /**
     * Redirects back to the page with a success notice code.
     *
     * @param string   $notice Notice key.
     * @param int|null $count  Optional processed count.
     *
     * @return void
     */
    private function redirect_with_notice(string $notice, ?int $count = null): void
    {
        $args = [
            'page'   => self::PAGE_SLUG,
            'status' => $this->get_status_filter_from_request(),
            's'      => $this->get_search_query_from_request(),
            'paged'  => $this->get_page_from_request(),
            'notice' => $notice,
        ];

        if ($count !== null) {
            $args['count'] = $count;
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Renders admin notices based on query args.
     *
     * @return void
     */
    private function render_notices(): void
    {
        $notice = isset($_GET['notice']) ? sanitize_key((string) wp_unslash($_GET['notice'])) : '';
        $count  = isset($_GET['count']) ? absint($_GET['count']) : 0;

        if ($notice === '') {
            return;
        }

        $message = '';

        switch ($notice) {
            case 'approved':
                $message = __('Отзивът беше одобрен успешно.', 'woo-feedback');
                break;

            case 'trashed':
                $message = __('Отзивът беше преместен в кошчето.', 'woo-feedback');
                break;

            case 'deleted':
                $message = __('Отзивът беше изтрит окончателно.', 'woo-feedback');
                break;

            case 'bulk_approved':
                $message = sprintf(__('Одобрени отзиви: %d', 'woo-feedback'), $count);
                break;

            case 'bulk_trashed':
                $message = sprintf(__('Преместени в кошчето отзиви: %d', 'woo-feedback'), $count);
                break;

            case 'no_selection':
                $message = __('Не са избрани отзиви за масово действие.', 'woo-feedback');
                break;
        }

        if ($message === '') {
            return;
        }

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    /**
     * Returns the current search query.
     *
     * @return string
     */
    private function get_search_query(): string
    {
        return $this->get_search_query_from_request();
    }

    /**
     * Returns the current status filter.
     *
     * @return string
     */
    private function get_status_filter(): string
    {
        return $this->get_status_filter_from_request();
    }

    /**
     * Returns the current page number.
     *
     * @return int
     */
    private function get_current_page(): int
    {
        return $this->get_page_from_request();
    }

    /**
     * Gets the current page from request.
     *
     * @return int
     */
    private function get_page_from_request(): int
    {
        $paged = isset($_REQUEST['paged']) ? absint($_REQUEST['paged']) : 1;

        return max(1, $paged);
    }

    /**
     * Gets the search value from request.
     *
     * @return string
     */
    private function get_search_query_from_request(): string
    {
        $search = isset($_REQUEST['s']) ? sanitize_text_field((string) wp_unslash($_REQUEST['s'])) : '';

        return trim($search);
    }

    /**
     * Gets the status value from request.
     *
     * @return string
     */
    private function get_status_filter_from_request(): string
    {
        $status = isset($_REQUEST['status']) ? sanitize_key((string) wp_unslash($_REQUEST['status'])) : 'all';

        if (!in_array($status, ['all', 'hold', 'approve', 'trash'], true)) {
            $status = 'all';
        }

        return $status;
    }

    /**
     * Queries review comments for the admin list.
     *
     * @param string $status   Status filter.
     * @param string $search   Search query.
     * @param int    $paged    Current page.
     * @param int    $per_page Items per page.
     *
     * @return array{reviews: array<int, WP_Comment>, total: int}
     */
    private function get_reviews(string $status, string $search, int $paged, int $per_page): array
    {
        $args = [
            'post_type'    => 'product',
            'type'         => 'review',
            'status'       => 'all',
            'number'       => $per_page,
            'offset'       => ($paged - 1) * $per_page,
            'orderby'      => 'comment_date_gmt',
            'order'        => 'DESC',
            'search'       => $search !== '' ? $search : '',
            'count'        => false,
            'hierarchical' => false,
        ];

        if ($status !== 'all') {
            $args['status'] = $this->map_status_to_comment_query_status($status);
        }

        $reviews = get_comments($args);

        if (!is_array($reviews)) {
            $reviews = [];
        }

        $count_args = $args;
        $count_args['count']  = true;
        $count_args['number'] = 0;
        $count_args['offset'] = 0;

        $total = (int) get_comments($count_args);

        return [
            'reviews' => array_values(array_filter($reviews, static fn ($item): bool => $item instanceof WP_Comment)),
            'total'   => $total,
        ];
    }

    /**
     * Returns counts for each supported status filter.
     *
     * @param string $search Optional search query.
     *
     * @return array<string, int>
     */
    private function get_status_counts(string $search = ''): array
    {
        $base = [
            'post_type'    => 'product',
            'type'         => 'review',
            'count'        => true,
            'number'       => 0,
            'offset'       => 0,
            'search'       => $search !== '' ? $search : '',
            'hierarchical' => false,
        ];

        $all_args           = $base;
        $all_args['status'] = 'all';

        $hold_args           = $base;
        $hold_args['status'] = 'hold';

        $approve_args           = $base;
        $approve_args['status'] = 'approve';

        $trash_args           = $base;
        $trash_args['status'] = 'trash';

        return [
            'all'     => (int) get_comments($all_args),
            'hold'    => (int) get_comments($hold_args),
            'approve' => (int) get_comments($approve_args),
            'trash'   => (int) get_comments($trash_args),
        ];
    }

    /**
     * Converts local filter value to comment query status value.
     *
     * @param string $status Local filter.
     *
     * @return string
     */
    private function map_status_to_comment_query_status(string $status): string
    {
        return match ($status) {
            'hold'    => 'hold',
            'approve' => 'approve',
            'trash'   => 'trash',
            default   => 'all',
        };
    }

    /**
     * Returns a human-readable status label.
     *
     * @param string $status Raw comment approval status.
     *
     * @return string
     */
    private function get_status_label(string $status): string
    {
        return match ($status) {
            '1'         => __('Одобрен', 'woo-feedback'),
            '0', 'hold' => __('Чака одобрение', 'woo-feedback'),
            'trash'     => __('В кошчето', 'woo-feedback'),
            'spam'      => __('Спам', 'woo-feedback'),
            default     => __('Неизвестен', 'woo-feedback'),
        };
    }

    /**
     * Checks whether the current screen is the WooFeedback reviews screen.
     *
     * @param WP_Screen $screen Screen instance.
     *
     * @return bool
     */
    private function is_reviews_screen(WP_Screen $screen): bool
    {
        $screen_id = isset($screen->id) && is_string($screen->id) ? $screen->id : '';
        $page      = isset($_REQUEST['page']) ? sanitize_key((string) wp_unslash($_REQUEST['page'])) : '';

        if ($page !== self::PAGE_SLUG) {
            return false;
        }

        return in_array(
            $screen_id,
            [
                'toplevel_page_' . self::PAGE_SLUG,
                'woo-feedback_page_' . self::PAGE_SLUG,
            ],
            true
        );
    }

    /**
     * Returns whether the current user can access WooFeedback admin pages.
     *
     * @return bool
     */
    private function current_user_can_access_admin(): bool
    {
        if (current_user_can(self::PLUGIN_CAPABILITY)) {
            return true;
        }

        if (current_user_can(self::LEGACY_CAPABILITY)) {
            return true;
        }

        $filtered_capability = apply_filters('woo_feedback/admin/capability', self::PLUGIN_CAPABILITY);

        if (!is_string($filtered_capability) || $filtered_capability === '') {
            return false;
        }

        return current_user_can($filtered_capability);
    }
}
