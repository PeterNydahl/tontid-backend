<?php

if(!defined('ABSPATH')) wp_die('Du har ej direktåtkomst till denna fil.'); 

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

    //kollar att vald vecka har ett giltigt värde
    if (!ctype_digit($selected_week) || $selected_week < 1 || $selected_week > 53) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Ogiltigt veckonummer.'
        ], 400);
    }

    // Skapa datumsträngar som matchar DB-formatet exakt
    $monday_first_hour = (new DateTime())->setISODate($year, (int)$selected_week, 1)->setTime(8, 0, 0)->format('Y-m-d H:i:s');
    $friday_last_hour  = (new DateTime())->setISODate($year, (int)$selected_week, 5)->setTime(20, 0, 0)->format('Y-m-d H:i:s');

    $selected_week_bookings = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name_bookings
            WHERE room_id IN ('%s')
            AND booking_start >= %s 
            AND booking_end <= %s
            ORDER BY booking_start ASC",
            $room_id,
            $monday_first_hour, 
            $friday_last_hour
        )
    );

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

        // Hämta bokningar för iterations dag (baserat på datum)
        $day_bookings = array_filter($selected_week_bookings, function ($b) use ($day_str) {
            return (new DateTime($b->booking_start))->format('Y-m-d') === $day_str;
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
