<?php
/**
 * Cloudflare Turnstile service for WooFeedback.
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
 * Handles Turnstile configuration checks and server-side token verification.
 */
final class TurnstileService
{
    /**
     * Cloudflare Turnstile siteverify endpoint.
     */
    private const VERIFY_ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

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
         * Returns whether the global security layer is enabled.
         *
         * @return bool
         */
        public function is_protection_enabled(): bool
        {
            return $this->settings->get('security_enable_protection', 'yes') === 'yes';
        }

        /**
         * Returns whether Turnstile is enabled from settings.
         *
         * @return bool
         */
        public function is_turnstile_enabled(): bool
        {
            if (!$this->is_protection_enabled()) {
                return false;
            }

            return $this->settings->get('security_enable_turnstile', 'no') === 'yes';
        }

        /**
         * Returns whether Turnstile is fully configured and usable.
         *
         * @return bool
         */
        public function is_turnstile_configured(): bool
        {
            if (!$this->is_turnstile_enabled()) {
                return false;
            }

            return $this->get_site_key() !== '' && $this->get_secret_key() !== '';
        }

        /**
         * Returns the configured site key.
         *
         * @return string
         */
        public function get_site_key(): string
        {
            $key = $this->settings->get('security_turnstile_site_key', '');

            return is_string($key) ? trim($key) : '';
        }

        /**
         * Returns the configured secret key.
         *
         * @return string
         */
        public function get_secret_key(): string
        {
            $key = $this->settings->get('security_turnstile_secret_key', '');

            return is_string($key) ? trim($key) : '';
        }

        /**
         * Returns whether the plugin should fail open or fail closed.
         *
         * @return bool
         */
        public function should_fail_open(): bool
        {
            return $this->settings->get('security_failsafe_mode', 'open') !== 'closed';
        }

        /**
         * Returns whether the Turnstile widget should be rendered in the frontend form.
         *
         * @return bool
         */
        public function should_render_widget(): bool
        {
            return $this->is_turnstile_configured();
        }

        /**
         * Returns the frontend API script URL for Turnstile.
         *
         * @return string
         */
        public function get_api_script_url(): string
        {
            return 'https://challenges.cloudflare.com/turnstile/v0/api.js';
        }

        /**
         * Returns the expected POST field name containing the token.
         *
         * @return string
         */
        public function get_response_field_name(): string
        {
            return 'cf-turnstile-response';
        }

