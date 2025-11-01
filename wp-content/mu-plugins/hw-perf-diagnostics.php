<?php
/**
 * Plugin Name: HW Performance Diagnostics
 * Description: Front-end timing instrumentation for Hayu Widyas OnSale archive.
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    if (! wp_next_scheduled('hw_perf_diagnostics_cleanup')) {
        wp_schedule_event(time() + MINUTE_IN_SECONDS, 'hourly', 'hw_perf_diagnostics_cleanup');
    }

    if (isset($_GET['hw-profiler'])) {
        $raw = sanitize_text_field(wp_unslash($_GET['hw-profiler']));
        if (in_array($raw, ['1', 'true', 'on'], true)) {
            setcookie('hw_diag_optin', '1', time() + 900, '/', '', is_ssl(), true);
            $_COOKIE['hw_diag_optin'] = '1';
        } else {
            setcookie('hw_diag_optin', '', time() - 3600, '/', '', is_ssl(), true);
            unset($_COOKIE['hw_diag_optin']);
        }
    }
});

add_action('hw_perf_diagnostics_cleanup', function () {
    global $wpdb;
    $transients = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_hw_diag_') . '%'
        )
    );
    foreach ($transients as $option_name) {
        $key = str_replace('_transient_', '', $option_name);
        if (0 === strpos($key, 'hw_diag_')) {
            get_transient($key); // Touch to let WP handle expiry.
        }
    }
});

function hw_perf_is_archive_like_page()
{
    if (function_exists('hw_is_product_archive_like')) {
        return hw_is_product_archive_like();
    }

    if (function_exists('is_shop') && is_shop()) {
        return true;
    }
    if (function_exists('is_product_taxonomy') && is_product_taxonomy()) {
        return true;
    }
    if (function_exists('is_post_type_archive') && is_post_type_archive('product')) {
        return true;
    }
    if (function_exists('is_page') && is_page()) {
        $slug = get_post_field('post_name', get_queried_object_id());
        if ('onsale' === $slug) {
            return true;
        }
    }

    return false;
}

function hw_perf_should_instrument()
{
    if (is_admin()) {
        return false;
    }

    if (! hw_perf_is_archive_like_page()) {
        return false;
    }

    return isset($_COOKIE['hw_diag_optin']) && '1' === $_COOKIE['hw_diag_optin'];
}

add_action('rest_api_init', function () {
    register_rest_route(
        'hw/v1',
        '/timings',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'hw_perf_get_timings',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'hw/v1',
        '/timings',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'hw_perf_store_timings',
            'permission_callback' => '__return_true',
            'args'                => [
                'id'      => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'payload' => [
                    'required'          => true,
                ],
            ],
        ]
    );
});

function hw_perf_store_timings(WP_REST_Request $request)
{
    $raw_body = $request->get_body();
    $decoded = $raw_body ? json_decode($raw_body, true) : null;

    $id = $decoded && isset($decoded['id']) ? sanitize_key($decoded['id']) : sanitize_key($request->get_param('id'));
    $payload = $decoded && isset($decoded['payload']) ? $decoded['payload'] : $request->get_param('payload');

    if (empty($id)) {
        return new WP_Error('hw_diag_invalid', 'Missing diagnostics id.');
    }

    if (is_string($payload)) {
        $payload = json_decode($payload, true);
    }
    if (! is_array($payload)) {
        return new WP_Error('hw_diag_payload', 'Invalid payload.');
    }

    set_transient('hw_diag_' . $id, $payload, 10 * MINUTE_IN_SECONDS);

    return rest_ensure_response([
        'stored' => true,
    ]);
}

function hw_perf_get_timings(WP_REST_Request $request)
{
    $id = sanitize_key($request->get_param('id'));
    if (empty($id)) {
        return new WP_Error('hw_diag_invalid', 'Missing diagnostics id.');
    }

    $payload = get_transient('hw_diag_' . $id);
    if (! $payload) {
        return new WP_Error('hw_diag_missing', 'No diagnostics data recorded yet.');
    }

    $response = rest_ensure_response($payload);
    $response->header('Cache-Control', 'no-store');

    return $response;
}

add_action('wp_enqueue_scripts', function () {
    if (! hw_perf_should_instrument()) {
        return;
    }

    wp_register_script('hw-perf-diagnostics', false, [], null, true);
    wp_enqueue_script('hw-perf-diagnostics');

    $script = <<<'JS'
(function(){
    if (window.__hwPerfInit) {return;}
    window.__hwPerfInit = true;
    window.__hwHeavy = window.__hwHeavy || [];

    function recordHeavy(payload){
        if (window.__hwHeavy.length >= 20) {
            return;
        }
        window.__hwHeavy.push(payload);
    }
    function ensureDiagId(){
        var match = document.cookie.match(/(?:^|; )hw_diag_id=([^;]+)/);
        if (match){return match[1];}
        var id = (Math.random().toString(36).slice(2) + Date.now().toString(36)).slice(0, 16);
        document.cookie = 'hw_diag_id=' + id + '; path=/; max-age=900; SameSite=Lax';
        return id;
    }
    var diagId = ensureDiagId();

    var originalSetInterval = window.setInterval;
    window.setInterval = function(callback, delay){
        var wrapped = callback;
        if (typeof callback === 'function') {
            wrapped = function(){
                var start = performance.now();
                try {
                    return callback.apply(this, arguments);
                } finally {
                    var duration = performance.now() - start;
                    if (duration > 50) {
                        var payload = {
                            type: 'interval',
                            delay: delay,
                            duration: duration,
                            timestamp: Date.now()
                        };
                        recordHeavy(payload);
                        console.warn('[HW] Heavy setInterval callback', payload);
                    }
                }
            };
        }
        return originalSetInterval.call(this, wrapped, delay);
    };

    if ('requestIdleCallback' in window) {
        var originalRIC = window.requestIdleCallback;
        window.requestIdleCallback = function(callback, options){
            if (typeof callback === 'function') {
                var wrapped = function(deadline){
                    var start = performance.now();
                    try {
                        return callback.call(this, deadline);
                    } finally {
                        var duration = performance.now() - start;
                        if (duration > 50) {
                            var payload = {
                                type: 'idleCallback',
                                duration: duration,
                                timestamp: Date.now()
                            };
                            recordHeavy(payload);
                            console.warn('[HW] Heavy requestIdleCallback', payload);
                        }
                    }
                };
                return originalRIC.call(this, wrapped, options);
            }
            return originalRIC.apply(this, arguments);
        };
    }

    var perfData = {
        lcp: null,
        fcp: null,
        cls: 0,
        entries: []
    };

    if ('PerformanceObserver' in window) {
        try {
            var lcpObserver = new PerformanceObserver(function(list){
                var entries = list.getEntries();
                var lastEntry = entries[entries.length - 1];
                if (lastEntry) {
                    perfData.lcp = {
                        value: lastEntry.renderTime || lastEntry.loadTime,
                        element: lastEntry.id || lastEntry.element && lastEntry.element.tagName
                    };
                    console.log('[HW] LCP', perfData.lcp);
                }
            });
            lcpObserver.observe({type:'largest-contentful-paint', buffered:true});
        } catch(e) {}

        try {
            var fcpObserver = new PerformanceObserver(function(list){
                var entry = list.getEntries()[0];
                if (entry) {
                    perfData.fcp = { value: entry.startTime };
                    console.log('[HW] FCP', perfData.fcp);
                }
            });
            fcpObserver.observe({type:'paint', buffered:true});
        } catch(e) {}

        try {
            var clsValue = 0;
            var sessionValue = 0;
            var sessionEntries = [];
            var clsObserver = new PerformanceObserver(function(list){
                list.getEntries().forEach(function(entry){
                    if (!entry.hadRecentInput) {
                        var firstSessionEntry = sessionEntries[0];
                        var lastSessionEntry = sessionEntries[sessionEntries.length - 1];
                        if (sessionValue && entry.startTime - lastSessionEntry.startTime > 1000 || entry.startTime - firstSessionEntry.startTime > 5000) {
                            sessionValue = 0;
                            sessionEntries = [];
                        }
                        sessionValue += entry.value;
                        sessionEntries.push(entry);
                        clsValue = Math.max(clsValue, sessionValue);
                        perfData.cls = clsValue;
                        console.log('[HW] CLS update', clsValue);
                    }
                });
            });
            clsObserver.observe({type:'layout-shift', buffered:true});
        } catch(e) {}
    }

    function collectPayload(){
        var resources = performance.getEntriesByType('resource') || [];
        return {
            id: diagId,
            metrics: {
                lcp: perfData.lcp,
                fcp: perfData.fcp,
                cls: perfData.cls,
                heavyCallbacks: window.__hwHeavy,
                resources: resources.length,
                navigation: performance.getEntriesByType('navigation')[0] || null
            }
        };
    }

    var flushed = false;

    function sendPayload(){
        if (flushed) {return;}
        flushed = true;
        var payload = collectPayload();
        try {
            var body = JSON.stringify({id: payload.id, payload: payload.metrics});
            if (navigator.sendBeacon) {
                var blob = new Blob([body], {type: 'application/json'});
                navigator.sendBeacon('/wp-json/hw/v1/timings', blob);
            } else {
                fetch('/wp-json/hw/v1/timings', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: body
                });
            }
        } catch (e) {
            console.warn('HW diagnostics send failed', e);
        }
    }

    document.addEventListener('visibilitychange', function(){
        if (document.visibilityState === 'hidden') {
            sendPayload();
        }
    });

    window.__hwDiagCollect = collectPayload;
})();
JS;

    wp_add_inline_script('hw-perf-diagnostics', $script);
});
