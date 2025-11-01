=== HW On Sale ===
Contributors: hwteam
Tags: woocommerce, sale, products, analytics, dashboard
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional on-sale product page with analytics dashboard, AJAX load-more, and advanced tracking.

== Description ==

HW On Sale is a production-ready WordPress/WooCommerce plugin that automatically creates a beautiful, full-width `/onsale` page displaying products from the "private-sale" category with advanced features:

**Frontend Features:**
* Automatic `/onsale` page creation with `[hw_onsale]` shortcode
* Responsive grid layout (4/3/2 columns for desktop/tablet/mobile)
* Product cards with image sliders (drag/swipe enabled)
* Dynamic discount badges showing max percentage for variable products
* AJAX "Load More" pagination
* Hover add-to-cart effects (desktop) with mobile accessibility
* Optimized images with srcset, lazy loading, and fetchpriority
* Full-width layout with theme compatibility

**Admin Dashboard:**
* Location: WooCommerce → On Sale Dashboard
* **Overview Tab**: KPI cards (Views, Clicks, CTR, Add to Cart), interactive charts (time series, top products, device breakdown), date range filtering, CSV export
* **Appearance & Settings Tab**: Configure banner/hero, grid layout, discount badges, performance options, and tracking settings

**Event Tracking:**
* Track page views, product impressions, clicks, and add-to-cart events
* Anonymized IP and user agent hashing (GDPR-friendly)
* Session-based analytics
* Exclude admin users from tracking

**Order Attribution:**
* Automatically track orders containing private-sale products
* Configurable attribution window (default 24 hours)
* Order meta `_hw_onsale_attributed` for revenue analysis

**Clean Architecture:**
* Follows Domain/Application/Infrastructure/Presentation layers
* SOLID principles throughout
* PSR-4 autoloading with namespaces
* Secure REST API endpoints with nonce verification
* PHPCS compliant code

== Installation ==

1. Upload the `hw-onsale` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure WooCommerce is installed and active
4. Visit the automatically created `/onsale` page
5. Configure settings in WooCommerce → On Sale Dashboard

== Quick Setup ==

**Adding Products to On Sale Page:**
1. Edit any WooCommerce product
2. In the "Product categories" section, assign the "private-sale" category
3. Set a sale price (if not already set)
4. The product will automatically appear on `/onsale`

**Configuring the Dashboard:**
1. Go to WooCommerce → On Sale Dashboard
2. Click the "Appearance & Settings" tab
3. Configure banner, grid layout, badges, and tracking options
4. Save changes

**Viewing Analytics:**
1. Go to WooCommerce → On Sale Dashboard
2. Select date range with "From" and "To" fields
3. Click "Refresh" to load analytics
4. Click "Export CSV" to download raw data

== Settings Explained ==

**Banner / Hero:**
* Show Banner: Enable/disable hero banner at top of page
* Banner Image: Select image from media library
* Title/Subtitle: Overlay text on banner
* CTA Text/Link: Call-to-action button
* Overlay Opacity: 0–0.6 (background darkness)
* Heights: Separate heights for desktop/mobile
* Alignment: Left/Center/Right text alignment

**Grid & Layout:**
* Columns: Desktop (2-6), Tablet (2-4), Mobile (1-3)
* Card Radius: Border radius in pixels
* Card Shadow: None/Small/Medium/Large
* Card Gap: Space between cards in pixels
* Show Slider Dots: Enable/disable image navigation dots
* Hover Add to Cart: Show button on hover (desktop only)
* Batch Size: Products per "Load More" click (4-48)
* Load More Label: Custom button text

**Discount Badge:**
* Position: Top-Left/Top-Center/Top-Right
* Style: Solid/Outline
* Hide Below % Threshold: Don't show badge if discount is below this value

**Performance:**
* Enable Transient Cache: Cache product query results
* Cache TTL: How long to cache (seconds)
* Prefetch Next Batch: Preload next products in background
* High Priority First Row: Set fetchpriority="high" for first 4 images

**Tracking & Attribution:**
* Enable Tracking: Turn analytics on/off
* Anonymize IP: Hash user agent instead of storing IP (GDPR)
* Exclude Admins: Don't track logged-in administrators
* Attribution Window: Hours to track order attribution (default 24)

== Extension Hooks ==

**Filters:**

`apply_filters( 'hw_onsale_query_args', $args )`
Modify WP_Query arguments for product listing.

`apply_filters( 'hw_onsale_discount_max', $pct, $product )`
Modify calculated discount percentage.

`apply_filters( 'hw_onsale_attribution_window_hours', 24 )`
Change attribution window duration.

