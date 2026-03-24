<?php
if(! defined('ABSPATH')) exit;

require_once(dirname(__DIR__) . '/services/user_service.php');

//*************************************************************************
//*********************** SKAPA TAXANOMIN 'GRUPPER' ***********************
//*************************************************************************

add_action('init', function () {
// Skapar en taxonomi som heter tontid_group & kopplar den till vår egen skapade post-type tontid_post
    register_taxonomy('tontid_group', ['tontid_post'], [
        'labels' => [
            'name' => 'Grupper',
            'singular_name' => 'Grupp',
        ],
        'public' => true,
        'hierarchical' => true, // fungerar som categories (kan ha undergrupper)
        'show_in_rest' => true, // viktigt för REST & React. Gör den tillgänglig i REST API
        'rewrite' => ['slug' => 'tontid-group'],
    ]);

});

//*************************************************************************
//****************************** SKAPA GRUPP ******************************
//*************************************************************************
add_action('rest_api_init', function(){
    register_rest_route('tontid/v1', '/create-group', [
        'methods' => 'POST',
        'callback' => 'create_group',
        'permission_callback' => '__return_true'  
    ]);
});

function create_group(WP_REST_Request $request){
    $user_email = sanitize_email($request->get_param('user_email'));
    if(empty($user_email)){
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'emailadress saknas'
        ], 400);
    }
    $group_name = sanitize_text_field($request->get_param('group_name'));
    if(empty($group_name)){
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Du måste ange ett namn på gruppen.'
        ], 400);
    }
    
    $group_description = sanitize_text_field($request->get_param('group_description'));
    
    $group_taxonomi = 'tontid_group';

    //skapa grupp (term)
    $result_insert = wp_insert_term($group_name, $group_taxonomi, [
        'description' => $group_description
        ]);
        
        $user_id = getUserIdByEmail($user_email);
        //skapa meta term för ägaskap av gruppen
        $group_meta_id = add_term_meta(
            $result_insert['term_id'],
            'tontid_group_owner_user_id',
            $user_id    
        );

    return new WP_REST_Response([
        'status' => 'success',
        '$group_meta_id' => $group_meta_id
    ], 200); 

}

//*************************************************************************
//****************** TA BORT GRUPP (SKAPAD AV ANVÄNDAREN) *****************
//*************************************************************************
add_action('rest_api_init', function () {
    register_rest_route('tontid/v1', '/delete-group-made-by-user', [
        'methods' => 'POST',
        'callback' => 'delete_group_made_by_user',
        'permission_callback' => '__return_true' // byt till inloggad senare om du vill
    ]);
});
function delete_group_made_by_user(WP_REST_Request $req){
    $user_email = sanitize_email( $req->get_param('user_email') );
    $term_id = intval($req->get_param('term_id'));

    $user_id = get_user_by_email( $user_email );

    $result = wp_delete_term($term_id, 'tontid_group');

    return new WP_REST_Response([
        'status' => 'success',
        'result' => $result
    ], 200);
}


//*************************************************************************
//************* HÄMTA GRUPPER SOM EJ ÄR SKAPADE AV ANVÄNDAREN *************
//*************************************************************************

add_action('rest_api_init', function () {
    register_rest_route('tontid/v1', '/get-groups', [
        'methods' => 'POST',
        'callback' => 'tontid_get_all_groups',
        'permission_callback' => '__return_true' // byt till inloggad senare om du vill
    ]);
});

//hämta alla enskilda element ("termer") från kategorin "tontid_group"
//I WordPress-jargongen är “termer” det generella ordet för alla enskilda element i en taxonomi.
// TAXONOMI = “typ” av gruppering, t.ex. category, post_tag eller din egen tontid_group.
// TERM = ett specifikt värde i taxonomin, t.ex. “Nyheter”, “Sport”, “Grupp A”, “Grupp B”.
function tontid_get_all_groups(WP_REST_Request $req) {
    $user_email = sanitize_email($req->get_param('user_email'));

    $groups = get_terms([
        'taxonomy' => 'tontid_group',
        'hide_empty' => false, // visa även grupper utan inlägg
    ]);
    
    //lägg till de grupper som inte användaren är ägare till
    $user_id = getUserIdByEmail($user_email);
    $data = [];
    foreach ($groups as $group) {
        if(get_term_meta( $group->term_id, 'tontid_group_owner_user_id', true ) !== $user_id){
            $data[] = [
                'id' => $group->term_id,
                'name' => $group->name,
                'slug' => $group->slug,
            ];
        }
    }

    return new WP_REST_Response([
        'status' => 'success',
        'data' => $data,
        'user_email' => $user_email
    ]);
}

//*************************************************************************
//***************** HÄMTA GRUPPER SKAPADE AV ANVÄNDAREN *******************
//*************************************************************************

add_action('rest_api_init', function () {
    register_rest_route('tontid/v1', '/get-groups-made-by-user', [
        'methods' => 'POST',
        'callback' => 'get_groups_made_by_user',
        'permission_callback' => '__return_true' // byt till inloggad senare om du vill
    ]);
});

