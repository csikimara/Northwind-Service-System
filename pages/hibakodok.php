<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// Golyóálló fájlkereső
$jsonPaths = [
    __DIR__ . '/../data/imports/hibakodok.json',
    __DIR__ . '/../data/hibakodok.json',
    __DIR__ . '/hibakodok.json',
    __DIR__ . '/../imports/hibakodok.json'
];

$jsonContent = false;
foreach ($jsonPaths as $path) {
    if (is_file($path)) {
        $jsonContent = file_get_contents($path);
        break;
    }
}

$allErrors = [];

if ($jsonContent !== false) {
    $decoded = json_decode($jsonContent, true);
    if (is_array($decoded)) {
        // REKURZÍV KERESŐ
        $rawErrors = [];
        $findErrors = function($array) use (&$findErrors, &$rawErrors) {
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    if (isset($value['hiba']) || isset($value['megoldas'])) {
                        $value['code_name'] = $key;
                        $rawErrors[] = $value;
                    } else {
                        $findErrors($value);
                    }
                }
            }
        };
        $findErrors($decoded);

        // Adatok egységesítése
        foreach ($rawErrors as $data) {
            $code = (string)($data['code_name'] ?? 'Ismeretlen');
            $hiba = (string)($data['hiba'] ?? 'Ismeretlen hiba');
            $megoldas = (string)($data['megoldas'] ?? 'Nincs javasolt megoldás.');
            
            $op = 0; $timer = 0; $eco = 0;

            if (preg_match('/OP_(\d+)/i', $code, $m)) $op = (int)$m[1];
            if (preg_match('/TIMER_(\d+)/i', $code, $m)) $timer = (int)$m[1];
            if (preg_match('/ECO_(\d+)/i', $code, $m)) $eco = (int)$m[1];

            if (isset($data['led_operation'])) $op = (int)$data['led_operation'];
            if (isset($data['led_timer'])) $timer = (int)$data['led_timer'];
            if (isset($data['led_economy'])) $eco = (int)$data['led_economy'];

            $allErrors[] = [
                'code' => $code,
                'hiba' => $hiba,
                'megoldas' => $megoldas,
                'op' => $op,
                'timer' => $timer,
                'eco' => $eco
            ];
        }
    }
}
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <title>Hibakódok & Diagnosztika</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/app.css">
  <style>
    /* Videó alapján készült LED Kalkulátor Dizájn */
    .led-calculator { background: var(--panel); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 25px; }
    
    .led-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; text-align: center; }
    .led-col { display: flex; flex-direction: column; gap: 5px; }
    
    .led-badge { color: white; font-weight: bold; font-size: 12px; padding: 6px 0; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; }
    .bg-green { background: #22c55e; }
    .bg-orange { background: #f97316; }
    
    .led-val { background: white; color: black; font-size: 28px; font-weight: 900; padding: 12px 0; border-radius: 6px; border: 2px solid #ccc; }
    
    .led-btn { background: #1e40af; color: white; border: none; padding: 12px 0; font-size: 20px; border-radius: 6px; cursor: pointer; transition: 0.1s; border-bottom: 3px solid #1e3a8a; }
    .led-btn:active { background: #1d4ed8; transform: translateY(2px); border-bottom: 1px solid #1e3a8a; }

    .calc-result { background: rgba(59, 130, 246, 0.15); border: 2px solid #3b82f6; border-radius: 10px; padding: 15px; margin-top: 20px; display: none; text-align: center; }
    
    /* Kereső Kártya Dizájn */
    .search-box { width: 100%; padding: 15px; border-radius: 8px; border: 1px solid var(--border); background: #111827; color: white; font-size: 16px; margin-bottom: 20px; box-sizing: border-box; }
    .err-card { background: #1f2937; border-left: 4px solid #ef4444; padding: 15px; border-radius: 8px; margin-bottom: 15px; }
    .err-card.ok { border-left-color: #f59e0b; }
    .badge-box { font-size: 12px; background: rgba(255,255,255,0.05); padding: 4px 8px; border-radius: 4px; display: inline-block; margin-top: 5px; }
    .err-sol { background: rgba(255,255,255,0.03); padding: 10px; border-radius: 6px; border-left: 2px solid #38bdf8; margin-top: 10px; text-align: left; }
  </style>
</head>
<body>

<main class="page">
  <div class="page-header">
    <div>
      <a class="back" href="<?= BASE_URL ?>/index.php">← Vissza a főmenübe</a>
      <h1>Fujitsu & Általános Hibakódok</h1>
      <p>Kombinált diagnosztikai segédlet: Kalkulátor és Kereső egyben.</p>
    </div>
  </div>

  <?php if (empty($allErrors)): ?>
    <div class="panel" style="border-color: #ef4444; text-align: center; padding: 20px;">
      <p style="color: #ef4444; font-weight: bold;">⚠️ Adatbázis hiba: Nem találtam egyetlen hibakódot sem a JSON fájlban!</p>
    </div>
  <?php else: ?>

    <div class="led-calculator">
      <h2 style="margin-top:0; font-size: 16px; color: #fff; margin-bottom: 20px; text-align: center;">🎛️ Intelligens LED Kalkulátor</h2>
      
      <div class="led-grid">
        <div class="led-col">
          <div class="led-badge bg-green">Operation</div>
          <div class="led-val" id="val-op">0</div>
          <button class="led-btn" onclick="changeVal('op', 1)">▲</button>
          <button class="led-btn" onclick="changeVal('op', -1)">▼</button>
        </div>
        
        <div class="led-col">
          <div class="led-badge bg-orange">Timer</div>
          <div class="led-val" id="val-timer">0</div>
          <button class="led-btn" onclick="changeVal('timer', 1)">▲</button>
          <button class="led-btn" onclick="changeVal('timer', -1)">▼</button>
        </div>
        
        <div class="led-col">
          <div class="led-badge bg-green">Economy</div>
          <div class="led-val" id="val-eco">0</div>
          <button class="led-btn" onclick="changeVal('eco', 1)">▲</button>
          <button class="led-btn" onclick="changeVal('eco', -1)">▼</button>
        </div>
      </div>

      <div id="calc-result" class="calc-result">
        <h3 style="color:#fff; margin:0 0 10px 0; font-size: 20px;" id="res-title">--</h3>
        <p style="color:#e5e7eb; font-size:15px; margin:0; line-height: 1.5;" id="res-desc">--</p>
      </div>
    </div>

    <h2 style="font-size: 16px; color: #fff;">🔍 Keresés a teljes listában</h2>
    <input type="text" id="searchInput" class="search-box" placeholder="Írd be a kódot (pl. OP) vagy a hibát (pl. szenzor)..." onkeyup="filterCards()">

    <div id="errorListContainer">
      <?php foreach ($allErrors as $err): ?>
        <?php 
          $isOk = ($err['op'] === 0 && $err['timer'] === 0 && $err['eco'] === 0 && strpos(strtolower($err['hiba']), 'normál') !== false);
          $cardClass = $isOk ? 'err-card ok' : 'err-card';
          $searchData = strtolower($err['code'] . ' ' . $err['hiba'] . ' ' . $err['megoldas']);
        ?>
        <div class="err-card-wrapper" data-search="<?= htmlspecialchars($searchData) ?>">
          <div class="<?= $cardClass ?>">
            <h3 style="margin:0; color: #fff; font-size:16px;">Kód: [<?= htmlspecialchars(str_replace('_', ' ', $err['code'])) ?>] - <?= htmlspecialchars($err['hiba']) ?></h3>
            
            <div class="badge-box">
              <?php if ($isOk): ?>
                <span style="color:#9ca3af;">Nincs LED villogás</span>
              <?php else: ?>
                LED: 
                <?php if($err['op'] > 0): ?><span style="color:#ef4444;">Op. x<?= $err['op'] ?></span><?php endif; ?>
                <?php if($err['op'] > 0 && $err['timer'] > 0): ?> | <?php endif; ?>
                <?php if($err['timer'] > 0): ?><span style="color:#f59e0b;">Timer x<?= $err['timer'] ?></span><?php endif; ?>
                <?php if(($err['op'] > 0 || $err['timer'] > 0) && $err['eco'] > 0): ?> | <?php endif; ?>
                <?php if($err['eco'] > 0): ?><span style="color:#22c55e;">Eco x<?= $err['eco'] ?></span><?php endif; ?>
              <?php endif; ?>
            </div>
            
            <div class="err-sol">
                <strong style="font-size:13px; color:#e5e7eb;">Javasolt ellenőrzés / Megoldás:</strong><br>
                <span style="font-size:13px; color:#d1d5db;"><?= nl2br(htmlspecialchars($err['megoldas'])) ?></span>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>
</main>

<script>
  // --- 1. GOMBOS KALKULÁTOR LOGIKA ---
  const errors = <?= json_encode($allErrors, JSON_UNESCAPED_UNICODE) ?> || [];

  const validOp = [...new Set(errors.map(e => e.op))].sort((a, b) => a - b);
  const validTimer = [...new Set(errors.map(e => e.timer))].sort((a, b) => a - b);
  const validEco = [...new Set(errors.map(e => e.eco))].sort((a, b) => a - b);

  if (!validOp.includes(0)) validOp.unshift(0);
  if (!validTimer.includes(0)) validTimer.unshift(0);
  if (!validEco.includes(0)) validEco.unshift(0);

  let idxOp = 0;
  let idxTimer = 0;
  let idxEco = 0;

  function changeVal(type, dir) {
    if (type === 'op') {
      idxOp += dir;
      if (idxOp < 0) idxOp = 0;
      if (idxOp >= validOp.length) idxOp = validOp.length - 1;
    } else if (type === 'timer') {
      idxTimer += dir;
      if (idxTimer < 0) idxTimer = 0;
      if (idxTimer >= validTimer.length) idxTimer = validTimer.length - 1;
    } else if (type === 'eco') {
      idxEco += dir;
      if (idxEco < 0) idxEco = 0;
      if (idxEco >= validEco.length) idxEco = validEco.length - 1;
    }
    updateUI();
  }

  function updateUI() {
    const currentOp = validOp[idxOp];
    const currentTimer = validTimer[idxTimer];
    const currentEco = validEco[idxEco];

    document.getElementById('val-op').innerText = currentOp;
    document.getElementById('val-timer').innerText = currentTimer;
    document.getElementById('val-eco').innerText = currentEco;

    const resBox = document.getElementById('calc-result');

    if (currentOp === 0 && currentTimer === 0 && currentEco === 0) {
      resBox.style.display = 'none';
      return;
    }

    const match = errors.find(e => e.op === currentOp && e.timer === currentTimer && e.eco === currentEco);

    if (match) {
      resBox.style.display = 'block';
      document.getElementById('res-title').innerText = match.hiba;
      document.getElementById('res-desc').innerHTML = match.megoldas.replace(/\\n/g, '<br>');
    } else {
      resBox.style.display = 'none';
    }
  }

  // --- 2. HAGYOMÁNYOS KERESŐ LOGIKA ---
  function filterCards() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const cards = document.querySelectorAll('.err-card-wrapper');
    
    cards.forEach(card => {
      const text = card.getAttribute('data-search');
      if (text.includes(input)) {
        card.style.display = 'block';
      } else {
        card.style.display = 'none';
      }
    });
  }

  // Inicializálás
  updateUI();
</script>

</body>
</html>