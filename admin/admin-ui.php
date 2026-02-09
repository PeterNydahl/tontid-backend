<?php

require_once plugin_dir_path(__FILE__) . 'admin-handle-rooms.php';
require_once plugin_dir_path(__FILE__) . 'admin-handle-bookings.php';
require_once plugin_dir_path(__FILE__) . 'admin-show-schedule.php';
require_once plugin_dir_path(__FILE__) . 'admin-show-and-delete-bookings.php';
// require_once plugin_dir_path(__FILE__) . 'admin-upload-schedule.php';
// require_once plugin_dir_path(__FILE__) . 'admin-todays-bookings.php';
// require_once plugin_dir_path(__FILE__) . 'admin-schedule-blocks.php';
// require_once plugin_dir_path(__FILE__) . 'show-and-delete-schedule-blocks.php';



class AdminUI{
    // Från PHP 8.2 och framåt är "dynamic properties" deprecated.
    // Man kan inte längre skapa egenskaper genom att göra $this->foo = 'bar' om $foo inte är definierad i klassen.
    // Properties måste deklareras i klassen innan de tilldelas värden i konstruktorn.

    private $adminShowSchedule;
    
    public function __construct(){
        //hookar i wp funktion då adminpanelen byggs upp och anropar callbackmetod som lägger till programmets huvudmenyn
        // add_action('admin_menu', array($this, 'add_admin_menus'));
        // instansierar extern klass och sparar som property i denna klass så att vi kan anropa metoder från det externa objektet
        $this->adminShowSchedule = new AdminShowSchedule();
        //Nedanstående klasser ansåg jag överflödiga för rytmus sthlm. Men jag behåller den utkommenterade 
        //koden om det skulle bli aktuellt att använda i ett annat sammanhang.
        // new AdminHandleBookings();
        // new AdminShowAndDeleteBookings();
        // new AdminHandleRooms();

        // nedansåtende flyttade jag till huduvfilen
        // new AdminShowTodaysBookings();
        // new AdminUploadSchedule();
        // new AdminScheduleBlocks();
        // new AdminShowAndDeleteScheduleBlocks();
    }

    //Lägger till huvudmenyn
    public function add_admin_menus(){
            //wp funktion som lägger till programmets meny
            add_menu_page(
                // Sidtitel för title tags, dvs fliken i browsern
                'TonTid', 
                //Menynamn
                'TonTid', 
                // Anger "Capability" för inloggad roll
                'tontid_view_menu', 
                //slug i url
                'tontid', 
                // 
                // Eftersom vi befinner oss i metoden add_menu_page och allt är parametrar så blir det alltså en callback
                // på PHPiska anger man en array om man skickar in en metod sfrån ett objekt.
                // Alltså.. array($objekt, 'metodnamn') betyder: "Kör den här metoden på det här objektet." Det är standard i WordPress om callbacken är en metod i en klass, måste WordPress veta både objektet och metodnamnet. Då använder man en array. Gäller som sagt också php.
                // Och metoden genererar "Visa Schema" och TonTid har ingen "egen meny"
                // Callbacken anropas när menyn är aktiverad och anger alltså vad som ska visas i samband med din admin-sida.
                array($this->adminShowSchedule, 'display_show_schedule'), 
                'dashicons-format-audio', // Dashicon
                2, // position i menyn i dashboard
        );
    }
}
