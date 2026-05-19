<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/google_bridge.php'; // Itt hívjuk meg a naptár-szinkronizáló motort

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

$title = trim($_POST['title'] ?? '');
$eventDate = trim($_POST['event_date'] ?? '');

if ($title === '' || $eventDate === '') {
    redirect_with_error('Az esemény megnevezése és a dátum megadása kötelező.');
}

$startTime = trim($_POST['start_time'] ?? '');
$endTime = trim($_POST['end_time'] ?? '');
$customerId = trim($_POST['customer_id'] ?? '');
$location = trim($_POST['location'] ?? '');
$note = trim($_POST['note'] ?? '');

$pdo = db();

try {
    $pdo->beginTransaction();

    // 1. Mentés a MySQL adatbázisba
    $stmt = $pdo->prepare("
        INSERT INTO events (title, event_date, start_time, end_time, customer_id, location, note)
        VALUES (:title, :event_date, :start_time, :end_time, :customer_id, :location, :note)
    ");

    $stmt->execute([
        ':title'       => $title,
        ':event_date'  => $eventDate,
        ':start_time'  => $startTime !== '' ? $startTime : null,
        ':end_time'    => $endTime !== '' ? $endTime : null,
        ':customer_id' => $customerId !== '' ? (int)$customerId : null,
        ':location'    => $location !== '' ? $location : null,
        ':note'        => $note !== '' ? $note : null,
    ]);

    // 2. Mentés a Google Naptárba (közvetlenül a szerverről)
    // A dátum és időpont összefűzése ISO formátumba (pl: 2026-05-18T14:00:00)
    $isoStart = $eventDate . 'T' . ($startTime !== '' ? $startTime : '08:00') . ':00';
    $isoEnd = $eventDate . 'T' . ($endTime !== '' ? $endTime : '09:00') . ':00';
    
    syncJobToGoogle($title, $isoStart, $isoEnd, $note, $location);

    $pdo->commit();

    // Sikeres mentés visszajelzése
    echo '<!doctype html><html lang="hu"><head><meta charset="UTF-8"><title>Esemény mentve</title>';
    echo '<link rel="stylesheet" href="../assets/app.css"></head><body><main class="page">';
    echo '<div class="panel">';
    echo '<h1>✅ Esemény sikeresen mentve</h1>';
    echo '<p>Naptárba és adatbázisba rögzítve: <strong>' . htmlspecialchars($title) . '</strong></p>';
    echo '<div class="actions">';
    echo '<a class="btn" href="../pages/esemenyek.php">Vissza az eseményekhez</a>';
    echo '<a class="btn secondary" href="../index.php">Főmenü</a>';
    echo '</div>';
    echo '</div></main></body></html>';

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (APP_DEBUG) {
        redirect_with_error($e->getMessage());
    }
    redirect_with_error('Mentési hiba történt a naptár vagy az adatbázis rögzítése során.');
}