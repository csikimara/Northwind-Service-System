<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$txtPath = __DIR__ . '/../data/imports/varolista.txt';

if (!is_file($txtPath)) {
    die('Hiba: A data/imports/varolista.txt fájl nem található. Kérlek ellenőrizd!');
}

$pdo = db();
$importedCount = 0;

$lines = file($txtPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

if ($lines === false) {
    die('Hiba: Nem sikerült beolvasni a varolista.txt fájlt.');
}
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <title>Várólista Importálása - <?= htmlspecialchars(APP_NAME) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/app.css">
</head>
<body>

<main class="page">

  <div class="page-header">
    <div>
      <a class="back" href="<?= BASE_URL ?>/index.php">← Vissza a főmenübe</a>
      <h1>Régi várólista importálása</h1>
      <p>Függő munkák átemelése a <code>data/imports/varolista.txt</code> fájlból.</p>
    </div>
  </div>

  <div class="panel">
    <?php
    if (isset($_GET['run']) && $_GET['run'] === '1') {
        try {
            $pdo->beginTransaction();

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                // Feldolgozzuk a sort. Elválasztó lehet a cső (|) karakter vagy sima szöveg
                $parts = explode('|', $line);
                $customerName = trim($parts[0] ?? 'Ismeretlen Ügyfél (Várólista)');
                $description = trim($parts[1] ?? $line); // Ha nincs |, az egész sort leírásnak vesszük
                $phone = trim($parts[2] ?? null);
                $address = trim($parts[3] ?? null);

                // 1. Létrehozunk egy ügyfelet a várólistás tételhez
                $stmtCust = $pdo->prepare("
                    INSERT INTO customers (name, phone, address, note)
                    VALUES (:name, :phone, :address, 'Várólistáról importálva')
                ");
                $stmtCust->execute([
                    ':name' => $customerName,
                    ':phone' => $phone !== '' ? $phone : null,
                    ':address' => $address !== '' ? $address : null
                ]);
                $customerId = (int)$pdo->lastInsertId();

                // 2. Beillesztjük a hozzá tartozó munkát 'varolista' státusszal
                $stmtJob = $pdo->prepare("
                    INSERT INTO jobs (customer_id, job_type, status, title, description)
                    VALUES (:customer_id, 'karbantartas', 'varolista', :title, :description)
                ");
                $stmtJob->execute([
                    ':customer_id' => $customerId,
                    ':title' => 'Várólistás munka - ' . $customerName,
                    ':description' => $description
                ]);

                $importedCount++;
            }

            $pdo->commit();

            echo '<h2 style="color: var(--accent-2);">✅ Várólista sikeresen beimportálva!</h2>';
            echo '<p style="color: #fff;">Sikeresen rögzítve: <strong>' . $importedCount . ' db</strong> várakozó feladat.</p>';
            echo '<div class="actions" style="margin-top:20px;">';
            echo '<a href="../pages/varolista.php" class="btn">Ugrás a Várólistára ⏳</a>';
            echo '</div>';

        } catch (Throwable $e) {
            $pdo->rollBack();
            echo '<h2 style="color: var(--danger);">❌ Belső hiba történt</h2>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    } else {
        echo '<h2>Készen állsz a várakozó munkák beemelésére?</h2>';
        echo '<p>A rendszer megtalálta a listát, ami jelenleg <strong>' . count($lines) . ' db</strong> feldolgozandó sort tartalmaz.</p>';
        echo '<div class="actions" style="margin-top: 24px;">';
        echo '<a href="import_varolista.php?run=1" class="btn success">🚀 Importálás indítása</a>';
        echo '<a href="../index.php" class="btn secondary">Mégse</a>';
        echo '</div>';
    }
    ?>
  </div>

</main>

</body>
</html>