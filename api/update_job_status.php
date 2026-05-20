<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

function redirect_with_error(string $message, ?int $jobId = null): void
{
    http_response_code(400);
    echo '<p>Hiba: ' . htmlspecialchars($message) . '</p>';
    if ($jobId !== null && $jobId > 0) {
        echo '<p><a href="../pages/munka_reszletek.php?id=' . $jobId . '">Vissza a munkalaphoz</a></p>';
    } else {
        echo '<p><a href="../pages/munkanaplo.php">Vissza a munkanaplóhoz</a></p>';
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_error('Érvénytelen kérés.');
}

$jobId = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
$status = trim($_POST['status'] ?? '');

if ($jobId === 0 || $status === '') {
    redirect_with_error('Hiányzó adatok a frissítéshez.', $jobId);
}

// Biztonsági ellenőrzés, hogy csak a megengedett ENUM státuszokat fogadja el az adatbázis
$validStatuses = ['varolista', 'idopontozva', 'folyamatban', 'kesz', 'szamlazva', 'torolve'];
if (!in_array($status, $validStatuses, true)) {
    redirect_with_error('Érvénytelen státusz érték.', $jobId);
}

$pdo = db();

try {
    $stmt = $pdo->prepare("UPDATE jobs SET status = :status WHERE id = :id");
    $stmt->execute([
        ':status' => $status,
        ':id' => $jobId
    ]);

    // Sikeres frissítés után azonnal visszairányítjuk a böngészőt a részletező oldalra
    header('Location: ' . BASE_URL . '/pages/munka_reszletek.php?id=' . $jobId);
    exit;

} catch (Throwable $e) {
    if (APP_DEBUG) {
        redirect_with_error($e->getMessage(), $jobId);
    }
    redirect_with_error('Hiba történt a státusz frissítése során.', $jobId);
}