<?php

add_action('rest_api_init', function(){
    register_rest_route( 'tontid/v1', 'get-specified-rooms', [
        'methods' => 'POST',
        'callback' => 'get_specified_rooms',
        'permission_callback' => '__return_true'
    ]);
});

function get_specified_rooms(WP_REST_Request $request){
    
    $room_id = $request['roomId'];
    if(!$room_id)
        return new WP_REST_Response(
        [
            'status' => 'förfrågan misslyckades'
        ], 400);

    global $wpdb;
    $table_name = $wpdb->prefix . 'tontid_music_rooms';
    $query = $wpdb->prepare(
        "SELECT * FROM $table_name WHERE room_id = %d", 
        $room_id
    );
    $results = $wpdb->get_results($query);

    return new WP_REST_Response(
        [
            'status' => 'success',
            'data' => $results
        ], 200
    );

};
