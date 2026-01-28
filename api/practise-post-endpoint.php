<?php

add_action('rest_api_init', function() {
    register_rest_route( 'tontid/v1', 'post-endpoint', [
        'methods' => 'POST',
        'callback' => 'my_callback',
        'permission_callback' => '__return_true'
    ]);    
});

function my_callback(WP_REST_Request $req) {
    $data = $req->get_json_params();
    if(!$data)
        return new WP_REST_Response(
            [
                'status' => 'fett fel'
            ], 400
        );
    return new WP_REST_Response(
        [
            'status' => 'alla tiders!',
            'id' => $data['userId'],
            'bokning' => $data['booking']
        ], 200
    );
}































// add_action('rest_api_init', function() {
//     register_rest_route(
//         'tontid/v1',
//         '/post-endpoint',
//         [
//             'methods' => 'POST',
//             'callback' => 'my_callback',
//             'permission_callback' => '__return_true'
//         ]
//     );
// });

// function my_callback (WP_REST_Request $request) {
//     $body_data = $request->get_json_params();
//     if(!$body_data)
//         return new WP_REST_Response([
//     'status' => 'failed'
// ], 400);

//     $new_id = $body_data['userId'] + 10;

//     return new WP_REST_Response([
//     'status' => 'success',
//     'id' => "Ditt userId +10 är $new_id",
//     'bokning' => $body_data['booking']

// ], 200);
// }