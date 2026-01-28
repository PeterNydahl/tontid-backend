<?php
//för att kunna använda dbDelta()
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

class AdminHandleRooms {
    public function __construct(){
        add_action( 'admin_post_tontid_add_room', array($this, 'tontid_handle_add_room_form'));
        add_action( 'admin_post_tontid_delete_room', array($this, 'tontid_handle_delete_room'));
        add_action('admin_menu', array($this, 'add_handle_rooms_menu'));
    }

    /*************************************************************************************
                                 ADMIN MENY - HANTERA RUM
    *************************************************************************************/

    //Skapa meny i dahsboard
    public function add_handle_rooms_menu(){
        add_submenu_page(
            'tontid',
            'Hantera rum',
            'Hantera rum',
            'tontid_view_menu',
            'tontid-manage-rooms',
            array($this, 'displayManageRooms'),
            );
            // Förhindra att huvudmenyn dyker upp som submeny (vilket sker per default)
            remove_submenu_page( 'tontid', 'tontid' );
        }
    
    
    /* admin UI för att hantera rum (lägga till rum + ta bort rum)
    --------------------------------------------------- */
    public function displayManageRooms(){
        $this->displayAddRoomPage();
        $this->displayDeleteRoomPage();
    }

    /*************************************************************************************
                                       LÄGG TILL RUM
    *************************************************************************************/

    /* Lägg till rum - UI/INPUT 
    --------------------------------------------------- */

