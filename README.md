# HW On Sale - Premium WooCommerce On-Sale Product Grid

**Version:** 1.0.0  
**Requires:** WordPress 5.8+, WooCommerce 5.0+, PHP 7.4+  
**License:** GPL v2 or later

A professional, production-grade WordPress plugin for displaying on-sale products with advanced filtering, analytics dashboard, and Elementor banner integration.

---

## Features

### Front-End (`/onsale` Page)
- **Luxury minimal design** - No shadows, no borders, clean spacing
- **Product grid** with AJAX Load More (12 products per batch)
- **Image carousel** with drag/swipe, keyboard navigation, arrow controls
- **Smart price display** - Strikethrough regular, bold sale price
- **Hover swap** (desktop): Price → "Add to cart" text on hover
- **Advanced filters:**
  - Dynamic price slider (real-time min/max from current products)
  - Category filtering (parents as headers, children selectable)
  - In-stock toggle
  - Sorting (discount, price asc/desc)
  - Reset button when filters active
- **Variant selection modal** for variable products
  - Filters out-of-stock variations
  - AJAX add-to-cart
  - Full keyboard/screen reader support
- **Elementor banner** - Editable via Elementor Saved Templates

### Admin Dashboard
- **Analytics Overview** - Views, clicks, CTR, add-to-cart metrics
- **Time-series charts** - Product performance tracking
- **Top products** - Sorted by clicks/conversions
- **Export CSV** - Date range exports
- **Settings** - Grid config, banner, tracking options

---

## Installation

1. **Upload & Activate:**
   ```
   wp-content/plugins/hw-onsalehw/
   ```
   Activate via WordPress Admin → Plugins

2. **Requirements Check:**
   - WooCommerce must be installed and active
   - PHP 7.4+ (8.0+ recommended)
   - Products must have `private-sale` category

3. **Initial Setup:**
   - Navigate to **WooCommerce → On Sale Dashboard → Settings**
   - Configure grid columns, banner, tracking
   - Create a page with `[hw_onsale]` shortcode or visit `/onsale`

---

## Configuration

### Banner Setup (Elementor)

1. **Create Elementor Template:**
   - Go to Templates → Saved Templates → Add New
   - Design your banner (full-width section recommended)
   - Note the Template ID

2. **Plugin Settings:**
   - WooCommerce → On Sale Dashboard → Settings → Banner
   - Check "Use Elementor Template"
   - Enter Template ID
   - Save

**Alternative:** Use built-in banner with image upload and overlay settings.

### Product Setup

Products must be:
- Assigned to `product_cat=private-sale` category
- Have sale price set
- Published and in stock (optional filter)

### Tracking

**Enabled by default**, tracks:
- `view` - Page view
- `impression` - Product visibility (50% viewport)
- `card_click` - Product card clicks
- `add_to_cart` - Add to cart actions
- `load_more` - Pagination events

**Privacy:** IP addresses anonymized by default. Toggle in settings.

---

## Customization

### CSS Variables

Override in your theme's `style.css` or custom CSS:

```css
:root {
  --hw-accent: #ED1B76;        /* Brand accent color */
  --hw-ink: #111111;           /* Primary text */
  --hw-muted: #6b7280;         /* Secondary text */
  --hw-line: #eaeaea;          /* Dividers */
  --hw-radius: 16px;           /* Container radius */
  --hw-radius-img: 7px;        /* Media radius */
  --hw-grid-gap: 24px;         /* Card spacing */
}
```

### Filters & Actions

```php
// Modify query args
add_filter( 'hw_onsale_query_args', function( $args ) {
    // Custom modifications
    return $args;
});

// Add custom tracking data
add_filter( 'hw_onsale_track_event', function( $data, $event ) {
    $data['custom_field'] = 'value';
    return $data;
}, 10, 2);
```

---

## REST API

### Public Endpoints

**Get Products:**
```
GET /wp-json/hw-onsale/v1/list
Parameters:
  - offset (int): Pagination offset
  - limit (int): Results per page
  - orderby (string): discount-desc|price-asc|price-desc
  - min_price (int): Minimum price filter
  - max_price (int): Maximum price filter
  - categories (string): Comma-separated category IDs
  - in_stock (bool): Stock filter
```

**Get Price Range:**
```
GET /wp-json/hw-onsale/v1/price-range
Parameters:
  - categories (string): Optional category filter
Returns: {min: int, max: int}
```

