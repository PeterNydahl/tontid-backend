<?php
add_action('rest_api_init', function () {
    register_rest_route('my-jwt/v1', '/protected', [
        'methods' => 'GET',
        'callback' => 'my_jwt_protected_endpoint',
        'permission_callback' => '__return_true',
    ]);
});

// INPUT: ett anrop
// OUTPUT: payloaden av jwt:n 
function my_jwt_protected_endpoint($request) {
    $userData = validate_request_token($request);

    if (!$userData) {
        return new WP_Error('invalid_token', 'Token är ogiltig eller har gått ut', ['status' => 401]);
    }

    return [
        'message' => 'Du är auktoriserad!',
        'user' => $userData
    ];
}
