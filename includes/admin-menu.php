<?php
if (!defined('ABSPATH')) exit;
add_action('admin_menu', function () {
    // Set menu_position to appear after WooCommerce (WooCommerce is position 55)
    add_menu_page(
        'Manage Shipping States',
        'Shipping States',
        'manage_options',
        'csmfw-states',
        function () {
            include __DIR__ . '/state-list.php';
        },
        'dashicons-location-alt',
        56 // 👈 Appears right after WooCommerce
    );

    add_submenu_page('csmfw-states', 'Add New State', 'Add New', 'manage_options', 'csmfw-states-add', function () {
        include __DIR__ . '/state-add-form.php';
    });

    add_submenu_page('csmfw-hidden', 'Edit State', 'Edit', 'manage_options', 'csmfw-states-edit', function () {
        include __DIR__ . '/state-edit-form.php';
    });

    add_submenu_page( 'csmfw-states', 'Bulk Upload States', 'Bulk Upload', 'manage_options', 'csmfw-states-bulk-upload', function () {
        include __DIR__ . '/state-bulk-upload.php';
    });

    // Submenu: Custom Shipping Zones → Direct redirect
    add_submenu_page( 'csmfw-states', 'Custom Shipping Zones', 'Custom Shipping Zones', 'manage_options', 'csmfw-custom-shipping-zones', function () {
        wp_redirect(admin_url('admin.php?page=wc-settings&tab=shipping&section=csmfw_custom_zones'));
         exit;
    });
    
});


