<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$defaultRooms = [
    'Nappali',
    'Szülői háló 1',
    'Szülői háló 2',
    'Gyerekszoba 1',
    'Gyerekszoba 2',
    'Gyerekszoba 3',
    'Gyerekszoba 4',
    'Dolgozó',
    'Gardrób',
    'Előtér',
    'Dühöngő',
    'Konyha',
    'Étkező',
    'Egyéb'
];
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <title>Karbantartás / Felmérés - <?= htmlspecialchars(APP_NAME) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/app.css">
</head>
<body>

<main class="page">

  <div class="page-header">
    <div>
      <a class="back" href="<?= BASE_URL ?>/index.php">← Vissza</a>
      <h1>Karbantartás / Felmérés</h1>
      <p>Helyiségenkénti megjegyzések, karbantartási módok és fotók.</p>
    </div>
  </div>

  <form id="maintenanceForm" class="panel" method="post" enctype="multipart/form-data" action="<?= BASE_URL ?>/api/save_maintenance.php">
    
    <h2>Alapadatok</h2>
    <div class="form-grid">
      <label>
        Megrendelő neve
        <input type="text" name="customer_name" required autocomplete="name">
      </label>

      <label>
        Telefonszám
        <input type="tel" name="customer_phone" autocomplete="tel">
      </label>

      <label>
        Email
        <input type="email" name="customer_email" autocomplete="email">
      </label>

      <label>
        Dátum
        <input type="date" name="scheduled_date" value="<?= date('Y-m-d') ?>">
      </label>

      <label>
        Időpont
        <input type="time" name="scheduled_time">
      </label>

      <label>
        Munka típusa
        <select name="job_type">
          <option value="karbantartas">Karbantartás</option>
          <option value="felmeres">Felmérés</option>
          <option value="javitas">Javítás</option>
          <option value="telepites">Telepítés</option>
          <option value="egyeb">Egyéb</option>
        </select>
      </label>
    </div>

    <label style="margin-top:14px;">
      Cím
      <textarea name="customer_address"></textarea>
    </label>

    <label style="margin-top:14px;">
      Általános megjegyzés
      <textarea name="job_description"></textarea>
    </label>

    <div class="actions">
      <button type="button" class="secondary" onclick="addDefaultHouseTemplate()">🏠 Családi ház sablon</button>
      <button type="button" class="secondary" onclick="addOfficeTemplate()">🏢 Irodaház sablon</button>
      <button type="button" onclick="addZone()">➕ Emelet / zóna hozzáadása</button>
    </div>

    <h2>Helyiségek</h2>
    <div id="zones"></div>

    <div class="actions">
      <button type="submit" class="success">💾 Karbantartás mentése</button>
    </div>

    <p id="formStatus" class="status"></p>
  </form>

</main>

