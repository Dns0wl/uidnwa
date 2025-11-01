<?php
/**
 * Plugin Name: HW OnSale REST
 * Description: Replaces admin-ajax load more with REST + transient caching.
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route(
        'hw/v1',
        '/onsale',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'hw_onsale_rest_endpoint',
            'permission_callback' => '__return_true',
            'args'                => [
                'page'  => [
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
                'limit' => [
                    'default'           => 12,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]
    );
});

function hw_onsale_rest_endpoint(WP_REST_Request $request)
{
    $page  = max(1, (int) $request->get_param('page'));
    $limit = max(1, min(48, (int) $request->get_param('limit')));
    $cache_key = sprintf('hw_onsale_page_%d_%d', $page, $limit);

    $data = get_transient($cache_key);
    if (false === $data) {
        $product_ids = hw_onsale_get_ids();
        $total = count($product_ids);
        $offset = ($page - 1) * $limit;
        $page_ids = array_slice($product_ids, $offset, $limit);

        if (empty($page_ids)) {
            global $wpdb;
            $table = $wpdb->prefix . 'wc_product_meta_lookup';
            $page_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT product_id FROM {$table} WHERE on_sale = 1 AND stock_status = 'instock' ORDER BY min_price ASC LIMIT %d OFFSET %d",
                    $limit,
                    $offset
                )
            );
        }

        $page_ids = array_map('absint', array_filter($page_ids));

        if (empty($page_ids)) {
            $data = [
                'page'       => $page,
                'limit'      => $limit,
                'total'      => 0,
                'totalPages' => 0,
                'products'   => [],
            ];
        } else {
            $products = hw_format_products_for_rest($page_ids);
            $data = [
                'page'       => $page,
                'limit'      => $limit,
                'total'      => $total,
                'totalPages' => (int) ceil($total / $limit),
                'products'   => $products,
            ];
        }

        set_transient($cache_key, $data, 3 * MINUTE_IN_SECONDS);
    }

    $etag = '"' . md5(wp_json_encode($data)) . '"';
    $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim(wp_unslash($_SERVER['HTTP_IF_NONE_MATCH'])) : '';

    if ($if_none_match && $if_none_match === $etag) {
        $response = new WP_REST_Response(null, 304);
    } else {
        $response = rest_ensure_response($data);
        $response->header('ETag', $etag);
    }

    $response->header('Cache-Control', 'public, max-age=180, stale-while-revalidate=60');

    return $response;
}

function hw_format_products_for_rest(array $ids)
{
    $ids = array_values(array_unique(array_map('absint', $ids)));

    update_meta_cache('post', $ids);
    update_object_term_cache($ids, 'product');

    $products = [];
    foreach ($ids as $id) {
        $product = wc_get_product($id);
        if (! $product) {
            continue;
        }
        $image_id = $product->get_image_id();
        $image = ($image_id && function_exists('hw_get_optimized_product_image')) ? hw_get_optimized_product_image($image_id) : null;
        $price_html = $product->get_price_html();
        $products[] = [
            'id'          => $product->get_id(),
            'name'        => $product->get_name(),
            'permalink'   => $product->get_permalink(),
            'priceHtml'   => $price_html,
            'regular'     => $product->get_regular_price(),
            'sale'        => $product->get_sale_price(),
            'image'       => $image ? array_merge($image, [
                'loading'       => 'lazy',
                'fetchpriority' => 'low',
                'fallbackAlt'   => $product->get_name(),
            ]) : null,
            'badges'      => array_values(array_filter([
                $product->is_on_sale() ? __('Sale', 'hw-onsale') : null,
                $product->is_featured() ? __('Featured', 'hw-onsale') : null,
            ])),
            'inStock'     => $product->is_in_stock(),
            'rating'      => wc_get_rating_html($product->get_average_rating(), $product->get_rating_count()),
        ];
    }

    return $products;
}

function hw_onsale_get_ids()
{
    $precomputed = get_transient('hw_onsale_ids');
    if (is_array($precomputed) && ! empty($precomputed['ids'])) {
        return array_map('absint', $precomputed['ids']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'wc_product_meta_lookup';
    $ids = $wpdb->get_col("SELECT product_id FROM {$table} WHERE on_sale = 1 AND stock_status = 'instock'");
    return array_map('absint', $ids);
}

add_action('wp_enqueue_scripts', function () {
    if (! function_exists('hw_is_product_archive_like') || ! hw_is_product_archive_like()) {
        return;
    }

    wp_register_script('hw-onsale-rest', false, [], null, true);
    wp_enqueue_script('hw-onsale-rest');

    $script = <<<'JS'
(function(){
    if (window.__hwOnsaleRest) {return;}
    window.__hwOnsaleRest = true;

    if (!window.fetch || !window.Promise) {
        return;
    }

    if (!('requestAnimationFrame' in window)) {
        window.requestAnimationFrame = function(cb){ return setTimeout(cb, 16); };
    }

    var container = document.querySelector('[data-hw-onsale-grid]');
    if (!container) {return;}

    var loadMoreBtn = document.querySelector('[data-hw-onsale-more]');
    var cardTemplate = document.querySelector('#hw-onsale-card');
    var currentPage = parseInt(container.getAttribute('data-page') || '1', 10);
    var limit = parseInt(container.getAttribute('data-limit') || '12', 10);
    var rafToken = null;
    var loading = false;

    function updateImageElement(img, payload){
        if (!img || !payload) {return;}
        var picture = null;
        if (img.parentNode && img.parentNode.nodeType === 1 && img.parentNode.tagName && img.parentNode.tagName.toLowerCase() === 'picture') {
            picture = img.parentNode;
        }
        if (picture) {
            Array.prototype.slice.call(picture.querySelectorAll('source')).forEach(function(node){ node.remove(); });
        }
        if (!picture && payload.sources && (payload.sources.avif || payload.sources.webp)) {
            picture = document.createElement('picture');
            if (img.parentNode) {
                img.parentNode.insertBefore(picture, img);
            }
            picture.appendChild(img);
        }
        if (picture && payload.sources) {
            ['avif', 'webp'].forEach(function(format){
                if (payload.sources[format]) {
                    var source = document.createElement('source');
                    source.type = 'image/' + format;
                    source.setAttribute('srcset', payload.sources[format]);
                    if (payload.sizes) {
                        source.setAttribute('sizes', payload.sizes);
                    }
                    picture.insertBefore(source, img);
                }
            });
        }
        if (payload.src) {
            img.src = payload.src;
        }
        if (payload.srcset) {
            img.setAttribute('srcset', payload.srcset);
        } else {
            img.removeAttribute('srcset');
        }
        if (payload.sizes) {
            img.setAttribute('sizes', payload.sizes);
        }
        if (payload.alt !== undefined && payload.alt !== null) {
            img.alt = payload.alt;
        }
        if ((!img.getAttribute('alt') || img.getAttribute('alt') === '') && payload.fallbackAlt) {
            img.setAttribute('alt', payload.fallbackAlt);
        }
        if (payload.width) {
            img.width = payload.width;
        }
        if (payload.height) {
            img.height = payload.height;
        }
        img.setAttribute('data-hw-img', '');
        if (payload.placeholder) {
            img.setAttribute('data-placeholder', payload.placeholder);
        }
        img.setAttribute('decoding', 'async');
        if (payload.loading) {
            img.setAttribute('loading', payload.loading);
        } else if (!img.hasAttribute('loading')) {
            img.setAttribute('loading', 'lazy');
        }
        if (payload.fetchpriority) {
            img.setAttribute('fetchpriority', payload.fetchpriority);
        } else if (!img.hasAttribute('fetchpriority')) {
            img.setAttribute('fetchpriority', 'low');
        }
    }

    function syncGridMetrics(){
        if (rafToken) {return;}
        rafToken = requestAnimationFrame(function(){
            rafToken = null;
            var rect = container.getBoundingClientRect();
            container.style.setProperty('--hw-grid-height', Math.round(rect.height) + 'px');
        });
    }

    if ('ResizeObserver' in window) {
        var ro = new ResizeObserver(function(){
            syncGridMetrics();
        });
        ro.observe(container);
        syncGridMetrics();
    }

    function renderProduct(product){
        if (cardTemplate && 'content' in cardTemplate) {
            var fragment = document.importNode(cardTemplate.content, true);
            var link = fragment.querySelector('[data-hw-link]');
            if (link) {
                link.href = product.permalink;
                link.textContent = product.name;
            }
            var price = fragment.querySelector('[data-hw-price]');
            if (price) {
                price.innerHTML = product.priceHtml;
            }
            var img = fragment.querySelector('[data-hw-img]');
            if (img && product.image) {
                updateImageElement(img, product.image);
            }
            var badgeWrap = fragment.querySelector('[data-hw-badges]');
            if (badgeWrap) {
                badgeWrap.innerHTML = '';
                if (Array.isArray(product.badges)) {
                    product.badges.forEach(function(label){
                        var span = document.createElement('span');
                        span.className = 'hw-badge';
                        span.textContent = label;
                        badgeWrap.appendChild(span);
                    });
                }
            }
            return fragment;
        }

        var article = document.createElement('article');
        article.className = 'product-card';
        var anchor = document.createElement('a');
        anchor.className = 'product-link';
        anchor.href = product.permalink;
        if (product.image) {
            var img = document.createElement('img');
            anchor.appendChild(img);
            updateImageElement(img, product.image);
        }
        article.appendChild(anchor);
        var body = document.createElement('div');
        body.className = 'product-body';
        var title = document.createElement('h3');
        var titleLink = document.createElement('a');
        titleLink.href = product.permalink;
        titleLink.textContent = product.name;
        title.appendChild(titleLink);
        body.appendChild(title);
        var price = document.createElement('div');
        price.className = 'price';
        price.innerHTML = product.priceHtml;
        body.appendChild(price);
        article.appendChild(body);
        var badgeWrap = document.createElement('div');
        badgeWrap.setAttribute('data-hw-badges', '');
        if (Array.isArray(product.badges)) {
            product.badges.forEach(function(label){
                var span = document.createElement('span');
                span.className = 'hw-badge';
                span.textContent = label;
                badgeWrap.appendChild(span);
            });
        }
        article.insertBefore(badgeWrap, anchor);
        return article;
    }

    function fetchPage(page){
        if (loading || page <= currentPage) {
            return Promise.resolve();
        }

        var url = new URL(window.location.origin + '/wp-json/hw/v1/onsale');
        url.searchParams.set('page', page);
        url.searchParams.set('limit', limit);
        if (loadMoreBtn) {
            loadMoreBtn.disabled = true;
            loadMoreBtn.setAttribute('aria-busy', 'true');
        }
        loading = true;
        return fetch(url.toString(), {
            credentials: 'omit'
        }).then(function(response){
            if (response.status === 304) {
                return null;
            }
            return response.json();
        }).then(function(payload){
            if (!payload) {
                if (loadMoreBtn) {
                    loadMoreBtn.disabled = false;
                    loadMoreBtn.removeAttribute('aria-busy');
                }
                return;
            }
            if (Array.isArray(payload.products) && payload.products.length) {
                var fragment = document.createDocumentFragment();
                payload.products.forEach(function(product){
                    var node = renderProduct(product);
                    if (node) {
                        fragment.appendChild(node);
                    }
                });
                container.appendChild(fragment);
            }
            currentPage = payload.page;
            container.setAttribute('data-page', currentPage);
            syncGridMetrics();
            if (loadMoreBtn) {
                if (payload.page >= payload.totalPages) {
                    loadMoreBtn.style.display = 'none';
                } else {
                    loadMoreBtn.disabled = false;
                    loadMoreBtn.removeAttribute('aria-busy');
                }
            }
        }).catch(function(error){
            console.error('HW OnSale REST error', error);
            if (loadMoreBtn) {
                loadMoreBtn.disabled = false;
                loadMoreBtn.removeAttribute('aria-busy');
            }
        }).then(function(){
            loading = false;
        });
    }

    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function(ev){
            ev.preventDefault();
            if (loading) {return;}
            fetchPage(currentPage + 1);
        });
    }
})();
JS;

    wp_add_inline_script('hw-onsale-rest', $script);
}, 150);
