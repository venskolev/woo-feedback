<?php
/**
 * Security admin page for WooFeedback.
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
 * Handles security settings registration and rendering.
 */
final class SecurityPage
{
    /**
     * Settings group key.
     */
    private const SETTINGS_GROUP = 'woo_feedback_security_group';

    /**
     * Security page slug.
     */
    private const PAGE_SLUG = 'woo-feedback-security';

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
     * Registers security settings hooks.
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
            'woo_feedback_security_api_section',
            __('API ключове', 'woo-feedback'),
                             [$this, 'render_api_section_intro'],
                             self::PAGE_SLUG
        );

        add_settings_field(
            'security_enable_turnstile',
            __('Активиране на Cloudflare Turnstile', 'woo-feedback'),
                           [$this, 'render_yes_no_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_security_api_section',
                           [
                               'key'         => 'security_enable_turnstile',
                           'description' => __('Включва защитата с Cloudflare Turnstile във формата за изпращане на отзиви.', 'woo-feedback'),
                           ]
        );

        add_settings_field(
            'security_turnstile_site_key',
            __('Turnstile Site Key', 'woo-feedback'),
                           [$this, 'render_text_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_security_api_section',
                           [
                               'key'         => 'security_turnstile_site_key',
                           'placeholder' => __('Въведете публичния Site Key', 'woo-feedback'),
                           'description' => __('Публичният ключ на Turnstile widget-а, който ще се използва във фронтенда.', 'woo-feedback'),
                           ]
        );

        add_settings_field(
            'security_turnstile_secret_key',
            __('Turnstile Secret Key', 'woo-feedback'),
                           [$this, 'render_password_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_security_api_section',
                           [
                               'key'         => 'security_turnstile_secret_key',
                               'placeholder' => __('Въведете Secret Key', 'woo-feedback'),
                           'description' => __('Секретният ключ се използва само за сървърната валидация на Turnstile token-а.', 'woo-feedback'),
                           ]
        );

        add_settings_section(
            'woo_feedback_security_general_section',
            __('Общи настройки', 'woo-feedback'),
                             [$this, 'render_general_section_intro'],
                             self::PAGE_SLUG
        );

        add_settings_field(
            'security_enable_protection',
            __('Активиране на защитите', 'woo-feedback'),
                           [$this, 'render_yes_no_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_security_general_section',
                           [
                               'key'         => 'security_enable_protection',
                               'description' => __('Главен превключвател за всички защити на review submit потока.', 'woo-feedback'),
                           ]
        );

        add_settings_field(
            'security_enable_honeypot',
            __('Активиране на honeypot защита', 'woo-feedback'),
                           [$this, 'render_yes_no_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_security_general_section',
                           [
                               'key'         => 'security_enable_honeypot',
                               'description' => __('Добавя скрито поле за улавяне на автоматизирани бот заявки.', 'woo-feedback'),
                           ]
        );

        add_settings_field(
            'security_enable_time_check',
            __('Активиране на времева проверка', 'woo-feedback'),
                           [$this, 'render_yes_no_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_security_general_section',
                           [
                               'key'         => 'security_enable_time_check',
                               'description' => __('Отхвърля твърде бързи изпращания, които изглеждат като автоматизирани заявки.', 'woo-feedback'),
                           ]
        );

        add_settings_field(
            'security_min_submit_time',
            __('Минимално време преди изпращане', 'woo-feedback'),
                           [$this, 'render_number_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_security_general_section',
                           [
                               'key'         => 'security_min_submit_time',
                               'min'         => 1,
                               'max'         => 30,
                               'suffix'      => __('секунди', 'woo-feedback'),
                           'description' => __('Минималното време между зареждане на формата и изпращане на отзива.', 'woo-feedback'),
                           ]
        );

        add_settings_section(
            'woo_feedback_security_advanced_section',
            __('Разширени настройки', 'woo-feedback'),
                             [$this, 'render_advanced_section_intro'],
                             self::PAGE_SLUG
        );

        add_settings_field(
            'security_enable_rate_limit',
            __('Активиране на rate limiting', 'woo-feedback'),
                           [$this, 'render_yes_no_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_security_advanced_section',
                           [
                               'key'         => 'security_enable_rate_limit',
                               'description' => __('Ограничава броя на опитите за изпращане от един и същ източник в определен период.', 'woo-feedback'),
                           ]
        );

        add_settings_field(
            'security_rate_limit_window',
            __('Период за rate limiting', 'woo-feedback'),
                           [$this, 'render_number_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_security_advanced_section',
                           [
                               'key'         => 'security_rate_limit_window',
                               'min'         => 1,
                               'max'         => 1440,
                               'suffix'      => __('минути', 'woo-feedback'),
                           'description' => __('Времевият прозорец, в който се броят опитите за изпращане.', 'woo-feedback'),
                           ]
        );

        add_settings_field(
            'security_rate_limit_max_attempts',
            __('Максимален брой опити', 'woo-feedback'),
                           [$this, 'render_number_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_security_advanced_section',
                           [
                               'key'         => 'security_rate_limit_max_attempts',
                               'min'         => 1,
                               'max'         => 100,
                               'description' => __('Колко опита за изпращане са позволени в рамките на зададения период.', 'woo-feedback'),
                           ]
        );

        add_settings_field(
            'security_enable_duplicate_check',
            __('Активиране на duplicate контрол', 'woo-feedback'),
                           [$this, 'render_yes_no_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_security_advanced_section',
                           [
                               'key'         => 'security_enable_duplicate_check',
                               'description' => __('Предотвратява многократно изпращане на идентични или почти идентични отзиви за кратко време.', 'woo-feedback'),
                           ]
        );

        add_settings_field(
            'security_duplicate_window',
            __('Период за duplicate контрол', 'woo-feedback'),
                           [$this, 'render_number_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_security_advanced_section',
                           [
                               'key'         => 'security_duplicate_window',
                               'min'         => 1,
                               'max'         => 10080,
                               'suffix'      => __('минути', 'woo-feedback'),
                           'description' => __('Периодът, в който системата засича и блокира повторно изпращане на дублиращ се отзив.', 'woo-feedback'),
                           ]
        );

        add_settings_section(
            'woo_feedback_security_whitelist_section',
            __('Whitelist настройки', 'woo-feedback'),
                             [$this, 'render_whitelist_section_intro'],
                             self::PAGE_SLUG
        );

        add_settings_field(
            'security_whitelist_ips',
            __('Разрешени IP адреси', 'woo-feedback'),
                           [$this, 'render_textarea_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_security_whitelist_section',
                           [
                               'key'         => 'security_whitelist_ips',
                               'rows'        => 8,
                               'placeholder' => "127.0.0.1\n192.168.0.10",
                               'description' => __('По един IP адрес на ред. Тези адреси ще бъдат изключени от част от автоматичните ограничения.', 'woo-feedback'),
                           ]
        );

        add_settings_section(
            'woo_feedback_security_failsafe_section',
            __('Failsafe настройки', 'woo-feedback'),
                             [$this, 'render_failsafe_section_intro'],
                             self::PAGE_SLUG
        );

        add_settings_field(
            'security_failsafe_mode',
            __('Поведение при временен проблем', 'woo-feedback'),
                           [$this, 'render_failsafe_mode_field'],
                           self::PAGE_SLUG,
                           'woo_feedback_security_failsafe_section',
                           [
                               'key'         => 'security_failsafe_mode',
                               'description' => __('Определя какво да се случи, ако външна защита като Turnstile временно не може да бъде валидирана.', 'woo-feedback'),
                           ]
        );
    }

    /**
     * Sanitizes settings through the dedicated service.
     *
     * Preserves the existing Turnstile secret key when the password field is left empty.
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

        $existing_settings = $this->settings->all();

        if (
            array_key_exists('security_turnstile_secret_key', $value)
            && trim((string) $value['security_turnstile_secret_key']) === ''
        ) {
            $value['security_turnstile_secret_key'] = (string) ($existing_settings['security_turnstile_secret_key'] ?? '');
        }

        return $this->settings->sanitize_settings($value);
    }

    /**
     * Renders the security page.
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
        <h1><?php echo esc_html__('WooFeedback – Сигурност', 'woo-feedback'); ?></h1>

        <p>
        <?php echo esc_html__('Тук управлявате защитите на формата за изпращане на отзиви, включително Cloudflare Turnstile, honeypot, времева проверка, rate limiting и duplicate контрол.', 'woo-feedback'); ?>
        </p>

        <div style="margin:18px 0 24px 0; padding:14px 16px; background:#fff; border-left:4px solid #2271b1; box-shadow:0 1px 1px rgba(0,0,0,.04);">
        <p style="margin:0;">
        <?php echo esc_html__('Важно: WooFeedback използва свои собствени настройки за сигурност и не зависи от външни captcha плъгини. Ако желаете, може да използвате същите Cloudflare ключове, но те трябва да бъдат въведени и запазени тук.', 'woo-feedback'); ?>
        </p>
        </div>

        <form method="post" action="options.php">
        <?php
        settings_fields(self::SETTINGS_GROUP);
        do_settings_sections(self::PAGE_SLUG);
        submit_button(__('Запази настройките за сигурност', 'woo-feedback'));
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
     * Renders the API section intro.
     *
     * @return void
     */
    public function render_api_section_intro(): void
    {
        echo '<p>' . esc_html__('Настройте Cloudflare Turnstile ключовете, които WooFeedback ще използва за captcha защита на review формата.', 'woo-feedback') . '</p>';
    }

    /**
     * Renders the general section intro.
     *
     * @return void
     */
    public function render_general_section_intro(): void
    {
        echo '<p>' . esc_html__('Тук се управляват базовите защити срещу автоматизирани изпращания и подозрително поведение.', 'woo-feedback') . '</p>';
    }

    /**
     * Renders the advanced section intro.
     *
     * @return void
     */
    public function render_advanced_section_intro(): void
    {
        echo '<p>' . esc_html__('Тези настройки добавят допълнителен контрол срещу flood, abuse и повторяеми заявки.', 'woo-feedback') . '</p>';
    }

    /**
     * Renders the whitelist section intro.
     *
     * @return void
     */
    public function render_whitelist_section_intro(): void
    {
        echo '<p>' . esc_html__('Тук може да добавите IP адреси, които да бъдат третирани по-доверено при определени защитни проверки.', 'woo-feedback') . '</p>';
    }

    /**
     * Renders the failsafe section intro.
     *
     * @return void
     */
    public function render_failsafe_section_intro(): void
    {
        echo '<p>' . esc_html__('Failsafe настройките определят поведението на плъгина при временен проблем с външна валидация или защитен слой.', 'woo-feedback') . '</p>';
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
        autocomplete="off"
        />
        <?php if ($description !== '') : ?>
        <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Renders a password field.
     *
     * For security reasons the stored secret is not printed back into the HTML.
     * Leaving the field empty keeps the current secret unchanged.
     *
     * @param array<string, mixed> $args Field arguments.
     *
     * @return void
     */
    public function render_password_field(array $args): void
    {
        $key           = isset($args['key']) ? (string) $args['key'] : '';
        $description   = isset($args['description']) ? (string) $args['description'] : '';
        $placeholder   = isset($args['placeholder']) ? (string) $args['placeholder'] : '';
        $current_value = (string) $this->settings->get($key, '');
        $name          = $this->build_field_name($key);
        $id            = $this->build_field_id($key);
        $has_value     = $current_value !== '';

        ?>
        <input
        type="password"
        class="regular-text"
        name="<?php echo esc_attr($name); ?>"
        id="<?php echo esc_attr($id); ?>"
        value=""
        placeholder="<?php echo esc_attr($placeholder); ?>"
        autocomplete="new-password"
        spellcheck="false"
        />
        <?php if ($description !== '') : ?>
        <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <p class="description">
        <?php
        echo esc_html(
            $has_value
            ? __('Секретен ключ вече е запазен. Оставете полето празно, ако не желаете да го променяте.', 'woo-feedback')
            : __('Все още няма запазен секретен ключ. Въведете стойност, за да активирате сървърната валидация.', 'woo-feedback')
        );
        ?>
        </p>
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
        $max         = isset($args['max']) ? absint($args['max']) : 9999;
        $suffix      = isset($args['suffix']) ? (string) $args['suffix'] : '';
        $value       = (int) $this->settings->get($key, 0);
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
        <?php if ($suffix !== '') : ?>
        <span style="margin-left:8px;"><?php echo esc_html($suffix); ?></span>
        <?php endif; ?>
        <?php if ($description !== '') : ?>
        <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Renders a textarea field.
     *
     * @param array<string, mixed> $args Field arguments.
     *
     * @return void
     */
    public function render_textarea_field(array $args): void
    {
        $key         = isset($args['key']) ? (string) $args['key'] : '';
        $description = isset($args['description']) ? (string) $args['description'] : '';
        $placeholder = isset($args['placeholder']) ? (string) $args['placeholder'] : '';
        $rows        = isset($args['rows']) ? absint($args['rows']) : 6;
        $value       = (string) $this->settings->get($key, '');
        $name        = $this->build_field_name($key);
        $id          = $this->build_field_id($key);

        ?>
        <textarea
        name="<?php echo esc_attr($name); ?>"
        id="<?php echo esc_attr($id); ?>"
        rows="<?php echo esc_attr((string) $rows); ?>"
        class="large-text code"
        placeholder="<?php echo esc_attr($placeholder); ?>"
        spellcheck="false"
        ><?php echo esc_textarea($value); ?></textarea>
        <?php if ($description !== '') : ?>
        <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Renders the failsafe mode field.
     *
     * @param array<string, mixed> $args Field arguments.
     *
     * @return void
     */
    public function render_failsafe_mode_field(array $args): void
    {
        $key         = isset($args['key']) ? (string) $args['key'] : '';
        $description = isset($args['description']) ? (string) $args['description'] : '';
        $value       = (string) $this->settings->get($key, 'open');
        $name        = $this->build_field_name($key);
        $id          = $this->build_field_id($key);

        ?>
        <select name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id); ?>">
        <option value="open" <?php selected($value, 'open'); ?>>
        <?php echo esc_html__('Отворен режим', 'woo-feedback'); ?>
        </option>
        <option value="closed" <?php selected($value, 'closed'); ?>>
        <?php echo esc_html__('Затворен режим', 'woo-feedback'); ?>
        </option>
        </select>
        <?php if ($description !== '') : ?>
        <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>

        <p class="description" style="margin-top:8px;">
        <?php echo esc_html__('Отворен режим: при временен проблем заявката може да продължи според останалите защити. Затворен режим: заявката се спира, ако външната проверка не може да бъде потвърдена.', 'woo-feedback'); ?>
        </p>
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
        return 'woo-feedback-security-field-' . sanitize_key($key);
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
