<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$pdo = db();

try {
    // Lekérjük az összes meglévő ügyfelet a legördülő választóhoz
    $stmtCustomers = $pdo->query("SELECT id, name FROM customers ORDER BY name ASC");
    $allCustomers = $stmtCustomers->fetchAll();

    // Lekérjük az összes eseményt az ügyfelek nevével összekapcsolva, a legfrissebbek elöl
    $stmtEvents = $pdo->query("
        SELECT 
            e.id AS event_id,
            e.title,
            e.event_date,
            e.start_time,
            e.end_time,
            e.location,
            e.note,
            c.name AS customer_name
        FROM events e
        LEFT JOIN customers c ON e.customer_id = c.id
        ORDER BY e.event_date DESC, e.start_time DESC
    ");
    $events = $stmtEvents->fetchAll();
} catch (Throwable $e) {
    $allCustomers = [];
    $events = [];
    if (APP_DEBUG) {
        $errorMsg = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <title>Események és Teendők - <?= htmlspecialchars(APP_NAME) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/app.css">
  <style>
    .event-list-table {
      width: 100%;
      border-collapse: collapse;
      background: var(--panel);
      border-radius: 12px;
      overflow: hidden;
    }
    .event-list-table th, 
    .event-list-table td {
      padding: 12px 14px;
      text-align: left;
      border-bottom: 1px solid var(--border);
      vertical-align: top;
    }
    .event-list-table th {
      background: rgba(31, 41, 55, 0.7);
      color: var(--accent);
      font-weight: 600;
    }
    .event-list-table tr:last-child td {
      border-bottom: 0;
    }
    .event-list-table tr:hover td {
      background: rgba(255, 255, 255, 0.02);
    }
    .no-events {
      padding: 30px;
      text-align: center;
      color: var(--muted);
    }
    .time-range {
      font-size: 13px;
      color: var(--muted);
    }
  </style>
</head>
<body>

<main class="page">

  <div class="page-header">
    <div>
      <a class="back" href="<?= BASE_URL ?>/index.php">← Vissza a főmenübe</a>
      <h1>Események és Teendők</h1>
      <p>Általános naptári bejegyzések, emlékeztetők és egyedi határidők kezelése.</p>
    </div>
  </div>

  <?php if (isset($errorMsg)): ?>
    <div class="panel" style="border-color: var(--danger); color: var(--danger);">
      <strong>Adatbázis hiba:</strong> <?= htmlspecialchars($errorMsg) ?>
    </div>
  <?php endif; ?>

  <form class="panel" method="post" action="<?= BASE_URL ?>/api/save_event.php">
    <h2>🆕 Új esemény / teendő felvétele</h2>
    
    <div class="form-grid">
      <label>
        Esemény megnevezése / Cím *
        <input type="text" name="title" required placeholder="pl. Anyagbeszerzés, Szabadság, Szerver karbantartás">
      </label>

      <label>
        Dátum *
        <input type="date" name="event_date" value="<?= date('Y-m-d') ?>" required>
      </label>

      <label>
        Kezdési időpont
        <input type="time" name="start_time">
      </label>

      <label>
        Befejezési időpont
        <input type="time" name="end_time">
      </label>

      <label>
        Kapcsolódó ügyfél (Opcionális)
        <select name="customer_id">
          <option value="">-- Nem kötődik ügyfélhez --</option>
          <?php foreach ($allCustomers as $customer): ?>
            <option value="<?= (int)$customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        Helyszín
        <input type="text" name="location" placeholder="pl. Telephely, vagy egyedi cím">
      </label>
    </div>

    <label style="margin-top: 14px;">
      Részletes leírás / Megjegyzés
      <textarea name="note" placeholder="További részletek, megjegyzések az eseménnyel kapcsolatban..."></textarea>
    </label>

    <div class="actions">
      <button type="submit" class="success">➕ Esemény hozzáadása</button>
    </div>
  </form>

  <h2>🗓️ Rögzített események időrendben</h2>
  <div class="panel" style="padding: 0; overflow-x: auto;">
    <?php if (count($events) > 0): ?>
      <table class="event-list-table">
        <thead>
          <tr>
            <th>Dátum / Idő</th>
            <th>Esemény / Cím</th>
            <th>Ügyfél</th>
            <th>Helyszín</th>
            <th>Megjegyzés</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($events as $event): ?>
            <?php 
              $formattedDate = date('Y.m.d.', strtotime($event['event_date']));
              $startTime = $event['start_time'] ? date('H:i', strtotime($event['start_time'])) : '';
              $endTime = $event['end_time'] ? date('H:i', strtotime($event['end_time'])) : '';
              
              $timeDisplay = '';
              if ($startTime !== '') {
                  $timeDisplay = $startTime;
                  if ($endTime !== '') {
                      $timeDisplay .= ' - ' . $endTime;
                  }
              } else {
                  $timeDisplay = 'Egész napos';
              }
            ?>
            <tr>
              <td style="white-space: nowrap; font-weight: 600;">
                <span style="color: #fff;"><?= $formattedDate ?></span><br>
                <span class="time-range"><?= $timeDisplay ?></span>
              </td>
              <td style="font-weight: 600; color: var(--accent);">
                <?= htmlspecialchars($event['title']) ?>
              </td>
              <td>
                <?= $event['customer_name'] ? htmlspecialchars($event['customer_name']) : '<span style="color:var(--muted); font-style:italic;">--</span>' ?>
              </td>
              <td style="font-size: 14px;">
                <?= $event['location'] ? htmlspecialchars($event['location']) : '<span style="color:var(--muted); font-style:italic;">--</span>' ?>
              </td>
              <td style="font-size: 14px; line-height: 1.4; max-width: 300px;">
                <?= $event['note'] ? nl2br(htmlspecialchars($event['note'])) : '<span style="color:var(--muted); font-style:italic;">Nincs megjegyzés</span>' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="no-events">
        <p style="font-size: 16px; margin: 0;">Még nincsenek rögzített egyedi események.</p>
      </div>
    <?php endif; ?>
  </div>

</main>

</body>
</html>