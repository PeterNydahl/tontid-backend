<?php
add_action('rest_api_init', function() {
    register_rest_route('tontid/v1', '/create-booking', [
        'methods' => 'POST',
        'callback' => 'create_booking',
        'permission_callback' => '__return_true',
    ]);
});

function create_booking($request) {
    // så att vi slipper tänka på vinter/sommartid
    date_default_timezone_set('Europe/Stockholm');
    global $wpdb;

    //hämta användarens gmail från frontend. Denna samt userid (hämtas senare från backend) läggs till i
    //bokningen som på så vis blir kopplad till en specifik användare
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
 
    if (empty($user_wp)) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Hittades ingen användare med angiven e-postadress.'
        ], 404);
    }

    $user = $wpdb->get_row($query_user, ARRAY_A); // Returnerar direkt en assoc array
    $user_obj = get_userdata($user['ID']); // Blir ett WP_User-objekt
    $roles = $user_obj->roles; // Blir en array med roller
    $user_role = $roles[0];

    //Att sätta in i table längre fram i koden
    $wp_user_id = $user_wp[0]->ID;
    
    $table_name_bookings = $wpdb->prefix . 'tontid_bookings';

    //hämta user input om bokningen från frontend
    $room_id = $params['room_id'] ?? null;
    $date = $params['date'] ?? null;          // format: YYYY-MM-DD
    $start = $params['start'] ?? null;        // format: HH:mm
    $end = $params['end'] ?? null;            // format: HH:mm
    $lesson = $params['lesson'] ?? '';

    // Validera input
    if (!$room_id || !$date || !$start || !$end) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => "Alla fält måste fyllas i: $room_id, $date, $start, $end."
        ], 400);
    }

    $booking_start = new DateTime("$date $start");
    $booking_end = new DateTime("$date $end");
    $booking_duration = $booking_end->getTimestamp() - $booking_start->getTimestamp();
    $present_datetime = new DateTime();

    //Hämta kateogorin på rummet som bokningen är kopplat till (för att se om det är gg sal och se till att ej elever kan boka)    
    //----------------------------------------------
    $table_name_room = $wpdb->prefix . 'tontid_music_rooms';
    $sql_room = $wpdb->prepare("SELECT * FROM $table_name_room WHERE room_id = %s", $room_id);
    $results_room = $wpdb->get_row($sql_room, ARRAY_A);
    $room_cat = $results_room['room_category'];

    //----------------------------------------------


    // *************************************************************
    //                        Allmäna villkor
    // *************************************************************

    //En bokning måste vara minst 5 minuter
    if ($booking_duration <= 300) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'En bokning får inte vara under 5 minuter.'
        ], 403);
    }

    //Bokning får inte ske retroaktivt
    if($present_datetime > $booking_end) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Bokning får inte ske bakåt i tiden.'
        ], 400);
    }
    
    // Sluttid måste vara senare än starttid
    if ($booking_end <= $booking_start) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Sluttiden måste vara senare än starttiden.'
        ], 400);
    }


    // Kontrollera om bokningen krockar med existerande bokningar
    $existing_bookings = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name_bookings WHERE room_id = %s AND booking_start < %s AND booking_end > %s",
            $room_id,
            $booking_end->format('Y-m-d H:i:s'),
            $booking_start->format('Y-m-d H:i:s')
        )
    );

    if (!empty($existing_bookings)) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Tiden är redan bokad.'
        ], 409);
    }

    
    
    // *************************************************************
    //                    Villkor för alla elever
    // *************************************************************

    if(
        $user_role === 'elev'
        || $user_role === 'elev_piano'
        || $user_role === 'elev_trummor'
        || $user_role === 'elev_studiokorkort'
        || $user_role === 'elev_mupr_ak1'
        || $user_role === 'elev_mupr_ak2'
        || $user_role === 'elev_mupr_ak3'
        || $user_role === 'trumelev_studiokorkort'
        || $user_role === 'pianoelev_studiokorkort'
    ){
        // bokningen får inte gälla en gg sal
        if ($room_cat == 'gg'){
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Salen ej bokningsbar för elever.'
            ], 403);
        } 
    
        // En bokning får max vara 90 minuter
        if ($booking_duration > 5400){
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Som elev kan du max boka 90 minuter.'
            ], 403);
        }
    
        // Max en bokning åt gången
        $query_get_student_bookings = $wpdb->prepare("SELECT * FROM $table_name_bookings WHERE user_email = %s", $user_email);
        $result_student_bookings = $wpdb->get_results($query_get_student_bookings, ARRAY_A);
    
        foreach($result_student_bookings as $b){
            if ($present_datetime < new DateTime($b['booking_start'])){
                return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Du får inte göra mer än en bokning åt gången.'
                ], 403);
            }    
        };

        //En bokning får göras max 7 dygn framåt i tiden
        $present_timestamp = time();
        $time_diff = $booking_end->getTimestamp() - $present_timestamp;
        if ($time_diff > (86400 * 7)) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Du får boka max en vecka framåt.'
            ], 403);
        }
        //Bara pianoelever får boka 013
        $allowed_roles_013 = [
            'elev_piano',
            'pianoelev_studiokorkort'
        ];
        if($room_id === '013' && !in_array($user_role, $allowed_roles_013)){
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Denna sal får endast bokas av pianoelever'
            ], 403);
        }
        //Bara trumelever får boka 017 och 018
        $allowed_roles_drumrooms = [
            'elev_trummor',
            'trumelev_studiokorkort'
        ];
        if(($room_id === '017' || $room_id === '019') && $user_role !== 'elev_trummor'){
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Denna sal får endast bokas av trumelever'
                ], 403);
        }
        //Bara trumelever får boka 031 efter kl 17:00
        if(
            $room_id === '031' 
            && (new DateTime($start) > new DateTime('17:00') || new DateTime($end) > new DateTime('17:00'))
            && $user_role !== 'elev_trummor'
        ) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Efter kl 17 kan denna sal bara bokas av trumelever'
            ], 403);
        }
        
        //Studio D (118) - för elever med studiokörkort (inkl IV)
        $allowed_roles_118 = [
            'trumelev_studiokorkort',
            'pianoelev_studiokorkort',
            'elev_studiokorkort',
            'elev_mupr_ak1',
            'elev_mupr_ak2',
            'elev_mupr_ak3',
        ];
        if($room_id === '118' && !in_array($user_role, $allowed_roles_118, true)){
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Bara elever med studiokörkort får boka denna sal'
            ], 403);
        }

        //Studio C (116) - för Mupr åk 1, 2 & 3 
        $allowed_roles_116 = [
            'elev_mupr_ak1',
            'elev_mupr_ak2',
            'elev_mupr_ak3'
        ];
        if($room_id === '116' && !in_array($user_role, $allowed_roles_116, true)){
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Denna sal är bara bokningsbar för mupr åk 1-3'
            ], 403);
        }

        //Studio B (120) - för Mupr åk 1, 2 & 3 
        $allowed_roles_120 = [
            'elev_mupr_ak2',
            'elev_mupr_ak3'
        ];
        if($room_id === '120' && !in_array($user_role, $allowed_roles_120)){
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Denna sal är bara bokningsbar för mupr åk 2-3'
            ], 403);
        }

        //Studio A (122) - för Mupr åk 1, 2 & 3 
        $allowed_roles_122 = [
            'elev_mupr_ak3'
        ];
        if($room_id === '122' && !in_array($user_role, $allowed_roles_122)){
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Denna sal är bara bokningsbar för mupr åk 3'
            ], 403);
        }
    }
 



    // *************************************************************
    //           Villkor för elever med studiobehörighet
    // *************************************************************



    // Skapa bokning
    $inserted = $wpdb->insert(
        $table_name_bookings,
        [
            'user_email' => $user_email,
            'user_id' => $wp_user_id,
            'room_id' => $room_id,
            'booking_start' => $booking_start->format('Y-m-d H:i:s'),
            'booking_end' => $booking_end->format('Y-m-d H:i:s'),
            'lesson' => $lesson,
        ],
        ['%s','%d','%s','%s','%s','%s']
    );
    //felmeddenade om det inte gick att lägga till bokningen i db
    if (!$inserted) {
        error_log("DB Error: " . $wpdb->last_error);
        error_log("Last Query: " . $wpdb->last_query);
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Kunde inte skapa bokning.'
        ], 500); 
    }
    

    return new WP_REST_Response([
        'status' => 'success',
        'message' => 'Bokningen skapades!',
        'booking' => [
            'room_id' => $room_id,
            'booking_start' => $booking_start->format('Y-m-d H:i:s'),
            'booking_end' => $booking_end->format('Y-m-d H:i:s'),
            'lesson' => $lesson,
        ]
    ], 201);
}
