<?php
if (!defined('ABSPATH')) exit;
add_action('admin_menu', function () {
    // Set menu_position to appear after WooCommerce (WooCommerce is position 55)
    add_menu_page(
        'Manage Shipping States',
        'Shipping States',
        'manage_options',
        'tsrfw-states',
        function () {
            include __DIR__ . '/state-list.php';
        },
        'dashicons-location-alt',
        56 // 👈 Appears right after WooCommerce
    );

    add_submenu_page('tsrfw-states', 'Add New State', 'Add New', 'manage_options', 'tsrfw-states-add', function () {
        include __DIR__ . '/state-add-form.php';
    });

    add_submenu_page('tsrfw-hidden', 'Edit State', 'Edit', 'manage_options', 'tsrfw-states-edit', function () {
        include __DIR__ . '/state-edit-form.php';
    });

    add_submenu_page( 'tsrfw-states', 'Bulk Upload States', 'Bulk Upload', 'manage_options', 'tsrfw-states-bulk-upload', function () {
        include __DIR__ . '/state-bulk-upload.php';
    });

    // Submenu: Custom Shipping Zones → Direct redirect
    add_submenu_page( 'tsrfw-states', 'Custom Shipping Zones', 'Custom Shipping Zones', 'manage_options', 'tsrfw-custom-shipping-zones', function () {
        wp_redirect(admin_url('admin.php?page=wc-settings&tab=shipping&section=tsrfw_custom_zones'));
         exit;
    });
    
});


