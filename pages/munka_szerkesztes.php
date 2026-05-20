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
    // Munka és ügyfél alapadatainak lekérése
    $stmtJob = $pdo->prepare("
        SELECT j.*, c.name AS customer_name, c.address AS customer_address
        FROM jobs j
        JOIN customers c ON j.customer_id = c.id
        WHERE j.id = :job_id
    ");
    $stmtJob->execute([':job_id' => $jobId]);
    $job = $stmtJob->fetch();

    if (!$job) {
        die('Hiba: A keresett munka nem található.');
    }

    // Zónák lekérése
    $stmtZones = $pdo->prepare("SELECT * FROM job_zones WHERE job_id = :job_id ORDER BY sort_order ASC");
    $stmtZones->execute([':job_id' => $jobId]);
    $zones = $stmtZones->fetchAll();

    // Helyiségek lekérése
    $stmtRooms = $pdo->prepare("SELECT * FROM job_rooms WHERE job_id = :job_id ORDER BY sort_order ASC");
    $stmtRooms->execute([':job_id' => $jobId]);
    $allRooms = $stmtRooms->fetchAll();

    // Csoportosítás zónák szerint
    $roomsByZone = [];
    foreach ($allRooms as $room) {
        $roomsByZone[$room['zone_id']][] = $room;
    }

} catch (Throwable $e) {
    if (APP_DEBUG) {
        die('Adatbázis hiba: ' . htmlspecialchars($e->getMessage()));
    }
    die('Hiba történt az adatok betöltésekor.');
}
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <title>Munkalap Kitöltése #<?= $job['id'] ?> - <?= htmlspecialchars(APP_NAME) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/app.css">
  <style>
    .room-edit-card {
      background: rgba(255, 255, 255, 0.01);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 16px;
      margin-bottom: 20px;
    }
    .room-edit-card h4 {
      margin: 0 0 14px 0;
      color: #fff;
      font-size: 16px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      padding-bottom: 6px;
    }
    .photo-upload-section {
      margin-top: 14px;
      background: rgba(0, 0, 0, 0.2);
      padding: 12px;
      border-radius: 8px;
    }
    .photo-upload-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 12px;
      margin-top: 8px;
    }
    .photo-input-group {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .photo-input-group label {
      font-size: 12px;
      color: var(--muted);
      margin: 0;
    }
    .photo-input-group input[type="file"] {
      font-size: 12px;
      padding: 5px;
    }
  </style>
</head>
<body>

<main class="page">

  <div class="page-header">
    <div>
      <a class="back" href="<?= BASE_URL ?>/pages/munka_reszletek.php?id=<?= $job['id'] ?>">← Vissza a munkalaphoz</a>
      <h1>Helyszíni jegyzőkönyv kitöltése</h1>
      <p>Ügyfél: <strong><?= htmlspecialchars($job['customer_name']) ?></strong> | Cím: <?= htmlspecialchars($job['customer_address']) ?></p>
    </div>
  </div>

  <form method="post" action="<?= BASE_URL ?>/api/save_work_report.php" enctype="multipart/form-data">
    <input type="hidden" name="job_id" value="<?= $job['id'] ?>">

    <?php if (count($zones) > 0): ?>
      <?php foreach ($zones as $zone): ?>
        <div class="panel" style="margin-bottom: 24px;">
          <h2 style="color: var(--accent); margin-top: 0; display: flex; align-items: center; gap: 8px;">
            📍 Zóna: <?= htmlspecialchars($zone['zone_name']) ?>
          </h2>

          <?php if (isset($roomsByZone[$zone['id']])): ?>
            <?php foreach ($roomsByZone[$zone['id']] as $room): ?>
              <div class="room-edit-card">
                <h4>🚪 Helyiség: <?= htmlspecialchars($room['room_name']) ?></h4>
                
                <div class="form-grid">
                  <label>
                    Karbantartás / Munka módja
                    <select name="rooms[<?= $room['id'] ?>][maintenance_mode]">
                      <option value="nincs" <?= $room['maintenance_mode'] === 'nincs' ? 'selected' : '' ?>>Nincs munka / Kihagyva</option>
                      <option value="alap" <?= $room['maintenance_mode'] === 'alap' ? 'selected' : '' ?>>Alap karbantartás</option>
                      <option value="nagy_zsakos" <?= $room['maintenance_mode'] === 'nagy_zsakos' ? 'selected' : '' ?>>Nagy / zsákos mosás 🧼</option>
                      <option value="javitas" <?= $room['maintenance_mode'] === 'javitas' ? 'selected' : '' ?>>Javítás 🛠️</option>
                      <option value="felmeres" <?= $room['maintenance_mode'] === 'felmeres' ? 'selected' : '' ?>>Felmérés</option>
                      <option value="alkatresz_szukseges" <?= $room['maintenance_mode'] === 'alkatresz_szukseges' ? 'selected' : '' ?>>Alkatrész szükséges ⚠️</option>
                      <option value="nem_hozzaferheto" <?= $room['maintenance_mode'] === 'nem_hozzaferheto' ? 'selected' : '' ?>>Nem hozzáférhető</option>
                      <option value="ugyfel_nem_kerte" <?= $room['maintenance_mode'] === 'ugyfel_nem_kerte' ? 'selected' : '' ?>>Ügyfél nem kérte</option>
                    </select>
                  </label>

                  <label>
                    Beltéri egység típusa
                    <input type="text" name="rooms[<?= $room['id'] ?>][indoor_unit_type]" value="<?= htmlspecialchars($room['indoor_unit_type'] ?? '') ?>" placeholder="pl. ASYG09LMCE">
                  </label>

                  <label>
                    Beltéri gyári száma (S/N)
                    <input type="text" name="rooms[<?= $room['id'] ?>][indoor_serial]" value="<?= htmlspecialchars($room['indoor_serial'] ?? '') ?>" placeholder="Gyári szám">
                  </label>

                  <label>
                    Kültéri egység típusa
                    <input type="text" name="rooms[<?= $room['id'] ?>][outdoor_unit_type]" value="<?= htmlspecialchars($room['outdoor_unit_type'] ?? '') ?>" placeholder="pl. AOYG09LMCE">
                  </label>

                  <label>
                    Kültéri gyári száma (S/N)
                    <input type="text" name="rooms[<?= $room['id'] ?>][outdoor_serial]" value="<?= htmlspecialchars($room['outdoor_serial'] ?? '') ?>" placeholder="Gyári szám">
                  </label>
                </div>

                <label style="margin-top: 12px;">
                  Helyszíni jegyzet / Megjegyzés ehhez a géphez
                  <textarea name="rooms[<?= $room['id'] ?>][note]" rows="2" placeholder="pl. Tiszta, gombaölőzve, tálcafűtés ellenőrizve. Vagy: Mókuskerék erősen szennyezett volt."><?= htmlspecialchars($room['note'] ?? '') ?></textarea>
                </label>

                <div class="photo-upload-section">
                  <div style="font-size: 13px; font-weight: 600; color: var(--accent-2); margin-bottom: 8px;">📷 Képek csatolása (Közvetlenül a NAS-ra rendezve)</div>
                  <div class="photo-upload-grid">
                    
                    <div class="photo-input-group">
                      <label>Beltéri matrica / adattábla</label>
                      <input type="file" name="photos[<?= $room['id'] ?>][belteri_matrica][]" accept="image/*" capture="environment" multiple>
                    </div>

                    <div class="photo-input-group">
                      <label>Kültéri matrica / adattábla</label>
                      <input type="file" name="photos[<?= $room['id'] ?>][kulteri_matrica][]" accept="image/*" capture="environment" multiple>
                    </div>

                    <div class="photo-input-group">
                      <label>Állapot ELŐTTE</label>
                      <input type="file" name="photos[<?= $room['id'] ?>][allapot_elotte][]" accept="image/*" capture="environment" multiple>
                    </div>

                    <div class="photo-input-group">
                      <label>Állapot UTÁNA</label>
                      <input type="file" name="photos[<?= $room['id'] ?>][allapot_utana][]" accept="image/*" capture="environment" multiple>
                    </div>

                    <div class="photo-input-group">
                      <label>Hiba / Cserélt alkatrész</label>
                      <input type="file" name="photos[<?= $room['id'] ?>][hiba][]" accept="image/*" capture="environment" multiple>
                    </div>

                  </div>
                </div>

              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p style="color: var(--muted); font-style: italic;">Nincsenek helyiségek ebben a zónában.</p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="panel">
        <p style="color: var(--muted); text-align: center; margin: 0;">Ehhez a munkához nem lettek zónák előkészítve.</p>
      </div>
    <?php endif; ?>

    <div class="actions" style="margin-top: 30px; background: var(--panel); padding: 16px; border-radius: 14px; border: 1px solid var(--border);">
      <button type="submit" class="success" style="width: 100%; padding: 14px; font-size: 16px;">💾 Helyszíni adatok és fotók mentése a NAS-ra</button>
    </div>
  </form>

</main>

</body>
</html>