<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <title>Northwind Service System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <link rel="stylesheet" href="assets/app.css">
  
  <style>
    .dashboard-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 20px;
      margin-top: 30px;
    }
    .menu-card {
      background: var(--panel);
      border: 1px solid var(--border);
      border-top: 4px solid var(--accent);
      border-radius: 12px;
      padding: 22px;
      text-decoration: none;
      color: var(--text);
      transition: transform 0.2s ease, box-shadow 0.2s ease, border-top-color 0.2s ease;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .menu-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 24px rgba(0,0,0,0.4);
      border-top-color: var(--accent-2);
      background: rgba(255, 255, 255, 0.01);
    }
    .card-icon {
      font-size: 32px;
      margin-bottom: 6px;
    }
    .card-title {
      font-size: 18px;
      font-weight: 700;
      color: #fff;
      margin: 0;
    }
    .card-desc {
      font-size: 13px;
      color: var(--muted);
      line-height: 1.4;
      margin: 0;
    }
    .card-blue { border-top-color: #3b82f6; }
    .card-green { border-top-color: #22c55e; }
    .card-orange { border-top-color: #f59e0b; }
    .card-purple { border-top-color: #a855f7; }
    .card-cyan { border-top-color: #06b6d4; }
    .card-red { border-top-color: #ef4444; }
  </style>
</head>
<body>

<main class="page">

  <div class="page-header">
    <div>
      <h1 style="font-size: 32px; letter-spacing: -0.5px;">Northwind Service System</h1>
      <p>Ügyfélkezelés, karbantartás, munkanapló, naptár és műszaki adatbázis.</p>
    </div>
  </div>

  <div class="dashboard-grid">

    <a href="pages/idopont.php" class="menu-card card-blue">
      <div class="card-icon">📞</div>
      <h3 class="card-title">Új ügyfél / Időpont</h3>
      <p class="card-desc">Új megrendelő felvétele, zónák generálása és naptári időpont rögzítése.</p>
    </a>

    <a href="pages/ugyfelek.php" class="menu-card card-purple">
      <div class="card-icon">👥</div>
      <h3 class="card-title">Ügyfelek</h3>
      <p class="card-desc">Keresés név, telefon vagy cím alapján. Előzmények és gyors új munka indítása.</p>
    </a>

    <a href="pages/varolista.php" class="menu-card card-orange">
      <div class="card-icon">⏳</div>
      <h3 class="card-title">Várólista</h3>
      <p class="card-desc">Várakozó, még nem ütemezett karbantartások és javítások listája.</p>
    </a>

    <a href="munkanaplo.php" class="menu-card card-green">
      <div class="card-icon">📝</div>
      <h3 class="card-title">Új Munkanapló</h3>
      <p class="card-desc">Kinti adatbevitel (terepes űrlap). Felmérés, telepítés, karbantartás rögzítése.</p>
    </a>

    <a href="pages/archivum.php" class="menu-card">
      <div class="card-icon">🗂️</div>
      <h3 class="card-title">Munkanaplók (Archívum)</h3>
      <p class="card-desc">Aktív feladatok, elvégzett munkák, javítások és helyszíni adatok áttekintése.</p>
    </a>

    <a href="pages/esemenyek.php" class="menu-card" style="border-top-color: #a855f7;">
      <div class="card-icon" style="color: #a855f7;">🗓️</div>
      <h3 class="card-title">Események rögzítése</h3>
      <p class="card-desc">Nem munkával kapcsolatos bejegyzések, megbeszélések, társasházi egyeztetések és anyagbeszerzés.</p>
    </a>

    <a href="pages/naptar.php" class="menu-card card-cyan">
      <div class="card-icon">📅</div>
      <h3 class="card-title">Naptár view</h3>
      <p class="card-desc">Napi, heti és havi bontású áttekintés az előre egyeztetett címekről.</p>
    </a>

    <a href="pages/hibakodok.php" class="menu-card card-red">
      <div class="card-icon">🛠️</div>
      <h3 class="card-title">Műszaki segéd</h3>
      <p class="card-desc">Fujitsu és általános klíma hibakódok, LED villogások és helyszíni diagnosztika.</p>
    </a>

    <a href="pages/statisztika.php" class="menu-card">
      <div class="card-icon">📊</div>
      <h3 class="card-title">Statisztikák</h3>
      <p class="card-desc">Elvégzett alap tisztítások, nagy zsákos mosások száma és üzleti mutatók.</p>
    </a>

  </div>

</main>

</body>
</html>