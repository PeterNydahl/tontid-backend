<?php

add_action('rest_api_init', function(){
    register_rest_route( 
        'tontid/v1', 
        '/check-if-user-made-booking', 
        [
            'methods' => 'POST',
            'callback' => 'check_if_user_made_booking',
            'permission_callback' => '__return_true'
        ]
    );
});

function check_if_user_made_booking($request){
global $wpdb;
    $params = $request->get_json_params();

    //hämta användarens id från databasen för att kunna jämföra med bokningens klumn user_id
    $user_email = $params['user_email'] ?? null;
    
    if (!$user_email) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Användarens e-post måste anges.'
        ], 400);
    }
    
    $table_name_users = $wpdb->prefix . 'users';
    $query_user = $wpdb->prepare("SELECT * FROM $table_name_users WHERE user_email = %s", $user_email);
    $user_wp = $wpdb->get_results($query_user);
 
    if (empty($user_wp)) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Hittades ingen användare med angiven e-postadress.'
        ], 404);
    }

    $wp_user_id = $user_wp[0]->ID;    


    return new WP_REST_Response([
        'status' => 'success',
        'user_id' => $wp_user_id
    ],200);
}