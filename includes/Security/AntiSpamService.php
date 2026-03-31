<?php
/**
 * Anti-spam and abuse protection service for WooFeedback.
 *
 * @package WooFeedback
 */

declare(strict_types=1);

namespace WDT\WooFeedback\Security;

use WDT\WooFeedback\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles honeypot, minimum submit time, whitelist checks and duplicate detection helpers.
 */
final class AntiSpamService
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
     * Returns whether the global protection layer is enabled.
     *
     * @return bool
     */
    public function is_protection_enabled(): bool
    {
        return $this->settings->get('security_enable_protection', 'yes') === 'yes';
    }

    /**
     * Returns whether honeypot protection is enabled.
     *
     * @return bool
     */
    public function is_honeypot_enabled(): bool
    {
        if (!$this->is_protection_enabled()) {
            return false;
        }

        return $this->settings->get('security_enable_honeypot', 'yes') === 'yes';
    }

    /**
     * Returns whether time-based protection is enabled.
     *
     * @return bool
     */
    public function is_time_check_enabled(): bool
    {
        if (!$this->is_protection_enabled()) {
            return false;
        }

        return $this->settings->get('security_enable_time_check', 'yes') === 'yes';
    }

    /**
     * Returns whether duplicate detection is enabled.
     *
     * @return bool
     */
    public function is_duplicate_check_enabled(): bool
    {
        if (!$this->is_protection_enabled()) {
            return false;
        }

        return $this->settings->get('security_enable_duplicate_check', 'yes') === 'yes';
    }

    /**
     * Returns the honeypot field name.
     *
     * @return string
     */
    public function get_honeypot_field_name(): string
    {
        return 'woo_feedback_hp';
    }

    /**
     * Returns the submit timestamp field name.
     *
     * @return string
     */
    public function get_started_at_field_name(): string
    {
        return 'woo_feedback_started_at';
    }

    /**
     * Returns the minimum submit time in seconds.
     *
     * @return int
     */
    public function get_min_submit_time(): int
    {
        $value = (int) $this->settings->get('security_min_submit_time', 4);

        if ($value < 1) {
            return 1;
        }

        return $value;
    }

    /**
     * Returns the duplicate window in minutes.
     *
     * @return int
     */
    public function get_duplicate_window_minutes(): int
    {
        $value = (int) $this->settings->get('security_duplicate_window', 15);

        if ($value < 1) {
            return 15;
        }

        return $value;
    }

    /**
     * Returns all whitelisted IPs.
     *
     * @return array<int, string>
     */
    public function get_whitelisted_ips(): array
    {
        $raw = $this->settings->get('security_whitelist_ips', '');

        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw);

        if (!is_array($lines)) {
            return [];
        }

        $ips = [];

        foreach ($lines as $line) {
            $ip = trim((string) $line);

            if ($ip === '') {
                continue;
            }

            $ips[] = $ip;
        }

        return array_values(array_unique($ips));
    }

    /**
     * Returns whether the provided IP is whitelisted.
     *
     * @param string $ip IP address.
     *
     * @return bool
     */
    public function is_whitelisted_ip(string $ip): bool
    {
        $ip = trim($ip);

        if ($ip === '') {
            return false;
        }

        foreach ($this->get_whitelisted_ips() as $allowed_ip) {
            if ($ip === $allowed_ip) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validates the honeypot field content.
     *
     * @param mixed  $value    Submitted field value.
     * @param string $remote_ip Visitor IP.
     *
     * @return array<string, mixed>
     */
    public function validate_honeypot(mixed $value, string $remote_ip = ''): array
    {
        if (!$this->is_honeypot_enabled()) {
            return $this->result(true, 'honeypot_disabled');
        }

        if ($this->is_whitelisted_ip($remote_ip)) {
            return $this->result(true, 'honeypot_bypassed_whitelist');
        }

        $normalized = '';

        if (is_scalar($value)) {
            $normalized = trim((string) $value);
        }

        if ($normalized !== '') {
            return $this->result(false, 'honeypot_filled');
        }

        return $this->result(true, 'honeypot_clear');
    }

    /**
     * Validates the minimum submit time.
     *
     * @param mixed  $started_at Submitted start timestamp.
     * @param string $remote_ip  Visitor IP.
     *
     * @return array<string, mixed>
     */
    public function validate_submit_time(mixed $started_at, string $remote_ip = ''): array
    {
        if (!$this->is_time_check_enabled()) {
            return $this->result(true, 'time_check_disabled');
        }

        if ($this->is_whitelisted_ip($remote_ip)) {
            return $this->result(true, 'time_check_bypassed_whitelist');
        }

        if (!is_scalar($started_at)) {
            return $this->result(false, 'missing_submit_timestamp');
        }

        $started_at_value = absint((string) $started_at);

        if ($started_at_value < 1) {
            return $this->result(false, 'invalid_submit_timestamp');
        }

        $elapsed = time() - $started_at_value;
        $minimum = $this->get_min_submit_time();

        if ($elapsed < $minimum) {
            return $this->result(false, 'submit_too_fast', [
                'elapsed' => $elapsed,
                'minimum' => $minimum,
            ]);
        }

        return $this->result(true, 'submit_time_ok', [
            'elapsed' => $elapsed,
            'minimum' => $minimum,
        ]);
    }

    /**
     * Returns a normalized review content hash.
     *
     * @param int    $product_id Product ID.
     * @param string $author     Review author.
     * @param string $email      Review email.
     * @param string $content    Review content.
     *
     * @return string
     */
    public function build_duplicate_hash(int $product_id, string $author, string $email, string $content): string
    {
        $payload = [
            'product_id' => $product_id,
            'author'     => $this->normalize_text_for_hash($author),
            'email'      => $this->normalize_email_for_hash($email),
            'content'    => $this->normalize_text_for_hash($content),
        ];

        return hash('sha256', wp_json_encode($payload));
    }

    /**
     * Finds a recent duplicate review based on normalized hash.
     *
     * @param int    $product_id Product ID.
     * @param string $author     Review author.
     * @param string $email      Review email.
     * @param string $content    Review content.
     *
     * @return int
     */
    public function find_recent_duplicate_review_id(
        int $product_id,
        string $author,
        string $email,
        string $content
    ): int {
        if (!$this->is_duplicate_check_enabled()) {
            return 0;
        }

        $hash = $this->build_duplicate_hash($product_id, $author, $email, $content);

        global $wpdb;

        if (!isset($wpdb->commentmeta, $wpdb->comments)) {
            return 0;
        }

        $window_minutes = $this->get_duplicate_window_minutes();
        $since_gmt      = gmdate('Y-m-d H:i:s', time() - ($window_minutes * MINUTE_IN_SECONDS));

        $sql = $wpdb->prepare(
            "SELECT c.comment_ID
            FROM {$wpdb->comments} c
            INNER JOIN {$wpdb->commentmeta} cm
            ON c.comment_ID = cm.comment_id
            WHERE c.comment_post_ID = %d
            AND c.comment_type = %s
            AND c.comment_date_gmt >= %s
            AND cm.meta_key = %s
            AND cm.meta_value = %s
            ORDER BY c.comment_ID DESC
            LIMIT 1",
            $product_id,
            'review',
            $since_gmt,
            '_woo_feedback_duplicate_hash',
            $hash
        );

        $review_id = $wpdb->get_var($sql);

        return absint($review_id);
    }

    /**
     * Stores the duplicate hash on a newly created review.
     *
     * @param int    $comment_id Comment ID.
     * @param int    $product_id Product ID.
     * @param string $author     Review author.
     * @param string $email      Review email.
     * @param string $content    Review content.
     *
     * @return void
     */
    public function store_duplicate_hash(
        int $comment_id,
        int $product_id,
        string $author,
        string $email,
        string $content
    ): void {
        if ($comment_id < 1 || !$this->is_duplicate_check_enabled()) {
            return;
        }

        $hash = $this->build_duplicate_hash($product_id, $author, $email, $content);

        update_comment_meta($comment_id, '_woo_feedback_duplicate_hash', $hash);
    }

    /**
     * Normalizes text for duplicate hashing.
     *
     * @param string $value Text value.
     *
     * @return string
     */
    private function normalize_text_for_hash(string $value): string
    {
        $value = wp_strip_all_tags($value);
        $value = sanitize_textarea_field($value);
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value);

        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    /**
     * Normalizes email for duplicate hashing.
     *
     * @param string $value Email value.
     *
     * @return string
     */
    private function normalize_email_for_hash(string $value): string
    {
        $value = sanitize_email($value);
        $value = strtolower($value);

        return trim($value);
    }

    /**
     * Creates a normalized result payload.
     *
     * @param bool                 $passed  Whether the validation passed.
     * @param string               $reason  Internal reason code.
     * @param array<string, mixed> $details Additional context.
     *
     * @return array<string, mixed>
     */
    private function result(bool $passed, string $reason, array $details = []): array
    {
        return [
            'passed'  => $passed,
            'reason'  => $reason,
            'details' => $details,
        ];
    }
}
