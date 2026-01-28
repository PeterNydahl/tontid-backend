<?php

if (!defined('ABSPATH')) exit;
date_default_timezone_set('Europe/Stockholm');

class AdminShowTodaysBookings
{

    private $show_only_evening_bookings = false;

  


    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_my_admin_menu'));
    }

    public function add_my_admin_menu()
    {
        add_menu_page(
            'Dagens bokningar',
            'Visa dagens boknignar',
            'tontid_view_menu',
            'tontid-show-todays-bookings',
            array($this, 'display_todays_bookings'),
            'dashicons-yes',
            1
        );
    }

    public function display_todays_bookings()
    {
        if(isset($_GET['only-evening']) && $_GET['only-evening']==1){
            $this->show_only_evening_bookings = true;
        } else {
            $this->show_only_evening_bookings = false;
        }
    ?>
        <style>
            .container {
                display:grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 20px;                
            }

            .wrapper--room {
                border: 1px solid hsl(0, 0%, 60%);
                border-radius: 5px;
                background-color: #cccccc;
                box-shadow: 1px 1px 1px 1px  hsl(0, 0%, 30%);
            }

            
            h3.header--room {
                text-align: center;
                color: hsl(0, 100%, 100%);
            }
            
            .wrapper--bookings{
                padding: 10px;
            }
            .wrapper--booking {
                border: 1px solid hsl(0, 100%, 100%);
                background: hsl(0, 100%, 100%);
                border-radius: 5px;
                margin-bottom: 10px;
                width: 250px;
                text-align: center;
                padding: 10px;
                font-weight: 600;
            }
            .wrapper--booking:nth-last-child(1){
                margin-bottom: 0px;
            }
            
            .header-wrapper{
                background-color: #2172b1;
                border: 1px solid #2172b1;
            }
            a.btn{
                box-shadow: 1px 1px 1px 1px  hsl(0, 0%, 30%);
                padding: 15px 20px;
                margin-bottom: 15px;
                display: inline-block;
                border-radius: 5px;
                text-decoration: none;
                font-size: medium;
                color: hsl(0, 100%, 100%);
            }
            a.btn.btn--success{
                border: 1px solid hsl(100.95deg 49.61% 39.8%);
                background-color: hsl(100.95deg 49.61% 49.8%);
            }
            a.btn.btn--warning{
                border:1px solid hsl(353.93deg 91.75% 51.96%);
                background-color: hsl(353.93deg 91.75% 61.96%);
            }

            a.btn:hover{
                filter:brightness(0.8);
            }

        </style>
        <h1>Dagens bokningar</h1>
        <?php 
            if(!$this->show_only_evening_bookings){
                echo '<a class="btn btn--warning" href="' . admin_url('admin.php?page=tontid-show-todays-bookings&only-evening=1') . '">Bara eftermiddag/kväll</a>';
            } else {
                echo '<a class="btn btn--success" href="' . admin_url('admin.php?page=tontid-show-todays-bookings') . '"> Alla dagens bokningar</a>';
            }
        ?>
        <!-- <pre>
            <?php 
                print_r($this->get_todays_manual_bookings($this->show_only_evening_bookings));
            ?>
        </pre> -->
        <div class="container">
            <?php
                $rooms_with_bookings = $this->sort_bookings_and_rooms();
                if(isset($rooms_with_bookings)){
                    foreach ($rooms_with_bookings as $room => $b) {
                        echo "<div class='wrapper--room'>";
                            echo "<div class='header-wrapper'>";
                                echo "<h3 class='header--room'>Rum $room</h3>";
                            echo "</div>";
                        echo "<div class='wrapper--bookings'>";
                            foreach ($b as $b_data) {
                                echo "<div class='wrapper--booking'>";
                                    echo "<div>$b_data[user_email]</div>";
                                    $start = (new DateTime($b_data['booking_start']))->format('H:i');
                                    $end = (new DateTime($b_data['booking_end']))->format('H:i');
                                    echo "<div>Kl $start-$end</div>";
                                echo "</div>";
                            }
                        echo "</div>";
                        echo "</div>";
                    }
                }
            ?>
        </div>
<?php
    }

    /* sort bookings and rooms
    --------------------------------------------------*/
    public function sort_bookings_and_rooms()
    {
        $rooms = [];
        $bookings = $this->get_todays_manual_bookings($this->show_only_evening_bookings);
        if(isset($bookings)) {
            foreach ($bookings as $b) {
                if (!isset($b['room_id'], $rooms)) {
                    $rooms[] = $b['room_id'];
                }
                $rooms[$b['room_id']][] = $b;
            }
            return $rooms;
        } else {
            return;
        }
    }

    /* Db
    --------------------------------------------------*/
    public function get_todays_manual_bookings($show_evening)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tontid_bookings';

        $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE booking_type = %s", "manual");
        $results_manual_bookings = $wpdb->get_results($sql, ARRAY_A);

        // sortera efter starttid
        usort($results_manual_bookings, function ($a, $b) {
            return strtotime($a['booking_start']) <=> strtotime($b['booking_start']);
        });



        $today = (new DateTime())->format('d');

        foreach ($results_manual_bookings as $b) {
            $booking_day = (new DateTime($b['booking_start']))->format('d');
            if ($booking_day === $today) {
                $todays_bookings[] = $b;
            } 
        }

        $evening_today = (new DateTime('today 16:00:00'))->getTimestamp();
        if(!empty($todays_bookings)){
            foreach($todays_bookings as $b){
                if((new DateTime($b['booking_start']))->getTimestamp() > $evening_today){
                    $todays_evening_bookings [] = $b;
                }
            }
            return $show_evening ? $todays_evening_bookings : $todays_bookings;
        } else{
            return;
        }

        
    }
}
