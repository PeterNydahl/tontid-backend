<?php

if(!defined('ABSPATH')) exit;
set_time_limit(300);
class AdminShowAndDeleteScheduleBlocks{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_post_tontid_delete_schedule_block', array($this, 'handle_delete_schedule_block'));
    }

    public function add_menu(){
        add_submenu_page(
            'tontid-schedule-blocks',
            'Visa/ta bort schemablockeringar',
            'Visa/ta bort schemablockeringar',
            'tontid_view_menu',
            'show-and-delete-schedule-blocks',
            array($this, 'display_add_and_delete_schedule_bookings')
        );
    }

    public function message_displayer(){
        if(isset($_GET['message']) && $_GET['message']=='schedule_block_deleted')
            TonTidUtils::show_notice_schedule_block_was_deleted();
        if(isset($_GET['error']) && $_GET['error']=='delete_schedule_block_failed')
            TonTidUtils::show_notice_schedule_block_failed();
    }

    public function display_add_and_delete_schedule_bookings(){
        global $wpdb;
        $table_name = $wpdb->prefix . 'tontid_bookings';
        $schedule_blocks = $wpdb->get_results("SELECT * FROM $table_name WHERE booking_type = 'schemablock' ORDER BY booking_start ASC", ARRAY_A);
        echo "<h1>Schemablockeringar</h1>";
        $this->message_displayer();
        if(!empty($schedule_blocks)){
         
        echo "<table class='form-table delete-bookings-table'>";
            echo "<tr>
                    <th>Rum</th>
                    <th>Namn</th>
                    <th>Starttid</th>
                    <th>Sluttid</th>
                    <th>Ta bort</th>
                </tr>";
                
                //dela schemblockeringar in i grupper
                $groups = [];
                $g_index = 0;
                for($i = 0; $i < count($schedule_blocks); $i++){
                    if($i === 0)
                        $groups[$g_index][] = $schedule_blocks[$i];
                    if($i > 0 && $schedule_blocks[$i-1]['booking_start'] === $schedule_blocks[$i]['booking_start'])
                        $groups[$g_index][] = $schedule_blocks[$i];
                    else{
                        if($i > 1)
                            $g_index++; 
                        $groups[$g_index][] = $schedule_blocks[$i];
                    }
                }

                //visa schemablockar som grupper
                for($i = 0; $i < count($groups); $i++){
                    echo "<tr><td>";
                        foreach($groups[$i] as $schedule_blocking){
                            echo "{$schedule_blocking['room_id']}, ";
                            $booking_ids_of_schedule_blocking [] = $schedule_blocking['booking_id'];
                        }
                    echo "</td>";
                    echo "<td>{$groups[$i][0]['lesson']}</td>
                    <td>{$groups[$i][0]['booking_start']}</td>
                    <td>{$groups[$i][0]['booking_end']}</td>
                    <td>
                       <form action='" . esc_url(admin_url('admin-post.php')) . "' method='post'>";
                            wp_nonce_field('delete_schedule_block_nonce', 'delete_schedule_block_nonce');
                            echo "<input type='hidden' name='action' value='tontid_delete_schedule_block'>";
                            foreach($booking_ids_of_schedule_blocking as $block_id){
                                echo "<input type='hidden' name='rooms_schedule_blocking[]' value='" . $block_id . "'>";
                            }
                            $booking_ids_of_schedule_blocking = [];
                            echo "<input type='submit' value='Ta bort' class='delete-button'/>
                        </form>
                    </td>
                </tr>";
                }
        } else {
            echo "<p>Inga schemablokceringar kunde hittas.</p>";
        }
    }


    public function handle_delete_schedule_block(){
        global $wpdb;
        $table_name = $wpdb->prefix . "tontid_bookings";
        // Kollar om booking_to_delete_id finns i $_POST dvs om det skickats med i formuläret. 
        
        if(
            ! isset($_POST['delete_schedule_block_nonce']) ||
            ! wp_verify_nonce($_POST['delete_schedule_block_nonce'], 'delete_schedule_block_nonce')
        ) {
            wp_die('Ogiltig nonce!');
        }


        if(isset($_POST['rooms_schedule_blocking'])){
            $booking_ids = array_map('sanitize_text_field', $_POST['rooms_schedule_blocking']);
            foreach($booking_ids as $booking_id) {
                $wpdb->delete(
                    $table_name,
                    ['booking_id' => $booking_id],
                    ['%d']
                );
            }
        } else {
            $redirect_url = add_query_arg([
                'page' => 'show-and-delete-schedule-blocks',
                'message' => 'delete_schedule_block_failed'
            ], admin_url('admin.php')
            );
            wp_safe_redirect($redirect_url);
            exit;
        }
         

        $redirect_url = add_query_arg([
            'page' => 'show-and-delete-schedule-blocks',
            'message' => 'schedule_block_deleted'
        ], admin_url('admin.php')
        );
        wp_safe_redirect($redirect_url);
        exit;
    }

}