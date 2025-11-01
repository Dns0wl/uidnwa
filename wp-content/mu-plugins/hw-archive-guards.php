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

    function hydrateImage(node){
        if (!node || node.__hwHydrated || !node.getAttribute) {return;}
        if (!node.hasAttribute('data-hw-img')) {return;}
        node.__hwHydrated = true;
        if (!node.hasAttribute('decoding')) {
            node.setAttribute('decoding', 'async');
        }
        if (!node.hasAttribute('loading')) {
            node.setAttribute('loading', 'lazy');
        }
        if (!node.hasAttribute('fetchpriority')) {
            node.setAttribute('fetchpriority', 'low');
        }
        var placeholder = node.getAttribute('data-placeholder');
        if (placeholder && !node.style.backgroundImage) {
            node.style.backgroundImage = 'url(' + placeholder + ')';
            node.style.backgroundSize = 'cover';
            node.style.backgroundPosition = '50% 50%';
            node.style.backgroundRepeat = 'no-repeat';
        }
        node.addEventListener('load', function(){
            node.classList.add('is-loaded');
            node.style.removeProperty('background-image');
        });
    }

    function scanForImages(root){
        if (!root || !root.querySelectorAll) {return;}
        var nodes = root.querySelectorAll('img[data-hw-img]');
        for (var i = 0; i < nodes.length; i++) {
            hydrateImage(nodes[i]);
        }
    }

    scanForImages(document);

    if ('MutationObserver' in window) {
        var observer = new MutationObserver(function(records){
            records.forEach(function(record){
                if (record.addedNodes && record.addedNodes.length) {
                    for (var i = 0; i < record.addedNodes.length; i++) {
                        var node = record.addedNodes[i];
                        if (node.nodeType !== 1) {continue;}
                        if (node.matches && node.matches('img[data-hw-img]')) {
                            hydrateImage(node);
                        }
                        scanForImages(node);
                    }
                }
            });
        });
        observer.observe(document.documentElement, {childList:true, subtree:true});
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
    opacity: 0.92;
    transition: opacity 180ms ease;
}
.product-card img.is-loaded {
    opacity: 1;
}
.product-card [data-hw-badges] {
    min-height: 32px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.product-body {
    display: grid;
    gap: 8px;
}
.product-body .price {
    font-weight: 600;
    color: #dc2626;
    min-height: 1.8em;
    display: flex;
    align-items: center;
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
    function hw_cloudflare_format_image_url($url, $format_or_args = [])
    {
        if (empty($url)) {
            return $url;
        }

        if (0 === strpos($url, '//')) {
            $url = (is_ssl() ? 'https:' : 'http:') . $url;
        }

        $parsed = wp_parse_url($url);
        if (empty($parsed['path'])) {
            return $url;
        }

        if (is_string($format_or_args)) {
            $args = ['format' => $format_or_args];
        } elseif (is_array($format_or_args)) {
            $args = $format_or_args;
        } else {
            $args = [];
        }

        $defaults = [];
        if (! isset($args['quality'])) {
            $defaults['quality'] = isset($args['format']) ? 82 : 85;
        }

        $args = array_merge($defaults, $args);

        $directives = [];
        foreach ($args as $key => $value) {
            if (null === $value || '' === $value) {
                continue;
            }
            $directives[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        if (empty($directives)) {
            return $url;
        }

        $path = $parsed['path'];
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';

        return home_url('/cdn-cgi/image/' . implode(',', $directives) . $path . $query);
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
            $rewritten[] = trim(hw_cloudflare_format_image_url($url, ['format' => $format]) . ' ' . $descriptor);
        }

        return implode(', ', $rewritten);
    }
}

if (! function_exists('hw_get_optimized_product_image')) {
    function hw_get_optimized_product_image($image_id)
    {
        $image_id = absint($image_id);
        if (! $image_id) {
            return null;
        }

        $thumb = wp_get_attachment_image_src($image_id, 'woocommerce_thumbnail');
        if (! $thumb) {
            return null;
        }

        $full = wp_get_attachment_image_src($image_id, 'full');
        $base_url = wp_get_attachment_url($image_id);
        if (! $base_url) {
            $base_url = $thumb[0];
        }

        $base_width = max(1, (int) $thumb[1]);
        $base_height = max(1, (int) $thumb[2]);
        $aspect_ratio = $base_width > 0 ? $base_height / $base_width : 1.3333;
        $max_width = $full ? max($base_width, (int) $full[1]) : $base_width;

        $scales = [1, 1.5, 2];
        $widths = [];
        foreach ($scales as $scale) {
            $candidate = (int) round($base_width * $scale);
            if ($candidate > $max_width) {
                $candidate = $max_width;
            }
            if ($candidate >= $base_width) {
                $widths[] = $candidate;
            }
        }

        $widths = array_values(array_unique(array_filter($widths)));
        if (empty($widths)) {
            $widths = [$base_width];
        }

        $formats = ['avif', 'webp'];
        $sources = [];
        foreach ($formats as $format) {
            $parts = [];
            foreach ($widths as $variant_width) {
                $variant_height = (int) round($variant_width * $aspect_ratio);
                $parts[] = hw_cloudflare_format_image_url($base_url, [
                    'format' => $format,
                    'width'  => $variant_width,
                    'height' => $variant_height,
                    'fit'    => 'cover',
                    'quality'=> 80,
                ]) . ' ' . $variant_width . 'w';
            }
            $sources[$format] = implode(', ', $parts);
        }

        $fallback_srcset_parts = [];
        foreach ($widths as $variant_width) {
            $variant_height = (int) round($variant_width * $aspect_ratio);
            $fallback_srcset_parts[] = hw_cloudflare_format_image_url($base_url, [
                'width'  => $variant_width,
                'height' => $variant_height,
                'fit'    => 'cover',
                'quality'=> 85,
            ]) . ' ' . $variant_width . 'w';
        }

        $base_variant_width = $widths[0];
        $base_variant_height = (int) round($base_variant_width * $aspect_ratio);

        $placeholder = hw_cloudflare_format_image_url($base_url, [
            'format' => 'webp',
            'width'  => max(16, (int) round($base_variant_width / 12)),
            'height' => max(16, (int) round($base_variant_height / 12)),
            'fit'    => 'cover',
            'quality'=> 60,
            'blur'   => 40,
        ]);

        $alt_text = trim((string) get_post_meta($image_id, '_wp_attachment_image_alt', true));
        if ($alt_text === '') {
            $alt_text = get_the_title($image_id);
        }

        return [
            'src'         => hw_cloudflare_format_image_url($base_url, [
                'width'  => $base_variant_width,
                'height' => $base_variant_height,
                'fit'    => 'cover',
                'quality'=> 85,
            ]),
            'width'       => $base_width,
            'height'      => $base_height,
            'srcset'      => implode(', ', $fallback_srcset_parts),
            'sizes'       => '(min-width:980px) 25vw, 50vw',
            'sources'     => $sources,
            'alt'         => $alt_text,
            'placeholder' => $placeholder,
        ];
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

    $image = hw_get_optimized_product_image($image_id);
    if (! $image) {
        return $html;
    }

    $sources = isset($image['sources']) && is_array($image['sources']) ? $image['sources'] : [];
    $sizes = isset($image['sizes']) ? $image['sizes'] : '(min-width:980px) 25vw, 50vw';

    ob_start();
    ?>
    <picture>
        <?php if (! empty($sources['avif'])) : ?>
            <source type="image/avif" srcset="<?php echo esc_attr($sources['avif']); ?>" sizes="<?php echo esc_attr($sizes); ?>" />
        <?php endif; ?>
        <?php if (! empty($sources['webp'])) : ?>
            <source type="image/webp" srcset="<?php echo esc_attr($sources['webp']); ?>" sizes="<?php echo esc_attr($sizes); ?>" />
        <?php endif; ?>
        <img src="<?php echo esc_url($image['src']); ?>"
            width="<?php echo esc_attr($image['width']); ?>"
            height="<?php echo esc_attr($image['height']); ?>"
            srcset="<?php echo esc_attr($image['srcset']); ?>"
            sizes="<?php echo esc_attr($sizes); ?>"
            alt="<?php echo esc_attr($image['alt']); ?>"
            data-hw-img
            data-placeholder="<?php echo esc_url($image['placeholder']); ?>"
            decoding="async"
            class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail" />
    </picture>
    <?php
    $picture = ob_get_clean();

    return $picture;
}, 10, 3);
