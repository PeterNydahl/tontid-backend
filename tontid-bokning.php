<?php
/**
 * Plugin Name: Tontid, bokning av musikrum
 * Description: Ett plugin för att boka musikrum.
 * Version: 1.0
 * Author: Peter Nydahl
 */

// ABSPATH står för Absolute Path, alltså den absoluta sökvägen och är en PHP konstant i form av en sträng som pekar på WordPress-installationens rotmapp på servern.
// Man kan använda denna för säkerhet genom att kontrollera om ABSPATH är definierad. 
// Om den är det har filen öppnats via WordPress och allt är i sin ordning.
// Om inte så kanske någon försöker att öppna filen direkt via en browser och det innebär en potentiell säkerhetsrisk. 
// Så därför:
if (!defined('ABSPATH')) {
    wp_die('Du har inte rätt till direktåtkomst av den här filen');
}

/* UI (dashboard) TESTING - canvas för testning av kod! 👨‍🎨
---------------------------------------------------------*/
add_action('admin_menu', function(){
        add_menu_page(
            'Testmiljö', // page title 
            'Testmiljö', // menu title
            'tontid_view_menu',
            'test-environment', 
            'display_test_environment',
            'dashicons-admin-customizer', 
            0);
});
require_once plugin_dir_path(__FILE__) . '/admin/1-admin-test-environment.php';

/* UI (dashboard) TESTING - visa bokningar från schemafil ✈✈✈✈
---------------------------------------------------------*/
// add_action('admin_menu', function(){
//         add_menu_page(
//             'Test', 
//             'visa schemafil (test)', 
//             'tontid_view_menu',
//             'tontid-test', 
//             function() {require plugin_dir_path(__FILE__) . '/includes/test.php';}
//             ,'dashicons-airplane', 1);
// });

/* UI dashboard - lägg till delar 
---------------------------------------------------------*/
require_once plugin_dir_path(__FILE__) . '/admin/admin-todays-bookings.php';
new AdminShowTodaysBookings();
require_once plugin_dir_path(__FILE__) . '/admin/admin-upload-schedule.php';
new AdminUploadSchedule();
require_once plugin_dir_path(__FILE__) . '/admin/admin-schedule-blocks.php';
new AdminScheduleBlocks();
require_once plugin_dir_path(__FILE__) . '/admin/show-and-delete-schedule-blocks.php';
new AdminShowAndDeleteScheduleBlocks();

/*JWT (Jason Web Token) STUFF 
---------------------------------------------------------*/
//Import av bibliotek för att skapa jwt's
// Composer är ett kommandoradverktyg för att installera/hantera tredjepartsbibliotek i PHP, tex Firebase. 
// Biblioteken installeras automatiskt i en vendor-katalog. Filen vendor/autoload.php används för att smidigt 
// ladda in alla nödvändiga klasser och filer utan att behöva inkludera dem manuellt.
require_once __DIR__ . '/vendor/autoload.php';

//JWT-logik
require_once __DIR__ . '/jwt/jwt-functions.php';
require_once __DIR__ . '/jwt/auth.php';

// Inkludera API endpoints
// glob() är en PHP-funktion som returnerar en array med filnamn som matchar ett mönster.
foreach (glob(__DIR__ . '/api/*.php') as $file) {
    require_once $file;
}

/* LADDA IN MEDDELANDEN & KONTROLLFUNKTIONER
-----------------------------------------------------*/
require_once plugin_dir_path(__FILE__) . 'includes/messages-and-checks.php';

/*SKAPA ADMIN UI
-----------------------------------------------------*/
require_once plugin_dir_path(__FILE__) . 'admin/admin-ui.php';
// Om användare är admin - skapa en instans av admin-klassen för att initiera admin-menyn och hooks
if ( is_admin() ) {
    new AdminUI();
}

/* IMPORTERA FILER SOM HANTERAR RUM OCH BOKNINGAR
* Så att de blir del av detta scope och kan kommunicera med messages-and-checks.php
-----------------------------------------------------*/
require_once plugin_dir_path(__FILE__) . 'admin/admin-handle-rooms.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-functions.php';


/* SER TILL ATT RYTMUSGRUPPEN FINNS OCH ATT ALLA ANVÄNDARE ÄR MEDLEMMAR I DEN */
require_once plugin_dir_path(__FILE__) . 'includes/create-rytmus-group-memberships.php';
add_action('init', 'create_rytmus_group_membership_for_everyone');

