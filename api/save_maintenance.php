<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

function redirect_with_error(string $message): void
{
    http_response_code(400);
    echo '<p>Hiba: ' . htmlspecialchars($message) . '</p>';
    echo '<p><a href="../pages/idopont.php">Vissza az előző oldalra</a></p>';
    exit;
}

function slugify(string $text): string
{
    $text = trim($text);

    // Javított magyar ékezettábla, a makacs Ő, Ő, Ű, ű betűkkel együtt
    $map = [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u',
        'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ö'=>'O','Ő'=>'O','Ú'=>'U','Ü'=>'U','Ű'=>'U',
        'ó'=>'o','Ó'=>'O','ő'=>'o','Ő'=>'O','ű'=>'u','Ű'=>'U'
    ];

    $text = strtr($text, $map);
    $text = preg_replace('/[^A-Za-z0-9_-]+/', '_', $text);
    $text = trim($text, '_');

    return $text !== '' ? $text : 'ismeretlen';
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_error('Érvénytelen kérés.');
}

$customerId = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
$customerName = trim($_POST['customer_name'] ?? '');

if ($customerName === '') {
    redirect_with_error('A megrendelő neve kötelező.');
}

$customerPhone = trim($_POST['customer_phone'] ?? '');
$customerEmail = trim($_POST['customer_email'] ?? '');
$customerAddress = trim($_POST['customer_address'] ?? '');
$customerNote    = trim($_POST['customer_note'] ?? '');
$scheduledDate = trim($_POST['scheduled_date'] ?? date('Y-m-d'));
$scheduledTime = trim($_POST['scheduled_time'] ?? '');
$jobType = trim($_POST['job_type'] ?? 'karbantartas');
$jobDescription = trim($_POST['job_description'] ?? '');

// Dinamikus státusz kezelés: ha küld a frontend, azt mentjük, különben alapértelmezett 'scheduled'
$jobStatus = trim($_POST['status'] ?? 'scheduled'); 

$zones = $_POST['zones'] ?? [];

if (!is_array($zones) || count($zones) === 0) {
    redirect_with_error('Legalább egy zóna/helyiség szükséges.');
}

$pdo = db();

