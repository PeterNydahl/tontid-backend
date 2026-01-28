<?php
//*********************
// OBS! skolårets start måste tas in som input när filen laddas upp RAD 241 (ungefär)
//*********************

//***************************************************************
//      datastruktur på bokningar hämtade från schemat                      
//***************************************************************
//             [0] => 165 Bokningens ID (?) 
//             [1] => {33EF1C43-12BE-4719-A18B-29DB6E2D56E6} ID?
//             [2] => Måndag VECKODAG
//             [3] => 12:00 START
//             [4] => 300 LÄNGD
//             [5] => 
//             [6] => RYTMUS/MUSSKOL
//             [7] => 
//             [8] => 
//             [9] => SAL
//             [10] => 
//             [11] => 
//             [12] => 34-43, 45-51, 3-8, 10-14, 16-24 VECKOR


class AddSchemaBookings
{

    public static function orchestra()
    {
        // return self::read_schema_file();
        $schema_raw = self::read_and_filter_schema_file();
        $schema_filtered = self::filter_music_room_bookings($schema_raw);
        self::add_bookings_data($schema_filtered);
        self::delete_manual_bookings_clashes();
    }

    private static function read_and_filter_schema_file()
    {
        $upload_dir = dirname(__DIR__) . '/admin/uploads';
        $files = array_diff(scandir($upload_dir), array('.', '..'));

        if (!empty($files)) {
            $file = $upload_dir . '/' . reset($files);
        } else {
            wp_die("Ingen schemafil hittades");
        }

        $delimiter = "\t";
        $schema = [];
        $output_lines = []; // <-- här samlas de rader som ska sparas tillbaka

        if (($handle = fopen($file, 'r')) !== false) {

            stream_filter_append($handle, 'convert.iconv.Windows-1252/UTF-8');

            $start_reading = false;
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {

                if (!$start_reading) {
                    if (isset($row[0]) && str_starts_with($row[0], 'PK (7100)')) {
                        $start_reading = true;
                        // Spara även TRIGGER-raden i den nya filen
                        $output_lines[] = implode($delimiter, $row);
                    }
                    continue;
                }

                // Samla upp raderna som hör till schemat
                if(str_contains($row[9], ',')){
                    // error_log('Bokningen hade flera salar: ' . $row[9]);
                    $roomIds = explode(',', $row[9]);
                    foreach($roomIds as $roomId){
                        $row[9] = $roomId;
                        $schema[] = $row;
                    }
                } else {
                    $schema[] = $row;
                }

                $output_lines[] = implode($delimiter, $row);
            }
            
            fclose($handle);
        } else {
            wp_die("Kunde inte öppna schemafilen");
        }
        
        // ------------------------------
        // 🔥 SKRIV ÖVER ORIGINALFILEN
        // ------------------------------
        // Nu innehåller $output_lines bara det du vill spara.
        
        file_put_contents($file, implode("\n", $output_lines));
        
        return $schema;
    }


    //returnerar de bokningar som finns registrerade i db
    private static function filter_music_room_bookings($schema)
    {
        //hämtar musiksalar
        global $wpdb;
        $table_name = $wpdb->prefix . "tontid_music_rooms";
        $sql = "
            SELECT room_id 
            FROM $table_name 
        ";
        $music_rooms = $wpdb->get_results($sql, ARRAY_A);

        //filtrera bort de som inte är musiksalar
        $filtered_schema = [];
        foreach ($schema as $schema_booking) {
            foreach ($music_rooms as $music_room) {
                if (in_array($schema_booking[9], $music_room))
                    $filtered_schema[] = $schema_booking;
            }
        }
        //MARK
        return $filtered_schema;
    }

    //returnerar veckor kopplade till en bokning
    private static function get_weeks_from_booking($schema_booking)
    {
        $booking_weeks_ranges = $schema_booking;
        //ta bort dolda radbrytningstecken
        $booking_weeks_ranges = str_replace(["\r", "\n"], '', $booking_weeks_ranges);
        $ranges = explode(",", $booking_weeks_ranges);
        //ta bort whitespaces
        $ranges = array_map('trim', $ranges);
        foreach ($ranges as $range) {
            if (str_contains($range, '-')) {
                list($range_start, $range_end) = explode('-', $range);
                $start = (int)$range_start;
                $end = (int)$range_end;
                for ($i = $start; $i <= $end; $i++) {
                    $booking_weeks[] = $i;
                }
            } else {
                $booking_weeks[] = (int)$range;
            }
        }
        //returnera array med alla veckor för en bokning
        return $booking_weeks;
    }


    //räknar ut rätt nummer av veckodag
    private static function weekday_to_number($day)
    {
        switch (strtolower($day)) {
            case 'måndag':
                return 1;
            case 'tisdag':
                return 2;
            case 'onsdag':
                return 3;
            case 'torsdag':
                return 4;
            case 'fredag':
                return 5;
            case 'lördag':
                return 6;
            case 'söndag':
                return 7;
            default:
                throw new Exception("Ogiltig veckodag: $day");
        }
    }


