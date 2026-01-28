<?php

class AdminShowAndDeleteBookings {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_show_and_delete_bookings_menu'));
        add_action('admin_post_tontid_show_bookings', array($this, 'handle_room_filter_submit'));
        add_action('admin_post_tontid_delete_booking', array($this, 'handle_delete_booking'));
        //hooken känner om något input kommit tillbaka från "wp-admins postterminal"
        add_action('admin_post_tontid_delete_all_bookings', array($this, 'handle_delete_all_bookings'));
    }

    /**
     * Lägg till menyflik i admin
     */
    public function add_show_and_delete_bookings_menu() {
        add_submenu_page(
            'tontid',
            'Visa och ta bort bokningar',
            'Visa/ta bort bokningar',
            'tontid_view_menu',
            'tontid-show-and-delete-bookings',
            array($this, 'display_show_and_delete_bookings_page')
        );
    }

    public function display_show_and_delete_bookings_page() {
        $this->display_select_room();
        $this->display_filtered_bookings();
    }

    public function message_displayer(){
        if(isset($_GET['message']) && $_GET['message']=='booking_deleted')
            TonTidUtils::show_notice_booking_was_deleted();
        if(isset($_GET['error']) && $_GET['error']=='delete_booking_failed')
            TonTidUtils::show_notice_delete_booking_failed();
    }

    /**
     * Visa alla bokningar + ta bort-knappar + rum-filtrering
     */
    public function display_select_room() { 
        //admin UI - visa alla bokningar alternativt ett valt rum
        //hämta alla rums id från databasen
        global $wpdb;
        $table_name = $wpdb->prefix . 'tontid_music_rooms';
        $rooms = $wpdb->get_results("SELECT room_id FROM $table_name");
        ?>
        
        <div class="wrap">

            <h1>Visa/ta bort bokningar</h1>
            <form action="<?php echo esc_url(admin_url('admin-post.php'));?>" method="get">
                
                <?php
            $this->message_displayer();
            ?>
            <input type="hidden" name="action" value="tontid_show_bookings">
            <table class="form-table select-room-for-booking-filtering" style="width:auto">
                <tr>
                    <td>
                        <!-- <label for="filter_by_room"><strong>Välj rum</strong></label>         -->
                        <select name="selected_room" id="filter_by_room">
                            <option value="">Välj rum</option>
                            <option value="alla_rum">- alla rum -</option>
                            <?php
                                        foreach ($rooms as $room) {
                                            echo "<option value='{$room->room_id}'>{$room->room_id}</option>";
                                        }
                                        ?>                            
                                    </select>
                                </td>
                                <td style="vertical-align: bottom;">
                                    <?php submit_button('Visa bokningar', 'primary', 'submit', false); ?>
                                </td>
                            </form>
                </tr>
            </table>
            <!-- knapp för att ta bort alla bokningar         -->
            <!-- formuläret skickas till wp postkontoret (filen admin-post.php) för säkerhetskontroll -->
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <!-- När du skickar ett formulär till admin-post.php i WordPress måste du ange ett fält som heter action. -->
                <!-- WordPress läser värdet på action och letar efter en "hook" som matchar. -->
                <!-- I konstruktorn ligger en action-hook som reagerar på den säkerhetskontrollerade varianten (som därmed fått prexiet som en kvalitetsstämpel i säkerhet "admin-post")admin_post_tontid_delete_booking -->
                <input type="hidden" name="action" value="tontid_delete_all_bookings">
                <input type="submit" value="Ta bort alla bokningar" class="delete-button"/>
            </form>

        </div>
        <?php



}

