<?php
if(!defined('ABSPATH')) exit;

add_action('rest_api_init', function(){
    register_rest_route( 'tontid/v2', '/tontid-upload-image', [
        'methods' => ['POST'],
        'callback' => 'tontid_upload_image',
        'permission_callback' => '__return_true'
    ] );
});

function tontid_upload_image(WP_REST_Request $request){
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

    //hämta params
    $params = $request->get_params();
    $post_id = intval($params['post_id']);

    $user_email = sanitize_email($params['user_email']);

    $files = $request->get_file_params();
    if(empty($files['image'])){
        return new WP_Error('no_image', 'ingen bild skickades', ['status' => 400]);
    }

    $file = $files['image'];

    //Ladda upp filen via WordPress
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    $attachment_id = media_handle_upload('image', $post_id); 

    if (is_wp_error($attachment_id)) {
        error_log('WP Upload Error: ' . $attachment_id->get_error_message());
        return new WP_Error('upload_error', 'Fel vid uppladdning: ' . $attachment_id->get_error_message(), ['status' => 500]);
    }

    // ✅ Koppla bilden som featured image
    set_post_thumbnail($post_id, $attachment_id);

    return [
        'attachment_id' => $attachment_id,
        'url' => wp_get_attachment_url($attachment_id),
        'post_id' => $post_id,
        'user_email' => $user_email
    ];
}