    //räknar ut start - och sluttiden för en bokning
    private static function calculate_booking_time($week, string $day, $first_year_of_academic_year, $start_time, $duration)
    {
        //ser till att året blir rätt beroende på termin
        //max och min-värde för vt: 1 & 26
        if ($week > 1 && $week <= 26)
            $first_year_of_academic_year++;
        //hämtar rätt datum 
        $date = new DateTime();
        $date->setISODate($first_year_of_academic_year, $week, self::weekday_to_number($day));
        $date->format('Y-m-d H:i:s');
        //lägger till starttid
        list($start_h, $start_min) = explode(':', $start_time);
        $date->setTime((int)$start_h, (int)$start_min);
        $booking_start = clone $date;
        // $booking_start->format('Y/m/d H:i:s');
        //räknar ut sluttid
        $timestamp_start = $booking_start->getTimestamp();
        $duration_int = (int)(trim($duration));
        $duration_int_sec = $duration_int * 60;
        $timestamp_end = $timestamp_start + $duration_int_sec;
        $booking_end = new DateTime();
        $booking_end->setTimestamp($timestamp_end);
        $booking_end->format('Y/m/d H:i:s');

        // returnerar en array som innehåller start och sluttid
        return [
            $booking_start->format('Y/m/d H:i:s'),
            $booking_end->format('Y/m/d H:i:s')
        ];
    }


    //********************* THE BIG MIGHTY LOOP *********************/
    //Loopar igenom aoch lägger till alla bokningar i db
    private static function add_bookings_data($filtered_schema)
    {
        
    
        //ta bort alla nuvarande schemabokningar
        global $wpdb;
        $table_name = $wpdb->prefix . 'tontid_bookings';
        $deleted = $wpdb->delete(
            $table_name,
            ['booking_type' => 'schema'],
            ['%s']
        );
        if ($deleted === false) {
            error_log("Fel bid borttagning av nuvarande schemabokningar");
        } else {
            error_log("Bortagna rader: $deleted");
        }

        //Hämta schemablockeringar 
        $schedule_blocks = $wpdb->get_results("SELECT * FROM $table_name WHERE booking_type = 'schemablock'", ARRAY_A);        

        //loopar igenom scehmats alla "bokningsobjekt"

        foreach ($filtered_schema as $schema_booking) {  
            $schema_booking_weeks = self::get_weeks_from_booking($schema_booking[12]); //veckor har index 12 i bokningsobjektet

            //loopa igenom veckor för aktuell schemabokning, skapa ett bokningsarray för db och lägg till i array 
            foreach ($schema_booking_weeks as $week) {
                
                // mötestid kan ha siffran 7 istället för veckodag
                if ($schema_booking[2] == "7")
                    continue;
                
                $booking_time = self::calculate_booking_time($week, $schema_booking[2], 2025, $schema_booking[3], $schema_booking[4]);

                // om bokning krockar med en schemablockering - gå vidare till nästa iteration
                foreach($schedule_blocks as $sb){
                    if(
                        strtotime($booking_time[0]) < strtotime($sb['booking_end']) 
                        && strtotime($booking_time[1]) > strtotime($sb['booking_start'])
                    )
                    continue 2;        
                }

                //eventuellt bättre lösning på oivantstående
                // $exists = $wpdb->get_var(
                //     $wpdb->prepare(
                //         "
                //         SELECT 1 
                //         FROM {$table_name} 
                //         WHERE booking_type = 'schemablock'
                //         AND room_id = %s
                //         AND booking_start < %s
                //         AND booking_end > %s
                //         LIMIT 1
                //         ",
                //         $schema_booking[9], $booking_time[1], $booking_time[0]
                //     )
                // );

                // if ($exists) {
                //     continue; // hoppa över den här bokningen
                // }


                //lägg till bokning i db
                $wpdb->insert(
                    $table_name,
                    [
                        'room_id' => $schema_booking[9],
                        //registrera namn på lektion om det finns, annars $schema_booking[8] vilket är salspärr
                        'lesson' => empty(!$schema_booking[5]) ? $schema_booking[5] : $schema_booking[8],
                        'teacher' => $schema_booking[7],
                        'booking_start' => $booking_time[0],
                        'booking_end' => $booking_time[1],
                        'booking_type' => 'schema'
                    ],
                    []
                );
            }
        }


        return "<p>Schemafilen är nu registrerad!🥳</p>";
    }

    private static function delete_manual_bookings_clashes()
    {
        //hämta manuella bokningar
        global $wpdb;
        $table_name = $wpdb->prefix . 'tontid_bookings';
        $sql_manual_bookings = $wpdb->prepare("
            SELECT *
            FROM $table_name
            WHERE `booking_type` = %s
        ", 'manual');
        $manual_bookings = $wpdb->get_results($sql_manual_bookings, ARRAY_A);

        //hämta schemabokningar 
        $sql_schema_bookings = $wpdb->prepare(
            "
            SELECT *
            FROM $table_name
            WHERE `booking_type` = %s",
            'schema'
        );
        $schema_bookings = $wpdb->get_results($sql_schema_bookings, ARRAY_A);

        //jämför och ta bort manuella bokningar som krockar
        foreach ($schema_bookings as $schema_booking) {
            foreach ($manual_bookings as $manual_booking) {
                if (
                    $manual_booking['room_id'] === $schema_booking['room_id'] &&
                    strtotime($manual_booking['booking_start']) < strtotime($schema_booking['booking_end']) &&
                    strtotime($manual_booking['booking_end']) > strtotime($schema_booking['booking_start'])
                ) {
                    $wpdb->delete(
                        $table_name,
                        [
                            'booking_id' => $manual_booking['booking_id']
                        ]
                    );
                };
            };
        };
    }
}
