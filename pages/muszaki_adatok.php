<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$jsonPath = __DIR__ . '/../data/imports/hibakodok.json';
$errorList = [];

if (is_file($jsonPath)) {
    $jsonContent = file_get_contents($jsonPath);
    if ($jsonContent !== false) {
        $decoded = json_decode($jsonContent, true);
        
        // Golyóálló keresés: Megnézzük a gyökérkönyvtárban és a fujitsu_adatbazis ágon belül is
        $logikak = [];
        if (isset($decoded['diagnosztikai_logikak'])) {
            $logikak = $decoded['diagnosztikai_logikak'];
        } elseif (isset($decoded['fujitsu_adatbazis']['diagnosztikai_logikak'])) {
            $logikak = $decoded['fujitsu_adatbazis']['diagnosztikai_logikak'];
        }
        
        foreach ($logikak as $logic) {
            if (isset($logic['hibakodok'])) {
                foreach ($logic['hibakodok'] as $code => $data) {
                    
                    $op = 0; $timer = 0; $eco = 0;

                    // 1. Kinyerés a kód nevéből (pl. OP_2_TIMER_0)
                    if (preg_match('/OP_(\d+)/i', $code, $m)) $op = (int)$m[1];
                    if (preg_match('/TIMER_(\d+)/i', $code, $m)) $timer = (int)$m[1];
                    if (preg_match('/ECO_(\d+)/i', $code, $m)) $eco = (int)$m[1];

                    // 2. Ha külön mezőben van, az felülírja
                    if (isset($data['led_operation'])) $op = (int)$data['led_operation'];
                    if (isset($data['led_timer'])) $timer = (int)$data['led_timer'];
                    if (isset($data['led_economy'])) $eco = (int)$data['led_economy'];

                    $errorList[] = [
                        'code' => $code,
                        'hiba' => $data['hiba'] ?? 'Ismeretlen műszaki hiba',
                        'megoldas' => $data['megoldas'] ?? 'Nincs javasolt ellenőrzési lépés.',
                        'op' => $op,
                        'timer' => $timer,
                        'eco' => $eco
                    ];
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <title>Műszaki segéd - <?= htmlspecialchars(APP_NAME) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/app.css">
  <style>
    .led-calculator {
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 24px;
      margin-bottom: 25px;
    }
    .led-row {
      display: grid;
      grid-template-columns: 200px 1fr 60px;
      align-items: center;
      gap: 20px;
      margin-bottom: 20px;
      padding-bottom: 20px;
      border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .led-row:last-child {
      margin-bottom: 0;
      padding-bottom: 0;
      border-bottom: none;
    }
    .led-label {
      font-size: 16px;
      font-weight: bold;
      display: flex;
      align-items: center;
      gap: 12px;
      color: #fff;
    }
    .led-dot {
      width: 16px;
      height: 16px;
      border-radius: 50%;
      display: inline-block;
    }
    .led-dot.green { background: #22c55e; box-shadow: 0 0 10px #22c55e; }
    .led-dot.orange { background: #f97316; box-shadow: 0 0 10px #f97316; }
    
    /* ERŐSZAKOS CSÚSZKA STÍLUS, HOGY BIZTOSAN MŰKÖDJÖN */
    input[type=range] {
      -webkit-appearance: none !important;
      width: 100%;
      background: #1e293b;
      height: 12px;
      border-radius: 6px;
      outline: none;
    }
    input[type=range]::-webkit-slider-thumb {
      -webkit-appearance: none !important;
      appearance: none !important;
      width: 30px;
      height: 30px;
      border-radius: 50%;
      background: #38bdf8;
      cursor: pointer;
      border: 3px solid #fff;
    }
    input[type=range]::-moz-range-thumb {
      width: 30px;
      height: 30px;
      border-radius: 50%;
      background: #38bdf8;
      cursor: pointer;
      border: 3px solid #fff;
    }
    
    .led-count {
      font-size: 26px;
      font-weight: 900;
      text-align: right;
      color: #38bdf8;
    }
    
    .result-box {
      background: rgba(239, 68, 68, 0.12);
      border: 2px solid var(--danger);
      border-radius: 14px;
      padding: 25px;
      margin-top: 25px;
    }
    .result-code {
      color: var(--danger);
      font-size: 13px;
      font-weight: 900;
      letter-spacing: 1.5px;
      margin-bottom: 6px;
    }
    .result-title {
      color: #fff;
      font-size: 22px;
      font-weight: bold;
      margin: 0 0 12px 0;
    }
    .result-desc {
      color: #e2e8f0;
      font-size: 16px;
      line-height: 1.6;
      margin: 0;
    }
    @media (max-width: 600px) {
      .led-row { grid-template-columns: 1fr; gap: 10px; text-align: center; }
      .led-label { justify-content: center; }
      .led-count { text-align: center; }
    }
  </style>
</head>
<body>

<main class="page">
  <div class="page-header">
    <div>
      <a class="back" href="<?= BASE_URL ?>/index.php">← Vissza a főmenübe</a>
      <h1>Műszaki segéd</h1>
      <p>Gyors Fujitsu LED diagnosztika csúszkák segítségével.</p>
    </div>
  </div>

  <?php if (empty($errorList)): ?>
    <div class="panel" style="border-color: var(--danger); text-align: center; padding: 30px;">
      <p style="color: var(--danger); font-weight: bold; margin: 0;">⚠️ Nem sikerült beolvasni a hibakódokat a JSON fájlból!</p>
      <small style="color: var(--muted);">Ellenőrizd, hogy a data/imports/hibakodok.json fájl helyesen van-e feltöltve.</small>
    </div>
  <?php else: ?>
    
    <div class="led-calculator">
      <div class="led-row">
        <div class="led-label">
          <span class="led-dot green"></span> Operation (Zöld)
        </div>
        <div>
          <input type="range" id="slider-op" min="0" max="0" value="0" oninput="updateValues()">
        </div>
        <div class="led-count" id="count-op">0</div>
      </div>

      <div class="led-row">
        <div class="led-label">
          <span class="led-dot orange"></span> Timer (Narancs)
        </div>
        <div>
          <input type="range" id="slider-timer" min="0" max="0" value="0" oninput="updateValues()">
        </div>
        <div class="led-count" id="count-timer">0</div>
      </div>

      <div class="led-row">
        <div class="led-label">
          <span class="led-dot green"></span> Economy (Zöld)
        </div>
        <div>
          <input type="range" id="slider-eco" min="0" max="0" value="0" oninput="updateValues()">
        </div>
        <div class="led-count" id="count-eco">0</div>
      </div>
    </div>

    <div id="result-box" class="result-box" style="display: none;">
      <div class="result-code" id="res-code">HIBAKÓD</div>
      <h3 class="result-title" id="res-title">Siker</h3>
      <p class="result-desc" id="res-desc">Leírás</p>
    </div>

  <?php endif; ?>
</main>

<script>
try {
  // Adatok átemelése
  const errors = <?= json_encode($errorList, JSON_UNESCAPED_UNICODE) ?> || [];

  // Csak a létező villogások kigyűjtése
  const validOp = [...new Set(errors.map(e => e.op))].sort((a, b) => a - b);
  const validTimer = [...new Set(errors.map(e => e.timer))].sort((a, b) => a - b);
  const validEco = [...new Set(errors.map(e => e.eco))].sort((a, b) => a - b);

  // A 0 mindig legyen opció
  if (!validOp.includes(0)) validOp.unshift(0);
  if (!validTimer.includes(0)) validTimer.unshift(0);
  if (!validEco.includes(0)) validEco.unshift(0);

  const sOp = document.getElementById('slider-op');
  const sTimer = document.getElementById('slider-timer');
  const sEco = document.getElementById('slider-eco');

  // Csúszkák méretezése
  if (sOp && sTimer && sEco) {
    sOp.max = validOp.length - 1;
    sTimer.max = validTimer.length - 1;
    sEco.max = validEco.length - 1;
  }

  function updateValues() {
    if (!sOp) return;
    
    const currentOp = validOp[parseInt(sOp.value)];
    const currentTimer = validTimer[parseInt(sTimer.value)];
    const currentEco = validEco[parseInt(sEco.value)];

    // Számok beírása
    document.getElementById('count-op').innerText = currentOp;
    document.getElementById('count-timer').innerText = currentTimer;
    document.getElementById('count-eco').innerText = currentEco;

    const resBox = document.getElementById('result-box');

    // Ha mind 0, tüntessük el
    if (currentOp === 0 && currentTimer === 0 && currentEco === 0) {
      resBox.style.display = 'none';
      return;
    }

    // Keresés
    const match = errors.find(e => e.op === currentOp && e.timer === currentTimer && e.eco === currentEco);

    if (match) {
      resBox.style.display = 'block';
      document.getElementById('res-code').innerText = "AZONNOSÍTOTT KÓD: " + match.code.replace(/_/g, ' ');
      document.getElementById('res-title').innerText = match.hiba;
      document.getElementById('res-desc').innerHTML = "<strong>Javasolt elhárítás / Ellenőrzés:</strong><br>" + match.megoldas.replace(/\\n/g, '<br>');
    } else {
      resBox.style.display = 'none';
    }
  }

  // Indításkor azonnal fusson le, hogy eltüntesse a dobozt
  if (sOp) {
    updateValues();
  }

} catch (err) {
  console.error("Hiba a felület betöltésekor: ", err);
}
</script>

</body>
</html>