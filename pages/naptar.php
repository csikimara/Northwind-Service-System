<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$pdo = db();

// Nézetek és dátumok kezelése
$view = $_GET['view'] ?? 'month'; // month, week, day
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$day = isset($_GET['day']) ? (int)$_GET['day'] : (int)date('d');

if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$currentDateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
$currentTimestamp = strtotime($currentDateStr);

// Hónapok magyar nevei
$monthNames = [
    1 => 'Január', 2 => 'Február', 3 => 'Március', 4 => 'Április',
    5 => 'Május', 6 => 'Június', 7 => 'Július', 8 => 'Augusztus',
    9 => 'Szeptember', 10 => 'Október', 11 => 'November', 12 => 'December'
];

// 1. ADATOK EGYSÉGESÍTÉSE ÉS LEKÉRDEZÉSE (Események + Munkák)
$calendarItems = [];

try {
    // Standalone események lekérése
    $stmtEv = $pdo->query("SELECT title, event_date AS d, start_time AS t, 'event' AS type, note FROM events");
    while ($row = $stmtEv->fetch()) {
        $dateKey = $row['d'];
        $calendarItems[$dateKey][] = [
            'title' => $row['title'],
            'time' => $row['t'] ? date('H:i', strtotime($row['t'])) : '',
            'class' => 'event',
            'desc' => $row['note'] ?? ''
        ];
    }

    // Időpontozott munkák lekérése az ügyfél nevével összekötve
    $stmtJob = $pdo->query("
        SELECT j.id, c.name AS cust_name, j.job_type, j.scheduled_date AS d, j.scheduled_time AS t, j.title, j.description 
        FROM jobs j 
        JOIN customers c ON j.customer_id = c.id 
        WHERE j.status = 'scheduled'
    ");
    while ($row = $stmtJob->fetch()) {
        $dateKey = $row['d'];
        if ($dateKey) {
            $calendarItems[$dateKey][] = [
                'id' => $row['id'],
                'title' => $row['cust_name'] . ' - ' . ($row['title'] ?: mb_strtoupper($row['job_type'])),
                'time' => $row['t'] ? date('H:i', strtotime($row['t'])) : '',
               'class' => mb_strtolower($row['job_type']),
                'desc' => $row['description'] ?? ''
            ];
        }
    }
} catch (Throwable $e) {
    $errorMsg = $e->getMessage();
}

// Segédfüggvény a tételek rendezéséhez időpont szerint
foreach ($calendarItems as $dateKey => &$items) {
    usort($items, function($a, $b) {
        return strcmp($a['time'], $b['time']);
    });
}
unset($items);
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <title>Northwind Naptár</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/app.css">
  <style>
    /* RENDKÍVÜLI SZÍNKÓDOLÁS A KÉRÉSEK ALAPJÁN */
    .cal-item {
      padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;
      margin-bottom: 4px; display: block; text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .cal-item.telepites { background: #ed1c24 !important; color: #fff !important; } /* Fujitsu piros */
    .cal-item.karbantartas { background: #0284c7 !important; color: #fff !important; } /* Northwind kék */
    .cal-item.felmeres { background: #06b6d4 !important; color: #000 !important; } /* Cián */
    .cal-item.javitas { background: #f59e0b !important; color: #000 !important; } /* Kért 4. szín: Amber/Narancs */
    .cal-item.event, .cal-item.egyeb { background: #4b5563 !important; color: #fff !important; }

    /* NÉZETVÁLASZTÓ NAVIGÁCIÓ */
    .view-nav { display: flex; gap: 10px; margin-bottom: 20px; background: var(--panel); padding: 8px; border-radius: 8px; border: 1px solid var(--border); }
    .view-btn { flex: 1; padding: 10px; text-align: center; background: #1f2937; color: var(--text); border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 14px; }
    .view-btn.active { background: #3b82f6; color: #fff; }

    .cal-header-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
    .btn-nav { background: var(--panel); border: 1px solid var(--border); color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: bold; }

    /* HAVI NÉZET STRUKTÚRA */
    .month-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
    .day-name { text-align: center; font-weight: bold; padding: 10px; background: rgba(255,255,255,0.02); color: var(--accent); font-size: 13px; border-radius: 4px; }
    .calendar-day { background: var(--panel); border: 1px solid var(--border); min-height: 110px; padding: 6px; border-radius: 8px; position: relative; }
    .calendar-day.empty { background: transparent; border: none; }
    .calendar-day.today { border-color: #3b82f6; background: rgba(59, 130, 246, 0.05); }
    .day-num { font-weight: bold; font-size: 14px; margin-bottom: 6px; display: inline-block; color: var(--muted); }
    .calendar-day.today .day-num { color: #3b82f6; }

    /* HETI NÉZET STRUKTÚRA */
    .week-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px; }
    @media (max-width: 768px) { .week-grid { grid-template-columns: 1fr; } }
    .week-day-box { background: var(--panel); border: 1px solid var(--border); border-radius: 8px; min-height: 250px; padding: 10px; }
    .week-day-title { border-bottom: 1px solid var(--border); padding-bottom: 6px; margin-bottom: 10px; font-weight: bold; color: var(--accent); }

    /* NAPI NÉZET STRUKTÚRA */
    .day-timeline { background: var(--panel); border: 1px solid var(--border); border-radius: 8px; padding: 20px; }
    .timeline-item { display: flex; gap: 20px; padding: 15px 0; border-bottom: 1px solid var(--border); }
    .timeline-item:last-child { border-bottom: none; }
    .time-col { font-size: 18px; font-weight: bold; color: var(--accent); min-width: 70px; }
    .content-col { flex: 1; }
  </style>
</head>
<body>

<main class="page">
  <div class="page-header">
    <div>
      <a class="back" href="<?= BASE_URL ?>/index.php">← Vissza a főmenübe</a>
      <h1>Időpontok és Naptár</h1>
      <p>A Northwind Kft. központi ütemező felülete.</p>
    </div>
  </div>

  <?php if (isset($errorMsg)): ?>
    <div class="panel" style="background: rgba(239,68,68,0.1); border-color: var(--danger); color: var(--danger);">
      <strong>Adatbázis hiba:</strong> <?= htmlspecialchars($errorMsg) ?>
    </div>
  <?php endif; ?>

  <div class="view-nav">
    <a href="?view=month&year=<?= $year ?>&month=<?= $month ?>&day=<?= $day ?>" class="view-btn <?= $view === 'month' ? 'active' : '' ?>">📅 Havi nézet</a>
    <a href="?view=week&year=<?= $year ?>&month=<?= $month ?>&day=<?= $day ?>" class="view-btn <?= $view === 'week' ? 'active' : '' ?>">📊 Heti nézet</a>
    <a href="?view=day&year=<?= $year ?>&month=<?= $month ?>&day=<?= $day ?>" class="view-btn <?= $view === 'day' ? 'active' : '' ?>">⏱️ Napi nézet</a>
  </div>

  <?php if ($view === 'month'): ?>
    <?php
    $firstDayOfMonth = date('N', McC_mktime($year, $month, 1));
    $daysInMonth = date('t', McC_mktime($year, $month, 1));
    $prevMonthUrl = '?view=month&year=' . ($month == 1 ? $year - 1 : $year) . '&month=' . ($month == 1 ? 12 : $month - 1);
    $nextMonthUrl = '?view=month&year=' . ($month == 12 ? $year + 1 : $year) . '&month=' . ($month == 12 ? 1 : $month + 1);
    ?>
    <div class="cal-header-controls">
      <a href="<?= $prevMonthUrl ?>" class="btn-nav">« Előző hónap</a>
      <h2 style="margin:0; color:#fff; font-size:20px;"><?= $year ?>. <?= $monthNames[$month] ?></h2>
      <a href="<?= $nextMonthUrl ?>" class="btn-nav">Következő hónap »</a>
    </div>

    <div class="month-grid">
      <div class="day-name">Hé</div><div class="day-name">Ke</div><div class="day-name">Sze</div><div class="day-name">Csü</div><div class="day-name">Pé</div><div class="day-name">Szo</div><div class="day-name">Va</div>
      
      <?php
      for ($i = 1; $i < $firstDayOfMonth; $i++) {
          echo '<div class="calendar-day empty"></div>';
      }
      for ($d = 1; $d <= $daysInMonth; $d++) {
          $dateKey = sprintf('%04d-%02d-%02d', $year, $month, $d);
          $isToday = ($dateKey === date('Y-m-d')) ? 'today' : '';
          echo '<div class="calendar-day ' . $isToday . '">';
          echo '<a href="?view=day&year='.$year.'&month='.$month.'&day='.$d.'" class="day-num">' . $d . '</a>';
          
          if (isset($calendarItems[$dateKey])) {
              foreach ($calendarItems[$dateKey] as $item) {
                  $url = isset($item['id']) ? BASE_URL . '/pages/munka_reszletek.php?id=' . $item['id'] : '#';
                  echo '<a href="'.$url.'" class="cal-item ' . $item['class'] . '" title="'.htmlspecialchars($item['title']).'">';
                  echo htmlspecialchars(($item['time'] ? $item['time'] . ' ' : '') . $item['title']);
                  echo '</a>';
              }
          }
          echo '</div>';
      }
      ?>
    </div>

  <?php elseif ($view === 'week'): ?>
    <?php
    $dayOfWeek = (int)date('N', $currentTimestamp);
    $mondayTimestamp = $currentTimestamp - (($dayOfWeek - 1) * 86400);
    $prevWeekUrl = '?view=week&year='.date('Y', $currentTimestamp - 604800).'&month='.date('m', $currentTimestamp - 604800).'&day='.date('d', $currentTimestamp - 604800);
    $nextWeekUrl = '?view=week&year='.date('Y', $currentTimestamp + 604800).'&month='.date('m', $currentTimestamp + 604800).'&day='.date('d', $currentTimestamp + 604800);
    $dayNamesEng = ['Hétfő', 'Kedd', 'Szerda', 'Csütörtök', 'Péntek', 'Szombat', 'Vasárnap'];
    ?>
    <div class="cal-header-controls">
      <a href="<?= $prevWeekUrl ?>" class="btn-nav">« Előző hét</a>
      <h2 style="margin:0; color:#fff; font-size:18px;">Heti ütemezés (<?= date('Y.m.d', $mondayTimestamp) ?> - <?= date('m.d', $mondayTimestamp + 6 * 86400) ?>)</h2>
      <a href="<?= $nextWeekUrl ?>" class="btn-nav">Következő hét »</a>
    </div>

    <div class="week-grid">
      <?php for ($i = 0; $i < 7; $i++): 
          $loopTime = $mondayTimestamp + ($i * 86400);
          $loopDateStr = date('Y-m-d', $loopTime);
      ?>
        <div class="week-day-box <?= $loopDateStr === date('Y-m-d') ? 'today' : '' ?>" style="<?= $loopDateStr === date('Y-m-d') ? 'border-color:#3b82f6;' : '' ?>">
          <div class="week-day-title"><?= $dayNamesEng[$i] ?> (<?= date('m.d', $loopTime) ?>)</div>
          <?php if (isset($calendarItems[$loopDateStr])): ?>
              <?php foreach ($calendarItems[$loopDateStr] as $item): 
                  $url = isset($item['id']) ? BASE_URL . '/pages/munka_reszletek.php?id=' . $item['id'] : '#';
              ?>
                <a href="<?= $url ?>" class="cal-item <?= $item['class'] ?>" style="white-space:normal; min-height:34px;">
                  <span style="display:block; font-size:10px; opacity:0.8;"><?= $item['time'] ?: 'Egész nap' ?></span>
                  <?= htmlspecialchars($item['title']) ?>
                </a>
              <?php endforeach; ?>
          <?php else: ?>
              <span style="color:var(--muted); font-size:13px; font-style:italic;">Nincs feladat</span>
          <?php endif; ?>
        </div>
      <?php endfor; ?>
    </div>

  <?php elseif ($view === 'day'): ?>
    <?php
    $prevDayUrl = '?view=day&year='.date('Y', $currentTimestamp - 86400).'&month='.date('m', $currentTimestamp - 86400).'&day='.date('d', $currentTimestamp - 86400);
    $nextDayUrl = '?view=day&year='.date('Y', $currentTimestamp + 86400).'&month='.date('m', $currentTimestamp + 86400).'&day='.date('d', $currentTimestamp + 86400);
    ?>
    <div class="cal-header-controls">
      <a href="<?= $prevDayUrl ?>" class="btn-nav">« Előző nap</a>
      <h2 style="margin:0; color:#fff; font-size:20px;"><?= date('Y. m. d.', $currentTimestamp) ?></h2>
      <a href="<?= $nextDayUrl ?>" class="btn-nav">Következő nap »</a>
    </div>

    <div class="day-timeline">
      <?php if (isset($calendarItems[$currentDateStr]) && count($calendarItems[$currentDateStr]) > 0): ?>
          <?php foreach ($calendarItems[$currentDateStr] as $item): 
              $url = isset($item['id']) ? BASE_URL . '/pages/munka_reszletek.php?id=' . $item['id'] : '#';
          ?>
            <div class="timeline-item">
              <div class="time-col"><?= $item['time'] ?: 'Gyakorló' ?></div>
              <div class="content-col">
                <a href="<?= $url ?>" class="cal-item <?= $item['class'] ?>" style="display:inline-block; font-size:15px; padding:6px 12px; white-space:normal;">
                  <?= htmlspecialchars($item['title']) ?>
                </a>
                <?php if(!empty($item['desc'])): ?>
                  <p style="margin:6px 0 0 0; color:var(--muted); font-size:14px; font-style:italic;"><?= nl2br(htmlspecialchars($item['desc'])) ?></p>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
      <?php else: ?>
          <div style="text-align:center; color:var(--muted); padding:30px; font-style:italic;">Ezen a napon nincsenek betervezett feladatok vagy események.</div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</main>

</body>
</html>
<?php
// Egyszerűsített mktime áthidalás a biztonságos futásért
function McC_mktime($hr, $min, $sec, $txtM = null, $txtD = null, $txtY = null) {
    if($txtM === null) { $txtY = $hr; $txtM = $min; $txtD = $sec; $hr = 0; $min = 0; $sec = 0; }
    return mktime($hr, $min, $sec, $txtM, $txtD, $txtY);
}
?>
