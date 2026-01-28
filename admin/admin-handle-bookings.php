<?php

class AdminHandleBookings {
    public function __construct(){
        add_action('admin_menu', array($this, 'add_show_and_handle_bookings'));
        add_action('admin_post_tontid_add_booking', array($this, 'tontid_handle_add_booking'));
        // add_action('admin_post_tontid_show_bookings', array($this, 'tontid_handle_show_selected_schedule')); 
    }

    /*************************************************************************************
                               ADMIN MENY - VISA OCH HANTERA BOKNINGAR
    *************************************************************************************/
    public function add_show_and_handle_bookings(){
        add_submenu_page(
        'tontid',                     // Parent slug
        'Skapa bokning',             // Sidtitel
        'Skapa bokning',             // Menynamn
        'tontid_view_menu',             // Capability
        'tontid-handle-bookings',         // Slug
        array( $this, 'display_handle_booking_page' ) // Callback
        );     
    }
    /* admin UI för att hantera en bokning
    ------------------------------------------------------------------------------------*/
    public function display_handle_booking_page() {
        $this->display_add_booking();
    }
    
    public function message_displayer_add_booking() {
        if (isset($_GET['message']) && $_GET['message'] === 'booking_added')
            TonTidUtils::show_notice_booking_added();

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

        if (isset($_GET['error']) && $_GET['error'] === 'booking_conflict')
            TonTidUtils::show_notice_booking_conflict();
    }


    /*************************************************************************************
                                    LÄGG TILL BOKNING
    *************************************************************************************/

    /* Lägg till bokning - UI/INPUT 
    --------------------------------------------------- */
    public function display_add_booking(){
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'tontid_music_rooms';
        $sql = "SELECT room_id FROM $table_name";
        $rooms = $wpdb->get_results($sql);
        ?>
        
        <div class="wrap">
            <h1>Skapa bokning</h1>
            
            <?php
            $this->message_displayer_add_booking();
            ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" id="handle_booking_form">
                    <input type="hidden" name="action" value="tontid_add_booking">
                    <?php wp_nonce_field('tontid_add_booking_nonce', 'tontid_add_booking_nonce') ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="add_booking_select_room-id">Rums-ID</label>
                            </th>
                            <td>
                                <select id="add_booking_select_room_id" name="room_id">
                                    <option value="">Välj ett rum</option>
                                    <?php foreach($rooms as $room){
                                        echo '<option>' . esc_html($room->room_id) . '</option>';         
                                    }?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="add_booking_lesson">Lektion</label>
                            </th>
                            <td>
                                <input type="text" name="booking_lesson">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="add_booking_start_time">Starttid</label>
                            </th>
                            <td>
                                <input type="text" name="booking_start" class="tontid-flatpickr-time">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="add_booking_start_end">Sluttid</label>
                            </th>
                            <td>
                                <input type="text" name="booking_end" class="tontid-flatpickr-time">
                            </td>
                        </tr>
                    </table>
                    <?php if ( ! empty( $rooms ) ) : ?>
                        <?php submit_button( 'Lägg till bokning', 'primary', 'submit', false ); ?>
                    <?php endif; ?>
                </form>
        </div>
        <?php
    }

    /* Lägg till bokning - databashantering
    --------------------------------------------------- */