<script>
const defaultRooms = <?= json_encode($defaultRooms, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>;
let zoneCounter = 0;
let roomCounter = 0;

function safeName(value) {
  return String(value || '')
    .trim()
    .replace(/\s+/g, '_')
    .replace(/[^\p{L}\p{N}_-]/gu, '')
    .toLowerCase();
}

function addZone(zoneName = '') {
  zoneCounter++;
  const zones = document.getElementById('zones');
  const zoneId = `zone_${zoneCounter}`;
  
  const div = document.createElement('div');
  div.className = 'zone';
  div.dataset.zoneId = zoneId;
  div.innerHTML = `
    <div class="zone-title">
      <label style="flex:1;">
        Emelet / zóna neve
        <input type="text" name="zones[${zoneId}][name]" value="${escapeHtml(zoneName || 'Új zóna')}" required>
      </label>
      <button type="button" class="danger" onclick="this.closest('.zone').remove()">Törlés</button>
    </div>
    <div class="rooms"></div>
    <div class="actions">
      <button type="button" class="secondary" onclick="addRoom('${zoneId}')">➕ Helyiség hozzáadása</button>
    </div>
  `;
  
  zones.appendChild(div);
  return zoneId;
}

function addRoom(zoneId, roomName = '') {
  roomCounter++;
  const zone = document.querySelector(`[data-zone-id="${zoneId}"]`);
  const rooms = zone.querySelector('.rooms');
  const roomId = `room_${roomCounter}`;
  
  const div = document.createElement('div');
  div.className = 'room';
  div.dataset.roomId = roomId;
  div.innerHTML = `
    <h4>Helyiség</h4>
    <div class="form-grid">
      <label>
        Helyiség neve
        <input type="text" name="zones[${zoneId}][rooms][${roomId}][name]" value="${escapeHtml(roomName || '')}" placeholder="pl. Nappali" required>
      </label>
      
      <label>
        Karbantartás módja
        <select name="zones[${zoneId}][rooms][${roomId}][maintenance_mode]">
          <option value="nincs">Nincs munka</option>
          <option value="alap">Alap karbantartás</option>
          <option value="nagy_zsakos">Nagy / zsákos mosás</option>
          <option value="javitas">Javítás</option>
          <option value="felmeres">Felmérés</option>
          <option value="alkatresz_szukseges">Alkatrész szükséges</option>
          <option value="nem_hozzaferheto">Nem hozzáférhető</option>
          <option value="ugyfel_nem_kerte">Ügyfél nem kérte</option>
        </select>
      </label>
      
      <label>
        Beltéri típusa
        <input type="text" name="zones[${zoneId}][rooms][${roomId}][indoor_unit_type]" placeholder="pl. Fujitsu ASYG...">
      </label>
      
      <label>
        Beltéri szériaszám
        <input type="text" name="zones[${zoneId}][rooms][${roomId}][indoor_serial]">
      </label>
      
      <label>
        Kültéri típusa
        <input type="text" name="zones[${zoneId}][rooms][${roomId}][outdoor_unit_type]">
      </label>
      
      <label>
        Kültéri szériaszám
        <input type="text" name="zones[${zoneId}][rooms][${roomId}][outdoor_serial]">
      </label>
    </div>

    <label style="margin-top:12px;">
      Helyiség megjegyzés
      <textarea name="zones[${zoneId}][rooms][${roomId}][note]" placeholder="Mit végeztél el, hiba, alkatrészigény, hozzáférés, stb."></textarea>
    </label>

    <h4>Fotók</h4>
    <div class="photo-grid">
      <label>
        Beltéri matrica
        <input type="file" name="photos[${zoneId}][${roomId}][belteri_matrica][]" accept="image/*" multiple>
      </label>
      <label>
        Kültéri matrica
        <input type="file" name="photos[${zoneId}][${roomId}][kulteri_matrica][]" accept="image/*" multiple>
      </label>
      <label>
        Állapot előtte
        <input type="file" name="photos[${zoneId}][${roomId}][allapot_elotte][]" accept="image/*" multiple>
      </label>
      <label>
        Állapot utána
        <input type="file" name="photos[${zoneId}][${roomId}][allapot_utana][]" accept="image/*" multiple>
      </label>
      <label>
        Hiba / alkatrész
        <input type="file" name="photos[${zoneId}][${roomId}][hiba][]" accept="image/*" multiple>
      </label>
      <label>
        Egyéb fotó
        <input type="file" name="photos[${zoneId}][${roomId}][egyeb][]" accept="image/*" multiple>
      </label>
    </div>

    <div class="actions">
      <button type="button" class="danger" onclick="this.closest('.room').remove()">Helyiség törlése</button>
    </div>
  `;
  
  rooms.appendChild(div);
}

function addDefaultHouseTemplate() {
  document.getElementById('zones').innerHTML = '';
  zoneCounter = 0;
  roomCounter = 0;

  const ground = addZone('Földszint');
  defaultRooms.forEach(room => addRoom(ground, room));

  const floor = addZone('Emelet 1');
  defaultRooms.forEach(room => addRoom(floor, room));
}

function addOfficeTemplate() {
  document.getElementById('zones').innerHTML = '';
  zoneCounter = 0;
  roomCounter = 0;

  const zone = addZone('Irodaház');
  const officeRooms = [
    'Igazgatói iroda',
    'Könyvelés',
    'Pénzügy',
    'Konyha',
    'Üzemeltetés',
    'Tárgyaló 1',
    'Tárgyaló 2',
    'Recepció',
    'Szerverhelyiség',
    'Open office',
    'Raktár',
    'Egyéb'
  ];
  officeRooms.forEach(room => addRoom(zone, room));
}

function escapeHtml(str) {
  return String(str)
    .replaceAll('&', '&amp;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;');
}

addDefaultHouseTemplate();
</script>
</body>
</html>