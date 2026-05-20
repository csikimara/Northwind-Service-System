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
  <style>
      .listening-active { background: #ef4444 !important; color: white !important; animation: pulse-mic 1.5s infinite; }
      @keyframes pulse-mic { 0% { opacity: 1; } 50% { opacity: 0.7; } 100% { opacity: 1; } }
      .loading-state { opacity: 0.5; pointer-events: none; }
  </style>
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

    <h2>Intelligens Funkciók</h2>
    
    <div style="margin-bottom: 24px; padding: 16px; background: rgba(56, 189, 248, 0.05); border-radius: 12px; border-left: 4px solid var(--accent); border-top: 1px solid var(--border); border-right: 1px solid var(--border); border-bottom: 1px solid var(--border);">
      <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <button type="button" id="start-dictation-btn" class="btn" style="flex: 1; min-width: 200px;" onclick="startVoiceRecognition()">🎤 Intelligens Diktálás indítása</button>
        <button type="button" id="fetch-gmail-btn" class="btn secondary" style="flex: 1; min-width: 200px;" onclick="fetchEmailData()">📧 Emailekből adatok lekérdezése</button>
      </div>
      <div id="dictation-status" style="color: var(--muted); font-size: 13px; margin-top: 12px; padding: 10px; background: var(--input); border: 1px solid var(--border); border-radius: 8px; min-height: 20px;">
        Készenlétben. Kattints az "Intelligens Diktálás indítása" gombra...
      </div>
    </div>

    <h2>Ügyfél adatai</h2>
    <div class="form-grid">
      <label>
        Megrendelő neve *
        <input type="text" name="customer_name" id="f_nev" required autocomplete="name" placeholder="pl. Megyer Gergő" value="<?= htmlspecialchars($existingCustomer['name'] ?? '') ?>">
      </label>

      <label>
        Telefonszám
        <input type="tel" name="customer_phone" id="f_tel" autocomplete="tel" placeholder="pl. +36 30 123 4567" value="<?= htmlspecialchars($existingCustomer['phone'] ?? '') ?>">
      </label>

      <label>
        Email cím
        <input type="email" name="customer_email" id="f_email" autocomplete="email" placeholder="pl. northwindhutestechnikakft@gmail.com" value="<?= htmlspecialchars($existingCustomer['email'] ?? '') ?>">
      </label>
    </div>

    <label style="margin-top: 14px;">
      Cím (Telepítés / munkavégzés helye)
      <textarea name="customer_address" id="f_cim" placeholder="Irányítószám, Város, Utca, Házszám, Emelet/Ajtó"><?= htmlspecialchars($existingCustomer['address'] ?? '') ?></textarea>
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
        <select name="job_type" id="f_tipus">
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
      <textarea name="job_description" id="f_megj" placeholder="pl. Ügyfél elmondása szerint nem hűt rendesen, vagy: Éves nagymosás esedékes 3 db gépre."></textarea>
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
      <button type="button" class="secondary" onclick="clearZones()">❌ Üresen hagy (Egyedileg adom meg később)</button>
    </div>

    <div id="zones" style="margin-top: 20px;"></div>

    <div class="actions" style="margin-top: 30px; border-top: 1px solid var(--border); padding-top: 20px;">
      <button type="submit" class="success">💾 Ügyfél és Időpont mentése</button>
      <a href="<?= BASE_URL ?>/index.php" class="btn secondary">Mégse</a>
    </div>

  </form>

</main>

<script>
// A te éles és működő Google Script URL-ed
const SCRIPT_URL = "https://script.google.com/macros/s/AKfycbyGwd1BHK6V2P8jToWW3zytvc-ZrOyVZj6GIWsK1RmwAS_Eean5fzY4_OrSra12O2EJmA/exec";

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

// ========== DIKTÁLÁS INTELLIGENS MEGOLDÁS ==========
function startVoiceRecognition() {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
        alert("A böngésződ nem támogatja a hangfelismerést. Használd a Chrome-ot!");
        return;
    }

    const recognition = new SpeechRecognition();
    recognition.lang = 'hu-HU';
    recognition.interimResults = false;

    const btn = document.getElementById('start-dictation-btn');
    const statusDiv = document.getElementById('dictation-status');
    
    btn.innerHTML = "⌛ FIGYELEK... (Beszélj)";
    btn.classList.add('listening-active');
    statusDiv.innerText = "Mondd a szöveget, az AI automatikusan szétválogatja a mezőket...";

    recognition.onresult = (event) => {
        const transcript = event.results[0][0].transcript;
        btn.innerHTML = "🤖 AI ELEMZÉS...";
        statusDiv.innerText = `Feldolgozás a Google Scripten keresztül: "${transcript}"`;
        
        fetch(`${SCRIPT_URL}?mode=ai_parse&text=${encodeURIComponent(transcript)}`)
            .then(res => res.json())
            .then(data => {
                if(data.nev) document.getElementById('f_nev').value = data.nev;
                if(data.cim) document.getElementById('f_cim').value = data.cim;
                if(data.tel) document.getElementById('f_tel').value = data.tel;
                if(data.email) document.getElementById('f_email').value = data.email;
                if(data.tipus) document.getElementById('f_tipus').value = data.tipus.toLowerCase();
                if(data.leiras) document.getElementById('f_megj').value = data.leiras;
                
                btn.innerHTML = "🎤 Intelligens Diktálás indítása";
                btn.classList.remove('listening-active');
                statusDiv.innerText = "✅ SIKERES AI KITÖLTÉS! Minden adat a helyére került.";
            })
            .catch(err => {
                alert("AI feldolgozási hiba történt!");
                btn.innerHTML = "🎤 Intelligens Diktálás indítása";
                btn.classList.remove('listening-active');
                statusDiv.innerText = "❌ Hiba a kitöltés során.";
            });
    };

    recognition.onerror = (event) => {
        btn.innerHTML = "🎤 Intelligens Diktálás indítása";
        btn.classList.remove('listening-active');
        statusDiv.innerText = "❌ Mikrofon hiba vagy elutasított hozzáférés.";
    };

    recognition.start();
}

