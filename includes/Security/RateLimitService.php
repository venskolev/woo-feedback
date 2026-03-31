<?php
/**
 * Rate limiting service for WooFeedback.
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
 * Handles rate limiting by IP, email and product context.
 */
final class RateLimitService
{
    /**
     * Transient key prefix.
     */
    private const TRANSIENT_PREFIX = 'woo_feedback_rl_';

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
     * Returns whether rate limiting is enabled.
     *
     * @return bool
     */
    public function is_rate_limit_enabled(): bool
    {
        if (!$this->is_protection_enabled()) {
            return false;
        }

        return $this->settings->get('security_enable_rate_limit', 'yes') === 'yes';
    }

    /**
     * Returns the configured window in minutes.
     *
     * @return int
     */
    public function get_window_minutes(): int
    {
        $value = (int) $this->settings->get('security_rate_limit_window', 10);

        if ($value < 1) {
            return 10;
        }

        return $value;
    }

    /**
     * Returns the maximum attempts allowed per window.
     *
     * @return int
     */
    public function get_max_attempts(): int
    {
        $value = (int) $this->settings->get('security_rate_limit_max_attempts', 5);

        if ($value < 1) {
            return 5;
        }

        return $value;
    }

    /**
     * Checks whether a submit is allowed for the given source.
     *
     * @param string $remote_ip  Visitor IP.
     * @param string $email      Visitor email.
     * @param int    $product_id Product ID.
     *
     * @return array<string, mixed>
     */
    public function is_allowed(string $remote_ip, string $email, int $product_id): array
    {
        if (!$this->is_rate_limit_enabled()) {
            return $this->result(true, 'rate_limit_disabled');
        }

        $keys = $this->build_context_keys($remote_ip, $email, $product_id);

        if ($keys === []) {
            return $this->result(true, 'rate_limit_no_context');
        }

        $window_seconds = $this->get_window_minutes() * MINUTE_IN_SECONDS;
        $max_attempts   = $this->get_max_attempts();

        foreach ($keys as $key_info) {
            $state = $this->get_counter_state($key_info['key'], $window_seconds);

            if ($state['count'] >= $max_attempts) {
                return $this->result(false, 'rate_limit_exceeded', [
                    'scope'          => $key_info['scope'],
                    'window_minutes' => $this->get_window_minutes(),
                                     'max_attempts'   => $max_attempts,
                                     'retry_after'    => $state['retry_after'],
                                     'count'          => $state['count'],
                ]);
            }
        }

        return $this->result(true, 'rate_limit_allowed', [
            'window_minutes' => $this->get_window_minutes(),
                             'max_attempts'   => $max_attempts,
        ]);
    }

    /**
     * Records a submit attempt for the given source.
     *
     * @param string $remote_ip  Visitor IP.
     * @param string $email      Visitor email.
     * @param int    $product_id Product ID.
     *
     * @return void
     */
    public function record_attempt(string $remote_ip, string $email, int $product_id): void
    {
        if (!$this->is_rate_limit_enabled()) {
            return;
        }

        $keys = $this->build_context_keys($remote_ip, $email, $product_id);

        if ($keys === []) {
            return;
        }

        $window_seconds = $this->get_window_minutes() * MINUTE_IN_SECONDS;

        foreach ($keys as $key_info) {
            $this->increment_counter($key_info['key'], $window_seconds);
        }
    }

    /**
     * Builds all contextual keys for the current source.
     *
     * @param string $remote_ip  Visitor IP.
     * @param string $email      Visitor email.
     * @param int    $product_id Product ID.
     *
     * @return array<int, array<string, string>>
     */
    private function build_context_keys(string $remote_ip, string $email, int $product_id): array
    {
        $keys = [];

        $normalized_ip    = $this->normalize_ip($remote_ip);
        $normalized_email = $this->normalize_email($email);
        $normalized_post  = $product_id > 0 ? (string) $product_id : '';

        if ($normalized_ip !== '') {
            $keys[] = [
                'scope' => 'ip',
                'key'   => $this->build_storage_key('ip|' . $normalized_ip),
            ];
        }

        if ($normalized_email !== '') {
            $keys[] = [
                'scope' => 'email',
                'key'   => $this->build_storage_key('email|' . $normalized_email),
            ];
        }

        if ($normalized_post !== '' && $normalized_ip !== '') {
            $keys[] = [
                'scope' => 'product_ip',
                'key'   => $this->build_storage_key('product|' . $normalized_post . '|ip|' . $normalized_ip),
            ];
        }

        if ($normalized_post !== '' && $normalized_email !== '') {
            $keys[] = [
                'scope' => 'product_email',
                'key'   => $this->build_storage_key('product|' . $normalized_post . '|email|' . $normalized_email),
            ];
        }

        return $keys;
    }

    /**
     * Builds a normalized transient key.
     *
     * @param string $raw Raw context.
     *
     * @return string
     */
    private function build_storage_key(string $raw): string
    {
        return self::TRANSIENT_PREFIX . md5($raw);
    }

    /**
     * Reads a current counter state.
     *
     * @param string $key            Storage key.
     * @param int    $window_seconds Window duration.
     *
     * @return array<string, int>
     */
    private function get_counter_state(string $key, int $window_seconds): array
    {
        $stored = get_transient($key);

        if (!is_array($stored)) {
            return [
                'count'       => 0,
                'retry_after' => 0,
            ];
        }

        $count      = isset($stored['count']) ? absint($stored['count']) : 0;
        $created_at = isset($stored['created_at']) ? absint($stored['created_at']) : 0;

        if ($created_at < 1) {
            return [
                'count'       => 0,
                'retry_after' => 0,
            ];
        }

        $age = time() - $created_at;

        if ($age >= $window_seconds) {
            delete_transient($key);

            return [
                'count'       => 0,
                'retry_after' => 0,
            ];
        }

        $retry_after = $window_seconds - $age;

        if ($retry_after < 0) {
            $retry_after = 0;
        }

        return [
            'count'       => $count,
            'retry_after' => $retry_after,
        ];
    }

    /**
     * Increments a counter within the current time window.
     *
     * @param string $key            Storage key.
     * @param int    $window_seconds Window duration.
     *
     * @return void
     */
    private function increment_counter(string $key, int $window_seconds): void
    {
        $stored = get_transient($key);

        if (!is_array($stored)) {
            $stored = [
                'count'      => 0,
                'created_at' => time(),
            ];
        }

        $count      = isset($stored['count']) ? absint($stored['count']) : 0;
        $created_at = isset($stored['created_at']) ? absint($stored['created_at']) : 0;

        if ($created_at < 1) {
            $created_at = time();
        }

        $age = time() - $created_at;

        if ($age >= $window_seconds) {
            $count      = 0;
            $created_at = time();
        }

        $count++;

        set_transient(
            $key,
            [
                'count'      => $count,
                'created_at' => $created_at,
            ],
            $window_seconds
        );
    }

    /**
     * Normalizes an email value.
     *
     * @param string $email Raw email.
     *
     * @return string
     */
    private function normalize_email(string $email): string
    {
        $email = sanitize_email($email);

        if (!is_email($email)) {
            return '';
        }

        return strtolower($email);
    }

    /**
     * Normalizes an IP value.
     *
     * @param string $ip Raw IP.
     *
     * @return string
     */
    private function normalize_ip(string $ip): string
    {
        $ip = trim($ip);

        if ($ip === '') {
            return '';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return '';
        }

        return $ip;
    }

    /**
     * Builds a normalized result payload.
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
