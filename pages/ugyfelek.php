<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$pdo = db();
$search = trim($_GET['q'] ?? '');

try {
    if ($search !== '') {
        // Négy külön kérdőjelet használunk, hogy a szigorú szerverek is megértsék
        $stmt = $pdo->prepare("
            SELECT * FROM customers 
            WHERE name LIKE ? 
               OR phone LIKE ? 
               OR email LIKE ? 
               OR address LIKE ? 
            ORDER BY name ASC
        ");
        $param = '%' . $search . '%';
        // Pontosan négyszer adjuk át a paramétert, mindegyik kérdőjelnek sajátot
        $stmt->execute([$param, $param, $param, $param]);
    } else {
        $stmt = $pdo->query("SELECT * FROM customers ORDER BY name ASC");
    }
    $customers = $stmt->fetchAll();
} catch (Throwable $e) {
    $customers = [];
    if (APP_DEBUG) {
        $errorMsg = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <title>Ügyfélbázis - <?= htmlspecialchars(APP_NAME) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/app.css">
  <style>
    .search-box {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
    }
    .search-box input {
      flex: 1;
    }
    .customer-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
      background: var(--panel);
      border-radius: 12px;
      overflow: hidden;
    }
    .customer-table th, 
    .customer-table td {
      padding: 12px 14px;
      text-align: left;
      border-bottom: 1px solid var(--border);
      vertical-align: middle;
    }
    .customer-table th {
      background: rgba(31, 41, 55, 0.7);
      color: var(--accent);
      font-weight: 600;
    }
    .customer-table tr:last-child td {
      border-bottom: 0;
    }
    .customer-table tr:hover td {
      background: rgba(255, 255, 255, 0.02);
    }
    .no-results {
      padding: 20px;
      text-align: center;
      color: var(--muted);
    }
    .actions-cell {
      display: flex;
      gap: 6px;
      white-space: nowrap;
    }
    .btn-table {
      padding: 5px 10px;
      font-size: 12px;
      font-weight: 600;
      text-decoration: none;
      border-radius: 6px;
      display: inline-flex;
      align-items: center;
    }
    .btn-call {
      background: rgba(34, 197, 94, 0.1);
      color: #22c55e;
      border: 1px solid rgba(34, 197, 94, 0.2);
    }
    .btn-call:hover {
      background: #22c55e;
      color: #000;
    }
  </style>
</head>
<body>

<main class="page">

  <div class="page-header">
    <div>
      <a class="back" href="<?= BASE_URL ?>/index.php">← Vissza a főmenübe</a>
      <h1>Ügyfelek nyilvántartása</h1>
      <p>A Northwind systemben rögzített összes megrendelő listája, elérhetősége és keresése.</p>
    </div>
  </div>

  <?php if (isset($errorMsg)): ?>
    <div class="panel" style="border-color: var(--danger); color: var(--danger);">
      <strong>Adatbázis hiba:</strong> <?= htmlspecialchars($errorMsg) ?>
    </div>
  <?php endif; ?>

  <form method="get" action="" class="panel search-box">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Keresés név, telefonszám, email vagy cím alapján..." autofocus>
    <button type="submit">Keresés 🔍</button>
    <?php if ($search !== ''): ?>
      <a href="ugyfelek.php" class="btn secondary">Szűrés törlése</a>
    <?php endif; ?>
  </form>

  <div class="panel" style="padding: 0; overflow-x: auto;">
    <?php if (count($customers) > 0): ?>
      <table class="customer-table">
        <thead>
          <tr>
            <th>Név</th>
            <th>Telefonszám</th>
            <th>Email</th>
            <th>Cím / Munkavégzés helye</th>
            <th>Rögzítve</th>
            <th style="text-align: center;">Műveletek</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($customers as $customer): ?>
            <tr>
              <td style="font-weight: 600; color: #fff;">
                <?= htmlspecialchars($customer['name']) ?>
              </td>
              <td>
                <?= $customer['phone'] ? htmlspecialchars($customer['phone']) : '<span style="color:var(--muted); font-size:13px;">Nincs megadva</span>' ?>
              </td>
              <td>
                <?= $customer['email'] ? htmlspecialchars($customer['email']) : '<span style="color:var(--muted); font-size:13px;">Nincs megadva</span>' ?>
              </td>
              <td style="font-size: 14px;">
                <?= $customer['address'] ? nl2br(htmlspecialchars($customer['address'])) : '<span style="color:var(--muted); font-size:13px;">Nincs megadva</span>' ?>
              </td>
              <td style="font-size: 13px; color: var(--muted);">
                <?= date('Y.m.d.', strtotime($customer['created_at'])) ?>
              </td>
              <td>
                <div class="actions-cell">
                  <?php if ($customer['phone']): ?>
                    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $customer['phone']) ?>" class="btn-table btn-call">
                      Hívás 📞
                    </a>
                  <?php endif; ?>
                  <a href="idopont.php?customer_id=<?= $customer['id'] ?>" class="btn-table success">
                    Új munka 🛠️
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="no-results">
        <?php if ($search !== ''): ?>
          <p>Nincs a keresési feltételnek megfelelő ügyfél ("<strong><?= htmlspecialchars($search) ?></strong>").</p>
        <?php else: ?>
          <p>Az ügyfélbázis jelenleg még üres. Rögzíts egy új időpontot vagy karbantartást a feltöltéshez!</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

</main>

</body>
</html>