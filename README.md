WooFeedback

WooFeedback е индивидуален WordPress плъгин за INRA, предназначен за професионално управление и визуализация на WooCommerce отзиви.

Плъгинът не създава отделна review система. Вместо това използва native WooCommerce / WordPress review логиката и добавя професионален слой за:

Визуализация на отзивите чрез shortcode.

Collapse бутон с badge за брой одобрени отзиви.

Опционално показване на форма за нов отзив.

Задължително или конфигурируемо админ одобрение.

Отделен административен екран за бързо управление на отзивите.

Настройки за контрол на поведението.

Помощна административна страница с практическа документация за работа с плъгина.

Специализиран security слой за защита на public review submit потока.

Основна концепция

WooFeedback работи върху стандартния WooCommerce модел за product reviews:

review = WordPress comment към product

comment_type = review

rating = comment meta с ключ rating

moderation = native WordPress comment approval flow

Това означава:

Не се създава custom table.

Не се създава custom post type.

Не се дублират review данни.

Не се прави втори източник на истина.

Остава пълна съвместимост с WooCommerce.

WooFeedback е слой за управление и визуализация, а не отделна review платформа.

Основни възможности

1) Frontend shortcode

Плъгинът предоставя shortcode [woo_feedback], който може да показва:

Бутон за отваряне/затваряне на отзивите.

Badge с броя одобрени отзиви.

Списък с одобрените reviews за продукта.

Опционална форма за нов review.

2) Collapse бутон с badge

По подразбиране отзивите могат да се показват в разгъваем блок, за да не се товари визуално продуктовата страница. Бутонът показва:

Заглавие.

Брой одобрени отзиви.

3) Форма за нов отзив

Формата за нов отзив работи върху native WordPress/WooCommerce review flow. Поддържа:

Име и Имейл.

Оценка (Rating).

Текст на отзива.

При нужда може да се изисква потребителят да бъде логнат.

4) Админ одобрение

WooFeedback позволява всеки нов review да бъде:

Автоматично изпратен за одобрение.

Или одобрен директно, ако това е конфигурирано.

Препоръчителният продукционен режим е всички нови reviews да чакат админ одобрение.

5) Отделен административен екран

Плъгинът добавя собствено админ меню WooFeedback, което включва:

Отзиви: Списък с reviews, филтър по статус, търсене, бързи и масови действия, статистически карти.

Настройки: Пълен контрол върху поведението на плъгина.

Сигурност: Управление на Turnstile, honeypot, time check, rate limiting, duplicate control и failsafe логика.

Помощ: Подробна администратора документация за работа с shortcode, одобрение, uninstall поведение и practically useful указания.

6) Security layer

WooFeedback включва собствен plugin-owned security слой за review submit потока, без зависимост от външни captcha плъгини. Поддържа:

Cloudflare Turnstile.

Honeypot защита.

Minimum submit time anti-bot проверка.

Rate limiting по IP, email и product context.

Duplicate review detection.

Failsafe режим при временен проблем с външна проверка.

7) AJAX submit

При стандартен браузър review формата може да се изпраща без full page reload чрез AJAX, като се запазва и standard POST fallback за среди без JavaScript.

Изисквания

WordPress 6.4+

PHP 8.0+

WooCommerce 8.0+

Инсталация

Качете папката woo-feedback в директорията wp-content/plugins/.

Активирайте плъгина от WordPress администрацията.

Уверете се, че WooCommerce е активен.

Настройте плъгина от:

WooFeedback → Настройки

WooFeedback → Сигурност

Употреба

Показване за текущия продукт:

[woo_feedback]

Показване за конкретен продукт:

[woo_feedback product_id="123"]

или

[woo_feedback id="123"]

Параметри на shortcode

product_id / id - ID на продукта.

title - Заглавие на блока.

show_form - "yes|no" (показване на формата).

collapsed - "yes|no" (дали да е свит по подразбиране).

show_count - "yes|no" (показване на badge с бройка).

button_text - Текст на бутона за разгъване.

empty_message - Текст при липса на отзиви.

Примери

[woo_feedback collapsed="yes" show_count="yes" show_form="no"]
[woo_feedback product_id="123" title="Мнения на читатели" button_text="Виж отзивите"]

Административни настройки

От екрана WooFeedback → Настройки могат да се управляват:

Активиране на shortcode.

Показване на формата по подразбиране.

Изискване за логнат потребител.

Задължително одобрение.

Автоматично скриване на стандартния WooCommerce reviews tab.

Преводи и съобщения (success/error, заглавия, текстове на бутони).

Брой елементи в админ списъка.

Security настройки

От екрана WooFeedback → Сигурност могат да се управляват:

Enable security protections.

