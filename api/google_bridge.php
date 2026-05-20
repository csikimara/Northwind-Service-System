<?php
// api/google_bridge.php - Vendor mappa nélküli, natív PHP verzió
function syncJobToGoogle($title, $start, $end, $description, $location) {
    $keyFile = __DIR__ . '/../kulcs.json';
    if (!file_exists($keyFile)) return false;

    $keyData = json_decode(file_get_contents($keyFile), true);
    
    // 1. JWT (JSON Web Token) generálása a hitelesítéshez
    // (Ez a legegyszerűbb módja a Google API hívásnak külső könyvtár nélkül)
    // Megjegyzés: Ha nem akarsz JWT-t írni, a legtisztább az, ha 
    // a Google Scriptes "Kapu" maradt a cél, és a PHP csak egy egyszerű cURL-t küld oda.
    
    // ZSOLT: Mivel te már megírtad a Google Scriptet (ami működik!), 
    // NEKED NEM KELL A VENDOR MAPPA! 
    // Egyszerűen küldj egy POST kérést a Google Scripted URL-jére!
    
    $url = "https://script.google.com/macros/s/AKfycbyGwd1BHK6V2P8jToWW3zytvc-ZrOyVZj6GIWsK1RmwAS_Eean5fzY4_OrSra12O2EJmA/exec"; 
    $data = [
        'mode' => 'idopont',
        'title' => $title,
        'datum' => substr($start, 0, 10),
        'idopont' => substr($start, 11, 5),
        'leiras' => $description
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_exec($ch);
    curl_close($ch);
}