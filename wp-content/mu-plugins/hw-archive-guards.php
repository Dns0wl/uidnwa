<?php
/**
 * Plugin Name: HW Archive Guards
 * Description: Protect WooCommerce archive performance by gating scripts.
 */

if (! defined('ABSPATH')) {
    exit;
}

function hw_is_product_archive_like()
{
    if (is_shop() || is_product_taxonomy() || is_post_type_archive('product')) {
        return true;
    }

    if (is_page()) {
        $slug = get_post_field('post_name', get_queried_object_id());
        if ('onsale' === $slug) {
            return true;
        }
    }

    return false;
}

function hw_archive_url_is_blocked($url)
{
    if (empty($url)) {
        return false;
    }

    if (0 === strpos($url, '//')) {
        $url = (is_ssl() ? 'https:' : 'http:') . $url;
    }

    $host = wp_parse_url($url, PHP_URL_HOST);
    if (! $host) {
        return false;
    }

    $host = strtolower($host);
    $blocked = [
        'connect.facebook.net',
        'graph.facebook.com',
        'www.facebook.com',
        'capi-automations.us-east-2.amazonaws.com',
    ];

    foreach ($blocked as $needle) {
        if (false !== strpos($host, $needle)) {
            return true;
        }
    }

    return false;
}

add_action('wp_enqueue_scripts', function () {
    if (! hw_is_product_archive_like()) {
        return;
    }

    wp_dequeue_script('wc-cart-fragments');
    wp_dequeue_script('wp-embed');
}, 100);

add_action('wp_enqueue_scripts', function () {
    if (! hw_is_product_archive_like()) {
        return;
    }

    $scripts = wp_scripts();
    if ($scripts && ! empty($scripts->queue)) {
        foreach ((array) $scripts->queue as $handle) {
            if (! isset($scripts->registered[$handle])) {
                continue;
            }
            $src = $scripts->registered[$handle]->src;
            if (hw_archive_url_is_blocked($src)) {
                wp_dequeue_script($handle);
                wp_deregister_script($handle);
            }
        }
    }

    $styles = wp_styles();
    if ($styles && ! empty($styles->queue)) {
        foreach ((array) $styles->queue as $handle) {
            if (! isset($styles->registered[$handle])) {
                continue;
            }
            $src = $styles->registered[$handle]->src;
            if (hw_archive_url_is_blocked($src)) {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            }
        }
    }
}, 110);

add_filter('wp_resource_hints', function ($urls, $relation_type) {
    if (! hw_is_product_archive_like()) {
        return $urls;
    }

    if ('preconnect' !== $relation_type && 'dns-prefetch' !== $relation_type) {
        return $urls;
    }

    return array_values(array_filter($urls, function ($url) {
        return ! hw_archive_url_is_blocked($url);
    }));
}, 10, 2);

add_action('wp_enqueue_scripts', function () {
    if (! hw_is_product_archive_like()) {
        return;
    }

    wp_register_script('hw-archive-guards', false, [], null, true);
    wp_enqueue_script('hw-archive-guards');

    $script = <<<'JS'
(function(){
    if (window.__hwArchiveGuards) {return;}
    window.__hwArchiveGuards = true;

    var originalSetInterval = window.setInterval;
    window.setInterval = function(callback, delay){
        var wrapped = callback;
        if (typeof callback === 'function') {
            wrapped = function(){
                if (document.hidden) {
                    return;
                }
                return callback.apply(this, arguments);
            };
        }
        return originalSetInterval.call(this, wrapped, delay);
    };

    function patchAddEventListener(target){
        if (typeof WeakMap === 'undefined') {
            return;
        }
        var originalAdd = target.addEventListener;
        var originalRemove = target.removeEventListener;
        var store = new WeakMap();
        target.addEventListener = function(type, listener, options){
            if (type === 'scroll' || type === 'resize') {
                var lastCall = 0;
                var timeout;
                var passiveOptions;
                if (options === undefined) {
                    passiveOptions = {passive:true};
                } else if (typeof options === 'boolean') {
                    passiveOptions = {capture: options, passive: true};
                } else {
                    passiveOptions = Object.assign({passive:true}, options);
                }
                var delay = 200;
                var wrapped = listener;
                if (typeof listener === 'function') {
                    wrapped = store.get(listener);
                    if (!wrapped) {
                        wrapped = function(){
                            var now = Date.now();
                            var context = this;
                            var args = arguments;
                            var run = function(){
                                lastCall = now;
                                listener.apply(context, args);
                            };
                            if (now - lastCall >= delay) {
                                run();
                            } else {
                                clearTimeout(timeout);
                                timeout = setTimeout(run, delay);
                            }
                        };
                        store.set(listener, wrapped);
                    }
                }
                return originalAdd.call(this, type, wrapped, passiveOptions);
            }
            return originalAdd.call(this, type, listener, options);
        };
        target.removeEventListener = function(type, listener, options){
            if ((type === 'scroll' || type === 'resize') && typeof listener === 'function') {
                var wrapped = store.get(listener);
                if (wrapped) {
                    listener = wrapped;
                }
            }
            return originalRemove.call(this, type, listener, options);
        };
    }

    patchAddEventListener(window);
    patchAddEventListener(document);

    if (window.jQuery && window.jQuery.fx) {
        window.jQuery.fx.off = true;
    } else {
        document.addEventListener('DOMContentLoaded', function(){
            if (window.jQuery && window.jQuery.fx) {
                window.jQuery.fx.off = true;
            }
        });
    }
})();
JS;

    wp_add_inline_script('hw-archive-guards', $script);
}, 120);

