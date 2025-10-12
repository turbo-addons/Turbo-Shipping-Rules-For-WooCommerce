<?php
if (!defined('ABSPATH')) exit;

/**
 * Register method.
 */
add_filter('woocommerce_shipping_methods', function ($methods) {
    $methods['tsrfw_category_weight'] = 'TSRFW_Category_Weight_Rate';
    return $methods;
});

add_action('woocommerce_shipping_init', function () {

    if (class_exists('TSRFW_Category_Weight_Rate')) return;

    class TSRFW_Category_Weight_Rate extends WC_Shipping_Method {

        private static $instance = null;

        public function __construct($instance_id = 0) {
            $this->id = 'tsrfw_category_weight';
            $this->instance_id = absint($instance_id);
            $this->method_title = __('Category Weight Rate', 'turbo-shipping-rules-for-woocommerce');
            $this->method_description = __('Charge per kg for items that belong to selected categories. Cost = ceil(kg) × (base + per_kg).', 'turbo-shipping-rules-for-woocommerce');
            $this->supports = ['shipping-zones', 'instance-settings', 'instance-settings-modal'];
            $this->title = __('Weight-based Shipping', 'turbo-shipping-rules-for-woocommerce');
            $this->tax_status = 'none';
            $this->init();
        }

        public static function get_instance($instance_id = 0) {
            if (null === self::$instance) {
                self::$instance = new self($instance_id);
            }
            return self::$instance;
        }

        public function init() {
            $this->instance_form_fields = $this->get_instance_fields();
            $this->init_settings();
            $this->title = $this->get_option('title', $this->title);
            add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
        }

        protected function get_instance_fields() {
            $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
            $options = [];
            if (!is_wp_error($terms)) {
                foreach ($terms as $t) {
                    $options[(string)$t->term_id] = sanitize_text_field($t->name);
                }
            }

            return [
                'title' => [
                    'title' => __('Method title', 'turbo-shipping-rules-for-woocommerce'),
                    'type' => 'text',
                    'description' => __('Shown to customers at checkout.', 'turbo-shipping-rules-for-woocommerce'),
                    'default' => __('Weight-based Shipping', 'turbo-shipping-rules-for-woocommerce'),
                    'desc_tip' => true,
                ],
                'tsrfw_allowed_product_cats' => [
                    'title' => __('Product categories (applies to)', 'turbo-shipping-rules-for-woocommerce'),
                    'type' => 'multiselect',
                    'description' => __('This method appears and charges only for cart items in these categories.', 'turbo-shipping-rules-for-woocommerce'),
                    'options' => $options,
                    'default' => [],
                    'class' => 'wc-enhanced-select',
                    'desc_tip' => true,
                ],
                'base_cost' => [
                    'title' => __('Base price (per kg)', 'turbo-shipping-rules-for-woocommerce'),
                    'type' => 'price',
                    'description' => __('Fixed amount per kg (e.g., 10).', 'turbo-shipping-rules-for-woocommerce'),
                    'default' => '10',
                    'desc_tip' => true,
                ],
                'per_kg' => [
                    'title' => __('Additional per kg', 'turbo-shipping-rules-for-woocommerce'),
                    'type' => 'price',
                    'description' => __('Extra amount per kg (e.g., 5).', 'turbo-shipping-rules-for-woocommerce'),
                    'default' => '5',
                    'desc_tip' => true,
                ],
            ];
        }

        public function is_available($package) {
            $allowed_ids = array_map('absint', (array)$this->get_option('tsrfw_allowed_product_cats', []));
            if (empty($allowed_ids)) return false;

            foreach ((array)$package['contents'] as $item) {
                if (empty($item['data']) || !($item['data'] instanceof WC_Product)) continue;
                $pid = $item['data']->get_id();
                $cats = wc_get_product_term_ids($pid, 'product_cat');
                if (array_intersect($allowed_ids, $cats)) return true;
            }
            return false;
        }

        public function calculate_shipping($package = []) {
            $allowed_ids = array_map('absint', (array)$this->get_option('tsrfw_allowed_product_cats', []));
            $base = floatval($this->get_option('base_cost', 0));
            $perkg = floatval($this->get_option('per_kg', 0));

            $total_weight = 0;
            foreach ((array)$package['contents'] as $item) {
                if (empty($item['data']) || !($item['data'] instanceof WC_Product)) continue;
                $pid = $item['data']->get_id();
                $cats = wc_get_product_term_ids($pid, 'product_cat');
                if (!array_intersect($allowed_ids, $cats)) continue;

                $qty = isset($item['quantity']) ? (int)$item['quantity'] : 1;
                $total_weight += ((float)$item['data']->get_weight() * $qty);
            }

            if ($total_weight <= 0) return;

            $kg = $this->to_kg($total_weight, get_option('woocommerce_weight_unit', 'kg'));
            $kg = ceil($kg);

            $cost = $base + ($kg * $perkg);

            $this->add_rate([
                'id' => $this->id . ':' . $this->instance_id,
                'label' => $this->title,
                'cost' => $cost,
            ]);
        }

        protected function to_kg($value, $unit) {
            switch (strtolower($unit)) {
                case 'kg': return (float)$value;
                case 'g':  return (float)$value / 1000;
                case 'lbs':
                case 'lb': return (float)$value * 0.45359237;
                case 'oz': return (float)$value * 0.028349523125;
                default: return (float)$value;
            }
        }
    }
});

// Select2 load for shipping settings
add_action('admin_enqueue_scripts', function($hook) {

    $screen = get_current_screen();
    if ( $screen && $screen->id === 'woocommerce_page_wc-settings' ) {

        // Enqueue built-in Select2
        wp_enqueue_script('select2');
        wp_enqueue_style('select2');

        // ✅ Register your admin script
        wp_register_script(
            'tsrfw-admin',
            plugin_dir_url(__FILE__) . '../js/tsrfw-admin.js',
            ['jquery', 'select2'],
            TSRFW_Shipping_Rules_For_Woo::TSRFW_VERSION,
            true
        );

        // Add defer (WordPress 6.3+ support)
        wp_script_add_data('tsrfw-admin', 'strategy', 'defer');

        // Pass localized data
        wp_localize_script('tsrfw-admin', 'tsrfwAdmin', [
            'placeholder' => esc_html__( 'Select categories', 'turbo-shipping-rules-for-woocommerce' ),
        ]);

        // ✅ Enqueue the registered script
        wp_enqueue_script('tsrfw-admin');
    }
});


