<?php 

add_filter('rest_api_init', function(){
    register_rest_route( 'tontid/v1', '/get-all-bookings', [
        'methods' => 'GET',
        'callback' => 'get_all_bookings',
        'permission_callback' => '__return_true'
    ]);
});
 
function get_all_bookings(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'tontid_bookings';
    $all_bookings = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

    $selected_bookings = array_values(array_filter($all_bookings, fn($booking) 
    => $booking['room_id'] == 15));    

    return new WP_REST_Response(
        [
            'status' => 'success',
            'data' => $selected_bookings
        ], 200
    ); 
}


// <?php 

// add_action('rest_api_init', function(){
//     register_rest_route( 'tontid/v1', 'get-all-bookings', [
//         'methods' => 'GET',
//         'callback' => 'get_all_bookings',
//         'permission_callback' => '__return_true'
//     ]);
// });

// function get_all_bookings(){
//     global $wpdb;
//     $table_name = $wpdb->prefix . "tontid_bookings";
//     $all_bookings = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

//     if(!$all_bookings)
//         return new WP_REST_Response(
//             [
//                 'status' => 'error',
//                 'message' => 'Det finns inga bokningar i databasen!'
//             ], 404
//         );

//     $selected_bookings = array_values(array_filter($all_bookings, fn($b) 
//         => $b['room_id'] == 15 || $b['room_id'] == 108

//     ));

//     return new WP_REST_Response(
//         [
//             'status' => 'success',
//             'data' => $selected_bookings
//         ], 200
//     ); 
// }