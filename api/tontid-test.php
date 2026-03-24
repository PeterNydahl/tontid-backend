<?php

if(!defined('ABSPATH')) exit;

add_action('rest_api_init', function(){
    register_rest_route( 'tontid/v1', 'tontid-test', [
        'methods' => 'POST',
        'callback' => 'tontid_test',
        'permission_callback' => '__return_true'
    ]);
});

function tontid_test(){
    return new WP_REST_Response([
        'status' => 'success'
    ], 200);
}