        /**
         * Verifies the submitted Turnstile token on the server side.
         *
         * @param string $token     Submitted Turnstile token.
         * @param string $remote_ip Visitor IP address.
         *
         * @return array<string, mixed>
         */
        public function verify_token(string $token, string $remote_ip = ''): array
        {
            if (!$this->is_turnstile_enabled()) {
                return $this->result(true, 'turnstile_disabled');
            }

            if (!$this->is_turnstile_configured()) {
                if ($this->should_fail_open()) {
                    return $this->result(true, 'turnstile_not_configured_fail_open');
                }

                return $this->result(false, 'turnstile_not_configured_fail_closed');
            }

            $token = trim($token);

            if ($token === '') {
                return $this->result(false, 'missing_turnstile_token');
            }

            $body = [
                'secret'   => $this->get_secret_key(),
                'response' => $token,
            ];

            $remote_ip = $this->sanitize_remote_ip($remote_ip);

            if ($remote_ip !== '') {
                $body['remoteip'] = $remote_ip;
            }

            $response = wp_remote_post(
                self::VERIFY_ENDPOINT,
                [
                    'timeout' => 10,
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                    'body'    => $body,
                ]
            );

            if (is_wp_error($response)) {
                if ($this->should_fail_open()) {
                    return $this->result(true, 'turnstile_request_error_fail_open', [
                        'wp_error_message' => $response->get_error_message(),
                    ]);
                }

                return $this->result(false, 'turnstile_request_error_fail_closed', [
                    'wp_error_message' => $response->get_error_message(),
                ]);
            }

            $status_code = (int) wp_remote_retrieve_response_code($response);
            $raw_body    = wp_remote_retrieve_body($response);

            if ($status_code < 200 || $status_code >= 300 || $raw_body === '') {
                if ($this->should_fail_open()) {
                    return $this->result(true, 'turnstile_invalid_http_response_fail_open', [
                        'status_code' => $status_code,
                    ]);
                }

                return $this->result(false, 'turnstile_invalid_http_response_fail_closed', [
                    'status_code' => $status_code,
                ]);
            }

            $decoded = json_decode($raw_body, true);

            if (!is_array($decoded)) {
                if ($this->should_fail_open()) {
                    return $this->result(true, 'turnstile_invalid_json_fail_open');
                }

                return $this->result(false, 'turnstile_invalid_json_fail_closed');
            }

            $success      = !empty($decoded['success']);
            $error_codes  = [];
            $hostname     = '';
            $challenge_ts = '';

            if (isset($decoded['error-codes']) && is_array($decoded['error-codes'])) {
                $error_codes = array_values(
                    array_filter(
                        array_map(
                            static fn ($value): string => is_scalar($value) ? sanitize_text_field((string) $value) : '',
                                  $decoded['error-codes']
                        ),
                        static fn (string $value): bool => $value !== ''
                    )
                );
            }

            if (isset($decoded['hostname']) && is_scalar($decoded['hostname'])) {
                $hostname = sanitize_text_field((string) $decoded['hostname']);
            }

            if (isset($decoded['challenge_ts']) && is_scalar($decoded['challenge_ts'])) {
                $challenge_ts = sanitize_text_field((string) $decoded['challenge_ts']);
            }

            if (!$success) {
                return $this->result(false, 'turnstile_verification_failed', [
                    'error_codes'  => $error_codes,
                    'hostname'     => $hostname,
                    'challenge_ts' => $challenge_ts,
                ]);
            }

            if (!$this->is_valid_hostname($hostname)) {
                return $this->result(false, 'turnstile_hostname_mismatch', [
                    'error_codes'  => $error_codes,
                    'hostname'     => $hostname,
                    'challenge_ts' => $challenge_ts,
                    'expected'     => $this->get_expected_hostname(),
                ]);
            }

            return $this->result(true, 'turnstile_verified', [
                'error_codes'  => $error_codes,
                'hostname'     => $hostname,
                'challenge_ts' => $challenge_ts,
            ]);
        }

        /**
         * Returns the expected hostname for validation.
         *
         * @return string
         */
        private function get_expected_hostname(): string
        {
            $site_url = home_url('/');

            if (!is_string($site_url) || $site_url === '') {
                return '';
            }

            $host = wp_parse_url($site_url, PHP_URL_HOST);

            if (!is_string($host) || $host === '') {
                return '';
            }

            return strtolower($host);
        }

        /**
         * Validates the hostname returned by Turnstile.
         *
         * If the API does not provide a hostname or the local site hostname cannot be
         * resolved, the check stays permissive to avoid accidental lockouts.
         *
         * @param string $hostname Returned hostname.
         *
         * @return bool
         */
        private function is_valid_hostname(string $hostname): bool
        {
            $hostname = strtolower(trim($hostname));
            $expected = $this->get_expected_hostname();

            if ($hostname === '' || $expected === '') {
                return true;
            }

            return hash_equals($expected, $hostname);
        }

        /**
         * Sanitizes the remote IP before sending it to the verification endpoint.
         *
         * @param string $remote_ip Raw IP value.
         *
         * @return string
         */
        private function sanitize_remote_ip(string $remote_ip): string
        {
            $remote_ip = trim($remote_ip);

            if ($remote_ip === '') {
                return '';
            }

            return filter_var($remote_ip, FILTER_VALIDATE_IP) !== false ? $remote_ip : '';
        }

        /**
         * Creates a normalized result structure.
         *
         * @param bool                 $passed  Whether the check passed.
         * @param string               $reason  Internal reason code.
         * @param array<string, mixed> $details Additional details.
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