/*SKAPA TABELLER OCH ROLLER NÄR PLUGINET AKTIVERAS
-----------------------------------------------------*/
//inkludera filer där metoderna ligger
require_once plugin_dir_path(__FILE__) . 'includes/create-tables.php';
require_once plugin_dir_path(__FILE__) . 'includes/create-roles.php';
//Orkestreringsmetod som kör metoder vid aktivering av plugin
function plugin_activation_orchestra(){
    tontid_create_group_membership_table();
    tontid_create_music_rooms_table();
    tontid_create_bookings_table();
    tontid_create_post_likes_table();
    tontid_create_roles();

    //indexering av db för ökad prestanda
    global $wpdb;
    $table_name = $wpdb->prefix . 'tontid_bookings';

    $index_exists = $wpdb->get_var("
    SHOW INDEX FROM $table_name 
    WHERE Key_name = 'idx_type_room_start_end'
    ");

    if (!$index_exists) {
        $wpdb->query("
        ALTER TABLE $table_name
        ADD INDEX idx_type_room_start_end (booking_type, room_id, booking_start, booking_end)
    ");
    }
}
//och så själva wp-hooken för aktivering av plugin:
register_activation_hook( __FILE__, 'plugin_activation_orchestra');

/* IKNLUDERA FIL SOM HANTERAR BEHÖRIGHETER TILL DASHBOARD */
require_once plugin_dir_path( __FILE__ ) . 'admin/dashboard.php';

/* LADDA IN JACASCRIPT-BIBLIOTEK FÖR VISNING AV KALENDER FÖR BOKNING I ADMINPANELEN
-----------------------------------------------------*/
require_once plugin_dir_path( __FILE__ ) . 'includes/flatpickr-calender-setup.php';
add_action( 'admin_enqueue_scripts', 'tontid_enqueue_flatpickr' );

/* LADDA IN STYLE-FIL MED CSS TILL ADMIN UI */
add_action('admin_enqueue_scripts', 'tontid_enqueue_admin_styles');

function tontid_enqueue_admin_styles() {
    wp_enqueue_style(
        'tontid-admin-style',
        plugin_dir_url(__FILE__) . 'assets/admin/admin-style.css',
        array(),
        '1.0.0'
    );
}

//GAMMAL CORS-korrigering START
//TODO - ta bort när du ser att det verkligen räcker med den nya koden under denna utkommenterade!

//CORS (står för Cross-Origin Resource Sharing) är webbläsarens sätt att skydda servrar från att ta emot data från okända källor.
//Inställnnigarna nedan låter vår plugin “prata” med frontend från andra domäner genom att tillåta CORS.
//Tillåt CORS (enkelt exempel – använd specifik origin i produktion!)

// 📌 Varför detta fungerar bättre
// ✔ send_headers körs innan WordPress skickar några headers
// → Du slipper konflikter med rest_send_cors_headers.
// ✔ Preflight OPTIONS fångas tidigt i init
// → WP behöver inte ladda hela systemet.
// ✔ Samma logik gäller för alla svar, inte bara REST API

// // 1. Hantera preflight OPTIONS
// add_action('init', function () {
//     if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

//         $allowed_origins = [
//             'https://tontid.nu',
//             'https://www.tontid.nu',
//             'http://localhost:3000'
//         ];

//         $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

//         if (in_array($origin, $allowed_origins)) {
//             header("Access-Control-Allow-Origin: $origin");
//             header("Access-Control-Allow-Credentials: true");
//             header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
//             header("Access-Control-Allow-Headers: Content-Type, Authorization");
//         }

//         status_header(200);
//         exit;
//     }
// });

// // 2. Lägg korrekta headers på ALLA svar innan WordPress skickar sina egna
// add_action('send_headers', function () {

//     $allowed_origins = [
//         'https://tontid.nu',
//         'https://www.tontid.nu',
//         'http://localhost:3000',
//     ];

//     $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

//     if (in_array($origin, $allowed_origins)) {
//         header("Access-Control-Allow-Origin: $origin");
//         header("Access-Control-Allow-Credentials: true");
//         header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
//         header("Access-Control-Allow-Headers: Content-Type, Authorization");
//     }
// });
//GAMMAL CORS-korrigering SLUT


// --- Lägger CORS på alla REST API-respons ---
// Genom att använda 'rest_pre_serve_request' säkerställs att alla REST API-svar (inklusive POST och preflight OPTIONS)
// alltid får korrekta CORS-headers innan WordPress skickar output. 
// Tidigare lösningar med 'init' och 'send_headers' är överflödiga, eftersom de antingen inte alltid körs 
// för REST API eller riskerar att köras för sent. Detta är den mest robusta metoden för att hantera CORS i WordPress REST API.
function tontid_send_cors_headers() {
    $allowed_origins = [
        'http://localhost:3000',
        'https://tontid.nu',
        'https://www.tontid.nu',
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Authorization, Content-Type");
    }
}

// --- Lägg CORS på alla REST API-respons ---
// Denna filter ska ligga innan callbacks registreras
add_filter('rest_pre_serve_request', function ($served) {
    tontid_send_cors_headers();
    return $served;
});