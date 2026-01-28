<?php

/*************************************************************************************
                      SKAPA TABELLER VID AKTIVERING AV PLUGIN
          funktionerna anropas via pluginets huvudfil när pluginet aktiveras 
 ************************************************************************************/

/* Skapa tabell för musiksalar 
-------------------------------------------------------------------------------------*/
function tontid_create_music_rooms_table() {
    //Denna require_once behövs för att få tillgång till dbDelta-funktionen i WordPress.
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'tontid_music_rooms';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        room_id VARCHAR(255) NOT NULL,    
        room_description TEXT DEFAULT NULL,
        room_equipment TEXT DEFAULT NULL,
        room_category VARCHAR(255),
        PRIMARY KEY (room_id)
    ) $charset_collate;";
    dbDelta($sql); // Skapar tabellen om den inte finns

    // Lista med alla rum
    $rooms = [
        ['012', 'Bas', null, 'bas'],
        ['013', 'Piano', null, 'piano'],
        ['014', 'Gitarr K', null, 'gitarr'],
        ['015', 'Piano', null, 'piano'],
        ['017', 'Trummor', null, 'trummor'],
        ['018', 'Gitarr K', null, 'gitarr'],
        ['019', 'Trummor', null, 'trummor'],
        ['020', 'Ensemble', null, 'ensemble'],
        ['021', 'Ensemble', null, 'ensemble'],
        ['023', 'Ensemble', null, 'ensemble'],
        ['024', 'Ensemble', null, 'ensemble'],
        ['026', 'Ensemble', null, 'ensemble'],
        ['027', 'Ensemble (loge vid konsert)', null, 'ensemble'],
        ['031', 'Ensemble', null, 'ensemble'],
        ['032', 'Ensemble', null, 'ensemble'],
        ['102', 'Gemusal', null, 'gemusal'],
        ['103', 'Piano & sång', null, 'pianosang'],
        ['105', 'Bas & sång K', null, 'bassang'],
        ['106', 'Sång K', null, 'sang'],
        ['110', 'Piano & sång', null, 'pianosang'],
        ['111', 'Piano & sång', null, 'pianosang'],
        ['113', 'Grupprum sång, piano & gitarr', null, 'grupprumsangpianogitarr'],
        ['114', 'Piano & sång K', null, 'pianosang'],
        ['115', 'Piano & sång', null, 'pianosang'],
        ['116', 'Studio C', null, 'studio'],
        ['118', 'Studio D', null, 'studio'],
        ['120', 'Studio B', null, 'studio'],
        ['122', 'Studio A', null, 'studio'],
        ['225', 'Sång', null, 'sang'],
        ['226', 'Sång', null, 'sang'],
        ['227', 'Sång', null, 'sang'],
        ['232', 'Sång', null, 'sang'],
        ['233', 'Sång', null, 'sang'],
        ['synth1', 'Korg MS20 mini', null, 'korgms20mini'],
        ['036', 'GG', null, 'gg'],
        ['037', 'GG', null, 'gg']
    ];
    //replace() fungerar ungefär som “insert ELLER uppdatera” så SLIPPER MAN skriva extra kod för att kolla om tabellen redan finns

    // Infogar rader i databasen
    foreach ($rooms as $room) {
        $wpdb->replace(
            $table_name,
            [
                'room_id' => $room[0],
                'room_description' => $room[1],
                'room_category' => $room[3]
            ],
            ['%s', '%s', '%s']
        );
    }
}

/* Skapa tabell för bokningar 
-------------------------------------------------------------------------------------*/

function tontid_create_bookings_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . "tontid_bookings";
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        booking_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        room_id VARCHAR(50) NOT NULL,
        lesson VARCHAR(255) DEFAULT NULL,
        teacher VARCHAR(30) DEFAULT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        user_email VARCHAR(191) NOT NULL,
        booking_start DATETIME NOT NULL,
        booking_end DATETIME NOT NULL,
        booking_type ENUM('manual','schema','schemablock') NOT NULL DEFAULT 'manual',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (booking_id),
        KEY room_id (room_id),
        KEY user_email (user_email),
        KEY booking_start (booking_start)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

