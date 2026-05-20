<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode(['success' => false, 'message' => 'Nincs keresési kifejezés megadva.']);
    exit;
}

// Gmail kapcsolódási adatok
$emailUser = 'northwindhutestechnikakft@gmail.com';
$emailPass = 'zijf keiz tfql tmwd'; 

// Kapcsolódás a Gmail IMAP szerveréhez SSL-en keresztül
$mbox = @imap_open('{imap.gmail.com:993/imap/ssl}INBOX', $emailUser, $emailPass);

if (!$mbox) {
    echo json_encode([
        'success' => false, 
        'message' => 'Sikertelen Gmail kapcsolódás. Ellenőrizd a jelszót és az IMAP kiterjesztést!'
    ]);
    exit;
}

// Keresés az e-mailek között a megadott névre/kifejezésre
$searchCriteria = 'TEXT "' . iconv('UTF-8', 'ISO-8859-2', $q) . '"';
$emails = imap_search($mbox, $searchCriteria);

if (!$emails) {
    echo json_encode(['success' => false, 'message' => 'Nem található e-mail ezzel a névvel.']);
    imap_close($mbox);
    exit;
}

// A legfrissebb releváns e-mail azonosítója (a tömb utolsó eleme)
$latestEmailId = end($emails);

// Fejléc adatok kinyerése
$header = imap_headerinfo($mbox, $latestEmailId);
$from = $header->from[0];
$senderName = isset($from->personal) ? imap_utf8($from->personal) : $q;
$senderEmail = $from->mailbox . '@' . $from->host;

// E-mail szöveges törzsének letöltése (Plain text)
$body = imap_fetchbody($mbox, $latestEmailId, "1");
if (empty($body)) {
    $body = imap_fetchbody($mbox, $latestEmailId, "1.1");
}

// Kódolás kezelése (Quoted-Printable vagy Base64 dekódolás, ha szükséges)
$structure = imap_fetchstructure($mbox, $latestEmailId);
if (isset($structure->parts[0])) {
    $encoding = $structure->parts[0]->encoding;
} else {
    $encoding = $structure->encoding;
}

if ($encoding === 3) {
    $body = base64_decode($body);
} elseif ($encoding === 4) {
    $body = quoted_printable_decode($body);
}

$bodyUtf8 = mb_convert_encoding($body, 'UTF-8', 'auto');

// --- INTELLIGENS ADATBÁNYÁSZAT (REGEX) ---

$phone = '';
// Magyar formátumú telefonszámok keresése (+36..., 06..., mobil és vezetékes)
if (preg_match('/(\+36|06)[\s\-]?\d{1,2}[\s\-]?\d{3}[\s\-]?\d{4}/', $bodyUtf8, $matches)) {
    $phone = trim($matches[0]);
}

$address = '';
// Cím keresése irányítószám és utca/út/tér kulcsszavak alapján
if (preg_match('/(?:\d{4})\s+[A-ZÁÉÍÓÓÖŐÚÜŰ][a-záéíóöőúüű\s]+.+?(?:utca|u\.|út|tér|szám|emelet|ajtó)[^\n]*/iu', $bodyUtf8, $matches)) {
    $address = trim($matches[0]);
}

imap_close($mbox);

// Sikeres válasz visszaküldése JSON formátumban
echo json_encode([
    'success' => true,
    'data' => [
        'name'    => $senderName,
        'email'   => $senderEmail,
        'phone'   => $phone,
        'address' => $address,
        'note'    => trim(strip_tags($bodyUtf8)) // <--- Itt veszi ki a pontos szöveget!
    ]
]);