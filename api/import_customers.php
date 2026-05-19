<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$txtPath = __DIR__ . '/../data/imports/ugyfelek.txt';

if (!is_file($txtPath)) {
    die('Hiba: A data/imports/ugyfelek.txt fájl nem található. Kérlek ellenőrizd az elérési utat!');
}

$pdo = db();
$importedCount = 0;
$skippedCount = 0;

// Beolvassuk a fájl tartalmát soronként
$lines = file($txtPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

if ($lines === false) {
    die('Hiba: Nem sikerült beolvasni az ugyfelek.txt fájlt.');
}
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <title>Ügyfelek Importálása - <?= htmlspecialchars(APP_NAME) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/app.css">
</head>
<body>

<main class="page">

  <div class="page-header">
    <div>
      <a class="back" href="<?= BASE_URL ?>/index.php">← Vissza a főmenübe</a>
      <h1>Régi ügyfelek importálása</h1>
      <p>Adatok átemelése a <code>data/imports/ugyfelek.txt</code> fájlból a MySQL adatbázisba.</p>
    </div>
  </div>

  <div class="panel">
    <?php
    if (isset($_GET['run']) && $_GET['run'] === '1') {
        try {
            $pdo->beginTransaction();

            // Előkészítjük a beszúrást, ellenőrizve, hogy a név ne legyen üres
            $stmt = $pdo->prepare("
                INSERT INTO customers (name, phone, email, address, note)
                VALUES (:name, :phone, :email, :address, :note)
            ");

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                // Megpróbáljuk szétszedni a sort a leggyakoribb elválasztók alapján (|, tab, vessző)
                $parts = [];
                if (str_contains($line, '|')) {
                    $parts = explode('|', $line);
                } elseif (str_contains($line, "\t")) {
                    $parts = explode("\t", $line);
                } else {
                    // Ha nincs egyértelmű elválasztó, az egész sort névnek vesszük, a többit üresen hagyjuk
                    $parts = [$line];
                }

                $name = trim($parts[0] ?? '');
                
                if ($name === '') {
                    $skippedCount++;
                    continue;
                }

                $phone = trim($parts[1] ?? null);
                $email = trim($parts[2] ?? null);
                $address = trim($parts[3] ?? null);
                $note = trim($parts[4] ?? null);

                $stmt->execute([
                    ':name'    => $name,
                    ':phone'   => $phone !== '' ? $phone : null,
                    ':email'   => $email !== '' ? $email : null,
                    ':address' => $address !== '' ? $address : null,
                    ':note'    => $note !== '' ? $note : null,
                ]);

                $importedCount++;
            }

            $pdo->commit();

            echo '<h2 style="color: var(--accent-2);">✅ Importálás sikeresen befejeződött!</h2>';
            echo '<p>Feldolgozott sorok száma: <strong>' . count($lines) . '</strong></p>';
            echo '<p>Sikeresen átmásolva a MySQL-be: <strong style="color: var(--accent-2);">' . $importedCount . ' db ügyfél</strong></p>';
            if ($skippedCount > 0) {
                echo '<p>Kihagyott (hibás vagy üres nevű) sorok: <strong>' . $skippedCount . '</strong></p>';
            }
            echo '<div class="actions" style="margin-top:20px;">';
            echo '<a href="../pages/ugyfelek.php" class="btn">Megyek az Ügyfelek listájához 👥</a>';
            echo '</div>';

        } catch (Throwable $e) {
            $pdo->rollBack();
            echo '<h2 style="color: var(--danger);">❌ Hiba történt az importálás során</h2>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    } else {
        // Alapállapot: Kiírjuk, mit találtunk, és kérünk egy jóváhagyást
        echo '<h2>Készen állsz az adatok átemelésére?</h2>';
        echo '<p>A rendszer megtalálta a fájlt, ami jelenleg <strong>' . count($lines) . ' db</strong> sort tartalmaz.</p>';
        echo '<p style="color: var(--muted); font-size: 14px;">Tipp: Az importálást elég egyszer lefuttatnod. Ha a folyamat kész, az ügyfeleid azonnal elérhetőek és kereshetőek lesznek a rendszerben.</p>';
        echo '<div class="actions" style="margin-top: 24px;">';
        echo '<a href="import_customers.php?run=1" class="btn success">🚀 Importálás indítása most</a>';
        echo '<a href="../index.php" class="btn secondary">Mégse</a>';
        echo '</div>';
    }
    ?>
  </div>

</main>

</body>
</html>