<?php
add_action('rest_api_init', function () {
    register_rest_route('tontid/v2', '/get-user', [
        'methods' => 'POST',
        'callback' => 'tontid_get_user',
        'permission_callback' => '__return_true',
    ]);
});

/* Skapa ny användare om den inte finns */
function create_new_user($user_email){
    // Trimma mailen till användarnamn
    $email_part = strstr($user_email, '@', true);
    $name_parts = explode('.', $email_part);
    $username = implode('', $name_parts);

    // Generera unikt användarnamn om det redan finns
    $base_username = $username;
    $suffix = 1;
    while (username_exists($username)){
        $username = $base_username . $suffix;
        $suffix++;
    }

    // Skapa lösenord
    $password = wp_generate_password();

    // Skapa användare
    $user_id = wp_create_user($username, $password, $user_email);
    if (is_wp_error($user_id)){
        return $user_id; // returnerar WP_Error
    }

    // Skapa WP_User-objekt och tilldela roll
    $user_obj = new WP_User($user_id);

    // Tilldela roll baserat på domän (exempel: g* = lärare, annars elev)
    $email_domain = substr(strrchr($user_email, "@"), 1); // ex: "ga.rytmus.se"
    $role = str_starts_with($email_domain, 'g') ? 'larare' : 'elev';
    $user_obj->set_role($role);

    return $user_obj;
}

/* REST-endpoint */
function tontid_get_user($request) {
    global $wp_roles;

    $user_email = $request->get_param('email');
    if (!$user_email) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'E-postadress måste anges.'
        ], 400);
    }

    // Hämta befintlig användare
    $user = get_user_by('email', $user_email);

    // Skapa ny användare om den inte finns
    if (!$user) {
        error_log("Skapar ny användare: " . $user_email);
        $user = create_new_user($user_email);

        if (is_wp_error($user)){
            return new WP_REST_Response([
                'status' => 'error',
                'message' => $user->get_error_message()
            ], 400);
        }
    }

    // Nu är $user ett WP_User-objekt, säkert att läsa roller
    $roles = $user->roles ?? [];
    $role_slug = $roles[0] ?? '';
    $role_name = $wp_roles->roles[$role_slug]['name'] ?? '';

    return new WP_REST_Response([
        'status' => 'success',
        'message' => 'Användare hämtad från databasen.',
        'user' => [
            'ID' => $user->ID,
            'user_login' => $user->user_login,
            'user_email' => $user->user_email,
        ],
        'user_role_slug' => $role_slug,
        'user_role_name' => $role_name
    ], 200);
}
