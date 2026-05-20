<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$pdo = db();
$errorMsg = null;
$allPartners = [];
$events = [];

try {
    // Intelligens partnerkezelő: Kigyűjtjük az eddig beírt egyedi neveket a lenyíló listához
    $stmtPartners = $pdo->query("
        SELECT DISTINCT customer_name 
        FROM maintenances 
        WHERE status = 'event' AND customer_name IS NOT NULL AND customer_name != '' 
        ORDER BY customer_name ASC
    ");
    $allPartners = $stmtPartners->fetchAll(PDO::FETCH_COLUMN);

    // Időrendi lekérdezés a központi táblából a fehér oldal hiba kiküszöbölésére
    $stmtEvents = $pdo->query("
        SELECT 
            id,
            customer_name,
            scheduled_date,
            scheduled_time,
            job_type,
            job_description
        FROM maintenances
        WHERE status = 'event'
        ORDER BY scheduled_date DESC, scheduled_time DESC
    ");
    $events = $stmtEvents->fetchAll();
} catch (Throwable $e) {
    if (APP_DEBUG) {
        $errorMsg = $e->getMessage();
    } else {
        $errorMsg = 'Adatbázis szinkronizációs hiba történt. Kérlek ellenőrizd a struktúrát!';
    }
}
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <title>Események rögzítése - <?= htmlspecialchars(APP_NAME) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/app.css">
  <style>
    .event-panel { border-left: 4px solid #a855f7 !important; }
    .btn-event { background: #a855f7 !important; color: #001018 !important; font-weight: bold; border: 0; padding: 14px 20px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 16px; width: 100%; transition: opacity 0.2s; }
    .btn-event:hover { opacity: 0.9; }
    .event-list-table { width: 100%; border-collapse: collapse; background: var(--panel); border-radius: 12px; overflow: hidden; }
    .event-list-table th, .event-list-table td { padding: 12px 14px; text-align: left; border-bottom: 1px solid var(--border); vertical-align: top; }
    .event-list-table th { background: rgba(168, 85, 247, 0.1); color: #c084fc; font-weight: 600; }
    .event-list-table tr:last-child td { border-bottom: 0; }
    .event-list-table tr:hover td { background: rgba(255, 255, 255, 0.01); }
    .badge-event { background: rgba(168, 85, 247, 0.2); color: #c084fc; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
  </style>
</head>
<body>

<main class="page">

  <div class="page-header">
    <div>
      <a class="back" href="<?= BASE_URL ?>/index.php">← Vissza a főmenübe</a>
      <h1>🗓️ Események és Teendők rögzítése</h1>
      <p>Nem munkalaphoz kötődő megbeszélések, társasházi egyeztetések és anyagbeszerzések kezelése.</p>
    </div>
  </div>

  <?php if ($errorMsg): ?>
    <div class="panel" style="border-color: var(--danger); color: var(--danger); background: rgba(239, 68, 68, 0.05);">
      <strong>Struktúra hiba:</strong> <?= htmlspecialchars($errorMsg) ?>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['success'])): ?>
    <div class="panel" style="border-color: var(--success); color: var(--success); background: rgba(34, 197, 94, 0.05); font-weight: bold;">
      ✅ Az esemény sikeresen elmentve és rögzítve a Google Naptárba!
    </div>
  <?php endif; ?>

  <form class="panel event-panel" method="post" action="<?= BASE_URL ?>/api/save_event.php">
    <h2>🆕 Új esemény / teendő felvétele</h2>
    
    <div class="form-grid">
      <label>
        Esemény jellege / Típus *
        <select name="event_type" required>
          <option value="Találkozás" selected>🤝 Találkozás / Megbeszélés</option>
          <option value="Üzemeltetés">🛠️ Üzemeltetés / Anyagbeszerzés</option>
          <option value="Társasház">🏢 Társasházi egyeztetés</option>
          <option value="Megbeszélés">📅 Megbeszélés</option>
          <option value="Üzleti ebéd">🍽️ Üzleti ebéd</option>
          <option value="Egyéb">📌 Egyéb adminisztráció</option>
        </select>
      </label>

      <label>
        Dátum *
        <input type="date" name="event_date" value="<?= date('Y-m-d') ?>" required>
      </label>

      <label>
        Kezdési időpont *
        <input type="time" name="start_time" required value="<?= date('H:i') ?>">
      </label>

      <label>
        Partner neve (Kivel találkozom?) *
        <input type="text" name="partner_name" id="partner_name" list="partnerDatalist" placeholder="Kezdj el gépelni egy nevet..." required autocomplete="off">
        <datalist id="partnerDatalist">
          <?php foreach ($allPartners as $partner): ?>
            <option value="<?= htmlspecialchars($partner) ?>">
          <?php endforeach; ?>
        </datalist>
      </label>

      <label>
        Helyszín (Térkép kompatibilis)
        <input type="text" name="location" placeholder="pl. Cím, étterem vagy telephely">
      </label>
    </div>

    <label style="margin-top: 14px;">
      Részletes leírás / Megjegyzés
      <textarea name="note" rows="3" placeholder="További részletek az eseménnyel kapcsolatban..."></textarea>
    </label>

    <div class="actions" style="margin-top: 20px;">
      <button type="submit" class="btn-event">💾 Esemény mentése a naptárba</button>
    </div>
  </form>

  <h2>🗓️ Rögzített bejegyzések időrendben</h2>
  <div class="panel" style="padding: 0; overflow-x: auto;">
    <?php if (count($events) > 0): ?>
      <table class="event-list-table">
        <thead>
          <tr>
            <th>Dátum / Idő</th>
            <th>Típus</th>
            <th>Partner</th>
            <th>Megjegyzés / Helyszín</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($events as $event): ?>
            <tr>
              <td style="white-space: nowrap; font-weight: 600;">
                <span style="color: #fff;"><?= date('Y.m.d.', strtotime($event['scheduled_date'])) ?></span><br>
                <span style="font-size: 13px; color: var(--muted);"><?= date('H:i', strtotime($event['scheduled_time'])) ?></span>
              </td>
              <td>
                <span class="badge-event"><?= htmlspecialchars(str_replace('esemeny_', '', $event['job_type'])) ?></span>
              </td>
              <td style="font-weight: 600; color: #38bdf8;">
                <?= htmlspecialchars($event['customer_name']) ?>
              </td>
              <td style="font-size: 14px; line-height: 1.4;">
                <?= nl2br(htmlspecialchars($event['job_description'])) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div style="padding: 30px; text-align: center; color: var(--muted);">
        <p style="font-size: 16px; margin: 0; font-style: italic;">Még nincsenek rögzített egyedi események.</p>
      </div>
    <?php endif; ?>
  </div>

</main>

</body>
</html>