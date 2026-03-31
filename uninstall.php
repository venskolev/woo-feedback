<?php
/**
 * Uninstall routine for WooFeedback.
 *
 * Removes only plugin-owned options and metadata.
 * Native WooCommerce/WordPress product reviews remain untouched.
 *
 * @package WooFeedback
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Deletes a single option and its network variant if present.
 *
 * @param string $option_name Option key.
 *
 * @return void
 */
function woo_feedback_delete_option(string $option_name): void
{
    delete_option($option_name);

    if (is_multisite()) {
        delete_site_option($option_name);
    }
}

/**
 * Deletes plugin-owned comment meta entries without touching
 * native WooCommerce review data such as rating.
 *
 * @param string $meta_key Comment meta key.
 *
 * @return void
 */
function woo_feedback_delete_comment_meta_by_key(string $meta_key): void
{
    global $wpdb;

    if (!isset($wpdb) || !($wpdb instanceof wpdb)) {
        return;
    }

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->commentmeta} WHERE meta_key = %s",
            $meta_key
        )
    );
}

woo_feedback_delete_option('woo_feedback_settings');
woo_feedback_delete_option('woo_feedback_version');
woo_feedback_delete_option('woo_feedback_installed_at');
woo_feedback_delete_option('woo_feedback_last_deactivated_at');

woo_feedback_delete_comment_meta_by_key('woo_feedback_source');
