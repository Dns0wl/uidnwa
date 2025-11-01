<?php
/**
 * Plugin Name: HW Onsale Accelerator
 * Description: High-performance accelerator for the /onsale landing page with caching and REST optimisations.
 * Version: 1.0.0
 * Author: HW Team
 * Text Domain: hw-onsale-accelerator
 */

defined( 'ABSPATH' ) || exit;

define( 'HW_ONSALE_ACCELERATOR_VERSION', '1.0.0' );
define( 'HW_ONSALE_ACCELERATOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'HW_ONSALE_ACCELERATOR_URL', plugin_dir_url( __FILE__ ) );

require_once HW_ONSALE_ACCELERATOR_DIR . 'includes/Cache.php';
require_once HW_ONSALE_ACCELERATOR_DIR . 'includes/Query.php';
require_once HW_ONSALE_ACCELERATOR_DIR . 'includes/template-tags.php';
require_once HW_ONSALE_ACCELERATOR_DIR . 'includes/Controller.php';

\HW_Onsale_Accelerator\Controller::instance();
