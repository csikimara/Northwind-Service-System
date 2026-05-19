<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$pdo = db();

// Biztonsági alapértékek, hogy soha ne omoljon össze az oldal
$totalCustomers = 0;
$statusCounts = [];
$workCounts = [];
$telepitesData = [];
$totalTelepites = 0;
$totalCso = 0;
$errorMsg = null;

try {
    // 1. Összes ügyfél száma
    $totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();

    // 2. Munkák száma státuszok szerint (a te munka_statusz oszlopod alapján)
    $stmtStatus = $pdo->query("SELECT munka_statusz, COUNT(*) as db FROM jobs GROUP BY munka_statusz");
    while ($row = $stmtStatus->fetch()) {
        if (!empty($row['munka_statusz'])) {
            $statusCounts[$row['munka_statusz']] = (int)$row['db'];
        }
    }

    // 3. Elvégzett karbantartások darabszáma (a te jobs tábládból számolva)
    $stmtClean = $pdo->query("
        SELECT karb_tipus, SUM(gep_darabszam) as db 
        FROM jobs 
        WHERE job_type = 'KARBANTARTAS' AND munka_statusz = 'lezarva' 
        GROUP BY karb_tipus
    ");
    while ($row = $stmtClean->fetch()) {
        $type = $row['karb_tipus'] ?: 'Normál karbantartás';
        $workCounts[$type] = (int)$row['db'];
    }

    // 4. TELEPÍTÉSI ÉS RÉZCSŐ STATISZTIKA (Évekre bontva és összesen)
    $stmtTelepites = $pdo->query("
        SELECT 
            YEAR(created_at) as ev, 
            COUNT(*) as db,
            SUM(rezcso_hossz) as cso_meter
        FROM jobs 
        WHERE job_type = 'TELEPITES' AND munka_statusz = 'lezarva' 
        GROUP BY YEAR(created_at) 
        ORDER BY ev DESC
    ");
    $telepitesData = $stmtTelepites->fetchAll(PDO::FETCH_ASSOC);
    
    // Nagy összesítők
    $totalTelepites = array_sum(array_column($telepitesData, 'db'));
    $totalCso = array_sum(array_column($telepitesData, 'cso_meter'));

} catch (Throwable $e) {
    if (APP_DEBUG) {
        $errorMsg = "Adatbázis hiba: " . $e->getMessage();
    } else {
        $errorMsg = "Hiba történt a statisztikák betöltésekor.";
    }
}

function translateStatus(string $status): string {
    $map = [
        'folyamatban' => 'Folyamatban ⚠️',
        'lezarva' => 'Lezárva / Kész ✅'
    ];
    return $map[$status] ?? $status;
}
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <title>Statisztika - <?= htmlspecialchars(APP_NAME) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/app.css">
  <style>
    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-card { background: var(--panel); border: 1px solid var(--border); border-radius: 12px; padding: 20px; text-align: center; }
    .stat-card h3 { margin: 0 0 10px 0; color: var(--muted); font-size: 14px; text-transform: uppercase; }
    .stat-card .number { font-size: 34px; font-weight: 900; color: #fff; }
    .stat-card .number.accent { color: var(--accent); }
    .stat-card .number.success { color: var(--accent-2); }
    .stat-card .number.pipe { color: #f59e0b; }
    
    .data-list { list-style: none; padding: 0; margin: 0; }
    .data-list li { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .data-list li:last-child { border-bottom: none; }
    
    .year-block { display: flex; flex-direction: column; }
    .year-title { font-weight: bold; font-size: 18px; color: #fff; }
    .year-details { display: flex; gap: 15px; margin-top: 5px; }
    .badge { background: rgba(255,255,255,0.1); padding: 5px 10px; border-radius: 6px; font-size: 14px; font-weight: bold; }
    .badge.machine { color: var(--accent); border: 1px solid var(--accent); background: rgba(56, 189, 248, 0.1); }
    .badge.pipe { color: #f59e0b; border: 1px solid #f59e0b; background: rgba(245, 158, 11, 0.1); }
  </style>
</head>
<body>

<main class="page">
  <div class="page-header">
    <div>
      <a class="back" href="<?= BASE_URL ?>/index.php">← Vissza a főmenübe</a>
      <h1>Statisztika és Eredmények</h1>
      <p>A Northwind Kft. számokban.</p>
    </div>
  </div>

  <?php if ($errorMsg): ?>
    <div class="panel" style="background: rgba(239,68,68,0.1); border-color: var(--danger); color: var(--danger); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
      <?= htmlspecialchars($errorMsg) ?>
    </div>
  <?php endif; ?>

  <div class="stat-grid">
    <div class="stat-card">
      <h3>Sikeres Telepítések</h3>
      <div class="number accent"><?= $totalTelepites ?> db</div>
    </div>
    <div class="stat-card">
      <h3>Felhasznált Rézcső</h3>
      <div class="number pipe"><?= number_format((float)$totalCso, 1, ',', ' ') ?> m</div>
    </div>
    <div class="stat-card">
      <h3>Elvégzett Karbantartások</h3>
      <div class="number success">
        <?= array_sum($workCounts) ?> db
      </div>
    </div>
  </div>

  <div class="stat-grid">
    
    <div class="panel">
      <h2 style="margin-top: 0; color: var(--accent); font-size: 18px; border-bottom: 1px solid var(--border); padding-bottom: 10px;">📊 Telepítési adatok évenként</h2>
      <?php if (count($telepitesData) > 0): ?>
        <ul class="data-list">
          <?php foreach ($telepitesData as $row): ?>
            <li>
              <div class="year-block">
                <span class="year-title"><?= htmlspecialchars((string)$row['ev']) ?>. év</span>
                <div class="year-details">
                  <span class="badge machine">❄️ <?= $row['db'] ?> db munka</span>
                  <span class="badge pipe">📏 <?= number_format((float)$row['cso_meter'], 1, ',', ' ') ?> m cső</span>
                </div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p style="color: var(--muted); font-style: italic;">Még nincs rögzített, befejezett telepítés az adatbázisban.</p>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2 style="margin-top: 0; color: #fff; font-size: 18px; border-bottom: 1px solid var(--border); padding-bottom: 10px;">📋 Munkák jelenlegi állapota</h2>
      <?php if (!empty($statusCounts)): ?>
        <ul class="data-list">
          <?php foreach ($statusCounts as $st => $count): ?>
            <li>
              <span><?= translateStatus($st) ?></span>
              <strong style="font-size: 16px;"><?= $count ?> db</strong>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p style="color: var(--muted); font-style: italic;">Nincsenek még rögzített munkák.</p>
      <?php endif; ?>
    </div>

  </div>
</main>

</body>
</html>