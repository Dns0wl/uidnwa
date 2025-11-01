<?php
/**
 * Plugin Name: HW Archive Performance
 * Description: Inline critical CSS, preload styles, and defer scripts on WooCommerce archives.
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('hw_perf_archive_is_scope')) {
    function hw_perf_archive_is_scope()
    {
        if (function_exists('hw_is_product_archive_like')) {
            return hw_is_product_archive_like();
        }

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
}

function hw_perf_archive_pre_get_posts(\WP_Query $query)
{
    if (is_admin() || ! $query->is_main_query() || ! hw_perf_archive_is_scope()) {
        return;
    }

    $query->set('no_found_rows', true);
    $query->set('cache_results', true);
    $query->set('update_post_meta_cache', true);
    $query->set('update_post_term_cache', true);
}
add_action('pre_get_posts', 'hw_perf_archive_pre_get_posts', 20);

function hw_perf_archive_inline_critical_css()
{
    if (! hw_perf_archive_is_scope()) {
        return;
    }

    $critical = <<<'CSS'
.site-content{contain:layout paint;min-height:100vh;}
.hw-onsale-grid{display:grid;gap:clamp(12px,2vw,24px);margin:0;padding:0;list-style:none;align-content:start;}
.hw-onsale-card{position:relative;display:flex;flex-direction:column;min-height:100%;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 14px 30px rgba(15,23,42,.08);contain:layout style paint;transform:translateZ(0);}
.hw-onsale-card__content{display:flex;flex-direction:column;gap:12px;padding:18px 18px 24px;}
.hw-onsale-card__title{margin:0;font-size:1rem;font-weight:600;line-height:1.35;}
.hw-onsale-card__title a{color:inherit;text-decoration:none;}
.hw-onsale-card__title a:focus-visible{outline:2px solid currentColor;outline-offset:3px;border-radius:6px;}
.hw-onsale-card__price{display:flex;flex-wrap:wrap;gap:6px;font-weight:600;font-size:1.05rem;align-items:center;}
.hw-onsale-slider{position:relative;}
.hw-onsale-slider__track{display:flex;overflow:hidden;scroll-behavior:smooth;}
.hw-onsale-slider__slide{position:relative;flex:1 0 100%;}
.hw-onsale-slider__slide::before{content:"";display:block;padding-top:125%;}
.hw-onsale-slider__slide picture,.hw-onsale-slider__slide img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block;background:linear-gradient(135deg,#f5f7fa,#e6ebf2);}
.hw-onsale-badge{position:absolute;top:16px;left:16px;z-index:3;display:flex;align-items:center;justify-content:center;min-width:52px;min-height:52px;border-radius:999px;padding:8px 12px;background:rgba(220,38,38,.92);color:#fff;font-weight:700;font-size:.95rem;box-shadow:0 20px 40px rgba(220,38,38,.35);transform:translateZ(0);}
.hw-onsale-badge--top-right{left:auto;right:16px;}
.hw-onsale-slider__dots{display:flex;justify-content:center;gap:8px;padding:12px 0 16px;}
.hw-onsale-slider__dot{width:10px;height:10px;border-radius:999px;background:rgba(15,23,42,.18);border:0;padding:0;transition:transform .2s ease,background .2s ease;}
.hw-onsale-slider__dot.is-active{background:rgba(15,23,42,.65);transform:scale(1.15);}
.hw-onsale-load-more{display:flex;justify-content:center;padding:28px 0 0;}
.hw-onsale-load-more__button{min-width:200px;min-height:48px;border-radius:999px;font-weight:600;border:1px solid rgba(15,23,42,.16);background:#fff;color:inherit;transition:transform .2s ease,box-shadow .2s ease;}
.hw-onsale-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:20px;}
.hw-onsale-toolbar__filters{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.hw-onsale-toolbar__filters button{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:999px;border:1px solid rgba(15,23,42,.15);background:#fff;font-weight:600;box-shadow:0 6px 14px rgba(15,23,42,.08);}
@media (min-width:768px){.hw-onsale-grid{grid-template-columns:repeat(auto-fill,minmax(220px,1fr));}}
@media (min-width:1200px){.hw-onsale-grid{grid-template-columns:repeat(4,minmax(0,1fr));}}
CSS;

    echo '<style id="hw-perf-critical">' . trim(preg_replace('/\s+/', ' ', $critical)) . '</style>';
}
add_action('wp_head', 'hw_perf_archive_inline_critical_css', 1);

function hw_perf_archive_preload_styles($html, $handle, $href, $media)
{
    if (! hw_perf_archive_is_scope()) {
        return $html;
    }

    $handles = [
        'styler-style',
        'woocommerce-general',
        'woocommerce-layout',
        'woocommerce-smallscreen',
        'hw-onsale-styles',
        'onsale',
        'default',
        'bootstrap-grid',
    ];

    if (! in_array($handle, $handles, true)) {
        return $html;
    }

    $href = esc_url($href);
    $noscript = '<noscript><link rel="stylesheet" href="' . $href . '" media="all"></noscript>';
    return '<link rel="preload" as="style" href="' . $href . '"><link rel="stylesheet" href="' . $href . '" media="print" onload="this.media=\'all\'">' . $noscript;
}
add_filter('style_loader_tag', 'hw_perf_archive_preload_styles', 20, 4);

function hw_perf_archive_defer_scripts($tag, $handle, $src)
{
    if (! hw_perf_archive_is_scope()) {
        return $tag;
    }

    $handles = [
        'front-main',
        'lazyload',
        'hw-onsale-frontend',
        'hw-onsale-rest',
        'wp-util',
        'jquery-ui-position',
        'addtoany',
    ];

    if (! in_array($handle, $handles, true)) {
        return $tag;
    }

    if (false !== strpos($tag, ' defer ')) {
        return $tag;
    }

    return str_replace('<script ', '<script defer ', $tag);
}
add_filter('script_loader_tag', 'hw_perf_archive_defer_scripts', 20, 3);

function hw_perf_archive_optimize_assets()
{
    if (is_admin() || ! hw_perf_archive_is_scope()) {
        return;
    }

    wp_dequeue_script('wc-cart-fragments');
    wp_dequeue_script('wp-embed');

    $scripts = wp_scripts();
    if ($scripts && isset($scripts->registered['jquery'])) {
        $scripts->registered['jquery']->deps = array_diff(
            $scripts->registered['jquery']->deps,
            ['jquery-migrate']
        );
    }

    if (wp_script_is('jquery-migrate', 'enqueued') || wp_script_is('jquery-migrate', 'registered')) {
        wp_dequeue_script('jquery-migrate');
        wp_deregister_script('jquery-migrate');
    }
}
add_action('wp_enqueue_scripts', 'hw_perf_archive_optimize_assets', 100);

function hw_perf_archive_inline_helpers()
{
    if (! hw_perf_archive_is_scope()) {
        return;
    }

    wp_register_script('hw-perf-archive-helpers', '', [], null, true);
    wp_enqueue_script('hw-perf-archive-helpers');
    $script = <<<'JS'
window.addEventListener('DOMContentLoaded', function(){
    if (window.jQuery) {
        window.jQuery.fx.off = true;
    }
    if (!('hwPerfThrottle' in window)) {
        window.hwPerfThrottle = function(fn, limit){
            var waiting = false;
            return function(){
                if (waiting) {return;}
                waiting = true;
                var ctx = this, args = arguments;
                requestAnimationFrame(function(){
                    fn.apply(ctx, args);
                    setTimeout(function(){ waiting = false; }, limit);
                });
            };
        };
    }
});
JS;
    wp_add_inline_script('hw-perf-archive-helpers', $script);
}
add_action('wp_enqueue_scripts', 'hw_perf_archive_inline_helpers', 120);

function hw_perf_archive_mark_lcp_image($payload)
{
    if (! is_array($payload) || empty($payload['src'])) {
        return;
    }

    static $stored = null;
    if (null === $stored) {
        $stored = $payload;
        add_action('wp_head', function () use (&$stored) {
            if (! hw_perf_archive_is_scope() || ! $stored) {
                return;
            }
            $srcset = isset($stored['srcset']) ? esc_attr($stored['srcset']) : '';
            $sizes = isset($stored['sizes']) ? esc_attr($stored['sizes']) : '';
            $src = esc_url($stored['src']);
            $attributes = [];
            if ($srcset) {
                $attributes[] = 'imagesrcset="' . $srcset . '"';
            }
            if ($sizes) {
                $attributes[] = 'imagesizes="' . $sizes . '"';
            }
            $attributes = implode(' ', $attributes);
            echo '<link rel="preload" as="image" fetchpriority="high" href="' . $src . '" ' . $attributes . ' />';
        }, 5);
    }
}

function hw_perf_archive_register_lcp_image($image)
{
    if (! hw_perf_archive_is_scope()) {
        return;
    }
    hw_perf_archive_mark_lcp_image($image);
}