Enable Cloudflare Turnstile.

Turnstile site key.

Turnstile secret key.

Enable honeypot.

Enable minimum submit time check.

Minimum submit time in seconds.

Enable rate limiting.

Rate limit window.

Rate limit max attempts.

Enable duplicate detection.

Duplicate window in minutes.

Failsafe mode.

Административна помощ

От екрана WooFeedback → Помощ е налично ясно описание на:

Какво прави плъгинът.

Как работи с native WooCommerce reviews.

Как се използва shortcode-ът.

Как работи одобрението на отзивите.

Как работи collapse бутонът с badge.

Как се скрива стандартният WooCommerce reviews tab.

Какво се трие и какво не се трие при uninstall.

Практически указания за администратора.

Какво се съхранява

WooFeedback записва само свои настройки и ограничени метаданни:

woo_feedback_settings

woo_feedback_version

woo_feedback_installed_at

woo_feedback_last_deactivated_at

comment_meta: ключ woo_feedback_source

Какво НЕ се създава

Custom review таблица.

Custom post type за reviews.

Custom rating система.

Отделно review хранилище.

Uninstall поведение

При деинсталация на плъгина:

Изтрива се:

Plugin settings.

Plugin version/meta options.

Plugin-owned comment meta като woo_feedback_source.

Не се изтрива:

WooCommerce product reviews.

Native WordPress comments.

Rating meta на реалните reviews.

Продуктова история.

Това е умишлено и е правилното продукционно поведение, защото WooFeedback не е собственик на review данните.

Архитектура

Основен bootstrap: woo-feedback.php

Core:

includes/Core/Plugin.php

includes/Core/Activator.php

includes/Core/Deactivator.php

Settings:

includes/Settings/Settings.php

Admin:

includes/Admin/AdminMenu.php

includes/Admin/SettingsPage.php

includes/Admin/SecurityPage.php

includes/Admin/ReviewsPage.php

includes/Admin/HelpPage.php

Frontend:

includes/Frontend/Shortcodes.php

includes/Frontend/Assets.php

Reviews:

includes/Reviews/FormHandler.php

includes/Reviews/ReviewModeration.php

includes/Reviews/CommentTypes.php

includes/Reviews/ReviewQuery.php

includes/Reviews/ReviewColumns.php

includes/Reviews/Approval.php

Security:

includes/Security/TurnstileService.php

includes/Security/AntiSpamService.php

includes/Security/RateLimitService.php

Uninstall: uninstall.php

Changelog

### 1.2.6
- Fixed duplicate DOM IDs in WooFeedback shortcode output.
- Resolved accordion toggle issues affecting repeated review blocks, especially on mobile layouts.
- Improved frontend markup stability for multiple instances of the same product review block on one page.

### 1.2.1
- Fixed security settings defaults registration to match the current settings schema.
- Improved frontend review caching with persistent transient-based storage.
- Kept cache invalidation aligned with review submit and moderation-related update flows.
- Polished the production readiness of the security and performance layer introduced in 1.2.0.

### 1.2.0
- Added dedicated Security admin page.
- Added plugin-owned Cloudflare Turnstile integration.
- Added honeypot protection for the public review form.
- Added minimum submit time anti-bot validation.
- Added rate limiting by IP, email, and product context.
- Added duplicate review detection within a configurable time window.
- Added failsafe mode for external verification behavior.
- Added AJAX review submission with structured success/error responses.
- Preserved standard POST fallback for non-JavaScript environments.
- Improved review form UX and submit flow behavior.
- Added validation to respect whether reviews are enabled for the target product.
- Added frontend review pagination / load limiting strategy.
- Added review count/list cache layer with invalidation on review updates.
- Improved plugin lifecycle by moving upgrade execution to a cleaner admin path.
- Introduced WooFeedback-specific capability layer with backward-compatible fallback.

### 1.1.0
- Initial stable production release of WooFeedback.
- Added frontend shortcode rendering for native WooCommerce reviews.
- Added collapsible reviews block with review count badge.
- Added optional frontend review form based on native WooCommerce / WordPress reviews.
- Added configurable moderation flow for newly submitted reviews.
- Added dedicated admin pages for Reviews, Settings, and Help.
- Added separate admin review management screen with filters, bulk actions, and quick moderation controls.
- Added plugin settings for shortcode behavior, review form visibility, moderation, texts, and admin list size.
- Added help page with usage documentation, shortcode parameters, uninstall behavior, and administrator guidance.
- Preserved native WooCommerce / WordPress review storage without custom tables or duplicated review data.

Права и брандинг

Разработено за INRA от:
Ventsislav Kolev | WebDigiTech
https://webdigitech.de

Версия: 1.2.6
