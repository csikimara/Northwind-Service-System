<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$pdo = db();
$templates = [];
$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$existingCustomer = null;

try {
    if ($customerId > 0) {
        $stmtCust = $pdo->prepare("SELECT * FROM customers WHERE id = :id");
        $stmtCust->execute([':id' => $customerId]);
        $existingCustomer = $stmtCust->fetch();
    }

    $stmt = $pdo->query("SELECT DISTINCT template_name FROM room_templates ORDER BY template_name ASC");
    $templates = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    if (APP_DEBUG) {
        $templateError = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <title>Új ügyfél / Időpont rögzítése - <?= htmlspecialchars(APP_NAME) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/app.css">
</head>
<body>

<main class="page">

  <div class="page-header">
    <div>
      <a class="back" href="<?= BASE_URL ?>/index.php">← Vissza a főmenübe</a>
      <h1><?= $existingCustomer ? 'Új munka rögzítése létező ügyfélhez' : 'Új ügyfél / Időpont rögzítése' ?></h1>
      <p>Munkalap és naptári időpont előkészítése a helyszíni munkavégzéshez.</p>
    </div>
  </div>

  <form id="appointmentForm" class="panel" method="post" action="<?= BASE_URL ?>/api/save_maintenance.php" enctype="multipart/form-data">
    
    <input type="hidden" name="customer_id" value="<?= $customerId ?>">

    <h2>Ügyfél adatai</h2>
    <div class="form-grid">
      <label>
        Megrendelő neve *
        <input type="text" name="customer_name" required autocomplete="name" placeholder="pl. Megyer Gergő" value="<?= htmlspecialchars($existingCustomer['name'] ?? '') ?>">
      </label>

      <label>
        Telefonszám
        <input type="tel" name="customer_phone" autocomplete="tel" placeholder="pl. +36 30 123 4567" value="<?= htmlspecialchars($existingCustomer['phone'] ?? '') ?>">
      </label>

      <label>
        Email cím
        <input type="email" name="customer_email" autocomplete="email" placeholder="pl. northwindhutestechnikakft@gmail.com" value="<?= htmlspecialchars($existingCustomer['email'] ?? '') ?>">
      </label>
    </div>

    <label style="margin-top: 14px;">
      Cím (Telepítés / munkavégzés helye)
      <textarea name="customer_address" placeholder="Irányítószám, Város, Utca, Házszám, Emelet/Ajtó"><?= htmlspecialchars($existingCustomer['address'] ?? '') ?></textarea>
    </label>

    <label style="margin-top: 14px;">
      Ügyfélre vonatkozó állandó megjegyzés
      <textarea name="customer_note" placeholder="pl. Kapucsengő 12, kapu kód: 4412, vagy egyéb fontos háttérinfó..."><?= htmlspecialchars($existingCustomer['note'] ?? '') ?></textarea>
    </label>

    <hr style="border: 0; border-top: 1px solid var(--border); margin: 24px 0;">

    <h2>Munka / Időpont részletei</h2>
    <div class="form-grid">
      <label>
        Tervezett dátum
        <input type="date" name="scheduled_date" value="<?= date('Y-m-d') ?>">
      </label>

      <label>
        Tervezett időpont
        <input type="time" name="scheduled_time">
      </label>

      <label>
        Munka fő típusa
        <select name="job_type">
          <option value="karbantartas" selected>Karbantartás</option>
          <option value="javitas">Javítás</option>
          <option value="telepites">Telepítés</option>
          <option value="felmeres">Felmérés</option>
          <option value="egyeb">Egyéb munka</option>
        </select>
      </label>
    </div>

    <label style="margin-top: 14px;">
      Munka leírása / Bejelentett hiba jellege
      <textarea name="job_description" placeholder="pl. Ügyfél elmondása szerint nem hűt rendesen, vagy: Éves nagymosás esedékes 3 db gépre."></textarea>
    </label>

    <hr style="border: 0; border-top: 1px solid var(--border); margin: 24px 0;">

    <div class="zone-title">
      <h2>Helyszíni struktúra előkészítése</h2>
    </div>
    <p style="color: var(--muted); font-size: 14px; margin: 0 0 14px 0;">
      Válaszd ki, hogy a mentéskor automatikusan generáljunk-e alapértelmezett zónákat és helyiségeket a munkához. Ezzel a helyszínen rengeteg gépelést spórolsz meg.
    </p>

    <div class="actions">
      <button type="button" class="secondary" onclick="generateTemplateDirs('csaladi_haz')">🏠 Családi ház zónák generálása</button>
      <button type="button" class="secondary" onclick="generateTemplateDirs('irodahaz')">🏢 Irodaház zónák generálása</button>
      <button type="button" onclick="clearZones()">❌ Üresen hagy (Egyedileg adom meg később)</button>
    </div>

    <div id="zones" style="margin-top: 20px;"></div>

    <div class="actions" style="margin-top: 30px; border-top: 1px solid var(--border); padding-top: 20px;">
      <button type="submit" class="success">💾 Ügyfél és Időpont mentése</button>
      <a href="<?= BASE_URL ?>/index.php" class="btn secondary">Mégse</a>
    </div>

  </form>

</main>

<script>
const houseRooms = [
  'Nappali', 'Szülői háló 1', 'Szülői háló 2', 'Gyerekszoba 1', 
  'Gyerekszoba 2', 'Gyerekszoba 3', 'Gyerekszoba 4', 'Dolgozó', 
  'Gardrób', 'Előtér', 'Dühöngő', 'Konyha', 'Étkező', 'Egyéb'
];

const officeRooms = [
  'Igazgatói iroda', 'Könyvelés', 'Pénzügy', 'Konyha', 
  'Üzemeltetés', 'Tárgyaló 1', 'Tárgyaló 2', 'Recepció', 
  'Szerverhelyiség', 'Open office', 'Raktár', 'Egyéb'
];

let zoneCounter = 0;
let roomCounter = 0;

function escapeHtml(str) {
  return String(str)
    .replaceAll('&', '&amp;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;');
}

function clearZones() {
  document.getElementById('zones').innerHTML = '';
  zoneCounter = 0;
  roomCounter = 0;
}

function generateTemplateDirs(type) {
  clearZones();
  
  if (type === 'csaladi_haz') {
    const groundId = addZoneRow('Földszint');
    houseRooms.forEach(room => addRoomRow(groundId, room));
    
    const floorId = addZoneRow('Emelet 1');
    houseRooms.forEach(room => addRoomRow(floorId, room));
  } else if (type === 'irodahaz') {
    const officeId = addZoneRow('Irodaház');
    officeRooms.forEach(room => addRoomRow(officeId, room));
  }
}

function addZoneRow(zoneName) {
  zoneCounter++;
  const zonesDiv = document.getElementById('zones');
  const zoneId = `zone_${zoneCounter}`;
  
  const div = document.createElement('div');
  div.className = 'zone';
  div.dataset.zoneId = zoneId;
  div.style.padding = '10px';
  div.style.marginBottom = '10px';
  div.style.border = '1px solid var(--border)';
  div.style.borderRadius = '8px';
  
  div.innerHTML = `
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <strong style="color: var(--accent);">${escapeHtml(zoneName)}</strong>
      <input type="hidden" name="zones[${zoneId}][name]" value="${escapeHtml(zoneName)}">
      <span style="font-size:12px; color:var(--muted);" class="room-count-label"></span>
    </div>
    <div class="rooms-container" style="display:none;"></div>
  `;
  
  zonesDiv.appendChild(div);
  return zoneId;
}

function addRoomRow(zoneId, roomName) {
  roomCounter++;
  const zoneEl = document.querySelector(`[data-zone-id="${zoneId}"]`);
  const container = zoneEl.querySelector('.rooms-container');
  const roomId = `room_${roomCounter}`;
  
  const inputName = `zones[${zoneId}][rooms][${roomId}][name]`;
  const inputMode = `zones[${zoneId}][rooms][${roomId}][maintenance_mode]`;
  
  const hiddenName = document.createElement('input');
  hiddenName.type = 'hidden';
  hiddenName.name = inputName;
  hiddenName.value = roomName;
  
  const hiddenMode = document.createElement('input');
  hiddenMode.type = 'hidden';
  hiddenMode.name = inputMode;
  hiddenMode.value = 'nincs';
  
  container.appendChild(hiddenName);
  container.appendChild(hiddenMode);
  
  const count = container.querySelectorAll('input[name$="[name]"]').length;
  zoneEl.querySelector('.room-count-label').textContent = `(${count} helyiség előkészítve)`;
}

generateTemplateDirs('csaladi_haz');
</script>

</body>
</html>