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

/* UI (dashboard) FÖR RESULTAT VID TESTING 
---------------------------------------------------------*/
add_action('admin_menu', function(){
        add_menu_page(
            'Test', 
            'Test', 
            'tontid_view_menu',
            'tontid-test', 
            function() {require plugin_dir_path(__FILE__) . '/includes/test.php';}
            ,'dashicons-airplane', 1);
});

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


/*SKAPA TABELLER OCH ROLLER NÄR PLUGINET AKTIVERAS
-----------------------------------------------------*/
//inkludera filer där metoderna ligger
require_once plugin_dir_path(__FILE__) . 'includes/create-tables.php';
require_once plugin_dir_path(__FILE__) . 'includes/create-roles.php';
//Orkestreringsmetod som kör metoder vid aktivering av plugin
function plugin_activation_orchestra(){
    tontid_create_music_rooms_table();
    tontid_create_bookings_table();
    tontid_create_roles();
}
//och så hooken:
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

//CORS (står för Cross-Origin Resource Sharing) är webbläsarens sätt att skydda servrar från att ta emot data från okända källor.
//Inställnnigarna nedan låter vår plugin “prata” med frontend från andra domäner genom att tillåta CORS.
//Tillåt CORS (enkelt exempel – använd specifik origin i produktion!)

// 📌 Varför detta fungerar bättre
// ✔ send_headers körs innan WordPress skickar några headers
// → Du slipper konflikter med rest_send_cors_headers.
// ✔ Preflight OPTIONS fångas tidigt i init
// → WP behöver inte ladda hela systemet.
// ✔ Samma logik gäller för alla svar, inte bara REST API

// 1. Hantera preflight OPTIONS
add_action('init', function () {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

        $allowed_origins = [
            'https://tontid.nu',
            'https://www.tontid.nu',
            'http://localhost:3000'
        ];

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array($origin, $allowed_origins)) {
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Credentials: true");
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, Authorization");
        }

        status_header(200);
        exit;
    }
});

// 2. Lägg korrekta headers på ALLA svar innan WordPress skickar sina egna
add_action('send_headers', function () {

    $allowed_origins = [
        'https://tontid.nu',
        'https://www.tontid.nu',
        'http://localhost:3000'
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
    }
});
