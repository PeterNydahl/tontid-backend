<?php
add_action('rest_api_init', function() {
    register_rest_route('tontid/v2', '/get-users-bookings', [
        'methods' => 'POST',
        'callback' => 'get_users_bookings',
        'permission_callback' => '__return_true',
    ]);
});

function get_users_bookings($request){

    date_default_timezone_set('Europe/Stockholm');

    global $wpdb;
    $params = $request->get_json_params();

    $user_email = $params['user_email'] ?? null;
    
    if (!$user_email) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Användarens e-post måste anges.'
        ], 400);
    }

    //hämta användaren från databas
    $table_name_users = $wpdb->prefix . 'users';
    $query_user = $wpdb->prepare("SELECT * FROM $table_name_users WHERE user_email = %s", $user_email);
    $user_wp = $wpdb->get_results($query_user);
    $wp_user_id = $user_wp[0]->ID;
 
    if (empty($user_wp)) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Hittades ingen användare med angiven e-postadress.'
        ], 404);
    }
    
    $user = $wpdb->get_row($query_user, ARRAY_A); // Returnerar direkt en assoc array
    $user_obj = get_userdata($user['ID']); // Blir ett WP_User-objekt

    //hämta bokningar kopplade till user
    $table_name_bookings = $wpdb->prefix . 'tontid_bookings';
    $query = $wpdb->prepare("SELECT * FROM $table_name_bookings WHERE user_id = %s", $wp_user_id);
    $results_user_bookings = $wpdb->get_results($query, ARRAY_A);

    //filtrera bort gamla bokningar
    $now = time();
    $user_active_bookings = []; 
    foreach($results_user_bookings as $users_booking){
        $booking_end = strtotime($users_booking['booking_end']);
        if($booking_end > $now){
            $user_active_bookings [] = $users_booking;
        }
    }

    return new WP_REST_Response([
        'status' => 'success',
        'data' => $user_active_bookings,
    ], 200);
}

