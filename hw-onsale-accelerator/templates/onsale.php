<?php
/**
 * On Sale Template accelerated version.
 *
 * @package HW_Onsale_Accelerator
 */

defined( 'ABSPATH' ) || exit;

use function HW_Onsale_Accelerator\render_card;

$data = get_query_var( 'hw_onsale_acc_initial' );
if ( empty( $data ) || ! is_array( $data ) ) {
	$data = array(
		'products'    => array(),
		'total'       => 0,
		'total_pages' => 0,
		'price_range' => array( 'min' => 0, 'max' => 0 ),
		'params'      => array(
			'orderby'    => 'discount-desc',
			'min_price'  => null,
			'max_price'  => null,
			'categories' => array(),
			'in_stock'   => 0,
			'per_page'   => (int) get_option( 'hw_onsale_batch_size', 12 ),
		),
	);
}

$filters             = $data['params'];
$per_page_setting    = isset( $filters['per_page'] ) ? (int) $filters['per_page'] : (int) get_option( 'hw_onsale_batch_size', 12 );
$per_page            = max( 1, $per_page_setting );

if ( 'discount-desc' === ( $filters['orderby'] ?? 'discount-desc' ) ) {
	$per_page = 12;
}

$filters['per_page'] = $per_page;
$data['params']      = $filters;

$products            = isset( $data['products'] ) ? array_slice( $data['products'], 0, $per_page ) : array();
$total_found         = isset( $data['total'] ) ? (int) $data['total'] : 0;
$slider_min          = isset( $data['price_range']['min'] ) ? (int) $data['price_range']['min'] : 0;
$slider_max          = isset( $data['price_range']['max'] ) ? (int) $data['price_range']['max'] : 0;
$current_min_price   = isset( $filters['min_price'] ) && null !== $filters['min_price'] ? (int) $filters['min_price'] : $slider_min;
$current_max_price   = isset( $filters['max_price'] ) && null !== $filters['max_price'] ? (int) $filters['max_price'] : $slider_max;
$current_categories  = isset( $filters['categories'] ) ? (array) $filters['categories'] : array();
$has_active_filters  = ( $current_min_price > $slider_min ) || ( $slider_max > 0 && $current_max_price < $slider_max ) || ! empty( $current_categories );
$slider_min_attr     = max( 0, $slider_min );
$slider_max_attr     = max( $slider_min_attr + 1000, $slider_max );

get_header();
?>

