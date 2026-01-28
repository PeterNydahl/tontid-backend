<?php
add_action('rest_api_init', function() {
    register_rest_route('tontid/v1', '/get-rooms', [
        'methods' => 'GET',
        'callback' => 'get_rooms', 
        'permission_callback' => '__return_true'
    ]);
});

function get_rooms($request){
    // Höj max execution time till 5 minuter (300 sekunder)
    set_time_limit(300);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'tontid_music_rooms';
    $query = "SELECT * FROM $table_name";
    
    $rooms = $wpdb->get_results($query, ARRAY_A);
    
    if(empty($rooms)){
        return new WP_REST_Response([
            'status' => 'error',
            'message' => "Det finns inte några rum!"
        ], 400);
    };

    return new WP_REST_Response([
        'status' => 'success',
        'data' => $rooms
    ], 200);
}