## Changelog

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