<div class="hw-onsale-container">
        <?php if ( get_option( 'hw_onsale_banner_show', '0' ) === '1' && get_option( 'hw_onsale_banner_image', '' ) ) : ?>
                <div class="hw-onsale-banner">
                        <?php
                        $banner_image_id = get_option( 'hw_onsale_banner_image', '' );
                        $banner_image_url = $banner_image_id ? wp_get_attachment_url( $banner_image_id ) : '';
                        $banner_height_desktop = get_option( 'hw_onsale_banner_height_desktop', 400 );
                        $banner_height_mobile  = get_option( 'hw_onsale_banner_height_mobile', 300 );
                        ?>
                        <?php if ( $banner_image_url ) : ?>
                                <img
                                        src="<?php echo esc_url( $banner_image_url ); ?>"
                                        alt="<?php echo esc_attr( get_option( 'hw_onsale_banner_title', '' ) ); ?>"
                                        class="hw-onsale-banner__image"
                                        width="1600"
                                        height="<?php echo esc_attr( $banner_height_desktop ); ?>"
                                        style="height: <?php echo esc_attr( $banner_height_desktop ); ?>px; object-fit: cover;"
                                />
                        <?php endif; ?>

                        <?php
                        $banner_title     = get_option( 'hw_onsale_banner_title', '' );
                        $banner_subtitle  = get_option( 'hw_onsale_banner_subtitle', '' );
                        $banner_cta_text  = get_option( 'hw_onsale_banner_cta_text', '' );
                        $banner_cta_link  = get_option( 'hw_onsale_banner_cta_link', '' );
                        $banner_opacity   = get_option( 'hw_onsale_banner_overlay_opacity', 0.3 );
                        $banner_alignment = get_option( 'hw_onsale_banner_alignment', 'center' );

                        if ( $banner_title || $banner_subtitle || $banner_cta_text ) :
                                ?>
                                <div class="hw-onsale-banner__overlay" style="background: rgba(0, 0, 0, <?php echo esc_attr( $banner_opacity ); ?>); text-align: <?php echo esc_attr( $banner_alignment ); ?>;">
                                        <?php if ( $banner_title ) : ?>
                                                <h1 class="hw-onsale-banner__title"><?php echo esc_html( $banner_title ); ?></h1>
                                        <?php endif; ?>

                                        <?php if ( $banner_subtitle ) : ?>
                                                <p class="hw-onsale-banner__subtitle"><?php echo esc_html( $banner_subtitle ); ?></p>
                                        <?php endif; ?>

                                        <?php if ( $banner_cta_text && $banner_cta_link ) : ?>
                                                <a href="<?php echo esc_url( $banner_cta_link ); ?>" class="hw-onsale-banner__cta">
                                                        <?php echo esc_html( $banner_cta_text ); ?>
                                                </a>
                                        <?php endif; ?>
                                </div>
                        <?php endif; ?>
                </div>
        <?php endif; ?>

        <?php if ( ! empty( $products ) || $has_active_filters ) : ?>
                <!-- H) Filters & Sorting Bar -->
                <div class="hw-filters-bar">
                        <div class="hw-filter-left">
                                <button type="button" class="hw-filter-btn hw-filter-toggle" aria-label="<?php esc_attr_e( 'Open filters', 'hw-onsale' ); ?>">
                                        <span><?php esc_html_e( 'Filters', 'hw-onsale' ); ?></span>
                                        <?php if ( $has_active_filters ) : ?>
                                                <span class="hw-filter-count" aria-hidden="true">•</span>
                                        <?php endif; ?>
                                </button>

                                <div class="hw-sort hw-filter-toggle" role="group" aria-label="<?php esc_attr_e( 'Sort products', 'hw-onsale' ); ?>">
                                        <label for="hw-sort" class="screen-reader-text"><?php esc_html_e( 'Sort by', 'hw-onsale' ); ?></label>
                                        <select id="hw-sort" class="hw-sort-select" aria-label="<?php esc_attr_e( 'Sort products', 'hw-onsale' ); ?>">
                                                <option value="discount-desc" <?php selected( $filters['orderby'] ?? 'discount-desc', 'discount-desc' ); ?>><?php esc_html_e( 'Biggest Discount', 'hw-onsale' ); ?></option>
                                                <option value="price-asc" <?php selected( $filters['orderby'] ?? '', 'price-asc' ); ?>><?php esc_html_e( 'Lowest Price', 'hw-onsale' ); ?></option>
                                                <option value="price-desc" <?php selected( $filters['orderby'] ?? '', 'price-desc' ); ?>><?php esc_html_e( 'Highest Price', 'hw-onsale' ); ?></option>
                                        </select>
                                </div>

                                <?php if ( $has_active_filters ) : ?>
                                        <button type="button" class="hw-filter-reset hw-filter-reset--inline js-hw-filter-reset">
                                                <?php esc_html_e( 'Reset filters', 'hw-onsale' ); ?>
                                        </button>
                                <?php endif; ?>
                        </div>
                </div>

                <!-- Filter Drawer -->
                <div class="hw-filter-drawer" role="dialog" aria-modal="true" aria-labelledby="filter-drawer-title">
                        <div class="hw-filter-drawer__content">
                                <header class="hw-filter-drawer__header">
                                        <h2 id="filter-drawer-title" class="hw-filter-drawer__title"><?php esc_html_e( 'Filter', 'hw-onsale' ); ?></h2>
                                        <div class="hw-filter-drawer__header-actions">
                                                <button type="button" class="hw-filter-drawer__close" aria-label="<?php esc_attr_e( 'Close filters', 'hw-onsale' ); ?>">
                                                        ×
                                                </button>
                                        </div>
                                </header>

                                <div class="hw-filter-drawer__body">
                                        <section class="hw-filter-section">
                                                <p class="hw-filter-section__label"><?php esc_html_e( 'Price Range', 'hw-onsale' ); ?></p>
                                                <div class="hw-price-range" data-price-min="<?php echo esc_attr( $slider_min_attr ); ?>" data-price-max="<?php echo esc_attr( $slider_max_attr ); ?>">
                                                        <div class="hw-price-chart" aria-hidden="true"></div>
                                                        <div class="hw-price-slider" data-price-slider>
                                                                <div class="hw-price-slider__track">
                                                                        <div class="hw-price-slider__range"></div>
                                                                </div>
                                                                <input type="range" id="hw-price-min" class="hw-price-slider__input hw-price-slider__input--min" name="min_price" min="<?php echo esc_attr( $slider_min_attr ); ?>" max="<?php echo esc_attr( $slider_max_attr ); ?>" value="<?php echo esc_attr( $current_min_price ); ?>" step="1000" data-default="<?php echo esc_attr( $slider_min_attr ); ?>">
                                                                <input type="range" id="hw-price-max" class="hw-price-slider__input hw-price-slider__input--max" name="max_price" min="<?php echo esc_attr( $slider_min_attr ); ?>" max="<?php echo esc_attr( $slider_max_attr ); ?>" value="<?php echo esc_attr( $current_max_price ); ?>" step="1000" data-default="<?php echo esc_attr( $slider_max_attr ); ?>">
                                                        </div>
                                                        <div class="hw-price-slider__values">
                                                                <label class="hw-filter-value" for="hw-price-input-min">
                                                                        <span class="hw-filter-value__prefix"><?php esc_html_e( 'Rp', 'hw-onsale' ); ?></span>
                                                                        <input
                                                                                id="hw-price-input-min"
                                                                                type="text"
                                                                                inputmode="numeric"
                                                                                pattern="[0-9\\.,]*"
                                                                                class="hw-filter-value__input"
                                                                                data-min-value
                                                                                aria-label="<?php esc_attr_e( 'Minimum price', 'hw-onsale' ); ?>"
                                                                                value="<?php echo esc_attr( number_format_i18n( $current_min_price ) ); ?>"
                                                                        />
                                                                </label>
                                                                <label class="hw-filter-value" for="hw-price-input-max">
                                                                        <span class="hw-filter-value__prefix"><?php esc_html_e( 'Rp', 'hw-onsale' ); ?></span>
                                                                        <input
                                                                                id="hw-price-input-max"
                                                                                type="text"
                                                                                inputmode="numeric"
                                                                                pattern="[0-9\\.,]*"
                                                                                class="hw-filter-value__input"
                                                                                data-max-value
                                                                                aria-label="<?php esc_attr_e( 'Maximum price', 'hw-onsale' ); ?>"
                                                                                value="<?php echo esc_attr( number_format_i18n( $current_max_price ) ); ?>"
                                                                        />
                                                                </label>
                                                        </div>
                                                </div>
                                        </section>

                                        <section class="hw-filter-section">
                                                <p class="hw-filter-section__label"><?php esc_html_e( 'Categories', 'hw-onsale' ); ?></p>
                                                <?php
                                                $categories = get_terms(
                                                        array(
                                                                'taxonomy'   => 'product_cat',
                                                                'hide_empty' => true,
                                                                'parent'     => 0,
                                                        )
                                                );

                                                if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) :
                                                        foreach ( $categories as $parent_cat ) :
                                                                $children = get_terms(
                                                                        array(
                                                                                'taxonomy'   => 'product_cat',
                                                                                'hide_empty' => true,
                                                                                'parent'     => $parent_cat->term_id,
                                                                        )
                                                                );

                                                                if ( ! empty( $children ) && ! is_wp_error( $children ) ) :
                                                                        ?>
                                                                        <p class="hw-category-header"><?php echo esc_html( $parent_cat->name ); ?></p>
                                                                        <div class="hw-category-children">
                                                                                <?php foreach ( $children as $child_cat ) :
                                                                                        $is_checked = in_array( (string) $child_cat->term_id, array_map( 'strval', $current_categories ), true );
                                                                                        ?>
                                                                                        <label class="hw-category-child">
                                                                                                <input
                                                                                                        type="checkbox"
                                                                                                        name="categories[]"
                                                                                                        value="<?php echo esc_attr( $child_cat->term_id ); ?>"
                                                                                                        <?php checked( $is_checked ); ?>
                                                                                                >
                                                                                                <span><?php echo esc_html( $child_cat->name ); ?></span>
                                                                                        </label>
                                                                                <?php endforeach; ?>
                                                                        </div>
                                                                        <?php
                                                                endif;
                                                        endforeach;
                                                endif;
                                                ?>
                                        </section>

                                </div>

                                <div class="hw-filter-drawer__footer">
                                        <button type="button" class="hw-filter-reset hw-filter-reset--ghost js-hw-filter-reset"><?php esc_html_e( 'Clear all', 'hw-onsale' ); ?></button>
                                        <button type="button" class="hw-filter-apply"><?php esc_html_e( 'Apply', 'hw-onsale' ); ?></button>
                                </div>
                        </div>
                </div>
        <?php endif; ?>

        <?php if ( ! empty( $products ) ) : ?>
                <div class="hw-onsale-grid" role="list" aria-label="<?php esc_attr_e( 'On sale products', 'hw-onsale' ); ?>">
                        <?php foreach ( $products as $index => $product ) : ?>
                                <?php echo render_card( $product, $index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endforeach; ?>
                </div>

                <?php if ( $total_found > $per_page ) : ?>
                        <div class="hw-onsale-load-more">
                                <?php
                                $load_more_label = get_option( 'hw_onsale_load_more_label', '' );
                                if ( empty( $load_more_label ) ) {
                                        $load_more_label = __( 'Load more', 'hw-onsale' );
                                }
                                ?>
                                <button
                                        type="button"
                                        class="hw-onsale-load-more__button"
                                        aria-label="<?php esc_attr_e( 'Load more products', 'hw-onsale' ); ?>">
                                        <span class="hw-onsale-load-more__label"><?php echo esc_html( $load_more_label ); ?></span>
                                </button>
                        </div>
                <?php endif; ?>
        <?php else : ?>
                <div class="hw-onsale-empty" role="status">
                        <h2 class="hw-onsale-empty__title"><?php esc_html_e( 'No Products Found', 'hw-onsale' ); ?></h2>
                        <p class="hw-onsale-empty__text"><?php esc_html_e( 'There are currently no products on sale in the private sale category.', 'hw-onsale' ); ?></p>
                </div>
        <?php endif; ?>
        <div class="hw-product-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="hw-product-modal-title">
                <div class="hw-product-modal__panel" role="document">
                        <button type="button" class="hw-product-modal__close" data-modal-close aria-label="<?php esc_attr_e( 'Close', 'hw-onsale' ); ?>">×</button>
                        <div class="hw-product-modal__content">
                                <div
                                        class="hw-product-modal__image-wrapper"
                                        data-modal-image-trigger
                                        role="link"
                                        tabindex="0"
                                        aria-label="<?php esc_attr_e( 'View product details', 'hw-onsale' ); ?>">
                                        <img src="" alt="" class="hw-product-modal__image" data-modal-image style="display: none;">
                                </div>
                                <div class="hw-product-modal__details">
                                        <h2 id="hw-product-modal-title" class="hw-product-modal__title" data-modal-title></h2>
                                        <div class="hw-product-modal__price" data-modal-price></div>
                                        <button type="button" class="hw-product-modal__view-details" data-modal-view-details>
                                                <?php esc_html_e( 'View details', 'hw-onsale' ); ?>
                                        </button>
                                        <div class="hw-product-modal__divider" aria-hidden="true"></div>
                                        <div class="hw-product-modal__size" data-modal-size hidden>
                                                <button type="button" class="hw-product-modal__size-value" data-modal-size-value disabled></button>
                                        </div>
                                        <div class="hw-product-modal__materials hw-product-modal__categories" data-modal-categories hidden>
                                                <span class="hw-product-modal__materials-label hw-product-modal__categories-label"><?php esc_html_e( 'Categories', 'hw-onsale' ); ?></span>
                                                <div class="hw-product-modal__materials-list hw-product-modal__categories-list" data-modal-category-list></div>
                                        </div>
                                        <div class="hw-product-modal__divider" aria-hidden="true"></div>
                                        <form class="hw-product-modal__form" data-modal-form></form>
                                        <p class="hw-product-modal__feedback" data-modal-feedback></p>
                                        <div class="hw-product-modal__actions">
                                                <button type="button" class="hw-product-modal__submit" data-modal-submit disabled><?php esc_html_e( 'Add to cart', 'hw-onsale' ); ?></button>
                                                <button type="button" class="hw-product-modal__checkout" data-modal-checkout disabled><?php esc_html_e( 'Checkout', 'hw-onsale' ); ?></button>
                                        </div>
                                </div>
                        </div>
                </div>
        </div>

        <div class="hw-toast-container" aria-live="polite" aria-atomic="true"></div>
</div>

<?php
get_footer();
