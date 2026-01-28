<?php

// Okej, så här är grejen: den här hooken körs när WordPress bygger upp hela sitt REST API-system.
// Här passar vi alltså på att skapa och registrera en helt EGEN ENDPOINT.  
//
// register_rest_route() tar tre parametrar:
//
// 1: Namespace + versionen av vårt API (det blir som en del av URL:en).
// 2: Själva path:en för endpointen, alltså vad man lägger efter namnområdet.
// 3: En associerad array med inställningar för endpointen:
//    - 'methods': Vilken/vilka HTTP-metoder vi accepterar. Här är det bara POST.
//    - 'callback': Den funktion som ska köras när någon anropar endpointen.
//    - 'permission_callback': Bestämmer vem som får anropa endpointen.
//         __return_true betyder ”släpp in alla!”.
//         Men här kan man bli strängare och kolla t.ex. inloggning, API-nyckel,
//         eller om personen har betalat (lite som när man betalar för att få access till
//         ChatGPT:s API).
//
// Poängen: med det här upplägget kan man bygga helt egna API:er inuti WordPress.
//
add_action('rest_api_init', function () {
    register_rest_route('tontid-jwt/v1', '/login', [
        'methods' => 'POST',
        'callback' => 'tontid_jwt_login',
        'permission_callback' => '__return_true',
    ]);
});

//Loginfunktionen som körs när API endpointen anropas!
//INPUT: en request med user credentials
//OUTPUT: en jwt token och utgångsdatum
function tontid_jwt_login($request) {
    // Plockar ut användarnamn och lösenord ur JSON-objektet som skickas från frontend-land.
    // $request är alltså hela HTTP-förfrågan som kommer in från klienten.
    // get_json_params() är en WP REST API-funktion som:
    //   1. Tar kroppen (body) i HTTP-requesten
    //   2. Försöker tolka den som JSON
    //   3. Returnerar resultatet som en assoc-array
    //
    // Sedan använder vi ?? (null coalescing operator) som basically säger:
    //   - "Om det här array-elementet finns och inte är null, använd det värdet."
    //   - Annars: ge mig en tom sträng ('') istället.
    // Detta gör att koden inte kraschar om frontend glömmer att skicka med något av fälten.
    $params = $request->get_json_params();
    $username = $params['username'] ?? '';
    $password = $params['password'] ?? '';

    //om något av dem är tomma och därmet "", ge felmeddelande
    if (empty($username) || empty($password)) {
        return new WP_Error('missing_data', 'Användarnamn och lösenord krävs', ['status' => 400]);
    }

    //kontrollera user credentials med wordpress funktion, annars skicka felmeddelande
    $user = wp_authenticate($username, $password);
    if (is_wp_error($user)) {
        return new WP_Error('invalid_credentials', 'Felaktigt användarnamn eller lösenord', ['status' => 401]);
    }

    //om allt bra så här långt, skapa ett jwt för användaren!
    //denna metod är definierad i jwt/jwt-functions.php
    $jwt = create_jwt($user);

    // Allt gick vägen — användarnamn och lösenord stämde, vi har skapat ett JWT!
    // Nu skickar vi tillbaka ett svar till frontend som innehåller:
    //  - 'token': själva "VIP-passerkortet" användaren ska "visa upp" i framtida API-anrop
    //  - 'expires': ett tidsvärde (timestamp) som talar om när token slutar gälla
    //
    // Tanken är att frontend sparar detta (t.ex. i localStorage) och skickar med
    // 'Authorization: Bearer <token>' i headern på varje skyddat anrop.
    // När tiden gått ut måste användaren logga in igen för att få en ny token.
    return [
        'token' => $jwt['token'],
        'expires' => $jwt['expires']
    ];
}
