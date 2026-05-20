<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// Automatikusan behúzzuk a meglévő Google Naptár-szinkronizáló motort
if (file_exists(__DIR__ . '/google_bridge.php')) {
    require_once __DIR__ . '/google_bridge.php';
}

function redirect_with_error(string $message): void
{
    http_response_code(400);
    echo '<!doctype html><html lang="hu"><head><meta charset="UTF-8"><title>Hiba</title>';
    echo '<link rel="stylesheet" href="../assets/app.css"></head><body><main class="page"><div class="panel">';
    echo '<h1 style="color:var(--danger);">❌ Hiba</h1>';
    echo '<p>' . htmlspecialchars($message) . '</p>';
    echo '<p><a href="../pages/esemenyek.php" class="btn">Vissza az eseményekhez</a></p>';
    echo '</div></main></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_error('Érvénytelen kérés.');
}

$eventType   = trim($_POST['event_type'] ?? 'Egyéb');
$eventDate   = trim($_POST['event_date'] ?? '');
$startTime   = trim($_POST['start_time'] ?? '');
$partnerName = trim($_POST['partner_name'] ?? '');
$location    = trim($_POST['location'] ?? '');
$note        = trim($_POST['note'] ?? '');

if ($partnerName === '' || $eventDate === '' || $startTime === '') {
    redirect_with_error('A partner neve, a dátum és a kezdési időpont megadása kötelező.');
}

// A Google Naptár bejegyzés címe és formázott leírása
$calendarTitle = $eventType . ' - ' . $partnerName;
$fullDescription = $note;
if ($location !== '') {
    $fullDescription = "Helyszín: " . $location . "\n" . $note;
}

$pdo = db();

try {
    $pdo->beginTransaction();

    // 1. Mentés a MySQL központi táblába (status='event', a belső naptár lila színezéséhez)
    $stmt = $pdo->prepare("
        INSERT INTO maintenances (customer_name, scheduled_date, scheduled_time, job_type, job_description, status) 
        VALUES (:partner, :s_date, :s_time, :j_type, :descr, 'event')
    ");

    $stmt->execute([
        ':partner' => $partnerName,
        ':s_date'  => $eventDate,
        ':s_time'  => $startTime,
        ':j_type'  => 'esemeny_' . $eventType,
        ':descr'   => $fullDescription
    ]);

    // 2. Mentés a Google Naptárba ISO formátumban (pl: 2026-05-20T18:00:00)
    if (function_exists('syncJobToGoogle')) {
        $isoStart = $eventDate . 'T' . $startTime . ':00';
        
        // Alapértelmezett 1 órás időtartam generálása a Google felé
        $endHour = (int)substr($startTime, 0, 2) + 1;
        $endMinutes = substr($startTime, 3, 2);
        $formattedEndHour = str_pad((string)$endHour, 2, '0', STR_PAD_LEFT);
        if ((int)$formattedEndHour >= 24) { $formattedEndHour = "23"; $endMinutes = "59"; }
        $isoEnd = $eventDate . 'T' . $formattedEndHour . ':' . $endMinutes . ':00';
        
        syncJobToGoogle($calendarTitle, $isoStart, $isoEnd, $fullDescription, $location);
    }

    $pdo->commit();

    header('Location: ../pages/esemenyek.php?success=1');
    exit;

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (APP_DEBUG) {
        redirect_with_error($e->getMessage());
    }
    redirect_with_error('Mentési hiba történt a naptár vagy az adatbázis rögzítése során.');
}