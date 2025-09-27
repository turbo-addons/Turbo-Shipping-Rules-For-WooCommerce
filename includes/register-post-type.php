<?php
if (!defined('ABSPATH')) exit;
add_action('init', function () {
    register_post_type('csmfw_state', [
        'labels' => [
            'name'          => 'Shipping States',
            'singular_name' => 'Shipping State',
        ],
        'public'          => false,
        'show_ui'         => false, // UI is handled manually via custom pages
        'supports'        => ['title'],
        'capability_type' => 'post',
        'hierarchical'    => false,
        'menu_position'   => null,
    ]);
});
