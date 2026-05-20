<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$pdo = db();

try {
    // Lekérjük a várólistás munkákat az ügyfelek alapadataival ÉS az email címmel együtt
    $stmt = $pdo->query("
        SELECT 
            j.id AS job_id,
            j.customer_id,
            j.job_type,
            j.title,
            j.description,
            j.created_at AS json_created_at,
            c.name AS customer_name,
            c.phone AS customer_phone,
            c.address AS customer_address,
            c.email AS customer_email
        FROM jobs j
        JOIN customers c ON j.customer_id = c.id
        WHERE j.status = 'varolista'
        ORDER BY j.created_at ASC
    ");
    $waitingJobs = $stmt->fetchAll();
} catch (Throwable $e) {
    $waitingJobs = [];
    if (APP_DEBUG) {
        $errorMsg = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <title>Várólista - <?= htmlspecialchars(APP_NAME) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/app.css">
  <style>
    .bulk-actions-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 15px;
      margin-bottom: 20px;
      padding: 14px 20px;
      background: var(--panel);
      border-radius: 12px;
      border: 1px solid var(--border);
    }
    .bulk-email-btn {
      background: #3b82f6;
      color: #fff;
      font-weight: 600;
      padding: 8px 16px;
      border-radius: 6px;
      border: none;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      font-size: 14px;
      transition: background 0.2s;
    }
    .bulk-email-btn:hover {
      background: #2563eb;
    }
    .job-card {
      border-left: 4px solid var(--accent);
      margin-bottom: 16px;
      position: relative;
    }
    .job-card-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
    }
    .customer-selector {
      width: 18px;
      height: 18px;
      cursor: pointer;
      accent-color: var(--accent);
    }
    .job-meta {
      display: flex;
      gap: 20px;
      flex-wrap: wrap;
      font-size: 14px;
      color: var(--muted);
      margin-top: 12px;
      padding-top: 12px;
      border-top: 1px solid rgba(255, 255, 255, 0.05);
      align-items: center;
    }
    .job-badge {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      background: #334155;
      color: var(--text);
    }
    .job-badge.KARBANTARTÁS { background: rgba(30, 58, 138, 0.4); color: #93c5fd; border: 1px solid rgba(30, 58, 138, 0.7); }
    .job-badge.JAVÍTÁS { background: rgba(127, 29, 29, 0.4); color: #fca5a5; border: 1px solid rgba(127, 29, 29, 0.7); }
    .job-badge.TELEPÍTÉS { background: rgba(20, 83, 45, 0.4); color: #86efac; border: 1px solid rgba(20, 83, 45, 0.7); }
    .job-badge.FELMÉRÉS { background: rgba(112, 26, 117, 0.4); color: #f5d0fe; border: 1px solid rgba(112, 26, 117, 0.7); }
    
    .empty-list {
      text-align: center;
      padding: 4px 0;
      color: var(--muted);
    }
    .job-link {
      color: #fff;
      text-decoration: none;
      font-weight: 600;
    }
    .job-link:hover {
      color: var(--accent);
    }
    .btn-action-green {
      background: rgba(34, 197, 94, 0.15);
      color: #22c55e;
      border: 1px solid rgba(34, 197, 94, 0.3);
      padding: 6px 12px;
      font-size: 13px;
      font-weight: 600;
      text-decoration: none;
      border-radius: 6px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s;
    }
    .btn-action-green:hover {
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
      <h1>Várólista</h1>
      <p>Azonnali beavatkozást, javítást vagy szezonális tisztítást váró munkák, amelyek még nincsenek időpontra ütemezve.</p>
    </div>
  </div>

  <?php if (isset($errorMsg)): ?>
    <div class="panel" style="border-color: var(--danger); color: var(--danger);">
      <strong>Adatbázis hiba:</strong> <?= htmlspecialchars($errorMsg) ?>
    </div>
  <?php endif; ?>

  <?php if (count($waitingJobs) > 0): ?>
    <div class="bulk-actions-bar">
      <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 600; font-size: 14px;">
        <input type="checkbox" id="selectAllJobs" checked class="customer-selector">
        Összes kijelölése / Kijelölés törlése
      </label>
      <button type="button" id="submitBulkEmail" class="bulk-email-btn">
        Tömeges e-mail küldése ✉️ (<span id="checkedCount">0</span>)
      </button>
    </div>

    <div id="waitingListContainer">
      <?php foreach ($waitingJobs as $job): ?>
        <div class="panel job-card">
          <div class="job-card-header">
            <div style="display: flex; align-items: flex-start; gap: 14px;">
              <input type="checkbox" class="job-checkbox customer-selector" 
                     data-email="<?= htmlspecialchars($job['customer_email'] ?? '') ?>" 
                     data-name="<?= htmlspecialchars($job['customer_name'] ?? '') ?>"
                     <?= !empty($job['customer_email']) ? 'checked' : 'disabled title="Nincs email cím megadva"' ?>>
              
              <div>
                <span class="job-badge <?= htmlspecialchars($job['job_type']) ?>">
                  <?= htmlspecialchars($job['job_type']) ?>
                </span>
                <h3 style="margin: 8px 0 4px 0; font-size: 18px;">
                  <a class="job-link" href="munka_reszletek.php?id=<?= $job['job_id'] ?>">
                    <?= htmlspecialchars($job['customer_name']) ?>
                  </a>
                </h3>
                <p style="margin: 0; font-size: 14px; color: var(--accent);">
                  📍 <?= htmlspecialchars($job['customer_address'] ? $job['customer_address'] : 'Nincs cím megadva') ?>
                </p>
              </div>
            </div>
            <div style="text-align: right; font-size: 13px; color: var(--muted);">
              Bejelentve: <br><?= date('Y.m.d. H:i', strtotime($job['json_created_at'])) ?>
            </div>
          </div>

          <?php if ($job['description'] !== ''): ?>
            <div style="margin-top: 12px; background: rgba(0,0,0,0.2); padding: 10px; border-radius: 8px; font-size: 14px; line-height: 1.5;">
              <strong>Hiba / Leírás:</strong><br>
              <?= nl2br(htmlspecialchars($job['description'])) ?>
            </div>
          <?php endif; ?>

          <div class="job-meta">
            <div>📞 Telefon: <strong><?= $job['customer_phone'] ? htmlspecialchars($job['customer_phone']) : 'Nincs' ?></strong></div>
            <?php if (!empty($job['customer_email'])): ?>
              <div style="margin-left: 15px;">✉️ E-mail: <span style="color: var(--muted); font-size: 13px;"><?= htmlspecialchars($job['customer_email']) ?></span></div>
            <?php endif; ?>
            
            <div style="margin-left: auto; display: flex; align-items: center; gap: 15px;">
              <span style="color: var(--muted); font-size: 13px; margin-right: 5px;">Munkaszám: #<?= htmlspecialchars((string)$job['job_id']) ?></span>
              
              <a href="idopont.php?customer_id=<?= $job['customer_id'] ?>&job_id=<?= $job['job_id'] ?>" class="btn-action-green">
                Időpontot adok 📅
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="panel empty-list">
      <p style="font-size: 16px; margin: 10px 0;">🎉 A várólista jelenleg teljesen üres!</p>
      <p style="font-size: 14px; margin: 0;">Minden bejelentett munka el van végezve vagy be van ütemezve a naptárba.</p>
    </div>
  <?php endif; ?>

</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mainCheckbox = document.getElementById('selectAllJobs');
    const jobCheckboxes = document.querySelectorAll('.job-checkbox:not(:disabled)');
    const counterSpan = document.getElementById('checkedCount');
    const emailBtn = document.getElementById('submitBulkEmail');

    // Számláló frissítése
    function updateCounter() {
        const checkedCount = document.querySelectorAll('.job-checkbox:checked').length;
        counterSpan.textContent = checkedCount;
    }

    // Fő jelölőnégyzet kattintás
    if (mainCheckbox) {
        mainCheckbox.addEventListener('change', function() {
            jobCheckboxes.forEach(cb => {
                cb.checked = mainCheckbox.checked;
            });
            updateCounter();
        });
    }

    // Egyedi jelölőnégyzetek kattintása
    jobCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            if (!this.checked && mainCheckbox) {
                mainCheckbox.checked = false;
            }
            updateCounter();
        });
    });

    // Tömeges email gomb kezelése
    if (emailBtn) {
        emailBtn.addEventListener('click', function() {
            const checkedBoxes = document.querySelectorAll('.job-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('Kérlek, jelölj ki legalább egy ügyfelet, akinek e-mailt szeretnél küldeni!');
                return;
            }

            // Összegyűjtjük a kijelölt e-mail címeket
            const emails = [];
            checkedBoxes.forEach(cb => {
                const email = cb.getAttribute('data-email');
                if (email) emails.push(email);
            });

            // Átirányítjuk a tömeges levelező oldalra az email címekkel
            const emailList = encodeURIComponent(emails.join(','));
            window.location.href = 'tomeges_email.php?emails=' + emailList;
        });
    }

    // Induláskor számoljunk egyet
    updateCounter();
});
</script>

</body>
</html>