add_action('wp_head', function () {
    if (! hw_is_product_archive_like()) {
        return;
    }

    $critical = <<<'CSS'
.page-template-archive .site-main,
.woocommerce-page .site-main {
    max-width: 1180px;
    margin-inline: auto;
    padding-inline: min(4vw, 32px);
}
.page-template-archive .site-main h1,
.woocommerce-page .site-main h1 {
    font-size: clamp(1.75rem, 1.2rem + 1.5vw, 2.5rem);
    font-weight: 600;
    margin-bottom: 1.2rem;
}
[data-hw-onsale-grid] {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: clamp(16px, 2vw, 28px);
}
.product-card {
    position: relative;
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 12px;
    padding: 16px;
    background: #fff;
    display: flex;
    flex-direction: column;
    gap: 12px;
    contain: layout style paint;
    transition: transform 160ms ease, box-shadow 160ms ease;
}
.product-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 18px 36px rgba(15, 23, 42, 0.08);
}
.product-card img {
    width: 100%;
    height: auto;
    aspect-ratio: 3 / 4;
    object-fit: cover;
    border-radius: 10px;
    background: #f8fafc;
}
.product-body {
    display: grid;
    gap: 8px;
}
.product-body .price {
    font-weight: 600;
    color: #dc2626;
}
CSS;

    echo '<style id="hw-onsale-critical-css">' . wp_strip_all_tags($critical) . '</style>';
}, 2);

add_filter('style_loader_tag', function ($html, $handle, $href, $media) {
    if (! hw_is_product_archive_like()) {
        return $html;
    }

    $handles = ['theme-style', 'woocommerce-general', 'onsale-styles'];
    if (! in_array($handle, $handles, true)) {
        return $html;
    }

    $preload = sprintf(
        '<link rel="preload" as="style" href="%1$s" fetchpriority="low" />',
        esc_url($href)
    );
    $stylesheet = sprintf(
        '<link rel="stylesheet" href="%1$s" media="print" onload="this.media=\'all\'">',
        esc_url($href)
    );
    $noscript = sprintf(
        '<noscript><link rel="stylesheet" href="%1$s"></noscript>',
        esc_url($href)
    );

    return $preload . $stylesheet . $noscript;
}, 10, 4);

add_filter('wp_get_attachment_image_attributes', function ($attr, $attachment, $size) {
    if (! hw_is_product_archive_like()) {
        return $attr;
    }

    static $first = true;
    if ($first) {
        $attr['loading'] = 'eager';
        $attr['fetchpriority'] = 'high';
        $first = false;
    }

    return $attr;
}, 10, 3);

if (! function_exists('hw_cloudflare_format_image_url')) {
    function hw_cloudflare_format_image_url($url, $format)
    {
        $parsed = wp_parse_url($url);
        if (empty($parsed['path'])) {
            return $url;
        }

        $path = $parsed['path'];
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';

        return home_url('/cdn-cgi/image/format=' . $format . ',q=85' . $path . $query);
    }
}

if (! function_exists('hw_cloudflare_transform_srcset')) {
    function hw_cloudflare_transform_srcset($srcset, $format)
    {
        if (empty($srcset)) {
            return '';
        }

        $sources = array_map('trim', explode(',', $srcset));
        $rewritten = [];
        foreach ($sources as $source) {
            if ('' === $source) {
                continue;
            }
            $parts = preg_split('/\s+/', $source);
            $url = array_shift($parts);
            $descriptor = implode(' ', $parts);
            $rewritten[] = trim(hw_cloudflare_format_image_url($url, $format) . ' ' . $descriptor);
        }

        return implode(', ', $rewritten);
    }
}

add_filter('woocommerce_get_product_thumbnail', function ($html, $size, $args) {
    if (! hw_is_product_archive_like()) {
        return $html;
    }

    $product = wc_get_product(get_the_ID());
    if (! $product) {
        return $html;
    }

    $image_id = $product->get_image_id();
    if (! $image_id) {
        return $html;
    }

    $default_src = wp_get_attachment_image_src($image_id, 'medium_large');
    $default_srcset = wp_get_attachment_image_srcset($image_id, 'medium_large');
    $webp_srcset = hw_cloudflare_transform_srcset($default_srcset, 'webp');
    $avif_srcset = hw_cloudflare_transform_srcset($default_srcset, 'avif');
    $sizes = '(min-width:980px) 25vw, 50vw';
    $alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);

    ob_start();
    ?>
    <picture>
        <?php if ($avif_srcset) : ?>
            <source type="image/avif" srcset="<?php echo esc_attr($avif_srcset); ?>" sizes="<?php echo esc_attr($sizes); ?>" />
        <?php endif; ?>
        <?php if ($webp_srcset) : ?>
            <source type="image/webp" srcset="<?php echo esc_attr($webp_srcset); ?>" sizes="<?php echo esc_attr($sizes); ?>" />
        <?php endif; ?>
        <img src="<?php echo esc_url($default_src ? $default_src[0] : ''); ?>"
            width="<?php echo esc_attr($default_src ? $default_src[1] : ''); ?>"
            height="<?php echo esc_attr($default_src ? $default_src[2] : ''); ?>"
            srcset="<?php echo esc_attr($default_srcset); ?>"
            sizes="<?php echo esc_attr($sizes); ?>"
            alt="<?php echo esc_attr($alt); ?>"
            class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail" />
    </picture>
    <?php
    $picture = ob_get_clean();

    return $picture;
}, 10, 3);
