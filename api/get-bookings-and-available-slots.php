<?php
//registrera en endpoint get-bookings-and-available-slots
add_action('rest_api_init', function () {
    register_rest_route(
        'tontid/v2',
        '/get-bookings-and-available-slots',
        [
            'methods' => 'GET',
            'callback' => 'get_bookings_and_available_slots',
            'permission_callback' => '__return_true'
        ]
    );
});

function get_bookings_and_available_slots($request)
{
    global $wpdb;

    //skapa databasobjektet och variabel för tabellnamn
    $table_name_bookings = $wpdb->prefix . 'tontid_bookings';

    //skapa variabler room_id, selected_week med värden från frontend POST-bodyn(room, week)
    //skapa variabel för nuvarande år
    $room_id = $request->get_param('room');
    $selected_week = $request->get_param('week');
    $year = date('Y');
    $timestamp_now = time();
    

    //kolla att room_id finns
    if (!$room_id) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Inget rum angivet.'
        ], 400);
    }

    // skapa en query som hämtar alla bokningar för rummet där room_id är samma som $room_id
    // exekvera queryn och spara i $bookings
    $query = $wpdb->prepare("SELECT * FROM $table_name_bookings WHERE room_id = %s", $room_id);
    $bookings = $wpdb->get_results($query);

    //kollar att vald vecka har ett giltigt värde
    if (!ctype_digit($selected_week) || $selected_week < 1 || $selected_week > 53) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Ogiltigt veckonummer.'
        ], 400);
    }

    // Sortera ut bokningar av rummet baserat på vald vecka
    // 1. Skapa ett nytt DateTime-objekt $date
    // $date = new DateTime();

    // // 2. Sätt datumet till måndagen i vald vecka och år
    // $date->setISODate($year, (int)$selected_week, 1);

    // // 3. Sätt tiden till 08:00
    // $date->setTime(8, 0);

    // // 4. Hämta Unix-timestamp och spara i $monday_first_hour
    // $monday_first_hour = $date->getTimeStamp();

    // 5. Samma syntex men med kejdemetoder
    $monday_first_hour = (new DateTime())->setISODate($year, (int)$selected_week, 1)->setTime(8, 0)->getTimestamp();
    $friday_last_hour = (new DateTime())->setISODate($year, $selected_week, 5)->setTime(20, 0)->getTimestamp();

    // deklarera en tom arrray $selected_week_bookings som du lägger veckans bokningar i
    // datan från db som lagst i $bookings innehåller en tabell vid namn booking_start och som är ett datum med klockslag
    $selected_week_bookings = [];
    foreach ($bookings as $booking) {
        $booking_timestamp = (new DateTime($booking->booking_start))->getTimestamp();
        if ($booking_timestamp >= $monday_first_hour && $booking_timestamp <= $friday_last_hour) {
            $selected_week_bookings[] = $booking;
        }
    }

    // Om veckan är tom – returnera hela dagar som lediga
    if (empty($selected_week_bookings)) {
        $available_slots = [];

        for ($day = 1; $day <= 5; $day++) {
            $day_start = (new DateTime())->setISODate($year, (int)$selected_week, $day)->setTime(8, 0);
            $day_end   = (new DateTime())->setISODate($year, (int)$selected_week, $day)->setTime(20, 0);

            $available_slots[] = [
                'day'  => $day_start->format('Y-m-d'),
                'from' => '08:00',
                'to'   => '20:00',
                'isAvailable' => ((new DateTime($day_start->format('Y-m-d') . ' ' . '08:00'))->getTimestamp() < $timestamp_now && (new DateTime($day_start->format('Y-m-d') . ' ' . '20:00'))->getTimestamp() > $timestamp_now)
            ];
        }

        return new WP_REST_Response([
            'status' => 'success',
            'bookings' => [],
            'availableSlots' => $available_slots
        ], 200);
    }


    //*********************************************
    //RÄKNA UT LEDIGA TIDER (AVAILABLE SLOTS) START
    //*********************************************
    $available_slots = [];

    for ($day = 1; $day <= 5; $day++) {
        //för iterationens dag: räkna ut start & sluttid samt datum
        $day_start = (new DateTime())->setISODate($year, $selected_week, $day)->setTime(8, 0);
        $day_end = (new DateTime())->setISODate($year, $selected_week, $day)->setTime(20, 0);
        $day_str = $day_start->format('Y-m-d');

        // Hämta bokningar för iterations dag (basera på datum)
        $day_bookings = array_filter($selected_week_bookings, function ($b) use ($day_str) {
            return (new DateTime($b->booking_start))->format('Y-m-d') === $day_str;
        });

        // Enkla sorteringsfunktioner siffror & strängar: 
        // sort, rsort = sorterar på värden
        // asort, arsort = oxå på värden men nycklarna förblir densamma
        // ksort, krsort = sorterar på nycklarnas värde
        // Komplexa sorteringsfunktioner siffror & strängar: 
        // usort = sorterar baserat på en callback funktion. 
        // -- ett måste om du ska sortera objekt efter på något av dess egenskaper. 
        // -- också för arayer av ass-arrays
        // uasort - om du vill behålla nycklarnas värden
        // och så en time funktion! 
        // echo strtotime("2025-11-01 08:00:00");
        // Exempelutgång: 1741190400 (antal sekunder sedan 1970-01-01)

        // Sortera bokningar efter starttid
        // usort sorterar samma array som du skickar in — den skapar inte en kopia.
        usort($day_bookings, function ($a, $b) {
            return strtotime($a->booking_start) <=> strtotime($b->booking_start);
        });

        $current_start = clone $day_start;

        foreach ($day_bookings as $day_booking) {
            $day_booking_start = new DateTime($day_booking->booking_start);
            $day_booking_end = new DateTime($day_booking->booking_end);

            // Om det finns ett ledigt block före bokningen
            if ($current_start < $day_booking_start) {
                $available_slots[] = [
                    'day' => $day_str,
                    'from' => $current_start->format('H:i'),
                    'to' => $day_booking_start->format('H:i'),
                    'isAvailable' => $current_start->getTimestamp() < $timestamp_now && $day_booking_start->getTimestamp() > $timestamp_now
                ];
            }

            // Flytta starttid till slutet av bokningen
            if ($day_booking_end > $current_start) {
                $current_start = clone $day_booking_end;
            }
        }

        // Lägg till eventuellt ledigt block efter sista bokningen
        if ($current_start < $day_end) {
            $available_slots[] = [
                'day' => $day_str,
                'from' => $current_start->format('H:i'),
                'to' => $day_end->format('H:i'),
                'isAvailable' => $current_start->getTimestamp() < $timestamp_now && $day_end->getTimestamp() > $timestamp_now
            ];
        }
    }

    return new WP_REST_Response([
        'status' => 'success',
        'bookings' => $selected_week_bookings,
        'availableSlots' => $available_slots
    ], 200);
}
