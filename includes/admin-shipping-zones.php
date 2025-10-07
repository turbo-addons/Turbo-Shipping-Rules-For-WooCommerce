<?php
if (!defined('ABSPATH')) exit;

class TSRFW_Admin_Shipping_Zones {

    public function __construct() {
        add_filter('woocommerce_get_sections_shipping', [$this, 'add_shipping_section']);
        add_action('woocommerce_settings_shipping', [$this, 'shipping_zone_settings_page']);
        add_action('woocommerce_update_options_shipping', [$this, 'save_shipping_zones']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_tsrfw_get_zone_regions', [$this, 'ajax_get_zone_regions']);
    }

    // Add shipping section
    public function add_shipping_section($sections) {
        $sections['tsrfw_custom_zones'] = __('Custom Shipping Zones', 'turbo-shipping-rules-for-woocommerce');
        return $sections;
    }

    // Admin settings page
    public function shipping_zone_settings_page() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check, no action taken
        $section = isset($_GET['section']) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
        if ( $section !== 'tsrfw_custom_zones' ) {
            return;
        }

        $zones = $this->get_static_zones_from_states();

        $zone_options = [];
        foreach ($zones as $zone_key => $zone) {
            $zone_options[$zone_key] = $zone['name'];
        }

        $settings = [
            [
                'title' => __('Custom Shipping Zones', 'turbo-shipping-rules-for-woocommerce'),
                'type'  => 'title',
                'id'    => 'tsrfw_custom_zones_title',
            ],
            [
                'title'       => __('Shipping Zone Name', 'turbo-shipping-rules-for-woocommerce'),
                'type'        => 'select',
                'id'          => 'tsrfw_shipping_zone_name',
                'options'     => ['' => '-- Select Your Zone --'] + $zone_options,
                'desc_tip'    => true,
                'description' => __('Select a Shipping Zone.', 'turbo-shipping-rules-for-woocommerce'),
            ],
            [
                'title'       => __('Zone Regions', 'turbo-shipping-rules-for-woocommerce'),
                'type'        => 'multiselect',
                'id'          => 'tsrfw_shipping_zone_regions',
                'options'     => [], // populated via JS
                'desc_tip'    => true,
                'description' => __('Select regions for this zone.', 'turbo-shipping-rules-for-woocommerce'),
                'class'       => 'wc-enhanced-select',
            ],
            [
                'type' => 'sectionend',
                'id'   => 'tsrfw_custom_zones_section_end',
            ],
        ];

        woocommerce_admin_fields($settings);
    }

    // Save zones
    public function save_shipping_zones() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, just checking section
        $section = isset($_GET['section']) ? sanitize_text_field( wp_unslash($_GET['section']) ) : '';
        if ( $section !== 'tsrfw_custom_zones' ) {
            return;
        }

            // ✅ Verify nonce before processing POST
        if (
            ! isset($_POST['_wpnonce'])
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'woocommerce-settings' )
        ) {
            return;
        }

        if (isset($_POST['tsrfw_shipping_zone_name']) && isset($_POST['tsrfw_shipping_zone_regions'])) {
            $zone_name   = sanitize_text_field( wp_unslash( $_POST['tsrfw_shipping_zone_name'] ) );
            $zone_regions = array_map( 'sanitize_text_field', wp_unslash( $_POST['tsrfw_shipping_zone_regions'] ) );
            $zones = WC_Shipping_Zones::get_zones();
            $zone_exists = false;

            foreach ($zones as $zone) {
                if ($zone['zone_name'] === $zone_name) {
                    $zone_exists = true;
                    break;
                }
            }

            if (!$zone_exists) {
                $new_zone = new WC_Shipping_Zone();
                $new_zone->set_zone_name($zone_name);

                foreach ($zone_regions as $state_name) {
                    $state_query = new WP_Query([
                        'post_type'      => 'tsrfw_state',
                        'title'          => $state_name,
                        'posts_per_page' => 1,
                        'post_status'    => 'publish',
                    ]);

                    $state_post = $state_query->have_posts() ? $state_query->posts[0] : null;

                    if ($state_post) {
                        $country_code = get_post_meta($state_post->ID, 'country_code', true);
                        if (!$country_code) $country_code = 'BD';

                        $state_code = get_post_meta($state_post->ID, 'state_code', true); // <-- use real state_code
                        $state_code = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $state_code));
                        // Save in WooCommerce format
                        $new_zone->add_location("{$country_code}:{$state_code}", 'state');
                    }
                }

                $new_zone->save();
            }
        }
    }

    // Enqueue scripts
    public function enqueue_scripts($hook) {
        if ($hook !== 'woocommerce_page_wc-settings') return;

        wp_enqueue_script('jquery');
        wp_enqueue_script('select2');
        wp_enqueue_style('select2');

        wp_enqueue_script('tsrfw-shipping-js', plugin_dir_url(__FILE__) . '../js/custom-shipping-zones.js', ['jquery'], TSRFW_Shipping_Rules_For_Woo::TSRFW_VERSION, true);

        wp_localize_script('tsrfw-shipping-js', 'tsrfw_shipping', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('tsrfw_get_zone_regions'),
        ]);
    }

    // AJAX: get regions for selected zone
    public function ajax_get_zone_regions() {
        // ✅ Nonce verify (AJAX security)
        if (
            ! isset($_POST['_wpnonce']) ||
            ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
                'tsrfw_get_zone_regions'
            )
        ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'turbo-shipping-rules-for-woocommerce' ) ] );
        }

        // ✅ Safe read zone_id
        if ( ! isset( $_POST['zone_id'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Missing zone ID.', 'turbo-shipping-rules-for-woocommerce' ) ] );
        }

        $zone_id = sanitize_text_field( wp_unslash( $_POST['zone_id'] ) );

        $zones = $this->get_static_zones_from_states();

        if ( isset( $zones[ $zone_id ] ) ) {
            $regions = $zones[ $zone_id ]['regions'];
            wp_send_json_success( $regions );
        }

        wp_send_json_error( [ 'message' => __( 'Invalid zone.', 'turbo-shipping-rules-for-woocommerce' ) ] );
    }

    // Generate dynamic zones from custom states CPT
    public function get_static_zones_from_states() {
        $zones = [];
        $query = new WP_Query([
            'post_type'      => 'tsrfw_state',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ]);

        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $zone_name = get_post_meta($post->ID, 'custom_zone', true);
                $state_name = get_the_title($post->ID);

                if (!$zone_name) $zone_name = 'Other Zone';

                if (!isset($zones[$zone_name])) {
                    $zones[$zone_name] = [
                        'name' => $zone_name,
                        'regions' => [],
                    ];
                }

                $zones[$zone_name]['regions'][] = $state_name;
            }
        }

        return $zones;
    }
}

new TSRFW_Admin_Shipping_Zones();
