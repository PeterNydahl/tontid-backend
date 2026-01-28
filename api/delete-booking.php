<?php
add_action('rest_api_init', function(){
    register_rest_route(
        'tontid/v2', 
        '/delete-booking', [
            'methods' => 'POST',
            'callback' => 'delete_booking',            
            'permission_callback' => '__return_true'
        ]
    );
});

function delete_booking($request){
    global $wpdb;
    
    // KORRIGERAT: Inkludera understreck för tabellnamnet
    $table_name = $wpdb->prefix . 'tontid_bookings'; 
    
    $booking_id = $request->get_param('booking_id');

    // 1. Kontrollera om ID skickades
    if(empty($booking_id) || !is_numeric($booking_id)){
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Ogiltigt bokinings-id skickades.'
        ], 400); // 400 Bad Request
    }
    
    // KORRIGERAT: Bygg den korrekta DELETE-frågan i variabeln $sql
    $sql = $wpdb->prepare(
        "DELETE FROM $table_name WHERE booking_id = %d", 
        $booking_id
    );
    
    // KORRIGERAT: Exekvera variabeln $sql
    // $rows_deleted kommer att vara antalet rader raderade, 0, eller false.
    $rows_deleted = $wpdb->query($sql);
    
    // 2. Kontrollera resultatet av raderingen
    if ($rows_deleted === false) {
        // Databasfel
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Ett databasfel uppstod vid radering.',
            // Returnera $wpdb->last_error för felsökning!
            'wpdb_error' => $wpdb->last_error 
        ], 500); // 500 Internal Server Error
    } 
    
    if ($rows_deleted === 0) {
        // Raderingen lyckades, men ingen rad matchade ID:t
        return new WP_REST_Response([
            'status' => 'error',
            'message' => "Bokning med ID {$booking_id} hittades inte.",
            'rows_affected' => 0
        ], 404); // 404 Not Found
    }

    // --- Framgångsrikt resultat ($rows_deleted > 0) ---
    return new WP_REST_Response([
        'status' => 'success',
        'message' => 'Din bokningen har raderats.',
        'booking_id' => $booking_id
    ], 200); // 200 OK
}