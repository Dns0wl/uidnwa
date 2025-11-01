<?php
/**
 * Plugin Name: HW OnSale Precompute
 * Description: Precomputes OnSale product ids every five minutes.
 */

if (! defined('ABSPATH')) {
    exit;
}

add_filter('cron_schedules', function ($schedules) {
    if (! isset($schedules['five_minutes'])) {
        $schedules['five_minutes'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => __('Every Five Minutes', 'hw-onsale'),
        ];
    }
    return $schedules;
});

add_action('init', function () {
    if (! wp_next_scheduled('hw_onsale_prime_lookup')) {
        wp_schedule_event(time() + 60, 'five_minutes', 'hw_onsale_prime_lookup');
    }
});

add_action('hw_onsale_prime_lookup', 'hw_onsale_prime_lookup_handler');

function hw_onsale_prime_lookup_handler()
{
    global $wpdb;
    $table = $wpdb->prefix . 'wc_product_meta_lookup';
    $ids = $wpdb->get_col("SELECT product_id FROM {$table} WHERE on_sale = 1 AND stock_status = 'instock'");
    $ids = array_map('absint', $ids);
    set_transient('hw_onsale_ids', [
        'ids'       => $ids,
        'generated' => time(),
    ], 5 * MINUTE_IN_SECONDS);
}

add_action('init', function () {
    if (false === get_transient('hw_onsale_ids')) {
        hw_onsale_prime_lookup_handler();
    }
}, 20);