try {
    $pdo->beginTransaction();

    /**
     * 1. Ügyfél mentése vagy frissítése
     */
    if ($customerId > 0) {
        $stmt = $pdo->prepare("
            UPDATE customers 
            SET name = :name, phone = :phone, email = :email, address = :address, note = :note
            WHERE id = :id
        ");
        $stmt->execute([
            ':name' => $customerName,
            ':phone' => $customerPhone !== '' ? $customerPhone : null,
            ':email' => $customerEmail !== '' ? $customerEmail : null,
            ':address' => $customerAddress !== '' ? $customerAddress : null,
            ':note' => $customerNote !== '' ? $customerNote : null,
            ':id' => $customerId
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO customers (name, phone, email, address, note)
            VALUES (:name, :phone, :email, :address, :note)
        ");
        $stmt->execute([
            ':name' => $customerName,
            ':phone' => $customerPhone !== '' ? $customerPhone : null,
            ':email' => $customerEmail !== '' ? $customerEmail : null,
            ':address' => $customerAddress !== '' ? $customerAddress : null,
            ':note' => $customerNote !== '' ? $customerNote : null
        ]);
        $customerId = (int)$pdo->lastInsertId();
    }

    /**
     * 2. Munka létrehozása (Javított, dinamikus státuszkezeléssel!)
     */
    $stmt = $pdo->prepare("
        INSERT INTO jobs (
            customer_id,
            job_type,
            status,
            scheduled_date,
            scheduled_time,
            title,
            description
        )
        VALUES (
            :customer_id,
            :job_type,
            :status,
            :scheduled_date,
            :scheduled_time,
            :title,
            :description
        )
    ");

    $title = ucfirst($jobType) . ' - ' . $customerName;

    $stmt->execute([
        ':customer_id' => $customerId,
        ':job_type' => $jobType,
        ':status' => $jobStatus,
        ':scheduled_date' => $scheduledDate ?: null,
        ':scheduled_time' => $scheduledTime ?: null,
        ':title' => $title,
        ':description' => $jobDescription,
    ]);

    $jobId = (int)$pdo->lastInsertId();

    /**
     * 3. Mappa előkészítése a NAS-on
     */
    $year = date('Y', strtotime($scheduledDate ?: date('Y-m-d')));
    $dateSlug = date('Y-m-d', strtotime($scheduledDate ?: date('Y-m-d')));
    $customerSlug = slugify($customerName);

    $baseJobPath = rtrim(NAS_BASE_PATH, '/') . "/munkak/{$year}/{$dateSlug}/{$customerSlug}";
    ensure_dir($baseJobPath);

    /**
     * 4. Zónák és helyiségek mentése relációsan
     */
    $zoneSort = 0;

    foreach ($zones as $zoneKey => $zoneData) {
        $zoneName = trim($zoneData['name'] ?? '');

        if ($zoneName === '') {
            continue;
        }

        $zoneSort += 10;

        $stmt = $pdo->prepare("
            INSERT INTO job_zones (job_id, zone_name, sort_order)
            VALUES (:job_id, :zone_name, :sort_order)
        ");

        $stmt->execute([
            ':job_id' => $jobId,
            ':zone_name' => $zoneName,
            ':sort_order' => $zoneSort,
        ]);

        $zoneId = (int)$pdo->lastInsertId();
        $zoneSlug = slugify($zoneName);
        $zonePath = "{$baseJobPath}/{$zoneSlug}";
        ensure_dir($zonePath);

        $rooms = $zoneData['rooms'] ?? [];
        $roomSort = 0;

        foreach ($rooms as $roomKey => $roomData) {
            $roomName = trim($roomData['name'] ?? '');

            if ($roomName === '') {
                continue;
            }

            $roomSort += 10;

            $stmt = $pdo->prepare("
                INSERT INTO job_rooms (
                    job_id,
                    zone_id,
                    room_name,
                    maintenance_mode,
                    indoor_unit_type,
                    indoor_serial,
                    outdoor_unit_type,
                    outdoor_serial,
                    note,
                    sort_order
                )
                VALUES (
                    :job_id,
                    :zone_id,
                    :room_name,
                    :maintenance_mode,
                    :indoor_unit_type,
                    :indoor_serial,
                    :outdoor_unit_type,
                    :outdoor_serial,
                    :note,
                    :sort_order
                )
            ");

            $stmt->execute([
                ':job_id' => $jobId,
                ':zone_id' => $zoneId,
                ':room_name' => $roomName,
                ':maintenance_mode' => $roomData['maintenance_mode'] ?? 'nincs',
                ':indoor_unit_type' => trim($roomData['indoor_unit_type'] ?? ''),
                ':indoor_serial' => trim($roomData['indoor_serial'] ?? ''),
                ':outdoor_unit_type' => trim($roomData['outdoor_unit_type'] ?? ''),
                ':outdoor_serial' => trim($roomData['outdoor_serial'] ?? ''),
                ':note' => trim($roomData['note'] ?? ''),
                ':sort_order' => $roomSort,
            ]);

            $roomId = (int)$pdo->lastInsertId();
            $roomSlug = slugify($roomName);
            $roomPath = "{$zonePath}/{$roomSlug}";
            ensure_dir($roomPath);

            save_room_photos($jobId, $roomId, (string)$zoneKey, (string)$roomKey, $roomPath, $pdo);
        }
    }

    $pdo->commit();

    echo '<!doctype html><html lang="hu"><head><meta charset="UTF-8"><title>Mentve</title>';
    echo '<link rel="stylesheet" href="../assets/app.css"></head><body><main class="page">';
    echo '<div class="panel">';
    echo '<h1>✅ Karbantartás sikeresen mentve</h1>';
    echo '<p>Munka adatbázis azonosító: #' . htmlspecialchars((string)$jobId) . '</p>';
    echo '<p>Megrendelő: ' . htmlspecialchars($customerName) . '</p>';
    echo '<p>NAS Tárolási útvonal: <br><code style="font-size:12px; color:var(--accent);">' . htmlspecialchars($baseJobPath) . '</code></p>';
    echo '<div class="actions">';
    echo '<a class="btn" href="../pages/idopont.php">Új karbantartás</a>';
    echo '<a class="btn secondary" href="../index.php">Főmenü</a>';
    echo '</div>';
    echo '</div></main></body></html>';

} catch (Throwable $e) {
    $pdo->rollBack();
    if (APP_DEBUG) {
        redirect_with_error($e->getMessage());
    }
    redirect_with_error('Mentési hiba történt az adatbázis művelet során.');
}

function save_room_photos(
    int $jobId,
    int $roomId,
    string $zoneKey,
    string $roomKey,
    string $roomPath,
    PDO $pdo
): void {
    if (empty($_FILES['photos'])) {
        return;
    }

    $photoTypes = [
        'belteri_matrica',
        'kulteri_matrica',
        'allapot_elotte',
        'allapot_utana',
        'hiba',
        'alkatresz',
        'egyeb'
    ];

    foreach ($photoTypes as $photoType) {
        if (!isset($_FILES['photos']['name'][$zoneKey][$roomKey][$photoType])) {
            continue;
        }

        $names = $_FILES['photos']['name'][$zoneKey][$roomKey][$photoType];
        $tmpNames = $_FILES['photos']['tmp_name'][$zoneKey][$roomKey][$photoType];
        $errors = $_FILES['photos']['error'][$zoneKey][$roomKey][$photoType];
        $sizes = $_FILES['photos']['size'][$zoneKey][$roomKey][$photoType];

        if (!is_array($names)) {
            continue;
        }

        $count = count($names);

        for ($i = 0; $i < $count; $i++) {
            if ($errors[$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            if ($sizes[$i] > MAX_UPLOAD_SIZE) {
                continue;
            }

            $tmp = $tmpNames[$i];
            $mime = mime_content_type($tmp);

            if (!isset(ALLOWED_IMAGE_TYPES[$mime])) {
                continue;
            }

            $ext = ALLOWED_IMAGE_TYPES[$mime];
            $sequence = str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT);
            $storedName = "{$sequence}_{$photoType}.{$ext}";
            $target = rtrim($roomPath, '/') . '/' . $storedName;

            if (!move_uploaded_file($tmp, $target)) {
                continue;
            }

            $stmt = $pdo->prepare("
                INSERT INTO job_photos (
                    job_id,
                    room_id,
                    photo_type,
                    original_filename,
                    stored_filename,
                    nas_path
                )
                VALUES (
                    :job_id,
                    :room_id,
                    :photo_type,
                    :original_filename,
                    :stored_filename,
                    :nas_path
                )
            ");

            $stmt->execute([
                ':job_id' => $jobId,
                ':room_id' => $roomId,
                ':photo_type' => $photoType,
                ':original_filename' => $names[$i],
                ':stored_filename' => $storedName,
                ':nas_path' => $target,
            ]);
        }
    }
}