<?php
/**
 * Plugin deactivator for WooFeedback.
 *
 * @package WooFeedback
 */

declare(strict_types=1);

namespace WDT\WooFeedback\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles plugin deactivation tasks.
 */
final class Deactivator
{
    /**
     * Runs the deactivation routine.
     *
     * @return void
     */
    public static function deactivate(): void
    {
        self::store_deactivation_timestamp();
        self::flush_rewrite_rules_safe();
    }

    /**
     * Stores the last deactivation timestamp.
     *
     * @return void
     */
    private static function store_deactivation_timestamp(): void
    {
        update_option('woo_feedback_last_deactivated_at', (string) time());
    }

    /**
     * Flushes rewrite rules safely.
     *
     * @return void
     */
    private static function flush_rewrite_rules_safe(): void
    {
        flush_rewrite_rules(false);
    }
}