    public function displayAddRoomPage() {
        ?>
        <div class="wrap">
            <h1>Lägg till ett rum</h1>

            <?php
            if ( isset( $_GET['error'] ) && $_GET['error'] === 'duplicate_id' ) {
                TonTidUtils::show_notice_id_already_exists();
            } elseif ( isset( $_GET['message'] ) && $_GET['message'] === 'room_added' ) {
                TonTidUtils::show_notice_room_was_added();
            } elseif ( isset( $_GET['error'] ) && $_GET['error'] === 'database_error' ) {
                TonTidUtils::show_notice_room_not_added();
            } elseif ( isset( $_GET['error'] ) && $_GET['error'] === 'missing_id' ) {
                TonTidUtils::show_notice_missing_id();
            }
            ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="tontid_add_room">
                <?php wp_nonce_field( 'tontid_add_room_nonce', 'tontid_add_room_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="room_id">Rums-ID</label>
                        </th>
                        <td>
                            <input type="text" id="room_id" name="room_id" value="" class="regular-text" required>
                            <p class="description">Ange ett unikt ID för musiksalen.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="equipment">Utrustning</label>
                        </th>
                        <td>
                            <input type="checkbox" id="pa_system" name="equipment[]" value="pa system">
                            <label for="pa_system">PA System</label><br>
                            <input type="checkbox" id="monitors" name="equipment[]" value="monitors">
                            <label for="monitors">Monitorer</label><br>
                            <input type="checkbox" id="piano" name="equipment[]" value="piano">
                            <label for="piano">Piano</label><br>
                            <input type="checkbox" id="synth" name="equipment[]" value="synth">
                            <label for="synth">Synth</label><br>
                            <p class="description">Välj den tillgängliga utrustningen i detta rum.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="room_description">Beskrivning</label>
                        </th>
                        <td>
                            <input type="text" id="room_description" name="room_description" value="" class="regular-text" required>
                            <p class="description">Här kan du ange extra information om rummet!</p>
                        </td>
                    </tr>
                
                </table>
                

                <?php submit_button( 'Lägg till rum' ); ?>
            </form>
        </div>
        <?php
    }

    /* Lägg till rum - databashantering
    --------------------------------------------------- */
    public function tontid_handle_add_room_form() {
        // Verifiera säkerhetsnyckeln som genererats av nonce funktionen
        if ( ! isset( $_POST['tontid_add_room_nonce'] ) || ! wp_verify_nonce( $_POST['tontid_add_room_nonce'], 'tontid_add_room_nonce' ) ) {
            wp_nonce_ays( 'tontid_add_room_nonce' );
            exit;
        }

        if ( isset( $_POST['room_id'] ) && ! empty( $_POST['room_id'] ) ) {
            $room_id = sanitize_text_field( $_POST['room_id'] );
            $equipment = isset( $_POST['equipment'] ) ? array_map( 'sanitize_text_field', $_POST['equipment'] ) : array();
            $room_description = sanitize_text_field( $_POST['room_description'] );

            // kontrollera om rummet redan existerar
            if ( $this->tontid_check_if_room_id_exists( $room_id ) ) {
                // Om ID:t redan finns, skicka tillbaka användaren med ett felmeddelande
                $redirect_url = add_query_arg( 'error', 'duplicate_id', admin_url( 'admin.php?page=tontid-manage-rooms' ) );
                wp_safe_redirect( $redirect_url );
                exit;
            } else {
                // Om ID:t är unikt, lägg till rummet i databasen
                $equipment_string = implode( ', ', $equipment ); // Konvertera utrustnings-array till en sträng
                global $wpdb;
                $table_name = $wpdb->prefix . 'tontid_music_rooms';

                $insert_result = $wpdb->insert(
                    $table_name,
                    array(
                        'room_id' => $room_id,
                        'room_equipment' => $equipment_string,
                        'room_description' => $room_description
                    ),
                    array(
                        '%s',
                        '%s',
                        '%s'
                    )
                );

                if ( $insert_result ) { // Kontrollera om insert returnerade ett "truthy" värde (antal rader påverkade > 0 vid lyckad insert)
                    // Om insättningen lyckades, skicka tillbaka användaren med ett success-meddelande
                    $redirect_url = admin_url( 'admin.php?page=tontid-manage-rooms&message=room_added' );
                    wp_safe_redirect( $redirect_url );
                    exit;
                } else {
                    // Om något gick fel med insättningen
                    $redirect_url = admin_url( 'admin.php?page=tontid-manage-rooms&error=database_error' );
                    wp_safe_redirect( $redirect_url );
                    exit;
                }
            }
        } else {
            // Om inget rum-ID skickades
            $redirect_url = admin_url( 'admin.php?page=tontid-manage-rooms&error=missing_id' );
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }

    public function tontid_check_if_room_id_exists( $room_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tontid_music_rooms';
        $result = $wpdb->get_var( $wpdb->prepare( "SELECT room_id FROM $table_name WHERE room_id = %s", $room_id ) );
        return ( $result !== null );
    }
    
    /*************************************************************************************
                                       TA BORT RUM
    *************************************************************************************/

    /* Ta bort rum - UI/INPUT 
    --------------------------------------------------- */
    public function displayDeleteRoomPage() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tontid_music_rooms';
        $rooms = $wpdb->get_results( "SELECT room_id FROM $table_name" ); // Hämta ID och namn på rum
        ?>
        <div class="wrap">
            <h1>Ta bort ett rum</h1>
            <?php if ( isset( $_GET['message'] ) && $_GET['message'] === 'room_deleted' )
                    : TonTidUtils::show_notice_room_was_deleted();
                elseif ( isset( $_GET['error'] ) && $_GET['error'] === 'delete_failed' )
                    : TonTidUtils::show_notice_error();
                elseif ( isset( $_GET['error'] ) && $_GET['error'] === 'missing_id' ) 
                    : TonTidUtils::show_notice_error();
                endif; ?>

            <form class="form-table table-delete-room" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="tontid_delete_room">
                <?php wp_nonce_field( 'tontid_delete_room_nonce', 'tontid_delete_room_nonce' ); ?>

                <table class="form-table">
                    <tr>
     <select name="room_id_to_delete" id="room_id_to_delete" required>
                                <option value="">Välj ett rum</option>
                                <?php if ( ! empty( $rooms ) ) : ?>
                                    <?php foreach ( $rooms as $room ) : ?>
                                        <option value="<?php echo esc_attr( $room->room_id ); ?>">
                                            <?php echo esc_html( $room->room_id ) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <option value="" disabled>Inga rum har lagts till ännu</option>
                                <?php endif; ?>
                            </select>
                            
                            <input type="submit" class="delete-room-button"value="Ta bort rum">

                            
                      
                    </tr>
               
                    <tr>

                    </tr>
                </table>
            </form>
        </div>
        <?php
    }

    /* Ta bort rum - databashantering
    --------------------------------------------------- */
    public function tontid_handle_delete_room() {
        // Verifiera säkerhetsnyckeln från nonce
        if ( ! isset( $_POST['tontid_delete_room_nonce'] ) || ! wp_verify_nonce( $_POST['tontid_delete_room_nonce'], 'tontid_delete_room_nonce' ) ) {
            wp_nonce_ays( 'tontid_delete_room_nonce' ); // Visar ett felmeddelande om noncen inte är korrekt
            exit; // Avsluta skriptet
        }

        // Kontrollera om ett rums-ID har skickats med POST-metoden
        if ( isset( $_POST['room_id_to_delete'] ) && ! empty( $_POST['room_id_to_delete'] ) ) {
            // "Sanera" det inkommande rums-ID:t för att förhindra en potentiell SQL-injektion
            $room_id_to_delete = sanitize_text_field( $_POST['room_id_to_delete'] );

            global $wpdb;

            // Ange namnet på databastabellen för musiksalar
            $table_name = $wpdb->prefix . 'tontid_music_rooms';

            $deleted = $wpdb->delete(
                $table_name, // Tabellens namn
                array( 'room_id' => $room_id_to_delete ),
                array( '%s' )
            );

            // Kontrollera om borttagningen lyckades
            if ( $deleted ) {
                // Om borttagningen lyckades, skapa en omdirigerings-URL med ett success-meddelande (meddelandet ligger i admin-functions.php)
                $redirect_url = admin_url( 'admin.php?page=tontid-manage-rooms&message=room_deleted' );
            } else {
                // Om borttagningen misslyckades, skapa en omdirigerings-URL med ett felmeddelande (meddelandet ligger i admin-functions.php)
                $redirect_url = admin_url( 'admin.php?page=tontid-manage-rooms&error=delete_failed' );
            }

            // Utför omdirigeringen tillbaka till sidan för att ta bort rum
            wp_safe_redirect( $redirect_url );
            exit; // Avsluta skriptet efter omdirigeringen

        } else {
            // Om inget rums-ID valdes, skicka tillbaka användaren med ett varningsmeddelande
            $redirect_url = admin_url( 'admin.php?page=tontid-manage-rooms&error=missing_id' );
            wp_safe_redirect( $redirect_url );
            exit; // Avsluta skriptet
        }
    }

    
}    
