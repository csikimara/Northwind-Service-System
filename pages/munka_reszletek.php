<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$pdo = db();
$jobId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($jobId === 0) {
    die('Hiba: Érvénytelen vagy hiányzó munka azonosító.');
}

try {
    // 1. Munka és ügyfél alapadatainak lekérése
    $stmtJob = $pdo->prepare("
        SELECT 
            j.*, 
            c.name AS customer_name, 
            c.phone AS customer_phone, 
            c.email AS customer_email, 
            c.address AS customer_address,
            c.note AS customer_permanent_note
        FROM jobs j
        JOIN customers c ON j.customer_id = c.id
        WHERE j.id = :job_id
    ");
    $stmtJob->execute([':job_id' => $jobId]);
    $job = $stmtJob->fetch();

    if (!$job) {
        die('Hiba: A keresett munka nem található az adatbázisban.');
    }

    // 2. Zónák lekérése
    $stmtZones = $pdo->prepare("SELECT * FROM job_zones WHERE job_id = :job_id ORDER BY sort_order ASC");
    $stmtZones->execute([':job_id' => $jobId]);
    $zones = $stmtZones->fetchAll();

    // 3. Helyiségek lekérése
    $stmtRooms = $pdo->prepare("SELECT * FROM job_rooms WHERE job_id = :job_id ORDER BY sort_order ASC");
    $stmtRooms->execute([':job_id' => $jobId]);
    $allRooms = $stmtRooms->fetchAll();

    // Helyiségek csoportosítása zónák szerint a könnyebb kirajzoláshoz
    $roomsByZone = [];
    foreach ($allRooms as $room) {
        $roomsByZone[$room['zone_id']][] = $room;
    }

    // 4. Fotók lekérése
    $stmtPhotos = $pdo->prepare("SELECT * FROM job_photos WHERE job_id = :job_id");
    $stmtPhotos->execute([':job_id' => $jobId]);
    $allPhotos = $stmtPhotos->fetchAll();

    // Fotók csoportosítása helyiségek szerint
    $photosByRoom = [];
    foreach ($allPhotos as $photo) {
        $photosByRoom[$photo['room_id']][] = $photo;
    }

} catch (Throwable $e) {
    if (APP_DEBUG) {
        die('Adatbázis hiba: ' . htmlspecialchars($e->getMessage()));
    }
    die('Hiba történt az adatok betöltése során.');
}

// Státuszok fordítása és színei
function getStatusBadge(string $status): array {
    switch ($status) {
        case 'idopontozva': return ['label' => 'Időpontozva', 'color' => '#38bdf8'];
        case 'folyamatban': return ['label' => 'Folyamatban', 'color' => '#f59e0b'];
        case 'kesz': return ['label' => 'Kész', 'color' => '#22c55e'];
        case 'szamlazva': return ['label' => 'Számlázva', 'color' => '#10b981'];
        case 'torolve': return ['label' => 'Törölve', 'color' => '#ef4444'];
        default: return ['label' => 'Várólista', 'color' => '#9ca3af'];
    }
}

// Karbantartási módok magyarosítása
function getMaintenanceModeLabel(string $mode): string {
    switch ($mode) {
        case 'alap': return 'Alap karbantartás';
        case 'nagy_zsakos': return 'Nagy / zsákos mosás';
        case 'javitas': return 'Javítás';
        case 'felmeres': return 'Felmérés';
        case 'alkatresz_szukseges': return 'Alkatrész szükséges';
        case 'nem_hozzaferheto': return 'Nem hozzáférhető';
        case 'ugyfel_nem_kerte': return 'Ügyfél nem kérte';
        default: return 'Nincs munka / Elmaradt';
    }
}

// Fotó típusok magyarosítása
function getPhotoTypeLabel(string $type): string {
    switch ($type) {
        case 'belteri_matrica': return 'Beltéri matrica';
        case 'kulteri_matrica': return 'Kültéri matrica';
        case 'allapot_elotte': return 'Állapot előtte';
        case 'allapot_utana': return 'Állapot utána';
        case 'hiba': return 'Hiba / Alkatrész';
        default: return 'Egyéb fotó';
    }
}
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <title>Munkalap #<?= $job['id'] ?> - <?= htmlspecialchars(APP_NAME) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/app.css">
  <style>
    .meta-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 16px;
      margin-bottom: 20px;
    }
    .info-block {
      line-height: 1.6;
    }
    .info-block h3 {
      margin: 0 0 10px 0;
      color: var(--accent);
      font-size: 16px;
      border-bottom: 1px solid var(--border);
      padding-bottom: 6px;
    }
    .status-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 14px;
      background: rgba(0,0,0,0.2);
      padding: 12px 16px;
      border-radius: 12px;
      margin-top: 14px;
    }
    .unit-badge {
      background: rgba(255,255,255,0.05);
      padding: 4px 8px;
      border-radius: 6px;
      font-size: 12px;
      border: 1px solid var(--border);
    }
    .photo-card {
      background: rgba(0,0,0,0.3);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 6px;
      text-align: center;
    }
    .photo-card span {
      display: block;
      font-size: 11px;
      color: var(--muted);
      margin-top: 4px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
  </style>
</head>
<body>

<main class="page">

  <div class="page-header">
    <div>
      <a class="back" href="<?= BASE_URL ?>/pages/munkanaplo.php">← Vissza a munkanaplóhoz</a>
      <h1>Munkalap részletei #<?= $job['id'] ?></h1>
      <p><?= htmlspecialchars($job['title']) ?></p>
    </div>
    <div>
      <?php $badge = getStatusBadge($job['status']); ?>
      <span style="background: <?= $badge['color'] ?>; color: #000; padding: 6px 14px; border-radius: 8px; font-weight: 700; text-transform: uppercase; font-size: 14px;">
        <?= $badge['label'] ?>
      </span>
    </div>
  </div>

  <div class="panel">
    <div class="meta-grid">
      
      <div class="info-block">
        <h3>👤 Ügyfél információk</h3>
        <strong>Név:</strong> <?= htmlspecialchars($job['customer_name']) ?><br>
        <strong>Telefon:</strong> <?= $job['customer_phone'] ? htmlspecialchars($job['customer_phone']) : 'Nincs megadva' ?><br>
        <strong>Email:</strong> <?= $job['customer_email'] ? htmlspecialchars($job['customer_email']) : 'Nincs megadva' ?><br>
        <strong>Cím:</strong> <?= htmlspecialchars($job['customer_address']) ?>
      </div>

      <div class="info-block">
        <h3>📅 Időpont & Típus</h3>
        <strong>Dátum:</strong> <?= $job['scheduled_date'] ? date('Y.m.d.', strtotime($job['scheduled_date'])) : 'Nincs ütemezve' ?><br>
        <strong>Időpont:</strong> <?= $job['scheduled_time'] ? date('H:i', strtotime($job['scheduled_time'])) : 'Nincs megadva' ?><br>
        <strong>Munka jellege:</strong> <span style="text-transform: uppercase; font-weight:600; color:var(--accent);"><?= htmlspecialchars($job['job_type']) ?></span><br>
        <strong>Rögzítve:</strong> <?= date('Y.m.d. H:i', strtotime($job['created_at'])) ?>
      </div>

      <?php if (!empty($job['customer_permanent_note'])): ?>
        <div class="info-block">
          <h3>📌 Állandó megjegyzés az ügyfélhez</h3>
          <span style="color: var(--muted); font-style: italic;"><?= nl2br(htmlspecialchars($job['customer_permanent_note'])) ?></span>
        </div>
      <?php endif; ?>

    </div>

    <?php if (!empty($job['description'])): ?>
      <div style="background: rgba(0,0,0,0.2); padding: 12px; border-radius: 8px; margin-top: 14px; font-size: 14px;">
        <strong>Munkaleírás / Bejelentett hiba:</strong><br>
        <?= nl2br(htmlspecialchars($job['description'])) ?>
      </div>
    <?php endif; ?>

    <form class="status-bar" method="post" action="<?= BASE_URL ?>/api/update_job_status.php">
      <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
      <label style="flex-direction: row; align-items: center; gap: 10px; margin: 0; color: #fff;">
        <strong>Munkalap státusza:</strong>
        <select name="status" style="width: auto; padding: 6px 12px; border-radius: 8px;">
          <option value="idopontozva" <?= $job['status'] === 'idopontozva' ? 'selected' : '' ?>>Időpontozva</option>
          <option value="folyamatban" <?= $job['status'] === 'folyamatban' ? 'selected' : '' ?>>Folyamatban</option>
          <option value="kesz" <?= $job['status'] === 'kesz' ? 'selected' : '' ?>>Kész (Elvégezve)</option>
          <option value="szamlazva" <?= $job['status'] === 'szamlazva' ? 'selected' : '' ?>>Számlázva</option>
          <option value="torolve" <?= $job['status'] === 'torolve' ? 'selected' : '' ?>>Törölve</option>
        </select>
      </label>
      <button type="submit" class="success" style="padding: 7px 14px; font-size: 14px;">Státusz frissítése 💾</button>
    </form>
  </div>

  <h2>🛠️ Elvégzett munkák helyiségenként</h2>

  <?php if (count($zones) > 0): ?>
    <?php foreach ($zones as $zone): ?>
      <div class="zone">
        <div class="zone-title">
          <h3 style="margin: 0; color: var(--accent); font-size: 18px;">📍 Zóna: <?= htmlspecialchars($zone['zone_name']) ?></h3>
        </div>

        <div class="rooms">
          <?php if (isset($roomsByZone[$zone['id']])): ?>
            <?php foreach ($roomsByZone[$zone['id']] as $room): ?>
              <div class="room">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; border-bottom: 1px solid rgba(255,255,255,0.03); padding-bottom: 8px; margin-bottom: 10px;">
                  <h4 style="margin: 0; font-size: 16px; color: #fff;"><?= htmlspecialchars($room['room_name']) ?></h4>
                  <span style="font-size: 13px; font-weight: 700; color: var(--accent-2);">
                    ⚙️ <?= getMaintenanceModeLabel($room['maintenance_mode']) ?>
                  </span>
                </div>

                <?php if (!empty($room['indoor_unit_type']) || !empty($room['indoor_serial']) || !empty($room['outdoor_unit_type']) || !empty($room['outdoor_serial'])): ?>
                  <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px;">
                    <?php if (!empty($room['indoor_unit_type'])): ?>
                      <span class="unit-badge">Beltéri: <strong><?= htmlspecialchars($room['indoor_unit_type']) ?></strong></span>
                    <?php endif; ?>
                    <?php if (!empty($room['indoor_serial'])): ?>
                      <span class="unit-badge">S/N: <strong><?= htmlspecialchars($room['indoor_serial']) ?></strong></span>
                    <?php endif; ?>
                    <?php if (!empty($room['outdoor_unit_type'])): ?>
                      <span class="unit-badge">Kültéri: <strong><?= htmlspecialchars($room['outdoor_unit_type']) ?></strong></span>
                    <?php endif; ?>
                    <?php if (!empty($room['outdoor_serial'])): ?>
                      <span class="unit-badge">S/N: <strong><?= htmlspecialchars($room['outdoor_serial']) ?></strong></span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <?php if (!empty($room['note'])): ?>
                  <p style="margin: 0 0 10px 0; font-size: 14px; color: var(--text); background: rgba(0,0,0,0.1); padding: 8px; border-radius: 6px; line-height: 1.4;">
                    <strong>Helyszíni jegyzet:</strong><br>
                    <?= nl2br(htmlspecialchars($room['note'])) ?>
                  </p>
                <?php endif; ?>

                <?php if (isset($photosByRoom[$room['id']])): ?>
                  <div style="font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px;">📷 Csatolt fotók (NAS):</div>
                  <div class="photo-grid">
                    <?php foreach ($photosByRoom[$room['id']] as $photo): ?>
                      <div class="photo-card">
                        <div style="font-size: 24px; padding: 10px 0;">🖼️</div>
                        <span style="color:#fff; font-weight:600;"><?= getPhotoTypeLabel($photo['photo_type']) ?></span>
                        <span title="<?= htmlspecialchars($photo['stored_filename']) ?>"><?= htmlspecialchars($photo['stored_filename']) ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p style="color: var(--muted); font-style: italic; font-size: 14px; padding-left: 10px;">Nincsenek helyiségek rögzítve ebben a zónában.</p>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="panel">
      <p style="color: var(--muted); text-align: center; margin: 10px 0;">Ehhez a munkához nem lett részletes helyszíni struktúra rögzítve.</p>
    </div>
  <?php endif; ?>

</main>

</body>
</html>