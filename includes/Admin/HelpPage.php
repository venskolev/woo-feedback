<?php
/**
 * Help admin page for WooFeedback.
 *
 * @package WooFeedback
 */

declare(strict_types=1);

namespace WDT\WooFeedback\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders the help and usage page for administrators.
 */
final class HelpPage
{
    /**
     * Settings page slug.
     */
    private const SETTINGS_PAGE_SLUG = 'woo-feedback-settings';

    /**
     * Reviews page slug.
     */
    private const REVIEWS_PAGE_SLUG = 'woo-feedback-reviews';

    /**
     * Plugin-specific capability.
     */
    private const PLUGIN_CAPABILITY = 'manage_woo_feedback';

    /**
     * Legacy fallback capability.
     */
    private const LEGACY_CAPABILITY = 'moderate_comments';

    /**
     * Renders the help page.
     *
     * @return void
     */
    public function render_page(): void
    {
        if (!$this->current_user_can_access_admin()) {
            wp_die(esc_html__('Нямате права за достъп до тази страница.', 'woo-feedback'));
        }

        $settings_url = admin_url('admin.php?page=' . self::SETTINGS_PAGE_SLUG);
        $reviews_url  = admin_url('admin.php?page=' . self::REVIEWS_PAGE_SLUG);

        ?>
        <div class="wrap woo-feedback-admin-page">
        <h1><?php echo esc_html__('WooFeedback – Помощ', 'woo-feedback'); ?></h1>

        <p>
        <?php echo esc_html__('Тази страница описва как работи плъгинът WooFeedback, как се визуализират отзивите и как администраторът управлява процеса по одобрение и показване.', 'woo-feedback'); ?>
        </p>

        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:16px; margin:24px 0;">
        <div style="background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:18px;">
        <h2 style="margin-top:0; font-size:18px;">
        <?php echo esc_html__('Какво прави WooFeedback', 'woo-feedback'); ?>
        </h2>
        <p style="margin-bottom:0;">
        <?php echo esc_html__('WooFeedback добавя удобен начин за показване на продуктови отзиви в отделен блок чрез shortcode, като използва изцяло native системата за reviews и comments на WooCommerce и WordPress.', 'woo-feedback'); ?>
        </p>
        </div>

        <div style="background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:18px;">
        <h2 style="margin-top:0; font-size:18px;">
        <?php echo esc_html__('Важно уточнение', 'woo-feedback'); ?>
        </h2>
        <p style="margin-bottom:0;">
        <?php echo esc_html__('Плъгинът не създава отделна review система, не дублира отзивите и не пази второ копие на данните. Работи върху стандартните WooCommerce product reviews.', 'woo-feedback'); ?>
        </p>
        </div>

        <div style="background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:18px;">
        <h2 style="margin-top:0; font-size:18px;">
        <?php echo esc_html__('Бързи връзки', 'woo-feedback'); ?>
        </h2>
        <p style="margin:0 0 10px 0;">
        <a class="button button-secondary" href="<?php echo esc_url($settings_url); ?>">
        <?php echo esc_html__('Към настройките', 'woo-feedback'); ?>
        </a>
        </p>
        <p style="margin:0;">
        <a class="button button-secondary" href="<?php echo esc_url($reviews_url); ?>">
        <?php echo esc_html__('Към отзивите', 'woo-feedback'); ?>
        </a>
        </p>
        </div>
        </div>

        <div style="background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:24px; margin-bottom:20px;">
        <h2 style="margin-top:0;">
        <?php echo esc_html__('1. Използване на shortcode-а', 'woo-feedback'); ?>
        </h2>

        <p>
        <?php echo esc_html__('Основният shortcode за визуализация е:', 'woo-feedback'); ?>
        </p>

        <div style="background:#f6f7f7; border:1px solid #dcdcde; border-radius:6px; padding:14px; font-family:monospace; font-size:14px; margin-bottom:16px;">
        [woo_feedback]
        </div>

        <p>
        <?php echo esc_html__('Най-често shortcode-ът се поставя в продуктов шаблон, описание, builder блок или друга зона, в която искате да покажете отзивите в отделен блок.', 'woo-feedback'); ?>
        </p>

        <p style="margin-bottom:8px;">
        <?php echo esc_html__('Ако shortcode-ът се използва в контекста на конкретен продукт, плъгинът може да вземе продукта автоматично. При нужда може да подадете product ID ръчно.', 'woo-feedback'); ?>
        </p>
        </div>

        <div style="background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:24px; margin-bottom:20px;">
        <h2 style="margin-top:0;">
        <?php echo esc_html__('2. Параметри на shortcode-а', 'woo-feedback'); ?>
        </h2>

        <table class="widefat striped" style="margin-top:16px;">
        <thead>
        <tr>
        <th scope="col" style="width:180px;"><?php echo esc_html__('Параметър', 'woo-feedback'); ?></th>
        <th scope="col" style="width:180px;"><?php echo esc_html__('Стойности', 'woo-feedback'); ?></th>
        <th scope="col"><?php echo esc_html__('Описание', 'woo-feedback'); ?></th>
        </tr>
        </thead>
        <tbody>
        <tr>
        <td><code>product_id</code></td>
        <td><?php echo esc_html__('ID на продукт', 'woo-feedback'); ?></td>
        <td><?php echo esc_html__('Указва за кой продукт да се заредят отзивите.', 'woo-feedback'); ?></td>
        </tr>
        <tr>
        <td><code>id</code></td>
        <td><?php echo esc_html__('ID на продукт', 'woo-feedback'); ?></td>
        <td><?php echo esc_html__('Алтернативен параметър за product_id.', 'woo-feedback'); ?></td>
        </tr>
        <tr>
        <td><code>title</code></td>
        <td><?php echo esc_html__('Текст', 'woo-feedback'); ?></td>
        <td><?php echo esc_html__('Заглавие над списъка с отзиви.', 'woo-feedback'); ?></td>
        </tr>
        <tr>
        <td><code>show_form</code></td>
        <td><code>yes</code> / <code>no</code></td>
        <td><?php echo esc_html__('Показва или скрива формата за нов отзив под списъка.', 'woo-feedback'); ?></td>
        </tr>
        <tr>
        <td><code>collapsed</code></td>
        <td><code>yes</code> / <code>no</code></td>
        <td><?php echo esc_html__('Определя дали блокът да стартира в свито състояние.', 'woo-feedback'); ?></td>
        </tr>
        <tr>
        <td><code>show_count</code></td>
        <td><code>yes</code> / <code>no</code></td>
        <td><?php echo esc_html__('Показва badge с броя на одобрените отзиви в бутона.', 'woo-feedback'); ?></td>
        </tr>
        <tr>
        <td><code>button_text</code></td>
        <td><?php echo esc_html__('Текст', 'woo-feedback'); ?></td>
        <td><?php echo esc_html__('Текстът върху collapse бутона.', 'woo-feedback'); ?></td>
        </tr>
        <tr>
        <td><code>empty_message</code></td>
        <td><?php echo esc_html__('Текст', 'woo-feedback'); ?></td>
        <td><?php echo esc_html__('Съобщение, ако няма одобрени отзиви за продукта.', 'woo-feedback'); ?></td>
        </tr>
        </tbody>
        </table>
        </div>

        <div style="background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:24px; margin-bottom:20px;">
        <h2 style="margin-top:0;">
        <?php echo esc_html__('3. Примери за shortcode', 'woo-feedback'); ?>
        </h2>

        <div style="display:grid; gap:12px;">
        <div style="background:#f6f7f7; border:1px solid #dcdcde; border-radius:6px; padding:14px;">
        <strong><?php echo esc_html__('Стандартен вариант:', 'woo-feedback'); ?></strong>
        <div style="margin-top:8px; font-family:monospace;">[woo_feedback]</div>
        </div>

        <div style="background:#f6f7f7; border:1px solid #dcdcde; border-radius:6px; padding:14px;">
        <strong><?php echo esc_html__('За конкретен продукт:', 'woo-feedback'); ?></strong>
        <div style="margin-top:8px; font-family:monospace;">[woo_feedback product_id="123"]</div>
        </div>

        <div style="background:#f6f7f7; border:1px solid #dcdcde; border-radius:6px; padding:14px;">
        <strong><?php echo esc_html__('С отворен блок и форма за нов отзив:', 'woo-feedback'); ?></strong>
        <div style="margin-top:8px; font-family:monospace;">[woo_feedback product_id="123" collapsed="no" show_form="yes"]</div>
        </div>

        <div style="background:#f6f7f7; border:1px solid #dcdcde; border-radius:6px; padding:14px;">
        <strong><?php echo esc_html__('С персонализиран бутон и заглавие:', 'woo-feedback'); ?></strong>
        <div style="margin-top:8px; font-family:monospace;">[woo_feedback product_id="123" button_text="Виж всички мнения" title="Отзиви от наши клиенти"]</div>
        </div>

        <div style="background:#f6f7f7; border:1px solid #dcdcde; border-radius:6px; padding:14px;">
        <strong><?php echo esc_html__('Без badge с броя:', 'woo-feedback'); ?></strong>
        <div style="margin-top:8px; font-family:monospace;">[woo_feedback product_id="123" show_count="no"]</div>
        </div>
        </div>
        </div>

        <div style="background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:24px; margin-bottom:20px;">
        <h2 style="margin-top:0;">
        <?php echo esc_html__('4. Как работи одобрението на отзивите', 'woo-feedback'); ?>
        </h2>

        <ol style="padding-left:18px; margin-bottom:0;">
        <li style="margin-bottom:10px;">
        <?php echo esc_html__('Потребителят изпраща native WooCommerce review за продукт.', 'woo-feedback'); ?>
        </li>
        <li style="margin-bottom:10px;">
        <?php echo esc_html__('Ако в настройките е включено задължително одобрение, новият отзив се маркира като чакащ.', 'woo-feedback'); ?>
        </li>
        <li style="margin-bottom:10px;">
        <?php echo esc_html__('Администраторът преглежда чакащите отзиви в WooFeedback → Отзиви.', 'woo-feedback'); ?>
        </li>
        <li style="margin-bottom:10px;">
        <?php echo esc_html__('Оттам може бързо да одобри, премести в кошчето или изтрие запис.', 'woo-feedback'); ?>
        </li>
        <li>
        <?php echo esc_html__('На фронтенда се показват само одобрените отзиви.', 'woo-feedback'); ?>
        </li>
        </ol>
        </div>

        <div style="background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:24px; margin-bottom:20px;">
        <h2 style="margin-top:0;">
        <?php echo esc_html__('5. Как работи collapse бутонът с badge', 'woo-feedback'); ?>
        </h2>

        <p>
        <?php echo esc_html__('Блокът с отзиви започва по подразбиране в свито състояние, освен ако не подадете collapsed="no". Потребителят натиска бутона и списъкът се разгъва или прибира.', 'woo-feedback'); ?>
        </p>

        <p>
        <?php echo esc_html__('Badge-ът върху бутона показва броя на одобрените отзиви за текущия продукт. Ако не искате да се вижда броят, използвайте show_count="no".', 'woo-feedback'); ?>
        </p>

        <p style="margin-bottom:0;">
        <?php echo esc_html__('Това решение пази продуктовата страница по-лека визуално и не натоварва излишно потребителя с дълъг списък още при първо зареждане.', 'woo-feedback'); ?>
        </p>
        </div>

        <div style="background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:24px; margin-bottom:20px;">
        <h2 style="margin-top:0;">
        <?php echo esc_html__('6. Скриване на стандартния WooCommerce reviews tab', 'woo-feedback'); ?>
        </h2>

        <p>
        <?php echo esc_html__('В Настройки има опция „Скриване на стандартния WooCommerce tab“. Когато е включена, стандартният reviews tab на WooCommerce се премахва от продуктовата страница.', 'woo-feedback'); ?>
        </p>

        <p>
        <?php echo esc_html__('Това е полезно, когато искате да показвате отзивите само през WooFeedback shortcode-а и да избегнете дублиране на едни и същи мнения на две места.', 'woo-feedback'); ?>
        </p>

        <p style="margin-bottom:0;">
        <?php echo esc_html__('Ако настройката е изключена, стандартният WooCommerce reviews tab остава активен и WooFeedback може да се използва допълнително.', 'woo-feedback'); ?>
        </p>
        </div>

        <div style="background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:24px; margin-bottom:20px;">
        <h2 style="margin-top:0;">
        <?php echo esc_html__('7. Какво се трие и какво не се трие при uninstall', 'woo-feedback'); ?>
        </h2>

        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:16px;">
        <div style="border:1px solid #dcdcde; border-radius:6px; padding:16px; background:#fcfcfc;">
        <h3 style="margin-top:0; font-size:16px;">
        <?php echo esc_html__('Трие се', 'woo-feedback'); ?>
        </h3>
        <ul style="margin-bottom:0;">
        <li><?php echo esc_html__('Настройките на WooFeedback.', 'woo-feedback'); ?></li>
        <li><?php echo esc_html__('Версията и метаданните, записани от плъгина.', 'woo-feedback'); ?></li>
        </ul>
        </div>

        <div style="border:1px solid #dcdcde; border-radius:6px; padding:16px; background:#fcfcfc;">
        <h3 style="margin-top:0; font-size:16px;">
        <?php echo esc_html__('Не се трие', 'woo-feedback'); ?>
        </h3>
        <ul style="margin-bottom:0;">
        <li><?php echo esc_html__('Native product reviews в WooCommerce.', 'woo-feedback'); ?></li>
        <li><?php echo esc_html__('WordPress comment записите.', 'woo-feedback'); ?></li>
        <li><?php echo esc_html__('Rating meta към реалните product reviews.', 'woo-feedback'); ?></li>
        </ul>
        </div>
        </div>

        <p style="margin-top:16px; margin-bottom:0;">
        <?php echo esc_html__('Това е умишлено поведение, за да не се губят реални клиентски отзиви при премахване на плъгина.', 'woo-feedback'); ?>
        </p>
        </div>

        <div style="background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:24px; margin-bottom:20px;">
        <h2 style="margin-top:0;">
        <?php echo esc_html__('8. Практически указания за администратора', 'woo-feedback'); ?>
        </h2>

        <ul style="margin-bottom:0;">
        <li style="margin-bottom:10px;">
        <?php echo esc_html__('Ако ще използвате WooFeedback като основен блок за отзиви, активирайте shortcode-а и преценете дали да скриете стандартния WooCommerce reviews tab.', 'woo-feedback'); ?>
        </li>
        <li style="margin-bottom:10px;">
        <?php echo esc_html__('Ако държите на контрол върху публикуването, оставете „Задължително одобрение“ включено.', 'woo-feedback'); ?>
        </li>
        <li style="margin-bottom:10px;">
        <?php echo esc_html__('Проверявайте редовно страницата „Отзиви“, за да не стоят чакащи мнения твърде дълго.', 'woo-feedback'); ?>
        </li>
        <li style="margin-bottom:10px;">
        <?php echo esc_html__('Използвайте collapsed="yes", когато страницата е дълга и искате по-чисто визуално начало.', 'woo-feedback'); ?>
        </li>
        <li style="margin-bottom:10px;">
        <?php echo esc_html__('Използвайте show_form="yes" само там, където реално искате посетителят да може да остави нов отзив.', 'woo-feedback'); ?>
        </li>
        <li>
        <?php echo esc_html__('При ръчно поставяне в builder или custom layout винаги проверявайте дали shortcode-ът е в контекст на правилния продукт или подайте product_id изрично.', 'woo-feedback'); ?>
        </li>
        </ul>
        </div>

        <hr style="margin-top:40px; margin-bottom:20px;" />

        <div style="text-align:center; font-size:13px; color:#777;">
        <p style="margin-bottom:6px;">
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

        <p style="margin-top:0;">
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
