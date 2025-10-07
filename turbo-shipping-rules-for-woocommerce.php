<?php
/**
 * Plugin Name: TURBO - Shipping Rules for WooCommerce
 * Plugin URI: https://turbo-addons.com/turbo-shipping-rules-for-woocommerce/
 * Description: Easily manage WooCommerce shipping with custom states (inside city, outside city, intercity) and advanced weight-based shipping methods filtered by product categories. Fast, simple, and powerful shipping manager for WooCommerce.
 * Version: 1.0.0
 * Requires Plugins: woocommerce
 * Author: Turbo Addons
 * Author URI: https://turbo-addons.com
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: turbo-shipping-rules-for-woocommerce
 * Requires at least: 5.4
 * Requires PHP: 7.2
 * Tested up to: 6.5
 * WC requires at least: 4.0
 * WC tested up to: 8.9
 */

if (!defined('ABSPATH')) exit;

// ✅ HPOS compatibility declaration.
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

if (!class_exists('TSRFW_Shipping_Rules_For_Woo')) {
    final class TSRFW_Shipping_Rules_For_Woo {
        private static $instance = null;
        const TSRFW_VERSION = '1.0.0';
        public static function instance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            add_action('plugins_loaded', [$this, 'init_plugin']);
        }

        public function init_plugin() {
            if (!class_exists('WooCommerce')) {
                add_action('admin_notices', [$this, 'admin_notice_missing_wc']);
                return;
            }

            require_once plugin_dir_path(__FILE__) . 'includes/register-post-type.php';
            require_once plugin_dir_path(__FILE__) . 'includes/admin-menu.php';

            // **New file for custom shipping zones**
            require_once plugin_dir_path(__FILE__) . 'includes/admin-shipping-zones.php';
             // ✅ New: include Weight Based Shipping
            require_once plugin_dir_path(__FILE__) . 'includes/weight-based-shipping.php';

            add_filter('woocommerce_states', [$this, 'safe_merge_custom_states'], 20);
        }

        public function admin_notice_missing_wc() {
            echo '<div class="notice notice-error"><p>';
            esc_html_e('Custom Shipping Manager for WooCommerce requires WooCommerce to be installed and active.', 'turbo-shipping-rules-for-woocommerce');
            echo '</p></div>';
        }

        public function safe_merge_custom_states($states) {
            
            if (!did_action('woocommerce_init')) {
                return $states;
            }

            $query = new WP_Query([
                'post_type'      => 'tsrfw_state',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids', 
            ]);

            if (!empty($query->posts)) {
                foreach ($query->posts as $post_id) {
                    $code    = get_post_meta($post_id, 'state_code', true);
                    $name    = get_the_title($post_id);
                    $country = get_post_meta($post_id, 'country_code', true) ?: 'BD';

                    if ($code && $name) {
                        $states[$country][$code] = $name;
                    }
                }
            }

            return $states;
        }

        // Function to dynamically get zones from custom states
        public function get_static_zones_from_states() {
            $zones = [];

            $query = new WP_Query([
                'post_type'      => 'tsrfw_state',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            ]);

            if ($query->have_posts()) {
                foreach ($query->posts as $post) {
                    $zone_name = get_post_meta($post->ID, 'custom_zone', true); // group name
                    $state_name = get_the_title($post->ID); // region

                    if (!$zone_name) $zone_name = 'Other Zone';

                    // Initialize zone if not exists
                    if (!isset($zones[$zone_name])) {
                        $zones[$zone_name] = [
                            'name' => $zone_name,
                            'regions' => [],
                        ];
                    }

                    // Add state to the zone regions
                    $zones[$zone_name]['regions'][] = $state_name;
                }
            }

            return $zones;
        }

    }

    TSRFW_Shipping_Rules_For_Woo::instance();
}
