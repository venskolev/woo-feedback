<?php
/**
 * Settings admin page for WooFeedback.
 *
 * @package WooFeedback
 */

declare(strict_types=1);

namespace WDT\WooFeedback\Admin;

use WDT\WooFeedback\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles plugin settings registration and rendering.
 */
final class SettingsPage
{
    /**
     * Settings group key.
     */
    private const SETTINGS_GROUP = 'woo_feedback_settings_group';

    /**
     * Settings page slug.
     */
    private const PAGE_SLUG = 'woo-feedback-settings';

    /**
     * Plugin-specific capability.
     */
    private const PLUGIN_CAPABILITY = 'manage_woo_feedback';

    /**
     * Legacy fallback capability.
     */
    private const LEGACY_CAPABILITY = 'moderate_comments';

    /**
     * Settings service instance.
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
     * Registers settings hooks.
     *
     * @return void
     */
    public function register_settings(): void
    {
        register_setting(
            self::SETTINGS_GROUP,
            $this->settings->get_option_key(),
                         [
                             'type'              => 'array',
                         'sanitize_callback' => [$this, 'sanitize_settings'],
                         'default'           => $this->settings->defaults(),
                         ]
        );

        add_settings_section(
            'woo_feedback_general_section',
            __('Основни настройки', 'woo-feedback'),
                             [$this, 'render_general_section_intro'],
                             self::PAGE_SLUG
        );

        add_settings_field(
            'enable_shortcode',
            __('Активиране на shortcode', 'woo-feedback'),
                           [$this, 'render_yes_no_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_general_section',
                           [
                               'key'         => 'enable_shortcode',
                           'description' => __('Позволява използването на shortcode-а за визуализация на отзиви.', 'woo-feedback'),
                           ]
        );

        add_settings_field(
            'show_review_form_default',
            __('Показване на формата по подразбиране', 'woo-feedback'),
                           [$this, 'render_yes_no_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_general_section',
                           [
                               'key'         => 'show_review_form_default',
                           'description' => __('Определя дали формата за нов отзив да се показва по подразбиране в shortcode-а.', 'woo-feedback'),
                           ]
        );

        add_settings_field(
            'require_login_for_review',
            __('Само за влезли потребители', 'woo-feedback'),
                           [$this, 'render_yes_no_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_general_section',
                           [
                               'key'         => 'require_login_for_review',
                               'description' => __('Ако е включено, само влезли потребители могат да изпращат нови отзиви.', 'woo-feedback'),
                           ]
        );

        add_settings_field(
            'force_moderation',
            __('Задължително одобрение', 'woo-feedback'),
                           [$this, 'render_yes_no_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_general_section',
                           [
                               'key'         => 'force_moderation',
                               'description' => __('Всеки нов отзив ще чака одобрение от администратор преди публикуване.', 'woo-feedback'),
                           ]
        );

        add_settings_field(
            'auto_hide_woocommerce_tab',
            __('Скриване на стандартния WooCommerce tab', 'woo-feedback'),
                           [$this, 'render_yes_no_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_general_section',
                           [
                               'key'         => 'auto_hide_woocommerce_tab',
                               'description' => __('Позволява автоматично скриване на стандартния tab за отзиви на продуктовата страница.', 'woo-feedback'),
                           ]
        );

        add_settings_section(
            'woo_feedback_content_section',
            __('Текстове и съдържание', 'woo-feedback'),
                             [$this, 'render_content_section_intro'],
                             self::PAGE_SLUG
        );

        add_settings_field(
            'default_shortcode_title',
            __('Заглавие на блока', 'woo-feedback'),
                           [$this, 'render_text_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_content_section',
                           [
                               'key'         => 'default_shortcode_title',
                               'placeholder' => __('Отзиви', 'woo-feedback'),
                           'description' => __('Заглавието, което се показва над списъка с отзиви, ако не е подадено друго в shortcode-а.', 'woo-feedback'),
                           ]
        );

        add_settings_field(
            'empty_reviews_message',
            __('Съобщение при липса на отзиви', 'woo-feedback'),
                           [$this, 'render_text_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_content_section',
                           [
                               'key'         => 'empty_reviews_message',
                               'placeholder' => __('Все още няма отзиви.', 'woo-feedback'),
                           'description' => __('Текст, който се показва когато няма одобрени отзиви.', 'woo-feedback'),
                           ]
        );

        add_settings_field(
            'success_message',
            __('Съобщение при успешно изпращане', 'woo-feedback'),
                           [$this, 'render_text_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_content_section',
                           [
                               'key'         => 'success_message',
                               'placeholder' => __('Вашият отзив беше изпратен и очаква одобрение от администратор.', 'woo-feedback'),
                           'description' => __('Съобщение след успешно изпращане на нов отзив.', 'woo-feedback'),
                           ]
        );

        add_settings_field(
            'error_message',
            __('Съобщение при грешка', 'woo-feedback'),
                           [$this, 'render_text_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_content_section',
                           [
                               'key'         => 'error_message',
                               'placeholder' => __('Възникна проблем при изпращането на отзива. Моля, опитайте отново.', 'woo-feedback'),
                           'description' => __('Съобщение при неуспешно изпращане на нов отзив.', 'woo-feedback'),
                           ]
        );

        add_settings_field(
            'review_form_title',
            __('Заглавие на формата', 'woo-feedback'),
                           [$this, 'render_text_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_content_section',
                           [
                               'key'         => 'review_form_title',
                               'placeholder' => __('Добавете отзив', 'woo-feedback'),
                           'description' => __('Заглавие над формата за нов отзив.', 'woo-feedback'),
                           ]
        );

        add_settings_field(
            'submit_button_text',
            __('Текст на бутона', 'woo-feedback'),
                           [$this, 'render_text_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_content_section',
                           [
                               'key'         => 'submit_button_text',
                               'placeholder' => __('Изпрати отзив', 'woo-feedback'),
                           'description' => __('Текст на бутона за изпращане на нов отзив.', 'woo-feedback'),
                           ]
        );

        add_settings_section(
            'woo_feedback_admin_section',
            __('Администрация', 'woo-feedback'),
                             [$this, 'render_admin_section_intro'],
                             self::PAGE_SLUG
        );

        add_settings_field(
            'admin_items_per_page',
            __('Елементи на страница', 'woo-feedback'),
                           [$this, 'render_number_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_admin_section',
                           [
                               'key'         => 'admin_items_per_page',
                               'min'         => 1,
                               'max'         => 200,
                               'description' => __('Определя колко отзива да се показват на страница в административния списък.', 'woo-feedback'),
                           ]
        );

        add_settings_field(
            'enable_admin_dashboard_box',
            __('Информационен панел в администрацията', 'woo-feedback'),
                           [$this, 'render_yes_no_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_admin_section',
                           [
                               'key'         => 'enable_admin_dashboard_box',
                               'description' => __('Резервирана настройка за административен информационен блок в следваща стъпка.', 'woo-feedback'),
                           ]
        );
    }

    /**
     * Sanitizes settings through the dedicated service.
     *
     * @param mixed $value Raw option value.
     *
     * @return array<string, mixed>
     */
    public function sanitize_settings(mixed $value): array
    {
        if (!is_array($value)) {
            $value = [];
        }

        return $this->settings->sanitize_settings($value);
    }

    /**
     * Renders the settings page.
     *
     * @return void
     */
    public function render_page(): void
    {
        if (!$this->current_user_can_access_admin()) {
            wp_die(esc_html__('Нямате права за достъп до тази страница.', 'woo-feedback'));
        }

        ?>
        <div class="wrap woo-feedback-admin-page">
        <h1><?php echo esc_html__('WooFeedback – Настройки', 'woo-feedback'); ?></h1>

        <p>
        <?php echo esc_html__('Управлявайте показването на отзивите, поведението на формата и административните настройки на плъгина.', 'woo-feedback'); ?>
        </p>

        <form method="post" action="options.php">
        <?php
        settings_fields(self::SETTINGS_GROUP);
        do_settings_sections(self::PAGE_SLUG);
        submit_button(__('Запази настройките', 'woo-feedback'));
        ?>
        </form>

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
        <?php
    }

    /**
     * Renders the general section intro.
     *
     * @return void
     */
    public function render_general_section_intro(): void
    {
        echo '<p>' . esc_html__('Тук се настройва основното поведение на shortcode-а и логиката за изпращане на отзиви.', 'woo-feedback') . '</p>';
    }

    /**
     * Renders the content section intro.
     *
     * @return void
     */
    public function render_content_section_intro(): void
    {
        echo '<p>' . esc_html__('Тук управлявате текстовете, които се показват на посетителите във фронтенда.', 'woo-feedback') . '</p>';
    }

    /**
     * Renders the admin section intro.
     *
     * @return void
     */
    public function render_admin_section_intro(): void
    {
        echo '<p>' . esc_html__('Тези настройки засягат административния изглед и удобството при работа с отзивите.', 'woo-feedback') . '</p>';
    }

    /**
     * Renders a yes/no select field.
     *
     * @param array<string, mixed> $args Field arguments.
     *
     * @return void
     */
    public function render_yes_no_field(array $args): void
    {
        $key         = isset($args['key']) ? (string) $args['key'] : '';
        $description = isset($args['description']) ? (string) $args['description'] : '';
        $value       = (string) $this->settings->get($key, 'no');
        $name        = $this->build_field_name($key);
        $id          = $this->build_field_id($key);

        ?>
        <select name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id); ?>">
        <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html__('Да', 'woo-feedback'); ?></option>
        <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html__('Не', 'woo-feedback'); ?></option>
        </select>
        <?php if ($description !== '') : ?>
        <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Renders a text field.
     *
     * @param array<string, mixed> $args Field arguments.
     *
     * @return void
     */
    public function render_text_field(array $args): void
    {
        $key         = isset($args['key']) ? (string) $args['key'] : '';
        $description = isset($args['description']) ? (string) $args['description'] : '';
        $placeholder = isset($args['placeholder']) ? (string) $args['placeholder'] : '';
        $value       = (string) $this->settings->get($key, '');
        $name        = $this->build_field_name($key);
        $id          = $this->build_field_id($key);

        ?>
        <input
        type="text"
        class="regular-text"
        name="<?php echo esc_attr($name); ?>"
        id="<?php echo esc_attr($id); ?>"
        value="<?php echo esc_attr($value); ?>"
        placeholder="<?php echo esc_attr($placeholder); ?>"
        />
        <?php if ($description !== '') : ?>
        <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Renders a number field.
     *
     * @param array<string, mixed> $args Field arguments.
     *
     * @return void
     */
    public function render_number_field(array $args): void
    {
        $key         = isset($args['key']) ? (string) $args['key'] : '';
        $description = isset($args['description']) ? (string) $args['description'] : '';
        $min         = isset($args['min']) ? absint($args['min']) : 1;
        $max         = isset($args['max']) ? absint($args['max']) : 200;
        $value       = (int) $this->settings->get($key, 20);
        $name        = $this->build_field_name($key);
        $id          = $this->build_field_id($key);

        ?>
        <input
        type="number"
        class="small-text"
        name="<?php echo esc_attr($name); ?>"
        id="<?php echo esc_attr($id); ?>"
        value="<?php echo esc_attr((string) $value); ?>"
        min="<?php echo esc_attr((string) $min); ?>"
        max="<?php echo esc_attr((string) $max); ?>"
        step="1"
        />
        <?php if ($description !== '') : ?>
        <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Builds the option field name.
     *
     * @param string $key Option key.
     *
     * @return string
     */
    private function build_field_name(string $key): string
    {
        return $this->settings->get_option_key() . '[' . $key . ']';
    }

    /**
     * Builds the DOM field id.
     *
     * @param string $key Option key.
     *
     * @return string
     */
    private function build_field_id(string $key): string
    {
        return 'woo-feedback-field-' . sanitize_key($key);
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
