<?php
if (!defined('ABSPATH')) exit;
$message = '';
//Added: Restrict page access to admin users only
if ( ! current_user_can('manage_options') ) {
    wp_die( esc_html__('You do not have permission to access this page.', 'turbo-shipping-rules-for-woocommerce') );
}

if ( isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_wpnonce']) && wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'tsrfw_add_state_action' ) ) {

    //Added: Double-layer permission check before processing POST request
    if ( ! current_user_can('manage_options') ) {
        wp_die( esc_html__('You do not have permission to add new states.', 'turbo-shipping-rules-for-woocommerce') );
    }

    $name        = isset($_POST['state_name']) ? sanitize_text_field( wp_unslash( $_POST['state_name'] ) ) : '';
    $code        = !empty($_POST['state_code']) ? sanitize_key( wp_unslash( $_POST['state_code'] ) ) : sanitize_key( $name );
    $code        = strtoupper( $code );
    $country     = isset($_POST['country_code']) ? strtoupper( sanitize_key( wp_unslash( $_POST['country_code'] ) ) ) : 'BD';
    $custom_zone = isset($_POST['custom_zone']) ? sanitize_text_field( wp_unslash($_POST['custom_zone']) ) : '';

    // Check for duplicate: same state code + country
    $existing = new WP_Query([
        'post_type'      => 'tsrfw_state',
        'post_status'    => 'publish',
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'   => 'state_code',
                'value' => $code,
            ],
            [
                'key'   => 'country_code',
                'value' => $country,
            ],
        ],
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ]);

    if ($existing->have_posts()) {
        $message = '<div class="notice notice-error is-dismissible"><p>State with this code already exists in this country.</p></div>';
    } else {
        $post_id = wp_insert_post([
            'post_type'   => 'tsrfw_state',
            'post_title'  => $name,
            'post_status' => 'publish',
        ]);

        update_post_meta($post_id, 'state_code', $code);
        update_post_meta($post_id, 'state_name', $name);
        update_post_meta($post_id, 'country_code', $country);
        update_post_meta($post_id, 'custom_zone', $custom_zone); // <-- new field

        $redirect_url = add_query_arg([
            'page'     => 'tsrfw-states',
            'added'    => 1,
            '_wpnonce' => wp_create_nonce('tsrfw_notice_nonce'),
        ], admin_url('admin.php'));

        wp_redirect( $redirect_url );
        exit;
    }
}

// Get WooCommerce country list
$wc_countries = new WC_Countries();
$countries    = $wc_countries->get_countries();
?>

<div class="wrap">
    <h1>Add New Shipping State</h1>
    <?php echo esc_html( $message ); ?>
    <form method="post">
        <?php wp_nonce_field( 'tsrfw_add_state_action' ); ?>
        <table class="form-table">
            
            <tr>
                <th><label for="country_code">Country</label></th>
                <td>
                    <select name="country_code" id="country_code" required>
                        <?php foreach ($countries as $code => $label): ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($code, 'BD'); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th><label for="state_name">State Name</label></th>
                <td><input type="text" name="state_name" id="state_name" required></td>
            </tr>

            <tr>
                <th><label for="state_code">State Code (Optional)</label></th>
                <td>
                    <input type="text" name="state_code" id="state_code">
                    <p class="description">
                        If left blank, the code will be generated automatically from the state name. 
                        Do not use special characters or spaces when entering a custom state code.
                    </p>
                </td>
            </tr>

            <tr>
                <th><label for="custom_zone">Shipping Zone Group Name (Custom Zone)</label></th>
                <td>
                    <input type="text" name="custom_zone" id="custom_zone">
                    <p class="description">Optional: Enter a custom shipping zone group name.</p>
                </td>
            </tr>

        </table>
        <?php submit_button('Add State'); ?>
    </form>
</div>
