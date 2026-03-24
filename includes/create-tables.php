<?php

/*************************************************************************************
                      SKAPA TABELLER VID AKTIVERING AV PLUGIN
          funktionerna anropas via pluginets huvudfil när pluginet aktiveras 
 ************************************************************************************/

//Denna require_once behövs för att få tillgång till dbDelta-funktionen i WordPress.
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
//TODO ta bort alla require once innuti metoderna

//*************************************************************************
//************************* MEDLEMSKAP I GRUPP ****************************
//*************************************************************************

function tontid_create_group_membership_table(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'tontid_group_membership';
    //använd rätt teckenbibliotek så att det stämmer överens med wordpress senaste
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_name(
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        group_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        group_slug VARCHAR(255) NOT NULL,
        group_name VARCHAR(255) NOT NULL,
        PRIMARY KEY (id)
    )$charset_collate;";
    dbDelta($sql);
}

//*************************************************************************
//********************************* RUM ***********************************
//*************************************************************************

function tontid_create_music_rooms_table()
{
    
    // require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

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
        ['028', 'GG', 'aula', 'gg'], //trasslig
        ['035', 'GG', 'klassrum gemu, arko', 'gg'],
        ['036', 'GG', 'klassrum matte', 'gg'],
        ['037', 'GG', 'klassrum matte', 'gg'],
        ['107', 'GG', 'edi, mup, gemu', 'gg'],
        ['108', 'GG', 'edi, mup, gemu', 'gg'],
        ['202', 'GG', 'klassrum', 'gg'],
        ['203', 'GG', 'klassrum', 'gg'],
        ['204', 'GG', 'klassrum media', 'gg'],
        ['208', 'GG', 'klassrum', 'gg'],
        ['209', 'GG', 'klassrum', 'gg'],
        ['210', 'GG', 'klassrum', 'gg'],
        ['212', 'GG', 'grupprum grön', 'gg'],
        ['213', 'GG', 'grupprum elevkår', 'gg'],
        ['215', 'GG', 'klassrum', 'gg'],
        ['216', 'GG', 'klassrum', 'gg'],
        ['218', 'GG', 'klassrum', 'gg'], //trasslig
        ['223', 'GG', 'mediasal halvklass', 'gg'],
        ['224', 'GG', 'klassrum, nak', 'gg'],
        ['229', 'GG', 'klassrum', 'gg'], //trasslig
        ['230', 'GG', 'podcaststudio', 'gg'],
        ['231', 'GG', 'klassrum, gemu, arko', 'gg'],
        ['211', 'GG', 'grupprum', 'gg'],
        ['214', 'GG', 'grupprum', 'gg'],
        ['E1', 'GG', 'Eductus klassrum 1', 'gg'] //trasslig
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

//*************************************************************************
//***************************** BOKNINGAR *********************************
//*************************************************************************

function tontid_create_bookings_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . "tontid_bookings";
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        booking_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        room_id VARCHAR(255) NOT NULL,
        lesson VARCHAR(500) DEFAULT NULL,
        teacher VARCHAR(500) DEFAULT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        user_email VARCHAR(255) NOT NULL,
        booking_start DATETIME NOT NULL,
        booking_end DATETIME NOT NULL,
        booking_type ENUM('manual','schema','schemablock') NOT NULL DEFAULT 'manual',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (booking_id),
        KEY room_id (room_id),
        KEY user_email (user_email),
        KEY booking_start (booking_start)
    ) $charset_collate;";

    /* PK är alltid indexerad så det räcker att ange kolumne inom parantes! */
    /* KEY index_name (column_name) men man kan (som du ser ovan) ge samma namn */
    /* Parentesen anger vilken kolumn (eller kolumner) som indexet ska gälla för */

    // require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}


//*************************************************************************
//******************************* LIKES ***********************************
//*************************************************************************

function tontid_create_post_likes_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . "tontid_post_likes";
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        post_like_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id BIGINT UNSIGNED NOT NULL,
        author_id BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (post_like_id),
        KEY post_id_index (post_id),
        KEY author_id_index (author_id)
    ) $charset_collate;";
    dbDelta($sql);
}
