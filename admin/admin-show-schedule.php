<?php

class AdminShowSchedule{
    public function __construct(){
        add_action('admin_menu', array($this, 'add_admin_menu_show_schedule'));
        //när användaren submittat formuläret och det genomgått kontroll hos admin_post (wp-grej) kommer det tillbaka med prefixet 'admin_post' tillagt i namnet. Detta är som en säkerhetsstämpel på att innehållet är kontrollerat och utan risk.
        // Och då anropas funktionen som visar det valda schemat.
        add_action('admin_post_tontid_show_bookings', array($this, 'tontid_handle_show_selected_schedule')); 
    }

    /*************************************************************************************
                               ADMIN MENY - VISA SCHEMA
    *************************************************************************************/
    //skapa dashboard menyn
    public function add_admin_menu_show_schedule(){
        add_submenu_page(
        'tontid',                  // Parent slug
        'Visa schema',             // Sidtitel
        'Visa schema',             // Menynamn
        'tontid_view_menu',             // Capability
        'tontid-show-schedule',         // Slug
        array( $this, 'display_show_schedule' ) // Callback som blir en array på PHPiska eftersom det rör sig om en metod (som tillhör ett objekt)
        );     
    }

    public function display_show_schedule(){
        // Visar ett formulär där användaren kan välja vecka och rum (display_week_and_room_selector()).
        $this->display_week_and_room_selector();
        // Om användaren har skickat in formuläret (dvs. om $_GET['show_schedule'] är satt), körs funktionen som visar bokningsschemat för det valda rummet och veckan
        if (isset($_GET['show_schedule'])) {
            $this->tontid_handle_show_selected_schedule();
        }
    }
   
    //Om input är knas - visa felmeddelande!
    public function message_displayer(){
        if(isset($_GET['message']) && $_GET['message'] === 'booking_added')
            TonTidUtils::show_notice_booking_added();
        if(isset($_GET['error']) && $_GET['error'] === 'invalid_nonce')
            TonTidUtils::show_notice_error();
        if(isset($_GET['error']) && $_GET['error'] === 'invalid_date')
            TonTidUtils::show_notice_error();
        if(isset($_GET['error']) && $_GET['error'] === 'end_before_start')
            TonTidUtils::show_notice_end_before_start();
        if(isset($_GET['error']) && $_GET['error'] === 'missing_fields')
            TonTidUtils::show_notice_missing_fields();
        if(isset($_GET['error']) && $_GET['error'] === 'database_error')
            TonTidUtils::show_notice_error();
    }


    /*************************************************************************************
                                      VISA VECKOSHEMA 
    *************************************************************************************/
    /* UI : VÄLJ VECKA OCH SAL
    ----------------------------------------------------------------------- */
    public function display_week_and_room_selector() {
        
        /* Hämta årets resterande veckor */
        $current_datetime = new DateTime();
        $current_week = $current_datetime->format('W');
        
        $current_year = $current_datetime->format('o');
        $safe_date = new DateTime("{$current_year}-12-28");
        $last_week_of_current_year = $safe_date->format('W');
        
        // Värden för menyn för veckor. Default nuvarnade vecka, annars den senast valda
        $selected_week = isset($_GET['selected_week']) ? $_GET['selected_week'] : $current_week;
        $selected_room = isset($_GET['selected_room']) ? $_GET['selected_room'] : '';
      
        /* Hämta alla rum*/
        global $wpdb; 
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'tontid_music_rooms';
        $sql = "SELECT room_id FROM $table_name";
        $rooms = $wpdb->get_results($sql);
        
        ?>
        <!-- Hämta användarens input -->
        <form action="<?php echo esc_url(admin_url('admin.php')); ?>" method="get">
            <input type="hidden" name="page" value="tontid-show-schedule">
            <input type="hidden" name="show_schedule" value="1">
            
            <table class="form-table" style="width:auto">
                <tr>
                    <td>
                        <label for="selected_week_dropdown"><strong>Vecka</strong></label>
                        <select name="selected_week" id="select_week_dropdown">
                            <option value="">Välj vecka</option>
                            <?php 
                            for ($i = $current_week; $i <= $last_week_of_current_year; $i++) {
                                echo '<option value="' . esc_attr($i) . '" ' . ((isset($_GET['selected_week']) && $_GET['selected_week']== $i) ? 'selected' : "") . '>' . esc_html($i) . '</option>';         
                            }
                            ?>
                        </select>
                    </td>

                    <td>
                        <label for="selected_room_dropdown"><strong>Rum</strong></label>
                        <select name="selected_room" id="selected_room_dropdown">
                            <option value="">Välj rum</option>
                            <?php 
                            foreach ($rooms as $room) {
                                echo '<option value="' . esc_attr($room->room_id) . '" ' . ((isset($_GET['selected_room']) && $_GET['selected_room']==$room->room_id) ? "selected" : "") . '>' . $room->room_id . '</option>';      
                            }
                            ?>
                        </select>
                    </td>

                    <td style="vertical-align: bottom;">
                        <?php submit_button('Visa schema', 'primary', 'submit', false); ?>
                    </td>
                </tr>
            </table>
        </form>
        
        <?php

    }

