<?php
// Fil: api/jwt-user.php

// Inkludera dina JWT-funktioner och auth-helpers
require_once __DIR__ . '/../jwt/jwt-functions.php';
require_once __DIR__ . '/../jwt/auth.php';

// Registrera REST API endpoint
add_action('rest_api_init', function () {
    register_rest_route('jwt/v1', '/user', [
        'methods'  => 'GET',
        'callback' => 'get_current_jwt_user',
        'permission_callback' => '__return_true', // Vi hanterar auth själva
    ]);
});

function get_current_jwt_user($request) {
    // Validera token och hämta användardata
    $userData = validate_request_token($request);

    if (!$userData) {
        return new WP_Error('invalid_token', 'Token saknas eller är ogiltig', ['status' => 401]);
    }

    // Returnera användarinfo
    return [
        'user_id'  => $userData['user_id'],
        'username' => $userData['username'],
        'roles'    => $userData['roles'],
    ];
}