    public function tontid_handle_add_booking() {
        // Kontrollera nonce för säkerhet
        if ( ! isset( $_POST['tontid_add_booking_nonce'] ) || ! wp_verify_nonce( $_POST['tontid_add_booking_nonce'], 'tontid_add_booking_nonce' ) ) {
            $redirect_url = admin_url('admin.php?page=tontid-handle-bookings&error=invalid_nonce');
            wp_safe_redirect($redirect_url);
            exit;
        }

        // Kontrollera om alla fält fyllts i
        if (
            empty( $_POST['room_id'] ) ||
            empty( $_POST['booking_lesson'] ) ||
            empty( $_POST['booking_start'] ) ||
            empty( $_POST['booking_end'] )
        ) {
            $redirect_url = admin_url('admin.php?page=tontid-handle-bookings&error=missing_fields');
            wp_safe_redirect($redirect_url);
            exit;
        }
        // Sanera och validera data från formuläret
        $room_id = sanitize_text_field( $_POST['room_id'] );
        $booking_lesson = sanitize_text_field( $_POST['booking_lesson'] );
        $booking_start = sanitize_text_field( $_POST['booking_start'] );
        $booking_end = sanitize_text_field( $_POST['booking_end'] );

        //kontrollera att bokningens datum har fungerande tidsformat
        if ( strtotime( $booking_start ) === false || strtotime( $booking_end ) === false ) {
            $redirect_url = admin_url('admin.php?page=tontid-handle-bookings&error=invalid_date');
            wp_safe_redirect($redirect_url);
            return;
        }
        // kontrollera så att vald starttid är innan vald sluttid
        if ( strtotime( $booking_start ) >= strtotime( $booking_end ) ) {
            $redirect_url = admin_url('admin.php?page=tontid-handle-bookings&error=end_before_start');
            wp_safe_redirect($redirect_url);
            return;
        }

        // kontrollerar att bokning inte är möjligt bakåt i tiden
        if ( strtotime($booking_start) < time() ) {
            $redirect_url = admin_url('admin.php?page=tontid-handle-bookings&error=booking_in_past');
            wp_safe_redirect($redirect_url);
            return;
        }

        // en bokning får max vara 2 timmar lång
        $duration_seconds = strtotime($booking_end) - strtotime($booking_start);
        if ( $duration_seconds > 2 * 60 * 60 ) { // 2 timmar i sekunder
            $redirect_url = admin_url('admin.php?page=tontid-handle-bookings&error=booking_too_long');
            wp_safe_redirect($redirect_url);
            return;
        }

        // bokning får inte ske på lördag eller söndag 
        $booking_day = date('N', strtotime($booking_start)); // N är en numeriskt representation av veckodagen så: 1 = Måndag, 7 = Söndag
        if ( $booking_day == 6 || $booking_day == 7 ) {
            $redirect_url = admin_url('admin.php?page=tontid-handle-bookings&error=booking_on_weekend');
            wp_safe_redirect($redirect_url);
            return;
        }

        // en bokning får bara ske mellan kl 8 och 20
        $start_hour = (int) date('G', strtotime($booking_start)); // G returnerar ett heltal mellan 0–23, respresenterar timmar
        $end_hour   = (int) date('G', strtotime($booking_end));
        if ( $start_hour < 8 || $end_hour >= 20 ) {
            $redirect_url = admin_url('admin.php?page=tontid-handle-bookings&error=booking_outside_hours');
            wp_safe_redirect($redirect_url);
            return;
        }

        // Lägg till bokningen i databasen
        global $wpdb;
        $user_id = get_current_user_id();

        // Kontrollera att bokningen inte krockar med en annan bokning i samma rum
        $conflict_query = $wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}tontid_bookings
            WHERE room_id = %s
            AND (
                (booking_start < %s AND booking_end > %s)
            )
        ", $room_id, $booking_end, $booking_start);

        $conflict_count = $wpdb->get_var($conflict_query);

        if ($conflict_count > 0) {
            $redirect_url = admin_url('admin.php?page=tontid-handle-bookings&error=booking_conflict');
            wp_safe_redirect($redirect_url);
            return;
        }

        // Allt ser bra ut – spara bokningen
        $result = $wpdb->insert(
            $wpdb->prefix . 'tontid_bookings',
            array(
                'room_id' => $room_id,
                'lesson' => $booking_lesson,
                'user_id' => $user_id,
                'booking_start' => $booking_start,
                'booking_end' => $booking_end
            ),
            array(
                '%s',  // room_id
                '%s',  // lesson
                '%d',  // user_id
                '%s',  // booking_start
                '%s'   // booking_end
            )
        );





        // Kontrollera om insättningen lyckades
        if ( $result === false ) {
            $redirect_url = admin_url('admin.php?page=tontid-handle-bookings&error=database_error');
            wp_safe_redirect($redirect_url);
            exit;
        }

        // Om allt ok, omdirigera med ett framgångsmeddelande
        $redirect_url = admin_url('admin.php?page=tontid-handle-bookings&message=booking_added');
            wp_safe_redirect($redirect_url);
            exit;
        }
}
