<?php
//*************************************************************************
//**************************** SKAPA KOMMENTAR ****************************
//*************************************************************************

add_action('rest_api_init', function() {
    register_rest_route('tontid/v1', '/create-comment', [
        'methods' => 'POST',
        'callback' => 'create_comment', 
        'permission_callback' => '__return_true'
    ]);
});

function create_comment(WP_REST_Request $request){

    $post_id = (int) $request->get_param('post_id');

    if (! get_post($post_id)) {
        return new WP_Error(
            'invalid_post',
            'Inlägget finns inte',
            ['status' => 404]
        );
    }

    //Lägg eventuellt till om man ska kunna stänga av kommentarer för ett inlägg
    // if (! comments_open($post_id)) {
    //     return new WP_Error(
    //         'comments_closed',
    //         'Kommentarer är avstängda för detta inlägg',
    //         ['status' => 403]
    //     );
    // }

    $commentdata = [
        'comment_post_ID'      => $post_id,
        'comment_author'       => sanitize_text_field($request->get_param('author')),
        'comment_author_email' => sanitize_email($request->get_param('email')),
        'comment_content'      => sanitize_textarea_field($request->get_param('content')),
        'comment_parent'       => 0,
        'user_id'              => 1, //TODO ÄNDRA SEN, hämta via email i db
        'comment_approved'     => 1 //1 för att godkänna automatiskt 0 nej
    ];

    $comment_id = wp_insert_comment( $commentdata );

    if (! $comment_id) {
        return new WP_Error(
            'comment_failed',
            'Det gick tyvärr inte att skapa en kommentar!',
            [ 'status' => 500]
        );
    }
    //EXEMPEL I FRONTEND:
    // if (error.code === 'comment_failed') {
    //     // visa felmeddelande   
    // }

    return new WP_REST_Response([
        'success' => true,
        'comment_id' => $comment_id
    ], 200);
}

//*************************************************************************
//*************************** VISA KOMMENTARER ****************************
//*************************************************************************
// IN:post_id UT:alla kommentarer som är kopplade till detta id
add_action('rest_api_init', function() {
    register_rest_route('tontid/v1', '/get-tontid-post-comments', [
        'methods' => 'POST',
        'callback' => 'get_tontid_post_comments', 
        'permission_callback' => '__return_true'
    ]);
});

function get_tontid_post_comments(WP_REST_Request $request){
    //hämta alla kommentarer kopplade till ett post_id
    $post_id = $request->get_param('post_id');

    global $wpdb;
    $table_name = $wpdb->prefix . 'comments';
    $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE comment_post_ID = %d", $post_id);
    $results = $wpdb->get_results($sql, ARRAY_A);

    return new WP_REST_Response([
        'success' => true,
        'data' => $results
    ], 200);
}