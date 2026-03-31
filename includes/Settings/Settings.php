<?php
/**
 * Settings service for WooFeedback.
 *
 * @package WooFeedback
 */

declare(strict_types=1);

namespace WDT\WooFeedback\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles plugin settings, defaults and normalization.
 */
final class Settings
{
    /**
     * Main option key.
     */
    private const OPTION_KEY = 'woo_feedback_settings';

    /**
     * Stored plugin version option key.
     */
    private const VERSION_OPTION_KEY = 'woo_feedback_version';

    /**
     * Returns the option key used by the plugin.
     *
     * @return string
     */
    public function get_option_key(): string
    {
        return self::OPTION_KEY;
    }

    /**
     * Returns all settings merged with defaults.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = get_option(self::OPTION_KEY, []);

        if (!is_array($stored)) {
            $stored = [];
        }

        return array_merge($this->defaults(), $stored);
    }

    /**
     * Returns a single setting value.
     *
     * @param string $key     Setting key.
     * @param mixed  $default Fallback default.
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        return $default;
    }

    /**
     * Updates all settings after sanitization.
     *
     * @param array<string, mixed> $settings Raw settings.
     *
     * @return bool
     */
    public function update(array $settings): bool
    {
        $sanitized = $this->sanitize_settings($settings);

        return update_option(self::OPTION_KEY, $sanitized);
    }

    /**
     * Ensures default settings exist.
     *
     * @return void
     */
    public function register_defaults(): void
    {
        $current = get_option(self::OPTION_KEY, null);

        if (!is_array($current)) {
            add_option(self::OPTION_KEY, $this->defaults());
            return;
        }

        $merged = array_merge($this->defaults(), $current);

        if ($merged !== $current) {
            update_option(self::OPTION_KEY, $merged);
        }
    }

    /**
     * Performs version-based upgrades when needed.
     *
     * @return void
     */
    public function maybe_upgrade(): void
    {
        $stored_version = get_option(self::VERSION_OPTION_KEY, '');

        if (!is_string($stored_version)) {
            $stored_version = '';
        }

        if ($stored_version === WOO_FEEDBACK_VERSION) {
            return;
        }

        $current_settings = $this->all();
        $normalized       = $this->sanitize_settings($current_settings);

        update_option(self::OPTION_KEY, array_merge($this->defaults(), $normalized));
        update_option(self::VERSION_OPTION_KEY, WOO_FEEDBACK_VERSION);
    }

    /**
     * Returns the plugin default settings.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'enable_shortcode'           => 'yes',
            'show_review_form_default'   => 'no',
            'require_login_for_review'   => 'no',
            'force_moderation'           => 'yes',
            'auto_hide_woocommerce_tab'  => 'no',
            'default_shortcode_title'    => 'Отзиви',
            'empty_reviews_message'      => 'Все още няма отзиви.',
            'success_message'            => 'Вашият отзив беше изпратен и очаква одобрение от администратор.',
            'error_message'              => 'Възникна проблем при изпращането на отзива. Моля, опитайте отново.',
            'review_form_title'          => 'Добавете отзив',
            'submit_button_text'         => 'Изпрати отзив',
            'admin_items_per_page'       => 20,
            'enable_admin_dashboard_box' => 'yes',
        ];
    }

    /**
     * Sanitizes the full settings payload.
     *
     * @param array<string, mixed> $settings Raw settings.
     *
     * @return array<string, mixed>
     */
    public function sanitize_settings(array $settings): array
    {
        $defaults = $this->defaults();

        return [
            'enable_shortcode'           => $this->sanitize_yes_no($settings['enable_shortcode'] ?? $defaults['enable_shortcode']),
            'show_review_form_default'   => $this->sanitize_yes_no($settings['show_review_form_default'] ?? $defaults['show_review_form_default']),
            'require_login_for_review'   => $this->sanitize_yes_no($settings['require_login_for_review'] ?? $defaults['require_login_for_review']),
            'force_moderation'           => $this->sanitize_yes_no($settings['force_moderation'] ?? $defaults['force_moderation']),
            'auto_hide_woocommerce_tab'  => $this->sanitize_yes_no($settings['auto_hide_woocommerce_tab'] ?? $defaults['auto_hide_woocommerce_tab']),
            'default_shortcode_title'    => $this->sanitize_text($settings['default_shortcode_title'] ?? $defaults['default_shortcode_title']),
            'empty_reviews_message'      => $this->sanitize_textarea_line($settings['empty_reviews_message'] ?? $defaults['empty_reviews_message']),
            'success_message'            => $this->sanitize_textarea_line($settings['success_message'] ?? $defaults['success_message']),
            'error_message'              => $this->sanitize_textarea_line($settings['error_message'] ?? $defaults['error_message']),
            'review_form_title'          => $this->sanitize_text($settings['review_form_title'] ?? $defaults['review_form_title']),
            'submit_button_text'         => $this->sanitize_text($settings['submit_button_text'] ?? $defaults['submit_button_text']),
            'admin_items_per_page'       => $this->sanitize_per_page($settings['admin_items_per_page'] ?? $defaults['admin_items_per_page']),
            'enable_admin_dashboard_box' => $this->sanitize_yes_no($settings['enable_admin_dashboard_box'] ?? $defaults['enable_admin_dashboard_box']),
        ];
    }

    /**
     * Sanitizes values to yes/no.
     *
     * @param mixed $value Raw value.
     *
     * @return string
     */
    private function sanitize_yes_no(mixed $value): string
    {
        return $value === 'yes' ? 'yes' : 'no';
    }

    /**
     * Sanitizes a simple single-line text field.
     *
     * @param mixed $value Raw value.
     *
     * @return string
     */
    private function sanitize_text(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return sanitize_text_field((string) $value);
    }

    /**
     * Sanitizes a short textarea-like message into a clean single line.
     *
     * @param mixed $value Raw value.
     *
     * @return string
     */
    private function sanitize_textarea_line(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $text = sanitize_textarea_field((string) $value);
        $text = preg_replace('/\s+/', ' ', $text);

        if (!is_string($text)) {
            return '';
        }

        return trim($text);
    }

    /**
     * Sanitizes the admin items per page setting.
     *
     * @param mixed $value Raw value.
     *
     * @return int
     */
    private function sanitize_per_page(mixed $value): int
    {
        $number = absint($value);

        if ($number < 1) {
            $number = 20;
        }

        if ($number > 200) {
            $number = 200;
        }

        return $number;
    }
}