function get_groups_made_by_user(WP_REST_Request $req){
    $user_email = sanitize_email( $req->get_param('user_email'));
    $data = [];

    //hämta alla grupper
    $groups = get_terms([
        'taxonomy' => 'tontid_group',
        'hide_empty' => false, // visa även grupper utan inlägg
    ]);

    //filtrera ut grupper som är skapade av användaren
    $user_id = getUserIdByEmail($user_email);
    $data = [];
    foreach($groups as $group){
        if($user_id === get_term_meta( $group->term_id, 'tontid_group_owner_user_id', true )){
            $data [] = $group;
        }
    }

    return new WP_REST_Response([
        'status' => 'success',
        'data' => $data
    ],200);
}



//*************************************************************************
//********************** SKAPA MEDLEMSSKAP FÖR GRUPP **********************
//*************************************************************************
add_action('rest_api_init', function(){
    register_rest_route('tontid/v1', '/create-group-membership', [
        'methods' => 'POST',
        'callback' => 'create_group_membership',
        'permission_callback' => '__return_true'  
    ]);
});

function create_group_membership(WP_REST_Request $request){
    $group_id = intval($request->get_param('group_id'));
    $user_email = sanitize_text_field($request->get_param('user_email'));
    $group_slug = sanitize_text_field($request->get_param('group_slug'));
    $group_name = sanitize_text_field($request->get_param('group_name'));
    
    if(empty($group_id) || empty($user_email)){
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Grupp id eller användarmail saknas.'
        ], 400);
    }

    global $wpdb;

    $user_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT `ID` FROM $wpdb->users
            WHERE `user_email` = %s
            ", $user_email)
    );

    //kolla om användaren redan är medlem
    $table_name_membership = $wpdb->prefix . 'tontid_group_membership';
    $user_already_member = $wpdb->get_var(
        $wpdb->prepare("SELECT * FROM $table_name_membership
                        WHERE `group_id` = %d
                        AND `user_id` = %s",
                        $group_id, 
                        $user_id)
        );

    if($user_already_member) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Du är redan medlem i denna grupp!'
        ], 403);
    }

    $wpdb->insert(
        $table_name_membership,
        [
            'group_id' => $group_id,
            'user_id' => $user_id,
            'group_slug' => $group_slug,
            'group_name' => $group_name
        ]
    );
    

    return new WP_REST_Response([
        'status' => 'success'
    ], 200);   
}

//*************************************************************************
//********************* TA BORT MEDLEMSSKAP I GRUPP ***********************
//*************************************************************************
add_action('rest_api_init', function(){
    register_rest_route('tontid/v1', '/delete-group-membership', [
        'methods' => 'POST',
        'callback' => 'delete_group_membership',
        'permission_callback' => '__return_true'  
    ]);
});

function delete_group_membership(WP_REST_Request $request){
    $group_id = intval($request->get_param('group_id'));
    $user_email = sanitize_text_field($request->get_param('user_email'));

    global $wpdb;

    $user_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT `ID` FROM $wpdb->users
            WHERE `user_email` = %s", $user_email
        )
    );

    //ta bort medlemskap
    $wpdb->delete(
        $wpdb->prefix . 'tontid_group_membership',
        ['group_id' => $group_id,
        'user_id' => $user_id],
        ['%d','%s']
    );

    return new WP_REST_Response([
        'status' => 'success',
        'user_email' => $user_email,
        'group_id' => $group_id,
        'user_id' => $user_id
    ]);
}

//*************************************************************************
//************************* KONTROLLERA MEDLEMSKAP ************************
//*************************************************************************

add_action('rest_api_init', function(){
    register_rest_route('tontid/v1', '/check-if-user-is-group-member', [
        'methods' => 'POST',
        'callback' => 'check_if_user_is_group_member',
        'permission_callback' => '__return_true'
    ]);    
});

function check_if_user_is_group_member(WP_REST_Request $request){
    $user_email = sanitize_text_field($request->get_param('user_email'));
    
    // TODO - ta bort
    // $group_id = intval($request->get_param('group_id'));
    // if(empty($group_id)){
    //     return new WP_REST_Response([
    //         'status' => 'error',
    //         'message' => 'Grupp id saknas.'
    //     ], 400);
    // }

    if(empty($user_email)){
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Emailadress saknas.'
        ], 400);
    }

    global $wpdb;
    //hämta user-id
    $user_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT `ID` FROM $wpdb->users
            WHERE `user_email` = %s",
            $user_email 
        )
    );
    
    //kolla om använandare är medlem
    //hämta array med alla medlemskap
    $table_memberships = $wpdb->prefix . 'tontid_group_membership';
    $group_membership_ids_raw = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT `group_id` FROM $table_memberships  
            WHERE `user_id` = %d", $user_id), ARRAY_A
    );

    $group_membership_ids  = [];
    foreach($group_membership_ids_raw as $g){
        $group_membership_ids [] = intval($g['group_id']);
    }

    return new WP_REST_Response([
        'status' => 'success',
        'data' => $group_membership_ids
    ],200);
}