**Get Product Variations:**
```
GET /wp-json/hw-onsale/v1/product-variations?product_id=123
Returns: {attributes: [], variations: [], price_html: string}
```

### Admin Endpoints (require `manage_hw_onsale` capability)

**Get Analytics:**
```
GET /wp-json/hw-onsale/v1/analytics/summary?from=2024-01-01&to=2024-12-31
```

**Export Data:**
```
GET /wp-json/hw-onsale/v1/analytics/export?from=2024-01-01&to=2024-12-31
```

---

## Troubleshooting

### Products Not Showing

**Check:**
1. Products have `private-sale` category assigned
2. Products have sale price set
3. Products are published
4. Cache cleared (if using caching plugin)

**Debug:**
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Price Slider Not Updating

**Issue:** Min/max values not reflecting actual product prices

**Solution:**
- Ensure products are properly indexed
- Check browser console for AJAX errors
- Verify REST API endpoint accessible: `/wp-json/hw-onsale/v1/price-range`
- Clear object cache if using Redis/Memcached

### Variant Modal Not Opening

**Check:**
1. Product is type `variable`
2. Variations exist and are in stock
3. Browser console for JavaScript errors
4. WooCommerce variations properly configured

### Performance Issues

**Optimizations:**
1. Enable object caching (Redis, Memcached)
2. Reduce batch size (Settings → Grid → Load More Batch Size)
3. Limit product images (1-3 per product recommended)
4. Use CDN for static assets
5. Enable `hw_onsale_cache_enabled` in settings

### Elementor Banner Not Showing

**Check:**
1. Elementor plugin is active
2. Template ID is correct (numeric)
3. Template is published and not trashed
4. "Use Elementor Template" is checked in settings
5. "Show Banner" is enabled

### Conflicts with Other Plugins

**Known Conflicts:**
- **WP Rocket/W3 Total Cache:** Exclude `/wp-json/hw-onsale/` from caching
- **WPML/Polylang:** Ensure product translations include sale prices
- **Security Plugins:** Whitelist REST API endpoints

---

## Accessibility

- WCAG AA compliant
- Keyboard navigation (Tab, Enter, Esc, Arrow keys)
- Screen reader support (ARIA labels, roles, live regions)
- Focus management in modal
- High contrast mode support
- Reduced motion support (`prefers-reduced-motion`)

**Keyboard Shortcuts:**
- `Tab` - Navigate interactive elements
- `Enter/Space` - Activate buttons/links
- `Esc` - Close modal/drawer
- `Arrow Left/Right` - Navigate carousel
- `Home/End` - Jump to first/last slide

---

## Developer Notes

### Architecture

```
hw-onsalehw/
├── src/
│   ├── Application/      # Use cases, DTOs
│   ├── Domain/          # Entities, value objects, interfaces
│   ├── Infrastructure/  # Repositories, REST, DB, cache
│   └── Presentation/    # Components, shortcodes, admin
├── assets/
│   ├── css/            # onsale.css, admin-dashboard.css
│   └── js/             # onsale.js, admin-dashboard.js
├── templates/          # grid.php
└── hw-onsale.php      # Main plugin file
```

### Database Schema

**Table:** `wp_hw_onsale_events`
```sql
CREATE TABLE wp_hw_onsale_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id VARCHAR(64) NOT NULL,
  event VARCHAR(32) NOT NULL,
  product_id BIGINT UNSIGNED,
  discount_pct INT,
  device VARCHAR(16),
  ref TEXT,
  extra TEXT,
  ip VARCHAR(45),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_event (event),
  INDEX idx_product (product_id),
  INDEX idx_created (created_at)
);
```

### Coding Standards

- **PHPCS:** WordPress coding standards
- **Namespace:** `HW_Onsale\`
- **Autoload:** PSR-4
- **Security:** All inputs sanitized, outputs escaped
- **i18n:** Text domain `hw-onsale`

---

## Changelog

### 1.0.0 (2024-01-15)
- Initial release
- Product grid with AJAX pagination
- Advanced filtering (price, categories, stock)
- Variant selection modal
- Elementor banner integration
- Analytics dashboard
- Export functionality
- Tracking system

---

## Support

**Documentation:** https://example.com/docs/hw-onsale  
**Support:** support@example.com  
**Issues:** https://github.com/example/hw-onsale/issues

---

## Credits

Built with:
- WordPress 6.0+
- WooCommerce 7.0+
- Chart.js 4.4.0 (admin dashboard)
- No jQuery dependency (vanilla JS)

---

## License

GPL v2 or later. See LICENSE file.
