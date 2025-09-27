<?php
if (!defined('ABSPATH')) exit;

class CSMFW_Admin_Shipping_Zones {

    public function __construct() {
        add_filter('woocommerce_get_sections_shipping', [$this, 'add_shipping_section']);
        add_action('woocommerce_settings_shipping', [$this, 'shipping_zone_settings_page']);
        add_action('woocommerce_update_options_shipping', [$this, 'save_shipping_zones']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_csmfw_get_zone_regions', [$this, 'ajax_get_zone_regions']);
    }

    // Add shipping section
    public function add_shipping_section($sections) {
        $sections['csmfw_custom_zones'] = __('Custom Shipping Zones', 'custom-shipping-manager-for-woocommerce');
        return $sections;
    }

    // Admin settings page
    public function shipping_zone_settings_page() {
        if (!isset($_GET['section']) || $_GET['section'] !== 'csmfw_custom_zones') return;

        $zones = $this->get_static_zones_from_states();

        $zone_options = [];
        foreach ($zones as $zone_key => $zone) {
            $zone_options[$zone_key] = $zone['name'];
        }

        $settings = [
            [
                'title' => __('Custom Shipping Zones', 'custom-shipping-manager-for-woocommerce'),
                'type'  => 'title',
                'id'    => 'csmfw_custom_zones_title',
            ],
            [
                'title'       => __('Shipping Zone Name', 'custom-shipping-manager-for-woocommerce'),
                'type'        => 'select',
                'id'          => 'csmfw_shipping_zone_name',
                'options'     => ['' => '-- Select Your Zone --'] + $zone_options,
                'desc_tip'    => true,
                'description' => __('Select a Shipping Zone.', 'custom-shipping-manager-for-woocommerce'),
            ],
            [
                'title'       => __('Zone Regions', 'custom-shipping-manager-for-woocommerce'),
                'type'        => 'multiselect',
                'id'          => 'csmfw_shipping_zone_regions',
                'options'     => [], // populated via JS
                'desc_tip'    => true,
                'description' => __('Select regions for this zone.', 'custom-shipping-manager-for-woocommerce'),
                'class'       => 'wc-enhanced-select',
            ],
            [
                'type' => 'sectionend',
                'id'   => 'csmfw_custom_zones_section_end',
            ],
        ];

        woocommerce_admin_fields($settings);
    }

    // Save zones
    public function save_shipping_zones() {
        if (!isset($_GET['section']) || $_GET['section'] !== 'csmfw_custom_zones') return;

        if (isset($_POST['csmfw_shipping_zone_name']) && isset($_POST['csmfw_shipping_zone_regions'])) {
            $zone_name = sanitize_text_field($_POST['csmfw_shipping_zone_name']);
            $zone_regions = array_map('sanitize_text_field', $_POST['csmfw_shipping_zone_regions']);
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
                    $state_post = get_page_by_title($state_name, OBJECT, 'csmfw_state');

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

        wp_enqueue_script('csmfw-shipping-js', plugin_dir_url(__FILE__) . '../js/custom-shipping-zones.js', ['jquery'], false, true);

        wp_localize_script('csmfw-shipping-js', 'csmfw_shipping', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }

    // AJAX: get regions for selected zone
    public function ajax_get_zone_regions() {
        if (!isset($_POST['zone_id'])) wp_send_json_error();

        $zone_id = sanitize_text_field($_POST['zone_id']);
        $zones = $this->get_static_zones_from_states();

        if (isset($zones[$zone_id])) {
            $regions = $zones[$zone_id]['regions'];
            wp_send_json_success($regions);
        }

        wp_send_json_error();
    }

    // Generate dynamic zones from custom states CPT
    public function get_static_zones_from_states() {
        $zones = [];
        $query = new WP_Query([
            'post_type'      => 'csmfw_state',
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

new CSMFW_Admin_Shipping_Zones();
