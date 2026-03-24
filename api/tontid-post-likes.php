<?php 

if(!defined('ABSPATH')) exit;

//*************************************************************************
//********************* HÄMTA LIKES TILL ETT INLÄGG ***********************
//*************************************************************************

add_action('rest_api_init', function(){
    register_rest_route( 'tontid', '/v1/tontid-get-post-likes', [
        'methods' => 'POST',
        'callback' => 'get_post_likes',
        'permission_callback' => '__return_true'
    ]);
});

function get_post_likes(WP_REST_Request $request){
    $post_id = $request->get_param('post_id');

    global $wpdb;
    $table_name_likes = $wpdb->prefix . 'tontid_post_likes';
    $likes = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name_likes
                WHERE `post_id` = %d",
            $post_id
        ), ARRAY_A
    );

    return new WP_REST_Response(
        [
            'status' => 'success',
            'post_id' => $post_id,
            'likes' => $likes,
        ],
        200
    );
}

//*************************************************************************
//***************** HÄMTA LIKES FRÅN AKTUELL ANVÄNDARE ********************
//*************************************************************************

add_action('rest_api_init', function(){
    register_rest_route( 'tontid/v1', '/get-post-likes-made-by-user', [
        'methods' => 'POST',
        'callback' => 'get_post_likes_made_by_user',
        'permission_callback' => '__return_true'
    ]);
});

function get_post_likes_made_by_user(WP_REST_Request $request){
    $params = $request->get_json_params();
    $user_email = $params['user_email'];

    //hämta user id
    global $wpdb;
    $user_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT ID
            FROM {$wpdb->users}
            WHERE `user_email` = %s", $user_email
        )
    ); 

    //hämta inlägg som användaren gillat
    $table_name_likes = $wpdb->prefix . 'tontid_post_likes';
    $posts_liked_by_user_raw = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT post_id
            FROM $table_name_likes
            WHERE `author_id` = %d",
            $user_id),
            ARRAY_A
    );

    
    $posts_liked_by_user_cleaned = array_map(function($item){
        return (int)$item['post_id'];
    }, $posts_liked_by_user_raw);

    return new WP_REST_Response([
        'status' => 'sueccess',
        'posts_liked_by_user' => $posts_liked_by_user_cleaned
    ], 200);
}

 
//*************************************************************************
//*********************** SKAPA LIKE TILL INLÄGG **************************
//*************************************************************************

add_action('rest_api_init', function () {
    register_rest_route('tontid/v1', '/tontid-create-post-like', [
        'methods' => 'POST',
        'callback' => 'create_post_like',
        'permission_callback' => '__return_true'
    ]);
});

function create_post_like(WP_REST_Request $request) {
    global $wpdb;
    //bästa att hämta alla params först och sedan plocka från den arrayen
    $params = $request->get_json_params();
    $user_email = $params['user_email'] ?? null;
    $post_id = isset($params['post_id']) ? (int) $params['post_id'] : 0;

    // error_log("R-body" . $request->get_body());

    if (!$user_email || !$post_id) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Missing user_email or post_id'
        ], 400);
    }

    // Hämta user id
    $user_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT ID FROM {$wpdb->users} WHERE user_email = %s",
            $user_email
        )
    );

    error_log("userid test: $user_id");

    if (!$user_id) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'User id not found'
        ], 404);
    }

    //like logiken här...
    $table_name_likes = $wpdb->prefix . 'tontid_post_likes';
    $like_exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*)
            FROM $table_name_likes
            WHERE `author_id` = %d
            AND `post_id` = %d", 
            $user_id, $post_id)
    );
    
    if($like_exists > 0){
        //ta bort like
        $wpdb->delete(
            $table_name_likes,
            ['post_id' => $post_id],
            ['%s']
        );

        return new WP_REST_Response([
            "status" => "success",
            "message" => "Like borttagen."
        ], 403);
    }

    $wpdb->insert(
        $table_name_likes,
        [
            'author_id' => $user_id,
            'post_id' => $post_id   
        ], 
        [
            '%d',
            '%d'
        ]
    );

    return new WP_REST_Response([
        'status' => 'success',
        'user_id' => $user_id,
        'post_id' => $post_id
    ], 200);
}