public function handle_room_filter_submit() {
    if (isset($_GET['selected_room'])) {
        $selected_room = sanitize_text_field($_GET['selected_room']);
    } else {
        $selected_room = 'alla_rum';
    }
    
    // Skicka tillbaka till admin-sidan med rum som parameter
    $redirect_url = add_query_arg(array(
            'page' => 'tontid-show-and-delete-bookings',
            'selected_room' => $selected_room,
        ), admin_url('admin.php'));

        wp_redirect($redirect_url);
        exit;
    }

    public function display_filtered_bookings(){
                // Om ett rum är valt, hämta bokningar för det rummet
        if(isset($_GET['selected_room'])){
            $selected_room = sanitize_text_field($_GET['selected_room']);
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'tontid_bookings';
            if ($selected_room === 'alla_rum') {
                $bookings = $wpdb->get_results(
                "SELECT * FROM $table_name ORDER BY booking_start"
            );
        } else {
            $bookings = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $table_name WHERE room_id = %s ORDER BY booking_start",
                    $selected_room
                )
            );
        }

        if($bookings){
            echo "<h2>Visar bokningar för rum: $selected_room</h2>";
            echo "<table class='form-table delete-bookings-table'>";
            echo "<tr>
                    <th>Rum</th>
                    <th>Lektion</th>
                    <th>Starttid</th>
                    <th>Sluttid</th>
                    <th>Ta bort</th>
                </tr>";
            foreach($bookings as $booking){
                echo "<tr>
                    <td>{$booking->room_id}</td>
                    <td>{$booking->lesson}</td>
                    <td>{$booking->booking_start}</td>
                    <td>{$booking->booking_end}</td>
                    <td>
                        <form action='" . esc_url(admin_url('admin-post.php')) . "' method='post'>
                            <input type='hidden' name='action' value='tontid_delete_booking'>
                            <input type='hidden' name='booking_to_delete_id' value='{$booking->booking_id}' />
                            <input type='submit' value='Ta bort' class='delete-button'/>
                        </form>
                    </td>
                </tr>";
            }
            echo "</table>";
        }
    }

    public function handle_delete_booking(){
        global $wpdb;
        $table_name = $wpdb->prefix . "tontid_bookings";
        // Kollar om booking_to_delete_id finns i $_POST dvs om det skickats med i formuläret. 
        // booking_to_delete_id är värdet för attributet "name" i formuläret och dess tillhörande attribut "value" är det valda id numret. 
        if(isset($_POST["booking_to_delete_id"])){
            $booking_id = intval($_POST["booking_to_delete_id"]);
        };
        $result = $wpdb->delete(
            $table_name,
            // Detta nyckel-värde par visar att det gäller den rad av booking_id som har värdet $booking_id
            // Det är alltså ett "WHERE booking_id = $booking_id" i SQL-termer!
            array( 'booking_id'=>$booking_id ),
            // Varför en array?? Jo, för att man kan välja flera vilkor. '%d' är för heltal men du kan också välja '%s' för en sträng
            array( '%d')
        );
        if($result){
            // add_query_arg är en WordPress-funktion! Den används för att enkelt lägga till eller ändra query-parametrar i en URL
            $url = add_query_arg(
                // resultatet blir /wp-admin/admin.php?page=tontid-show-and-delete-bookings&message=booking_deleted
                // så i admin_url anger du url:ens "grund" i efter /wp-admin/ och i parametrarna ovan query parametrar, första nyckel och andra värde
                'message',
                'booking_deleted',
                admin_url('admin.php?page=tontid-show-and-delete-bookings')
            );
            // ytterligare en wp funktion som omdirigerar till url:en som definierades ovan
            wp_redirect($url);
            exit;
        } else {
            $url = add_query_arg(
                'error',
                'delete_booking_failed',
                admin_url('admin.php?page=tontid-show-and-delete-bookings')
            );
        };
    }

    //funktionen anropas via en hook som är definierad i konstruktorn. När an admin-post av aktuellt formulär kommer in så anropas denna funktion som atar bort alla bokningar
    public function handle_delete_all_bookings() {
        global $wpdb;
        $table_name = $wpdb->prefix . "tontid_bookings";
        // Tar bort ALLA bokningar
        $wpdb->query( "DELETE FROM {$table_name}" );
        wp_redirect(admin_url('admin.php?page=tontid-show-and-delete-bookings'));
    }
}