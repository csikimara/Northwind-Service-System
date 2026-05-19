<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

function redirect_with_error(string $message, ?int $jobId = null): void
{
    http_response_code(400);
    echo '<p>Hiba történt a mentés során: ' . htmlspecialchars($message) . '</p>';
    if ($jobId !== null && $jobId > 0) {
        echo '<p><a href="../pages/munka_szerkesztes.php?id=' . $jobId . '">Vissza a szerkesztéshez</a></p>';
    } else {
        echo '<p><a href="../index.php">Vissza a főmenübe</a></p>';
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_error('Érvénytelen kérés.');
}

$jobId = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
if ($jobId === 0) {
    redirect_with_error('Hiányzó munka azonosító.');
}

$pdo = db();

try {
    $pdo->beginTransaction();

    // 1. Lépés: Helyiségek szöveges adatainak frissítése
    if (isset($_POST['rooms']) && is_array($_POST['rooms'])) {
        $stmtUpdateRoom = $pdo->prepare("
            UPDATE job_rooms 
            SET maintenance_mode = :maintenance_mode,
                indoor_unit_type = :indoor_unit_type,
                indoor_serial    = :indoor_serial,
                outdoor_unit_type = :outdoor_unit_type,
                outdoor_serial   = :outdoor_serial,
                note             = :note
            WHERE id = :room_id AND job_id = :job_id
        ");

        foreach ($_POST['rooms'] as $roomId => $roomData) {
            $stmtUpdateRoom->execute([
                ':maintenance_mode'  => trim($roomData['maintenance_mode'] ?? 'nincs'),
                ':indoor_unit_type'  => trim($roomData['indoor_unit_type'] ?? null) ?: null,
                ':indoor_serial'     => trim($roomData['indoor_serial'] ?? null) ?: null,
                ':outdoor_unit_type' => trim($roomData['outdoor_unit_type'] ?? null) ?: null,
                ':outdoor_serial'    => trim($roomData['outdoor_serial'] ?? null) ?: null,
                ':note'              => trim($roomData['note'] ?? null) ?: null,
                ':room_id'           => (int)$roomId,
                ':job_id'            => $jobId
            ]);
        }
    }

    // 2. Lépés: Fotók kezelése és fizikai mentése a NAS-ra
    if (isset($_FILES['photos']) && is_array($_FILES['photos'])) {
        
        // Alap feltöltési gyökérkönyvtár meghatározása és létrehozása
        $uploadBaseDir = __DIR__ . '/../data/uploads';
        if (!is_dir($uploadBaseDir)) {
            mkdir($uploadBaseDir, 0755, true);
        }

        // Munkalap-szintű mappa létrehozása (pl. data/uploads/job_12)
        $jobDir = $uploadBaseDir . '/job_' . $jobId;
        if (!is_dir($jobDir)) {
            mkdir($jobDir, 0755, true);
        }

        $stmtInsertPhoto = $pdo->prepare("
            INSERT INTO job_photos (job_id, room_id, photo_type, original_filename, stored_filename, nas_path)
            VALUES (:job_id, :room_id, :photo_type, :original_filename, :stored_filename, :nas_path)
        ");

        // PHP töbdimenziós $_FILES struktúra kibontása biztonságosan
        foreach ($_FILES['photos']['name'] as $roomId => $types) {
            foreach ($types as $photoType => $indexes) {
                foreach ($indexes as $index => $originalName) {
                    
                    $error = $_FILES['photos']['error'][$roomId][$photoType][$index] ?? UPLOAD_ERR_NO_FILE;
                    
                    if ($error === UPLOAD_ERR_OK && $originalName !== '') {
                        $tmpName = $_FILES['photos']['tmp_name'][$roomId][$photoType][$index];
                        
                        // Biztonságos és egyedi fájlnév generálása a NAS-on
                        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                        $storedName = 'room_' . $roomId . '_' . $photoType . '_' . uniqid('', true) . '.' . $extension;
                        
                        $targetFilePath = $jobDir . '/' . $storedName;

                        // Fájl tényleges áthelyezése a NAS ideiglenes tárolójából az éles mappába
                        if (move_uploaded_file($tmpName, $targetFilePath)) {
                            // Relatív útvonal mentése az adatbázisba a későbbi könnyű megjelenítéshez
                            $nasRelativePath = 'data/uploads/job_' . $jobId . '/' . $storedName;

                            $stmtInsertPhoto->execute([
                                ':job_id'            => $jobId,
                                ':room_id'           => (int)$roomId,
                                ':photo_type'        => $photoType,
                                ':original_filename' => $originalName,
                                ':stored_filename'   => $storedName,
                                ':nas_path'          => $nasRelativePath
                            ]);
                        }
                    }
                }
            }
        }
    }

    $pdo->commit();

    // Ha a mentés sikeres, azonnal visszairányítunk a munkalap összefoglaló adatlapjára
    header('Location: ' . BASE_URL . '/pages/munka_reszletek.php?id=' . $jobId);
    exit;

} catch (Throwable $e) {
    $pdo->rollBack();
    if (APP_DEBUG) {
        redirect_with_error($e->getMessage(), $jobId);
    }
    redirect_with_error('Nem sikerült elmenteni az adatokat az adatbázisba.', $jobId);
}