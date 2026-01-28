<?php
//säkerhet
if (!defined('ABSPATH')){
    wp_die('Du har inte rätt till direktåtkomst av den här filen');
}


//inkludera bibliotek för JWT
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

//här definierar vi den "magiska" nyckeln som gör det möjligt att skapa VIP-passerkort (JWT-tokens)
//TODO nyckeln ska inte definieras här utan i wp-config.php på servern
define('JWT_SECRET_KEY', 'din_super_hemliga_nyckel');
//och när de går ut (angett i sekunder)
define('JWT_EXPIRATION', 3600);

/*SKAPA JWT TOKEN
----------------------------------------------------------------------*/
//den här funktionen skapar ett VIP-passerkort till nöjesparken (sajten)
//INPUT: en user
//OUTPUT: en jwt token och dess utgångsdatum
function create_jwt($user) {
    //sparar nuvarande tid och sätter utgångstid
    $issuedAt = time();
    $expirationTime = $issuedAt + JWT_EXPIRATION;

    $payload = [
        // 'iss' = "issuer", alltså vem som skapar token. 
        // Vi sätter den till site-URL så vi vet varifrån token kommer.
        'iss' => get_bloginfo('url'),

        // 'iat' = "issued at", när token skapades (timestamp i sekunder).
        // Bra för att kolla om en token är för gammal.
        'iat' => $issuedAt,

        // 'exp' = "expiration", när token går ut (timestamp i sekunder).
        // När tiden passerats är token ogiltig och användaren måste logga in igen.
        'exp' => $expirationTime,

        // 'data' = själva nyttolasten, alltså info vi vill skicka med tokenen.
        // Här kan frontend se det vi vill, och backend verifierar det när token används.
        'data' => [
            'user_id' => $user->ID,           // WordPress ID för användaren
            'username' => $user->user_login,  // Inloggningsnamnet
            'roles' => $user->roles           // Alla roller användaren har (admin, editor osv)
        ]
    ];

    //Nu ska vår token sättas ihop och signeras! HS256 är en vanlig algoritm för att generera signaturens värde
    $token = JWT::encode($payload, JWT_SECRET_KEY, 'HS256');

    return ['token' => $token, 'expires' => $expirationTime];
}

// Den här metoden kollar om ett JWT-token är giltigt.
//
// 1️⃣ Vi tar emot token som en sträng från t.ex. en HTTP-header.
//
// 2️⃣ JWT::decode() gör själva tunga jobbet:
//     - Den kollar signaturen mot vår hemliga nyckel (JWT_SECRET_KEY).
//     - Den kollar algoritmen (HS256 i vårt fall).
//     - Den ser till att token inte har gått ut (exp-tiden i payload).
//
// 3️⃣ Om allt är okej så får vi tillbaka hela payloaden som ett objekt.
//     Vi kastar om 'data'-delen till array för att det ska bli lättare att använda
//     i resten av PHP-koden.
//
// 4️⃣ Om något går fel (fel signatur, utgången token, trasig token osv.)
//     fångas det av catch-blocket och vi returnerar false.
//
// Slutsats: om du får en array tillbaka kan du lita på att användaren är verifierad.
// Om du får false betyder det att token inte är giltig, och du kan t.ex. neka åtkomst.
// INPUT: en jwt token
// OUTPUT: antingen false eller en array med data
function validate_jwt($token) {
    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET_KEY, 'HS256'));
        return (array)$decoded->data;
    } catch (Exception $e) {
        return false;
    }
}
