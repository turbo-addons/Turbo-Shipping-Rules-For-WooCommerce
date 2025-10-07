<?php
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/class-tsrfw-states-table.php'; // ✅ updated class file name

// ✅ Existing notice section (already safe)
if ( isset($_GET['added'], $_GET['_wpnonce']) && wp_verify_nonce( sanitize_key($_GET['_wpnonce']), 'tsrfw_notice_nonce' ) ) {
    echo '<div class="notice notice-success is-dismissible"><p>State added successfully.</p></div>';
}
if ( isset($_GET['updated'], $_GET['_wpnonce']) && wp_verify_nonce( sanitize_key($_GET['_wpnonce']), 'tsrfw_notice_nonce' ) ) {
    echo '<div class="notice notice-success is-dismissible"><p>State updated successfully.</p></div>';
}

/**
 * ==========================================================
 * BULK ACTION SECTION (POST Request)
 * ==========================================================
 */
if ( isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {

    //Added: Nonce verify for bulk actions
    if ( ! isset($_POST['_wpnonce']) || ! wp_verify_nonce( sanitize_key($_POST['_wpnonce']), 'tsrfw_bulk_action' ) ) {
        wp_die( esc_html__('Security check failed.', 'turbo-shipping-rules-for-woocommerce') );
    }

    //Added: Permission check
    if ( ! current_user_can('delete_posts') ) {
        wp_die( esc_html__('Insufficient permissions.', 'turbo-shipping-rules-for-woocommerce') );
    }

    //Sanitize action value
    $action = isset($_POST['action']) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';

    if ( ! empty($_POST['state']) && is_array($_POST['state']) ) {
        $raw_state_ids = wp_unslash($_POST['state']);
        $state_ids = array_map('absint', (array)$raw_state_ids);

        foreach ( $state_ids as $id ) {
            if ($action === 'delete') {
                wp_trash_post($id);
            } elseif ($action === 'delete_permanently') {
                wp_delete_post($id, true);
            } elseif ($action === 'restore') {
                //Restore post to publish status
                $post = wp_untrash_post($id);
                if ($post) {
                    wp_update_post([
                        'ID' => $id,
                        'post_status' => 'publish',
                    ]);
                }
            }
        }

        echo '<div class="notice notice-success is-dismissible"><p>Bulk action completed.</p></div>';
    }
}

/**
 * ==========================================================
 * SINGLE ITEM ACTIONS (GET Requests)
 * Each action (trash, restore, delete) now uses Nonce + Capability
 * ==========================================================
 */

//Added: Helper function for verifying nonce + running callback
function tsrfw_verify_and_run($key, $callback, $cap = 'delete_posts') {
    if ( isset($_GET[$key], $_GET['_wpnonce']) ) {
        if ( ! wp_verify_nonce( sanitize_key($_GET['_wpnonce']), 'tsrfw_' . $key . '_' . absint($_GET[$key]) ) ) {
            wp_die( esc_html__('Invalid nonce.', 'turbo-shipping-rules-for-woocommerce') );
        }
        if ( current_user_can($cap) ) {
            call_user_func($callback, absint($_GET[$key]) );
        } else {
            wp_die( esc_html__('Insufficient permissions.', 'turbo-shipping-rules-for-woocommerce') );
        }
    }
}

//Added: Trash action secured
tsrfw_verify_and_run('trash', function($id){
    wp_trash_post($id);
    echo '<div class="notice notice-success is-dismissible"><p>State moved to Trash.</p></div>';
});

//Added: Restore action secured
tsrfw_verify_and_run('restore', function($id){
    $post = wp_untrash_post($id);
    if ($post) {
        wp_update_post([
            'ID' => $id,
            'post_status' => 'publish',
        ]);
    }
    echo '<div class="notice notice-success is-dismissible"><p>State restored.</p></div>';
});

//Added: Delete permanently secured
tsrfw_verify_and_run('delete', function($id){
    wp_delete_post($id, true);
    echo '<div class="notice notice-success is-dismissible"><p>State permanently deleted.</p></div>';
});

/**
 * ==========================================================
 * TABLE DISPLAY SECTION (SAFE)
 * ==========================================================
 */
$tsrfw_table = new TSRFW_States_List_Table(); //updated class name
$tsrfw_table->prepare_items();
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Shipping States</h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=tsrfw-states-add' ) ); ?>" class="page-title-action">Add New</a>

    <form method="get">
        <input type="hidden" name="page" value="tsrfw-states" />
        <?php $tsrfw_table->views(); ?>
        <?php $tsrfw_table->search_box('Search States', 'tsrfw_state'); ?>
    </form>

    <form method="post">
        <?php
        //Added: Nonce field for bulk actions
        wp_nonce_field('tsrfw_bulk_action');
        $tsrfw_table->display();
        ?>
    </form>
</div>