// ========== EMAIL LEKÉRDEZÉS INTELLIGENS MEGOLDÁS ==========
async function fetchEmailData() {
  const nev = document.getElementById('f_nev').value;
  if (!nev) { 
    alert("Előbb írd be az ügyfél nevét a 'Megrendelő neve' mezőbe, hogy rákereshessünk a levelek között!"); 
    return; 
  }
  
  const btn = document.getElementById('fetch-gmail-btn') || document.querySelector('button[onclick="fetchEmailData()"]');
  const statusDiv = document.getElementById('dictation-status');
  
  // Gomb lezárása a dupla kattintás ellen és vizuális visszajelzés
  const eredetiGombSzoveg = btn.innerHTML;
  btn.innerText = "⌛ GMAIL SZINKRON...";
  btn.style.opacity = "0.5";
  btn.style.pointerEvents = "none";
  if (statusDiv) {
    statusDiv.innerText = `Biztonságos felhőkapcsolat felépítése a Google API-val... Keresés '${nev}' névre...`;
  }

  // A te már bizonyítottan működő Google Scripted éles címe
  const SCRIPT_URL = "https://script.google.com/macros/s/AKfycbyGwd1BHK6V2P8jToWW3zytvc-ZrOyVZj6GIWsK1RmwAS_Eean5fzY4_OrSra12O2EJmA/exec";

  try {
    // Lekérés a Google Scripttől
    const response = await fetch(`${SCRIPT_URL}?mode=gmail_parse&text=${encodeURIComponent(nev)}`);
    const data = await response.json();

    if (data.error) { 
      alert(data.error); 
      if (statusDiv) statusDiv.innerText = "❌ Nem találhatók adatok a megadott névhez.";
    } else {
      // Az adatokat tűpontosan betöltjük a megfelelő kék űrlap mezőkbe
      if (data.cim) document.getElementById('f_cim').value = data.cim;
      if (data.tel) document.getElementById('f_tel').value = data.tel;
      if (data.email) document.getElementById('f_email').value = data.email;
      
      if (statusDiv) statusDiv.innerText = "✅ SIKER! Az ügyfél lakcíme, telefonszáma és emailje beolvasva a Gmailből.";
      alert("Adatok sikeresen kinyerve az e-mailből!");
    }
  } catch (error) {
    console.error('Gmail lekérdezési hiba:', error);
    alert('Hiba történt a Gmail adatok lekérése közben!');
    if (statusDiv) statusDiv.innerText = "❌ Kommunikációs hiba a Google Script és a szerver között.";
  } finally {
    // Gomb visszaállítása eredeti állapotba
    btn.innerHTML = eredetiGombSzoveg;
    btn.style.opacity = "1";
    btn.style.pointerEvents = "auto";
  }
}
</script>

</body>
</html>