    /* VISA SCHEMA FÖR VALD VECKA OCH SAL
    ----------------------------------------------------------------------- */
    public function tontid_handle_show_selected_schedule(){
        // allmäna värden
        $year = date('Y');
        
        
        $room_id = isset($_GET['selected_room']) ? sanitize_text_field($_GET['selected_room']) : null;
        $selected_week = isset($_GET['selected_week']) ? sanitize_text_field($_GET['selected_week']) : null;
        
        //hämta alla bokningar för vald sal
        global $wpdb;
        $wpdb->show_errors();
        $table_name = $wpdb->prefix . 'tontid_bookings';
        $bookings = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE room_id = %s", $room_id));
        if(empty($bookings)){
            TonTidUtils::show_notice_no_bookings_found();
            return;
        }

        // sortera ut bokningar av rummet baserat på vald vecka
        $booking_timestamp = (new DateTime($bookings[0]->booking_start))->getTimestamp();
        $monday_first_hour = (new DateTime())->setISODate($year, (int)$selected_week, 1)->setTime(8, 0)->getTimestamp();
        $friday_last_hour = (new DateTime())->setISODate($year, (int)$selected_week, 5)->setTime(20, 0)->getTimestamp();

        $selected_week_bookings = [];
        foreach($bookings as $booking){
            $booking_timestamp = (new DateTime($booking->booking_start))->getTimestamp();
            if ($booking_timestamp >= $monday_first_hour && $booking_timestamp <= $friday_last_hour)
                $selected_week_bookings[] = $booking;
        }

        $table_name = $wpdb->prefix . 'tontid_bookings';
        $results_room_bookings = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE room_id = %s",
                $room_id
            ), ARRAY_A
        );

        if(!empty($results_room_bookings)){
            $booking = $results_room_bookings[0];
        }
            else {
                TonTid::show_notice_error();     
                return;
        }

        /* todo: ta bort denna kommentar sen. Klippt ut kod som räknar ut grid positioner för bokningar */
       
    ?>  

        
        <!-- VISA VECKOSCHEMA
         ----------------------------------------------------------------------- -->

        <h1>Bokningsschema <?php echo "rum: {$room_id}" ?></h1>
        <div class="schedule">
            <!-- Första raden: rubriker -->
                <div class="schedule__header">Tid</div>
                <div class="schedule__header">Måndag</div>
                <div class="schedule__header">Tisdag</div>
                <div class="schedule__header">Onsdag</div>
                <div class="schedule__header">Torsdag</div>
                <div class="schedule__header">Fredag</div>
            <!-- resterande rader upptas av tidsschemat -->

            <!-- KOLUMN 1: markeringar av tidsspannet för samtliga dagar (uppdelat i 5minutsers intervall) -->
            <div class="schedule__time-slots">
                <?php
                    $hour = 8;
                    $styling = 'schedule-timeslot';
                    for($i = 0; $i < 12; $i++){
                        echo "<div class='{$styling}'>{$hour}:00</div>";
                        echo "<div class='{$styling}'>{$hour}:30</div>"; 
                        $hour++;
                    }
                    echo "<div class='{$styling}'>{$hour}:00</div>";
                ?>
            </div>
            <!-- RESTERANDE KOLUMNER : VECKODAGAR 
             --------------------------------------------------------------------------------- -->
            <!-- MÅNDAG 
             ------------------------------------>
            <div class="schedule__weekday monday">
                <?php
                    if(isset($selected_week_bookings)){
                        foreach($selected_week_bookings as $booking){
                            $booking_day = (new DateTime($booking->booking_start))->format('l');
                            if($booking_day == 'Monday'){
                                echo TonTidAdminFunctions::calculateBookingGridPositionAndDuration($booking);
                            }
                        }
                    }
                ?>    
            </div>

            <!-- TISDAG 
             ------------------------------------>
            <div class="schedule__weekday tuesday">
                <?php
                    if(isset($selected_week_bookings)){
                        foreach($selected_week_bookings as $booking){
                            $booking_day = (new DateTime($booking->booking_start))->format('l');
                            if($booking_day == 'Tuesday'){
                                echo TonTidAdminFunctions::calculateBookingGridPositionAndDuration($booking);
                            }
                        }
                    }
                ?>    
            </div>

            <!-- ONSDAG 
             ------------------------------------>
            <div class="schedule__weekday wednesday">
                <?php
                    if(isset($selected_week_bookings)){
                        foreach($selected_week_bookings as $booking){
                            $booking_day = (new DateTime($booking->booking_start))->format('l');
                            if($booking_day == 'Wednesday'){
                                echo TonTidAdminFunctions::calculateBookingGridPositionAndDuration($booking);
                            }
                        }
                    }
                ?>    
            </div>

            <!-- TORSDAG 
             ------------------------------------>
            <div class="schedule__weekday thursday">
                <?php
                    if(isset($selected_week_bookings)){
                        foreach($selected_week_bookings as $booking){
                            $booking_day = (new DateTime($booking->booking_start))->format('l');
                            if($booking_day == 'Thursday'){
                                echo TonTidAdminFunctions::calculateBookingGridPositionAndDuration($booking);
                            }
                        }
                    }
                ?>    
            </div>

            <!-- FREDAG 
             ------------------------------------>
            <div class="schedule__weekday friday">
                <?php
                    if(isset($selected_week_bookings)){
                        foreach($selected_week_bookings as $booking){
                            $booking_day = (new DateTime($booking->booking_start))->format('l');
                            if($booking_day == 'Friday'){
                                echo TonTidAdminFunctions::calculateBookingGridPositionAndDuration($booking);
                            }
                        }
                    }
                ?>    
            </div>
        <?php
    }



}