<?php
if (!defined('ABSPATH')) exit;
if (
    !isset($_GET['id'], $_GET['_wpnonce']) ||
    !wp_verify_nonce( sanitize_key($_GET['_wpnonce']), 'csmfw_edit_state_action' ) ||
    !($post_id = absint($_GET['id']))
) {
    wp_die('Invalid request.');
}

$post = get_post($post_id);
if (!$post || $post->post_type !== 'csmfw_state') {
    wp_die('State not found.');
}

$message = '';

if ( isset($_SERVER['REQUEST_METHOD'], $_POST['state_name']) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $name        = sanitize_text_field( wp_unslash( $_POST['state_name'] ) );
    $code        = !empty($_POST['state_code']) ? sanitize_key($_POST['state_code']) : sanitize_key($name);
    $code        = strtoupper($code);
    $country     = isset($_POST['country_code']) ? strtoupper(sanitize_key($_POST['country_code'])) : 'BD';
    $custom_zone = isset($_POST['custom_zone']) ? sanitize_text_field( wp_unslash($_POST['custom_zone']) ) : '';

    // Check if another state exists with same code in the same country
    $existing = new WP_Query([
        'post_type'      => 'csmfw_state',
        'post_status'    => 'publish',
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Acceptable for small dataset of custom states.
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
        // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- We exclude one ID only, safe for plugin context.
        'post__not_in'   => [$post_id],
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ]);

    if ($existing->have_posts()) {
        $message = '<div class="notice notice-error is-dismissible"><p>Another state with this code already exists in this country.</p></div>';
    } else {
        wp_update_post([
            'ID'         => $post_id,
            'post_title' => $name,
        ]);

        update_post_meta($post_id, 'state_code', $code);
        update_post_meta($post_id, 'state_name', $name);
        update_post_meta($post_id, 'country_code', $country);
        update_post_meta($post_id, 'custom_zone', $custom_zone); // <-- preserve comments, added custom zone

        $redirect_url = add_query_arg([
            'page'      => 'csmfw-states',
            'updated'   => 1,
            '_wpnonce'  => wp_create_nonce('csmfw_notice_nonce'),
        ], admin_url('admin.php'));

        wp_redirect( $redirect_url );
        exit;
    }
}

$state_name   = $post->post_title;
$state_code   = get_post_meta($post_id, 'state_code', true);
$country_code = get_post_meta($post_id, 'country_code', true) ?: 'BD';
$custom_zone  = get_post_meta($post_id, 'custom_zone', true);

$wc_countries = new WC_Countries();
$countries    = $wc_countries->get_countries();
?>

<div class="wrap">
    <h1>Edit Shipping State</h1>
    <?php echo esc_html( $message ); ?>
    <form method="post">
        <?php wp_nonce_field( 'csmfw_update_state_action' ); ?>
        <table class="form-table">
            
            <tr>
                <th><label for="country_code">Country</label></th>
                <td>
                    <select name="country_code" id="country_code" required>
                        <?php foreach ($countries as $code => $label): ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($code, $country_code); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        
            <tr>
                <th><label for="state_name">State Name</label></th>
                <td><input type="text" name="state_name" id="state_name" value="<?php echo esc_attr($state_name); ?>" required></td>
            </tr>

            <tr>
                <th><label for="state_code">State Code</label></th>
                <td><input type="text" name="state_code" id="state_code" value="<?php echo esc_attr($state_code); ?>"></td>
            </tr>

            <tr>
                <th><label for="custom_zone">Shipping Zone Group Name (Custom Zone)</label></th>
                <td>
                    <input type="text" name="custom_zone" id="custom_zone" value="<?php echo esc_attr($custom_zone); ?>">
                    <p class="description">Optional: Enter a custom shipping zone group name.</p>
                </td>
            </tr>

        </table>
        <?php submit_button('Update State'); ?>
    </form>
</div>
