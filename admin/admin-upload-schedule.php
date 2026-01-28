<?php

/*************************************************************************************
                           ADMIN MENY - LADDA UPP SCHEMAFIL
*************************************************************************************/

//importerar fil som läser in bokningar från den uppladdade filen
$path = dirname(__DIR__, 1) . '/includes/add-schema-bookings.php';
if (file_exists($path)) {
    require_once $path;
} else {
    wp_die('Filen saknas: ' . $path);
}

class AdminUploadSchedule {
    public function __construct() {
        // Lägger till menyn i adminpanelen
        add_action('admin_menu', array($this, 'add_upload_schedule'));
        // Hanterar uppladdningen av fil via admin-post
        add_action('admin_post_tontid_handle_upload', array($this, 'handle_upload'));
    }

    // Lägger till submenu under huvudmenyn "TonTid"
    public function add_upload_schedule() {
        add_submenu_page(
            'tontid',                         // Parent slug
            'Ladda upp schema',               // Sidtitel
            'Ladda upp schema',               // Menynamn
            'tontid_view_menu',               // Capability
            'tontid-upload-schedule',         // Slug
            array($this, 'display_upload_schedule') // Callback
        );
    }

    // Visar uppladdningsformuläret i adminpanelen
    public function display_upload_schedule() {
        ?>
        <div class="wrap">
            <h2>Ladda upp schemafil</h2>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="file" name="tontid_schedule_file" required>
                
                <!-- Security nonce -->
                <?php wp_nonce_field('tontid_upload_schedule', 'tontid_upload_nonce'); ?>
                
                <!-- Anger vilken action som ska köras -->
                <input type="hidden" name="action" value="tontid_handle_upload">
                
                <?php submit_button('Ladda upp'); ?>
            </form>

            <?php 
            if (isset($_GET['message']) && $_GET['message'] === 'success') {
                echo '<div class="notice notice-success"><p>Filen laddades upp!</p></div>';
            }
            ?>
        </div>
        <?php
    }

    // Callback för att hantera filuppladdning
    public function handle_upload() {
        //Ta bort alla existerande filer
        $dir = __DIR__ . "/uploads";
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            print_r($file);
            echo "<p>Filen $file tas nu bort!</p>";
            unlink($dir . '/' . $file);
        }
        
        // 1. Kolla nonce
        if (!isset($_POST['tontid_upload_nonce']) || !wp_verify_nonce($_POST['tontid_upload_nonce'], 'tontid_upload_schedule')) {
            wp_die('Ogiltig säkerhetskontroll.');
        }

        // 2. Kolla att filen verkligen skickats
        if (empty($_FILES['tontid_schedule_file']['name'])) {
            wp_die('Ingen fil uppladdad.');
        }

        // 3. Definiera pluginets uploads-mapp
        $plugin_upload_dir = plugin_dir_path(__FILE__) . 'uploads/';

        // Skapa mappen om den inte finns
        if (!file_exists($plugin_upload_dir)) {
            if (!wp_mkdir_p($plugin_upload_dir)) {
                wp_die('Kunde inte skapa uploads-mappen. Kontrollera rättigheter.');
            }
        }

        // 4. Flytta filen till uploads-mappen
        $uploaded_file = $_FILES['tontid_schedule_file'];
        $destination = $plugin_upload_dir . basename($uploaded_file['name']);

        // Debug: logga filinfo
        error_log('Uppladdning påbörjad: ' . print_r($uploaded_file, true));
        error_log('Destination: ' . $destination);

        if (move_uploaded_file($uploaded_file['tmp_name'], $destination)) {
            // Lyckad uppladdning
            wp_redirect(admin_url('admin.php?page=tontid-upload-schedule&message=success'));
            print_r(AddSchemaBookings::orchestra());
            exit;
        } else {
            // Fel vid uppladdning
            error_log('Fel vid move_uploaded_file. Filens tmp_name: ' . $uploaded_file['tmp_name']);
            wp_die('Fel vid uppladdning av fil. Kontrollera att plugin-mappen är skrivbar.');
        }
    }
}
