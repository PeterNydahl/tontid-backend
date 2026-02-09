<?php
if(! defined('ABSPATH')) wp_die("du har inte direkt åtkomst till denna fil");

date_default_timezone_set('Europe/Stockholm');

//skapa en custom post type
add_action('init', function(){
    register_post_type('tontid_post', [
        'label' => 'wall',
        'public' => true,
        'show_in_rest' => true,
        'supports' => ['title', 'editor', 'author']
    ]);
});

//*************************************************************************
//***************************** SKAPA INLÄGG ******************************
//*************************************************************************

//enpoint skapa inlägg
add_action('rest_api_init', function(){
    register_rest_route('tontid/v1', '/tontid-create-post', [
        'methods' => 'POST',
        'callback' => 'tontid_create_post',
        'permission_callback' => '__return_true'
        // 'permission_callback' => function(){
        //     return is_user_logged_in();
        // } 
    ]);
}); 

//callback - skapa inlägg
function tontid_create_post(WP_REST_Request $request){
    $content = wp_kses_post($request->get_param('content'));
    $user_email = wp_kses_post($request->get_param('email'));
    
    //hämta user id från db
    global $wpdb;
    $table_name_users = $wpdb->prefix . 'users';
    $sql_get_user = $wpdb->prepare("SELECT ID from $table_name_users WHERE user_email = %s", $user_email);
    $user_id = $wpdb->get_var($sql_get_user);
    if(empty($content)){
        return new WP_Error('empty', 'Inlägget hade ingen innehåll', ['status' => 400]);
    }

    $post_id = wp_insert_post([
        'post_type'    => 'tontid_post',
        'post_title'   => wp_trim_words($content, 6, '_'),
        'post_content' => $content,
        'post_status'  => 'publish',
        'post_author'  => $user_id
    ]);

    if(is_wp_error($post_id)){
        return new WP_Error('db_error', 'Kunde inte skapa inlägg', ['status' => 500]);
    }

    return rest_ensure_response([
        'success' => true,
        'post_id' => $post_id
    ]);
}


//*************************************************************************
//********************** HÄMTA INLÄGG TILL WALL ***************************
//*************************************************************************
add_action('rest_api_init', function(){
    register_rest_route('tontid/v1','/tontid_get_posts', [
        'methods' => 'GET',
        'callback' => 'tontid_get_posts',
        'permission_callback' => '__return_true'
        // TODO - lägg till detta i produktion
        // 'permission_callback' => function(){
        //     return is_user_logged_in();
        // } 
    ]);
});

function tontid_get_posts(){

    $args = [
        'post_type' => 'tontid_post',
        'post_status' => 'publish',
        'numberposts' => -1
    ];

    $wallposts = get_posts($args);
    $data = [];
    foreach($wallposts as $wallpost){
        $data [] = [
            'id' => $wallpost->ID,
            //TODO ändra i anteckningar
            'title' => $wallpost->post_title,
            'content' => $wallpost->post_content,
            'author' => get_the_author_meta('user_email', $wallpost->post_author),
            'date' => $wallpost->post_date
        ];
    } 

    global $wpdb;
    


    // Men vi vill också ha :
    // För & efternamn alternativt gmail. Hämtas via users display_name eller user_email
    // kommentarer. Hämtafrån comments. plocka fram via comment_post_ID som är FK. 
    // likes (skapa ett nytt table). Styr vi upp sedan. 

    return new WP_REST_Response([
        'status' => 'sucess',
        'data' => $data
    ]);
}
