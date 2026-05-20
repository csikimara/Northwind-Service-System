<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$pdo = db();
$filter = $_GET['filter'] ?? 'all';

$jobs = [];
$errorMsg = '';

try {
    if ($filter === 'varolista') {
        // --- VÁRÓLISTA (GMAIL) LEKÉRDEZÉS A CUSTOMERS TÁBLÁBÓL ---
        // Az email_olvaso.php már a tiszta is_waitlist mezőt állítja be, semmi nem veszik el!
        $sql = "SELECT id, name AS ugyfel, waitlist_info AS cim, phone AS tel, email,
                       'megkeresés' AS job_type, NULL AS munka_statusz, 'waitlist' AS status,
                       created_at, NULL AS hiba_leiras, waitlist_info AS megjegyzes
                FROM customers
                WHERE is_waitlist = 1
                ORDER BY created_at DESC";
        $stmt = $pdo->query($sql);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // --- NORMÁL MUNKANAPLÓ LEKÉRDEZÉS ---
        $sql = "SELECT id, ugyfel, cim, tel, job_type, munka_statusz, status, created_at, hiba_leiras, megjegyzes 
                FROM jobs 
                WHERE status != 'varolista'";
        $params = [];

        if ($filter === 'idopontozva') {
            $sql .= " AND status = 'scheduled'";
        } elseif ($filter === 'folyamatban') {
            $sql .= " AND munka_statusz = 'folyamatban'";
        } elseif ($filter === 'kesz') {
            $sql .= " AND munka_statusz = 'lezarva'";
        } elseif ($filter === 'szamlazva') {
            $sql .= " AND status = 'invoiced'";
        } elseif ($filter === 'torolve') {
            $sql .= " AND status = 'deleted'";
        }

        $sql .= " ORDER BY created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $jobs = [];
    $errorMsg = $e->getMessage();
}
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <title>Munkanaplók Archívum - <?= htmlspecialchars(APP_NAME) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/app.css">
  <style>
    .filter-nav { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
    .filter-btn {
      padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 600;
      background: var(--panel); color: var(--text); border: 1px solid var(--border); transition: all 0.2s;
    }
    .filter-btn:hover { background: rgba(255,255,255,0.1); }
    .filter-btn.active { background: #3b82f6; color: #fff; border-color: #3b82f6; }
    
    .filter-btn.varolista { border-color: #f59e0b; color: #f59e0b; }
    .filter-btn.varolista.active { background: #f59e0b; color: #000; }
    
    .job-card { border-left: 4px solid var(--border); margin-bottom: 15px; position: relative; }
    .job-card.folyamatban { border-left-color: #f59e0b; }
    .job-card.lezarva { border-left-color: #22c55e; }
    .job-card.varolista { border-left-color: #f59e0b; background: rgba(245, 158, 11, 0.05); }
    
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; background: #333; }
    .badge.felmeres { color: #f5d0fe; }
    .badge.telepites { color: #86efac; }
    .badge.karbantartas { color: #93c5fd; }
    .badge.javitas { color: #fca5a5; }
    .badge.egyeb { color: #fcd34d; }
    .badge.bejövő { background: #f59e0b; color: #000; }

    .sync-btn {
        background: #10b981; color: white; border: none; padding: 10px 15px; border-radius: 6px; 
        font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 5px; font-size: 14px;
        transition: 0.2s;
    }
    .sync-btn:active { transform: scale(0.95); }
    .sync-btn:disabled { background: #64748b; cursor: not-allowed; }
  </style>
</head>
<body>

<main class="page">
  <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
    <div>
      <a class="back" href="<?= BASE_URL ?>/index.php">← Vissza a főmenübe</a>
      <h1>Munkanaplók (Archívum)</h1>
      <p>Az összes elvégzett munka és új megkeresés.</p>
    </div>
    
    <button id="syncBtn" class="sync-btn" onclick="syncGmail()">🔄 Gmail Szinkronizálása</button>
  </div>

  <?php if ($errorMsg): ?>
    <div class="panel" style="background: rgba(239,68,68,0.1); border-color: var(--danger); color: var(--danger); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
      <?= htmlspecialchars($errorMsg) ?>
    </div>
  <?php endif; ?>

  <div class="filter-nav">
    <a href="?filter=varolista" class="filter-btn varolista <?= $filter === 'varolista' ? 'active' : '' ?>">📧 Várólista (Új E-mailek)</a>
    <a href="?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">Mind (Kivéve várólista)</a>
    <a href="?filter=idopontozva" class="filter-btn <?= $filter === 'idopontozva' ? 'active' : '' ?>">Időpontozva</a>
    <a href="?filter=folyamatban" class="filter-btn <?= $filter === 'folyamatban' ? 'active' : '' ?>">Folyamatban</a>
    <a href="?filter=kesz" class="filter-btn <?= $filter === 'kesz' ? 'active' : '' ?>">Kész</a>
    <a href="?filter=szamlazva" class="filter-btn <?= $filter === 'szamlazva' ? 'active' : '' ?>">Számlázva</a>
  </div>

  <?php if (!empty($jobs)): ?>
    <?php foreach ($jobs as $job): ?>
      <div class="panel job-card <?= htmlspecialchars($job['munka_statusz'] ?? '') ?>">
        <div style="display: flex; justify-content: space-between;">
          <div>
            <?php 
                $badgeClass = strtolower(htmlspecialchars(explode(' ', $job['job_type'])[0])); 
            ?>
            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($job['job_type']) ?></span>
            
            <?php if($job['munka_statusz'] !== 'varolista'): ?>
                <span style="margin-left: 10px; font-size: 12px; color: var(--muted);">
                    <?= htmlspecialchars($job['munka_statusz'] === 'folyamatban' ? '⚠️ Folyamatban' : '✅ Kész') ?>
                </span>
            <?php endif; ?>

            <h3 style="margin: 8px 0; font-size: 18px; color: #fff;">
                <?= htmlspecialchars($job['ugyfel'] ?: 'Ismeretlen ügyfél') ?>
            </h3>
            
            <?php if($job['munka_statusz'] === 'varolista'): ?>
                <p style="margin: 0 0 5px 0; font-size: 14px; color: #38bdf8;">📞 <?= htmlspecialchars($job['tel'] ?: 'Nincs telefonszám a levélben') ?></p>
                <div style="background: rgba(0,0,0,0.3); padding: 10px; border-radius: 6px; font-size: 13px; color: #ccc; border-left: 2px solid #f59e0b;">
                    <?= htmlspecialchars(str_replace('VÁRÓLISTA | ', '', $job['cim'])) ?>
                </div>
                <a href="/munkanaplo.php" style="display: inline-block; margin-top: 10px; padding: 6px 12px; background: #38bdf8; color: #000; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 12px;">Új munkalap indítása →</a>
            <?php else: ?>
                <p style="margin: 0; font-size: 14px; color: var(--accent);">📍 <?= htmlspecialchars($job['cim'] ?: 'Nincs cím megadva') ?></p>
            <?php endif; ?>
          </div>
          
          <div style="text-align: right; color: var(--muted); font-size: 13px;">
            Rögzítve: <br>
            <?= !empty($job['created_at']) ? date('Y.m.d. H:i', strtotime($job['created_at'])) : 'Ismeretlen' ?>
          </div>
        </div>
        
        <?php if($job['munka_statusz'] !== 'varolista'): ?>
            <div style="margin-top: 15px; font-size: 14px; color: #ccc;">
            <?php if (!empty($job['hiba_leiras'])): ?>
                <p style="margin: 5px 0;"><strong>Hiba/Javítás:</strong> <?= htmlspecialchars($job['hiba_leiras']) ?></p>
            <?php endif; ?>
            <?php if (!empty($job['megjegyzes'])): ?>
                <p style="margin: 5px 0;"><strong>Megjegyzés:</strong> <?= htmlspecialchars($job['megjegyzes']) ?></p>
            <?php endif; ?>
            </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="panel" style="text-align: center; color: var(--muted); padding: 30px;">
      Nincsenek a megadott szűrésnek megfelelő munkák az archívumban.
    </div>
  <?php endif; ?>
</main>

<script>
    function syncGmail() {
        const btn = document.getElementById('syncBtn');
        const origText = btn.innerHTML;
        
        btn.innerHTML = '⏳ Olvasás...';
        btn.disabled = true;

        // Futtatjuk a háttérben az email_olvaso.php-t
        fetch('email_olvaso.php')
            .then(response => response.text())
            .then(data => {
                alert(data); // Kiírja a választ (pl. "✅ Szinkronizáció kész!...")
                // Automatikusan átváltunk a Várólista fülre, hogy lásd az újakat!
                window.location.href = '?filter=varolista'; 
            })
            .catch(error => {
                alert('Hiba történt a szerver elérésekor!');
                btn.innerHTML = origText;
                btn.disabled = false;
            });
    }
</script>

</body>
</html>