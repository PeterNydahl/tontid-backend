<?php

if(!defined('ABSPATH')) exit;

class AdminScheduleBlocks
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_meny'));
        // koppla ihop formuläret med funktion
        add_action('admin_post_tontid_schedule_blocking', array($this, 'tontid_handle_schedule_blocking'));
    }

    public function add_admin_meny()
    {
        add_menu_page(
            'Skapa blockering',
            'Skapa blockering',
            'tontid_view_menu',
            'tontid-schedule-blocks',
            array($this, 'display_schedule_blocks'),
            'dashicons-calendar',
            0
        );
    }

/*************************************************************************************
                                          UI
*************************************************************************************/

    public function display_schedule_blocks()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tontid_music_rooms';
        $sql = "SELECT * FROM $table_name";
        $rooms = $wpdb->get_results($sql, ARRAY_A);
?>

        <div class="wrap">
            <h1>Skapa blockering</h1>

            <?php
            $this->message_displayer_schedule_blocking();
            
            ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="handle_schedule_blocking_form">
                <?php submit_button('Skapa schemablock', 'primary', 'submit', false); ?>
                <input type="hidden" name="action" value="tontid_schedule_blocking">
                <?php wp_nonce_field('tontid_schedule_blocking_nonce', 'tontid_schedule_blocking_nonce') ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="schedule_blocking_lesson">Namn</label>
                        </th>
                        <td>
                            <input type="text" name="booking_lesson" value="Blockerad för bokning">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="schedule_blocking_start_time">Start</label>
                        </th>
                        <td>
                            <input type="text" name="booking_start" class="tontid-flatpickr-time">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="schedule_blocking_start_end">Slut</label>
                        </th>
                        <td>
                            <input type="text" name="booking_end" class="tontid-flatpickr-time">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="schedule_blocking_select_room-id">Salar</label>
                        </th>
                        <td>
                            
                                <?php foreach ($rooms as $room) {
                                    if($room['room_category'] !== 'gg' && $room['room_id'] !== 'synth1'){
                                        echo '<input type="checkbox" checked name="selected_rooms[]" value="' . esc_html($room['room_id']) . '" >' . esc_html($room['room_id']) . '<br>'; 
                                    }
                                } ?>
                            
                        </td>
                    </tr>
                </table>
            </form>
        </div>
<?php
}

