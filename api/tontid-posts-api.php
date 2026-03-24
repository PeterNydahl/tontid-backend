<?php
if (! defined('ABSPATH')) wp_die("du har inte direkt åtkomst till denna fil");

require_once(dirname(__DIR__) . "/services/user_service.php");

date_default_timezone_set('Europe/Stockholm');

//skapa en custom post type
add_action('init', function () {
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
add_action('rest_api_init', function () {
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
function tontid_create_post(WP_REST_Request $request)
{
    $content = wp_kses_post($request->get_param('content'));
    $user_email = wp_kses_post($request->get_param('email'));
    $group_slug = sanitize_text_field($request->get_param('group_slug')); // ✅ Ta emot slug

    //hämta user id från db
    global $wpdb;
    $table_name_users = $wpdb->prefix . 'users';
    $sql_get_user = $wpdb->prepare("SELECT ID from $table_name_users WHERE user_email = %s", $user_email);
    $user_id = $wpdb->get_var($sql_get_user);

    if (empty($content)) {
        return new WP_Error('empty', 'Inlägget hade ingen innehåll', ['status' => 400]);
    }

    $post_id = wp_insert_post([
        'post_type'    => 'tontid_post',
        'post_title'   => wp_trim_words($content, 6, '_'),
        'post_content' => $content,
        'post_status'  => 'publish',
        'post_author'  => $user_id
    ]);

    if (is_wp_error($post_id)) {
        return new WP_Error('db_error', 'Kunde inte skapa inlägg', ['status' => 500]);
    }

    // Om en slug skickades, koppla inlägget till term med den sluggen
    if ($group_slug) {
        wp_set_object_terms(
            $post_id,         // ID för inlägget
            $group_slug,      // slug istället för ID
            'tontid_group',   // taxonomin
            false             // false = ersätt existerande grupper, true = lägg till
        );
    }

    // Returnera info direkt om inlägget + kopplade grupper
    $assigned_terms = wp_get_post_terms($post_id, 'tontid_group');
    return rest_ensure_response([
        'success' => true,
        'post_id' => $post_id,
        'groups'  => $assigned_terms, // array med namn, slug, ID osv.
    ]);
}

//*************************************************************************
//********************* HÄMTA INLÄGG BASERAT PÅ GRUPP *********************
//*************************************************************************

add_action('rest_api_init', function(){
    // ⚡ Registrerar en dynamisk REST API-endpoint
    // URL: /wp-json/tontid/v1/posts-by-group/<slug>
    // (?P<slug>[a-zA-Z0-9-]+) → regex som fångar upp <slug> från URL och skickar den som $request['slug']
    // Exempel: /wp-json/tontid/v1/posts-by-group/fotboll → $request['slug'] = 'fotboll'
    // Callback-funktionen kan sedan använda denna slug för att filtrera inlägg efter grupp
    register_rest_route('tontid/v1', '/posts-by-group/(?P<slug>[a-zA-Z0-9-]+)', [
        'methods' => 'POST',
        'callback' => 'tontid_get_posts_by_group',
        'permission_callback' => '__return_true'
    ]);
});

/**
 * Hämtar inlägg för en specifik grupp och inkluderar metadata som bilder och gruppinfo.
 */
function tontid_get_posts_by_group(WP_REST_Request $request) {
    // 1. Tvätta inkommande slug från URL:en för att förhindra SQL-injections
    $slug = sanitize_text_field($request['slug']);
    $user_email = sanitize_text_field($request['user_email']);

    //om sluggen är wall, via inlägg från de grupper som användaren är medlem i.
    if($slug === 'wall') {
        $user_id = (getUserIdByEmail($user_email));
        $membership_slugs = getMembershipSlugs($user_id);        
        $slug = $membership_slugs;
    }

    
    // 2. Förbered argumenten för databassökningen
    $args = [
        'post_type'   => 'tontid_post',   // Endast din anpassade inläggstyp
        'post_status' => 'publish',       // Endast publicerade inlägg
        'tax_query'   => [                // Filtrera via din junction-tabell
            [
                'taxonomy' => 'tontid_group',
                'field'    => 'slug',
                'terms'    => $slug,
            ]
        ]
    ];

    // 3. Hämta inläggen baserat på ovanstående filter
    $posts = get_posts($args);
    $data = [];

    // 4. Loopa igenom varje inlägg för att bygga ett snyggt JSON-svar
    foreach ($posts as $post) {

        // --- Hämta Utvald Bild (Featured Image) ---
        $thumbnail_id = get_post_thumbnail_id($post->ID);
        // Om bild finns, hämta dess URL, annars sätt till null
        $thumbnail_url = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : null;

        // --- Hämta Grupper (Taxonomy Terms) ---
        // Här gör vi en extra slagning i junction-tabellen för att se ALLA grupper inlägget har
        $groups = wp_get_post_terms($post->ID, 'tontid_group');
        $group_data = [];

        // Kontrollera att hämtningen gick bra (att taxonomin faktiskt finns)
        if (!is_wp_error($groups)) {
            foreach ($groups as $group) {
                $group_data[] = [
                    'id'   => $group->term_id,
                    'name' => $group->name,
                    'slug' => $group->slug
                ];
            }
        }

        // --- Paketera all data för detta inlägg ---
        $data[] = [
            'id'                 => $post->ID,
            'title'              => $post->post_title,
            'content'            => $post->post_content,
            // Hämta författarens e-post (istället för bara ett dolt ID)
            'author'             => get_the_author_meta('user_email', $post->post_author),
            'date'               => $post->post_date,
            'featured_media_url' => $thumbnail_url,
            // 'groups'             => $group_data // Här skickar vi med arrayen med grupp-info
            'group_data'         => $group_data // Här skickar vi med arrayen med grupp-info
        ];
    }

    // 5. Skicka tillbaka resultatet som ett korrekt API-svar
    return new WP_REST_Response([
        'status' => 'success',
        'data'   => $data
    ]);
}

//TODO - ta bort(?) överflödig då vi alltid hämtar inlägg baserat på någon slags grupp (eller 'wall')
//*************************************************************************
//********************** HÄMTA INLÄGG TILL WALL ***************************
//*************************************************************************

add_action('rest_api_init', function () {
    register_rest_route('tontid/v1', '/tontid_get_posts', [
        'methods' => 'GET',
        'callback' => 'tontid_get_posts',
        'permission_callback' => '__return_true'
        // TODO - lägg till detta i produktion
        // 'permission_callback' => function(){
        //     return is_user_logged_in();
        // } 
    ]);
});

function tontid_get_posts()
{
    $args = [
        'post_type'   => 'tontid_post',
        'post_status' => 'publish',
        'numberposts' => -1
    ];

    $wallposts = get_posts($args);
    $data = [];

    foreach ($wallposts as $wallpost) {
        $thumbnail_id = get_post_thumbnail_id($wallpost->ID);
        $thumbnail_url = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : null;

        $data[] = [
            'id'                 => $wallpost->ID,
            'title'              => $wallpost->post_title,
            'content'            => $wallpost->post_content,
            'author'             => get_the_author_meta('user_email', $wallpost->post_author),
            'date'               => $wallpost->post_date,
            'featured_media_url' => $thumbnail_url, // ✅ direkt URL
        ];
    }

    return new WP_REST_Response([
        'status' => 'success',
        'data'   => $data
    ]);
}

