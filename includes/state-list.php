<?php
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/class-csmfw-states-table.php'; // ✅ updated class file name

if ( isset($_GET['added'], $_GET['_wpnonce']) && wp_verify_nonce( sanitize_key($_GET['_wpnonce']), 'csmfw_notice_nonce' )) { echo '<div class="notice notice-success is-dismissible"><p>State added successfully.</p></div>'; }
if ( isset($_GET['updated'], $_GET['_wpnonce']) && wp_verify_nonce( sanitize_key($_GET['_wpnonce']), 'csmfw_notice_nonce' )) { echo '<div class="notice notice-success is-dismissible"><p>State updated successfully.</p></div>'; }

if ( isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $action = isset($_POST['action']) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
    if (!empty($_POST['state']) && is_array($_POST['state'])) {
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via absint below
    $raw_state_ids = isset($_POST['state']) ? wp_unslash( $_POST['state'] ) : [];
    $state_ids = array_map( 'absint', (array) $raw_state_ids );

    foreach ( $state_ids as $state_id ) {
            $id = (int) $state_id;
            if ($action === 'delete') {
                wp_trash_post($id);
            } elseif ($action === 'delete_permanently') {
                wp_delete_post($id, true);
            } elseif ($action === 'restore') {
                // ✅ FIX: restore and set post_status to 'publish' so it appears in queries
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

if (isset($_GET['trash']) && current_user_can('delete_posts')) {
    wp_trash_post((int)$_GET['trash']);
    echo '<div class="notice notice-success is-dismissible"><p>State moved to Trash.</p></div>';
}
if (isset($_GET['restore']) && current_user_can('delete_posts')) {
    // ✅ FIX: restore and force post_status = publish
    $post = wp_untrash_post((int)$_GET['restore']);
    if ($post) {
        wp_update_post([
            'ID' => (int)$_GET['restore'],
            'post_status' => 'publish',
        ]);
    }
    echo '<div class="notice notice-success is-dismissible"><p>State restored.</p></div>';
}
if (isset($_GET['delete']) && current_user_can('delete_posts')) {
    wp_delete_post((int)$_GET['delete'], true);
    echo '<div class="notice notice-success is-dismissible"><p>State permanently deleted.</p></div>';
}

$csmfw_table = new CSMFW_States_List_Table(); // ✅ updated class name
$csmfw_table->prepare_items();
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Shipping States</h1>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=csmfw-states-add' ) ); ?>" class="page-title-action">Add New</a>
    <form method="get">
        <input type="hidden" name="page" value="csmfw-states" />
        <?php $csmfw_table->views(); ?>
        <?php $csmfw_table->search_box('Search States', 'csmfw_state'); ?>
    </form>

    <form method="post">
        <?php $csmfw_table->display(); ?>
    </form>
</div>
