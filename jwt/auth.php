<?php
//Två viktiga helper-funktioner (en helper-funktion är vanligtis en funktion man använder återkommande för samma sak) 
//för autentisering och auktorisering

//AUTENTISERING → "Är den här användaren verkligen inloggad och har en giltig token?"
//Används AV ALLA SKYDDADE ENDPOINTS för att först säkerställa att token är giltig innan något annat körs.
//INPUT: en request med jwt
//OUTPUT: payload delen av jwt:n
function validate_request_token($request) {
    //Hämtar värdet på http-headern 'Authorization' från REST-anropet.
    // REST-anrop från frontend skickar typiskt: Authorization: Bearer <token>.
    $authHeader = $request->get_header('authorization');

    // Vi måste nu kolla om authorization bearer token vi fått från frontend-land har rätt struktur!
    // preg_match() söker efter ett angivet mönster (pattern) i en sträng (subject). 
    // Så: $minSträng, $enAnnanSträngAttJämföraMed
    // Detta är ett exempel på ett Regex(Regular Expression) 
    // = En beskrivning av en viss struktur hos en sträng, som du använder för att jämföra med en annan sträng.
    // Metoden Returnerar 1 om det finns en match, 0 annars.
    // Tredje parametern $matches är en array som fylls med det som matchades.
    // $mathes[0] innehåller hela matchningen inklusive Regex (i detta fall "Bearer")
    // $matches[1] innehåller matchningen UTAN bearer, dvs jwt-strängen. 
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        // Om inget hittas returneras false, vilket betyder att användaren inte är autentiserad.
        return false;
    }
    //om den har rätt struktur lägger vi den i en variabel
    $token = $matches[1];
    // och sedan validerar vi och returnerar!
    // När du validerar token med validate_jwt($token) får du payloadens data-del tillbaka som en array! 
    return validate_jwt($token);
}

//AUKTORISERING → "Har den här inloggade användaren rätt roll för att göra det här?"
//Denna funktion Gör det superenkelt att lägga in auktorisering baserat på roller!
function current_user_has_role($userData, $role) {
    return in_array($role, $userData['roles']);
}