/*************************************************************************************
                                LäGG TiLL ScHEMA BlOCKERING
*************************************************************************************/


    public function tontid_handle_schedule_blocking()
    {
        // Kontrollera nonce för säkerhet
        if (! isset($_POST['tontid_schedule_blocking_nonce']) || ! wp_verify_nonce($_POST['tontid_schedule_blocking_nonce'], 'tontid_schedule_blocking_nonce')) {
            $redirect_url = admin_url('admin.php?page=tontid-schedule-blocks&error=invalid_nonce');
            wp_safe_redirect($redirect_url);
            exit;
        }

        // Kontrollera om alla fält fyllts i
        if (
            empty($_POST['selected_rooms']) ||
            empty($_POST['booking_lesson']) || 
            empty($_POST['booking_start']) ||
            empty($_POST['booking_end'])
        ) {
            $redirect_url = admin_url('admin.php?page=tontid-schedule-blocks&error=missing_fields');
            wp_safe_redirect($redirect_url);
            exit;
        }
        // Sanera och ta emot data från formuläret
        // $selected_rooms_raw = sanitize_text_field($_POST['selected_rooms']);
        $selected_rooms = array_map('sanitize_text_field', $_POST['selected_rooms']);
        $booking_lesson = sanitize_text_field($_POST['booking_lesson']);

        $start_datetime = new DateTime(sanitize_text_field($_POST['booking_start']));
        $start_timestamp = $start_datetime->getTimestamp();
        $start_date = $start_datetime->format('Y-m-d');
        $start_time = $start_datetime->format('H:i:s');

        $end_datetime = new DateTime(sanitize_text_field($_POST['booking_end']));
        $end_timestamp = $end_datetime->getTimestamp();
        $end_date = $end_datetime->format('Y-m-d');
        $end_time = $end_datetime->format('H:i:s');

        $blocking_dates = [];

        //skapa en array med det omfång av datum som blockeringen består av
        $twentyfourhours_in_seconds = 24 * 3600;
        for ($i = 0; $start_timestamp < $end_timestamp; $i++) {
            $date = (new DateTime())->setTimestamp($start_timestamp)->format('Y-m-d');
            $blocking_dates[] = $date;
            $start_timestamp += $twentyfourhours_in_seconds;
        }

        global $wpdb;
        $table_name_bookings = $wpdb->prefix . 'tontid_bookings';
        //ta bort bokningar som krockar med schemablock
        $sql_get_bookings = "SELECT * FROM $table_name_bookings";
        $bookings = $wpdb->get_results($sql_get_bookings, ARRAY_A);

        // Lägg till schemablockering i databasen
        $user_id = get_current_user_id();

        foreach ($selected_rooms as $room_id){
            foreach ($blocking_dates as $blocking_date) {
                $blocking_start = (new DateTime("$blocking_date $start_time"))->format('Y-m-d H:i:s');
                $blocking_end = (new DateTime("$blocking_date $end_time"))->format('Y-m-d H:i:s');
                
                //ta bort bokningar som krockar med schemablockering
                foreach($bookings as $b){
                    $b_start_timestamp = (new DateTime($b['booking_start']))->getTimestamp();
                    $b_end_timestamp = (new DateTime($b['booking_end']))->getTimestamp();
                    if (
                        $b_end_timestamp > (new DateTime($blocking_start))->getTimestamp() 
                        && $b_start_timestamp < (new DateTime($blocking_end))->getTimestamp()
                        && $b['room_id'] === $room_id
                        ) {
                            $sql_delete_booking = $wpdb->prepare(
                            "DELETE FROM $table_name_bookings WHERE booking_id = %d",
                            $b['booking_id']
                        );
                        // $rows_deleted kommer att vara antalet rader raderade, 0, eller false.
                        $rows_deleted = $wpdb->query($sql_delete_booking);

                        // 2. Kontrollera resultatet av raderingen
                        if ($rows_deleted === false) {
                                error_log("fel vid radering av bokning wpdb_error $wpdb->last_error");
                        }
                    }
                }       

                //lägg till schemablock
                $result = $wpdb->insert(
                    $wpdb->prefix . 'tontid_bookings',
                    array(
                        'room_id' => $room_id,
                        'lesson' => $booking_lesson,
                        'user_id' => $user_id,
                        'booking_start' => $blocking_start,
                        'booking_end' => $blocking_end,
                        'booking_type' => 'schemablock'
                    ),
                    array(
                        '%s',  // room_id
                        '%s',  // lesson
                        '%d',  // user_id
                        '%s',  // blocking_start
                        '%s',  // blocking_end
                        '%s'   // booking_type
                    )
                );
            }
        }

        if ($result === false) {
            error_log('DB ERROR: ' . $wpdb->last_error);
        }
        
        // kontrollera så att vald starttid är innan vald sluttid
        if (strtotime($_POST['booking_start']) >= strtotime($_POST['booking_end'])) {
            $redirect_url = admin_url('admin.php?page=tontid-schedule-blocks&error=end_before_start');
            wp_safe_redirect($redirect_url);
            return;
        }

        // kontrollerar att bokning inte är möjligt bakåt i tiden
        if (strtotime($_POST['booking_start']) < time()) {
            $redirect_url = admin_url('admin.php?page=tontid-schedule-blocks&error=booking_in_past');
            wp_safe_redirect($redirect_url);
            return;
        }

        $redirect_url = add_query_arg([
            'page' => 'tontid-schedule-blocks',
            'message' => 'blocking_added',
            // 'booking_start' => rawurlencode($_POST['booking_start']),
            // 'booking_end' => rawurlencode($_POST['booking_end']),
            // 'selected_rooms' => rawurlencode($_POST['selected_rooms_as_string'])
            // 'booking_lesson' => rawurlencode($_POST['booking_lesson'])
        ], admin_url('admin.php'));

        wp_safe_redirect($redirect_url);
        exit;
    }


    /*************************************************************************************
                                        MEDDELANDEN
     *************************************************************************************/

    public function message_displayer_schedule_blocking(){
        
        if(isset($_GET['message'])
            && $_GET['message'] === 'blocking_added'
            // && isset($_GET['booking_start'])
            // && isset($_GET['booking_end'])
            ){
            TonTidUtils::show_notice_blocking_added();
        }

        if (isset($_GET['error']) && $_GET['error'] === 'invalid_nonce')
            TonTidUtils::show_notice_invalid_nonce();

        if (isset($_GET['error']) && $_GET['error'] === 'invalid_date')
            TonTidUtils::show_notice_invalid_date();

        if (isset($_GET['error']) && $_GET['error'] === 'end_before_start')
            TonTidUtils::show_notice_end_before_start();

        if (isset($_GET['error']) && $_GET['error'] === 'missing_fields')
            TonTidUtils::show_notice_missing_fields();

        if (isset($_GET['error']) && $_GET['error'] === 'database_error')
            TonTidUtils::show_notice_database_error();

        if (isset($_GET['error']) && $_GET['error'] === 'booking_in_past')
            TonTidUtils::show_notice_booking_in_past();

        if (isset($_GET['error']) && $_GET['error'] === 'booking_too_long')
            TonTidUtils::show_notice_booking_too_long();

        if (isset($_GET['error']) && $_GET['error'] === 'booking_on_weekend')
            TonTidUtils::show_notice_booking_on_weekend();

        if (isset($_GET['error']) && $_GET['error'] === 'booking_outside_hours')
            TonTidUtils::show_notice_booking_outside_hours();

        // if (isset($_GET['error']) && $_GET['error'] === 'booking_conflict')
        //     TonTidUtils::show_notice_booking_conflict();
    }
}