`apply_filters( 'hw_onsale_admin_kpis', $kpis )`
Modify KPI data before display.

**Actions:**

`do_action( 'hw_onsale_event_tracked', $event )`
Fires after an event is successfully tracked.

== REST API Endpoints ==

**Public Endpoints:**

`GET /wp-json/hw-onsale/v1/list?offset=0&limit=12`
Returns paginated product cards.

`POST /wp-json/hw-onsale/v1/event`
Ingest tracking event (requires nonce).

**Admin Endpoints (require manage_hw_onsale capability):**

`GET /wp-json/hw-onsale/v1/analytics?from=YYYY-MM-DD&to=YYYY-MM-DD`
Returns analytics summary, time series, top products, and device breakdown.

`GET /wp-json/hw-onsale/v1/export?from=YYYY-MM-DD&to=YYYY-MM-DD`
Returns CSV export of events.

== Database Schema ==

**Table:** `wp_hw_onsale_events`

Columns:
* id (BIGINT, auto-increment)
* session_id (VARCHAR 40)
* event (VARCHAR 24): view, impression, card_click, add_to_cart, load_more
* product_id (BIGINT, nullable)
* discount_pct (TINYINT, nullable)
* user_agent_hash (CHAR 32, nullable)
* device (VARCHAR 12): desktop, tablet, mobile
* ref (VARCHAR 255, nullable)
* created_at (DATETIME)
* extra (LONGTEXT, nullable, JSON)

Indexes on: created_at, (event, created_at), (product_id, created_at), session_id

== Frequently Asked Questions ==

= How do I add products to the on-sale page? =

Assign products to the "private-sale" product category. They will automatically appear on `/onsale`.

= Can I change the URL from /onsale to something else? =

Yes, edit the page created by the plugin and change its slug in the permalink settings.

= How is discount percentage calculated for variable products? =

The plugin computes the discount for each variation and displays the **maximum discount percentage** across all variations.

= Is tracking GDPR compliant? =

Yes. When "Anonymize IP" is enabled (default), the plugin stores only a hashed user agent and does not collect personally identifiable information.

= Can I customize the grid layout? =

Absolutely. Use the "Appearance & Settings" tab to configure columns, card styling, gaps, and more.

= How do I disable tracking? =

Go to WooCommerce → On Sale Dashboard → Appearance & Settings → Tracking & Attribution, and uncheck "Enable Tracking".

= Where is order attribution stored? =

Orders attributed to the on-sale page have the order meta `_hw_onsale_attributed` set to "yes".

== Screenshots ==

1. Frontend /onsale page with responsive grid and product cards
2. Admin dashboard overview with KPIs and charts
3. Appearance & Settings configuration panel
4. Product card with image slider and discount badge

== Changelog ==

= 1.0.0 =
* Initial release
* Full-width /onsale page with AJAX load-more
* Smart admin dashboard with analytics and charts
* Event tracking with anonymization
* Order attribution system
* Clean Architecture implementation
* PHPCS compliant code

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== Developer Notes ==

**Architecture:**
This plugin follows Clean Architecture principles with clear separation of concerns:

* **Domain Layer**: Entities (ProductCard, Event), Value Objects (Discount), Repository Interfaces
* **Application Layer**: Use Cases (business logic), DTOs (data transfer)
* **Infrastructure Layer**: WP/WooCommerce implementations, REST controllers, database, caching
* **Presentation Layer**: Shortcodes, components (Card, Badge, Slider), admin dashboard

**Security:**
* All inputs sanitized with `sanitize_text_field()`, `absint()`, etc.
* All outputs escaped with `esc_html()`, `esc_url()`, `esc_attr()`
* Nonce verification on all state-changing operations
* Capability checks (`manage_hw_onsale`) for admin routes
* Prepared SQL statements via `$wpdb->prepare()`

**Performance:**
* Optional transient caching for product queries
* Lazy loading images with `loading="lazy"`
* High priority for first-row images with `fetchpriority="high"`
* Efficient database queries with proper indexes
* Minimal JavaScript (<5KB gzipped for frontend events)
* Chart.js loaded only on admin dashboard

**Accessibility:**
* ARIA roles and labels throughout
* Keyboard navigation support
* Focus-visible styles
* Screen reader text for charts
* Semantic HTML

**Extensibility:**
Use provided filters and actions to extend functionality without modifying core plugin files.

== Support ==

For support, feature requests, or bug reports, please contact support@example.com or visit https://example.com/support

== Credits ==

Developed by HW Team
Built with Clean Architecture, SOLID principles, and modern WordPress best practices.
