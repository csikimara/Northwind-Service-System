<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// Címek kinyerése az URL-ből, amiket a várólista küldött
$emails = $_GET['emails'] ?? '';
$emailList = array_filter(explode(',', $emails));
$count = count($emailList);

$status = '';
$statusMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targy = trim($_POST['targy'] ?? '');
    $uzenet = trim($_POST['uzenet'] ?? '');
    $cimek_rejtett = $_POST['cimek_rejtett'] ?? '';

    if (!empty($targy) && !empty($uzenet) && !empty($cimek_rejtett)) {
        
        // E-mail fejléc beállításai (A te címed lesz a feladó)
        // A címzettek Titkos Másolatba (BCC) kerülnek, hogy ne lássák egymást!
        $to = "northwind@northwind.hu"; 
        $headers = "From: Northwind Hűtéstechnika <northwind@northwind.hu>\r\n";
        $headers .= "Bcc: " . $cimek_rejtett . "\r\n";
        $headers .= "Reply-To: northwind@northwind.hu\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        // Sortörések átalakítása HTML formátummá
        $htmlMessage = nl2br(htmlspecialchars($uzenet));

        // Küldés (a cPanel beépített mail() függvényével)
        if (mail($to, $targy, $htmlMessage, $headers)) {
            $status = 'success';
            $statusMsg = 'A levelek sikeresen elküldve ' . count(explode(',', $cimek_rejtett)) . ' ügyfél részére!';
        } else {
            $status = 'error';
            $statusMsg = 'Hiba történt az e-mailek küldése közben. Ellenőrizd a szerver beállításait!';
        }
    } else {
        $status = 'error';
        $statusMsg = 'Kérlek, töltsd ki a tárgyat és az üzenetet is!';
    }
}
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <title>Tömeges e-mail - <?= htmlspecialchars(APP_NAME) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/app.css">
  <style>
    .email-panel { max-width: 800px; margin: 0 auto; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; color: var(--accent); font-weight: 600; font-size: 14px; margin-bottom: 8px; }
    .form-group input[type="text"], .form-group textarea { width: 100%; padding: 12px; background: rgba(0,0,0,0.2); border: 1px solid var(--border); border-radius: 8px; color: #fff; font-family: inherit; font-size: 15px; }
    .form-group input[type="text"]:focus, .form-group textarea:focus { outline: none; border-color: var(--accent); }
    .cimek-box { background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; font-size: 13px; color: var(--muted); line-height: 1.6; max-height: 150px; overflow-y: auto; margin-bottom: 20px; border: 1px solid var(--border); }
    .btn-submit { background: #3b82f6; color: #fff; border: none; padding: 14px 24px; font-size: 16px; font-weight: bold; border-radius: 8px; cursor: pointer; width: 100%; transition: background 0.3s; }
    .btn-submit:hover { background: #2563eb; }
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; text-align: center; }
    .alert.success { background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid rgba(34,197,94,0.5); }
    .alert.error { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239,68,68,0.5); }
  </style>
</head>
<body>

<main class="page">
  <div class="page-header" style="max-width: 800px; margin: 0 auto 20px auto;">
    <div>
      <a class="back" href="<?= BASE_URL ?>/pages/varolista.php">← Vissza a Várólistára</a>
      <h1>Hírlevél / Értesítő küldése</h1>
      <p>Karbantartási értesítők vagy tömeges tájékoztatók küldése a kijelölt ügyfeleknek.</p>
    </div>
  </div>

  <div class="panel email-panel">
    <?php if ($status === 'success'): ?>
      <div class="alert success">✅ <?= htmlspecialchars($statusMsg) ?></div>
    <?php elseif ($status === 'error'): ?>
      <div class="alert error">❌ <?= htmlspecialchars($statusMsg) ?></div>
    <?php endif; ?>

    <?php if ($count === 0 && $status !== 'success'): ?>
      <div class="alert error">Nincs e-mail cím kijelölve! Menj vissza a várólistára, és válassz ki ügyfeleket.</div>
    <?php else: ?>
      <form method="POST" action="">
        <div class="form-group">
          <label>Kijelölt címzettek (BCC titkos másolat - <?= $count ?> db)</label>
          <div class="cimek-box">
            <?= htmlspecialchars(implode(', ', $emailList)) ?>
          </div>
          <input type="hidden" name="cimek_rejtett" value="<?= htmlspecialchars($emails) ?>">
        </div>

        <div class="form-group">
          <label>Levél tárgya</label>
          <input type="text" name="targy" placeholder="Pl.: Idei karbantartás esedékessége" required>
        </div>

        <div class="form-group">
          <label>Üzenet szövege</label>
          <textarea name="uzenet" rows="10" placeholder="Tisztelt Ügyfelünk! Tájékoztatjuk, hogy..." required></textarea>
        </div>

        <button type="submit" class="btn-submit">
          E-mail küldése <?= $count ?> ügyfélnek 🚀
        </button>
      </form>
    <?php endif; ?>
  </div>
</main>

</body>
</html>