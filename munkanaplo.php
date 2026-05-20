<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// --- 1. FUNKCIÓ: KERESÉS / ÜGYFÉL BEHÚZÁSA (AJAX) ---
if (isset($_GET['ajax_ugyfelek'])) {
    $pdo = db();
    $stmt = $pdo->query("SELECT name AS nev, address AS cim, phone AS tel, email FROM customers ORDER BY name ASC");
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
    exit;
}

// --- 2. FUNKCIÓ: ELŐZMÉNYEK MUTATÁSA (AJAX) ---
if (isset($_POST['ajax_history'])) {
    $pdo = db();
    $nev = trim((string)($_POST['nev'] ?? ''));

    if ($nev === '') {
        echo "Nincs megadva név.";
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT j.created_at, j.job_type, j.megjegyzes, j.hiba_leiras, j.egyeb_munka_neve
        FROM jobs j
        JOIN customers c ON j.customer_id = c.id
        WHERE c.name LIKE ?
        ORDER BY j.created_at DESC
        LIMIT 10
    ");
    $stmt->execute(['%' . $nev . '%']);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$history) {
        echo "Nincs rögzített előzmény ehhez az ügyfélhez az új rendszerben.";
        exit;
    }

    foreach ($history as $row) {
        echo "[" . date('Y.m.d', strtotime((string)$row['created_at'])) . "] - " . mb_strtoupper((string)$row['job_type']) . "\n";
        if (!empty($row['egyeb_munka_neve'])) {
            echo "Munka megnevezése: " . $row['egyeb_munka_neve'] . "\n";
        }
        if (!empty($row['hiba_leiras'])) {
            echo "Hiba/Javítás: " . $row['hiba_leiras'] . "\n";
        }
        if (!empty($row['megjegyzes'])) {
            echo "Megjegyzés: " . $row['megjegyzes'] . "\n";
        }
        echo "-------------------------\n";
    }
    exit;
}

// --- 3. FUNKCIÓ: ŰRLAP MENTÉSE AZ ADATBÁZISBA (AJAX) ---
if (isset($_POST['mode'])) {
    $pdo = db();
    $mode = trim((string)($_POST['mode'] ?? ''));

    $ugyfel = trim((string)($_POST['ugyfel'] ?? ''));
    $cim = trim((string)($_POST['cim'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $tel = trim((string)($_POST['tel'] ?? ''));

    if ($mode === '' || $ugyfel === '') {
        echo "Hiba: hiányzó kötelező adatok.";
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM customers WHERE name = ? AND phone = ? LIMIT 1");
    $stmt->execute([$ugyfel, $tel]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($customer) {
        $customer_id = (int)$customer['id'];
        $updateStmt = $pdo->prepare("UPDATE customers SET address = ?, email = ? WHERE id = ?");
        $updateStmt->execute([$cim, $email, $customer_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO customers (name, phone, email, address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$ugyfel, $tel, $email, $cim]);
        $customer_id = (int)$pdo->lastInsertId();
    }

    $munka_foka = $_POST['munka_foka'] ?? null;
    $kulteri_konfig = $_POST['kulteri_konfig'] ?? null;
    $mono_osszes = isset($_POST['mono_osszes']) ? (int)$_POST['mono_osszes'] : 1;
    $multi_kulteri = $_POST['multi_kulteri'] ?? null;
    $mn_b_szam = isset($_POST['mn_b_szam']) ? (int)$_POST['mn_b_szam'] : 0;

    $rez_par = $_POST['rez_par'] ?? null;
    $rez_hossz = (isset($_POST['rez_hossz']) && $_POST['rez_hossz'] !== '') ? (float)$_POST['rez_hossz'] : null;
    $rezcso_hossz = (isset($_POST['rezcso_hossz']) && $_POST['rezcso_hossz'] !== '') ? (float)$_POST['rezcso_hossz'] : null;
    $mt_kabel = $_POST['mt_kabel'] ?? null;
    $csat_meret = $_POST['csat_meret'] ?? null;

    $id_lapos = isset($_POST['id_lapos']) ? (int)$_POST['id_lapos'] : 0;
    $id_kulso = isset($_POST['id_kulso']) ? (int)$_POST['id_kulso'] : 0;
    $id_belso = isset($_POST['id_belso']) ? (int)$_POST['id_belso'] : 0;
    $id_vegzaro = isset($_POST['id_vegzaro']) ? (int)$_POST['id_vegzaro'] : 0;
    $id_atvezeto = isset($_POST['id_atvezeto']) ? (int)$_POST['id_atvezeto'] : 0;

    $text_kon_tipus = $_POST['kon_tipus'] ?? null;
    $text_kon_elvezetes = $_POST['kon_elvezetes'] ?? null;
    $pvc_csat_hossz = (isset($_POST['pvc_csat_hossz']) && $_POST['pvc_csat_hossz'] !== '') ? (float)$_POST['pvc_csat_hossz'] : null;
    $kon_szivattyu = $_POST['kon_szivattyu'] ?? null;

    $fal_tipusa = $_POST['fal_tipusa'] ?? null;
    $k_hely = $_POST['k_hely'] ?? null;
    $k_tarto = $_POST['k_tarto'] ?? null;
    $letra = $_POST['letra'] ?? null;
    $betap = $_POST['betap'] ?? null;
    $villany_ki = $_POST['villany_ki'] ?? null;

    $karb_ido = (isset($_POST['karb_ido']) && $_POST['karb_ido'] !== '') ? (int)$_POST['karb_ido'] : null;
    $hutokozeg = (isset($_POST['hutokozeg']) && $_POST['hutokozeg'] !== '') ? (float)$_POST['hutokozeg'] : null;
    $belteri_szam = $_POST['belteri_szam'] ?? null;
    $kulteri_szam = $_POST['kulteri_szam'] ?? null;

    $gep_darabszam = isset($_POST['gep_darabszam']) ? (int)$_POST['gep_darabszam'] : 1;
    $karb_tipus = $_POST['karb_tipus'] ?? null;
    $karb_ciklus = (isset($_POST['karb_ciklus']) && $_POST['karb_ciklus'] !== '') ? (int)$_POST['karb_ciklus'] : null;
    $emelet = $_POST['emelet'] ?? null;

    $helyseg = $_POST['helyseg'] ?? null;
    if ($helyseg === 'egyeb') {
        $helyseg = trim((string)($_POST['custom_room_name'] ?? 'Egyéb'));
    }

    $hiba_leiras = $_POST['hiba_leiras'] ?? null;
    $egyeb_munka_neve = $_POST['egyeb_munka_neve'] ?? null;

    $megjegyzes = $_POST['megjegyzes'] ?? null;
    $munka_statusz = $_POST['munka_statusz'] ?? 'folyamatban';

    $gepek = [];
    if ($mode === 'felmeres') {
        if ($kulteri_konfig === 'mono') {
            for ($i = 1; $i <= $mono_osszes; $i++) {
                $tipus = trim((string)($_POST["mono_tipus_$i"] ?? ''));
                $db = isset($_POST["mono_db_$i"]) ? (int)$_POST["mono_db_$i"] : 1;

                if ($tipus !== '') {
                    $gepek[] = [
                        'type' => 'mono',
                        'model' => $tipus,
                        'qty'   => max(1, $db),
                    ];
                }
            }
        } elseif ($kulteri_konfig === 'multi') {
            for ($i = 1; $i <= $mn_b_szam; $i++) {
                $kat = trim((string)($_POST["b_kat_$i"] ?? ''));
                $pwr = trim((string)($_POST["b_pwr_$i"] ?? ''));

                if ($kat !== '' || $pwr !== '') {
                    $gepek[] = [
                        'type'     => 'multi_indoor',
                        'category' => $kat,
                        'power'    => $pwr,
                    ];
                }
            }
        }
    }
    $gepek_json = !empty($gepek) ? json_encode($gepek, JSON_UNESCAPED_UNICODE) : null;

    $sql = "INSERT INTO jobs (
        customer_id, job_type, ugyfel, cim, email, tel, munka_statusz, megjegyzes,
        munka_foka, kulteri_konfig, mono_osszes, multi_kulteri, mn_b_szam,
        rez_par, rez_hossz, rezcso_hossz, mt_kabel, csat_meret,
        id_lapos, id_kulso, id_belso, id_vegzaro, id_atvezeto,
        kon_tipus, kon_elvezetes, pvc_csat_hossz, kon_szivattyu,
        fal_tipusa, k_hely, k_tarto, letra, betap, villany_ki,
        karb_ido, hutokozeg, belteri_szam, kulteri_szam,
        gep_darabszam, karb_tipus, karb_ciklus, emelet, helyseg,
        hiba_leiras, egyeb_munka_neve, gepek_json
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $customer_id, mb_strtoupper($mode), $ugyfel, $cim, $email, $tel, $munka_statusz, $megjegyzes,
            $munka_foka, $kulteri_konfig, $mono_osszes, $multi_kulteri, $mn_b_szam,
            $rez_par, $rez_hossz, $rezcso_hossz, $mt_kabel, $csat_meret,
            $id_lapos, $id_kulso, $id_belso, $id_vegzaro, $id_atvezeto,
            $text_kon_tipus, $text_kon_elvezetes, $pvc_csat_hossz, $kon_szivattyu,
            $fal_tipusa, $k_hely, $k_tarto, $letra, $betap, $villany_ki,
            $karb_ido, $hutokozeg, $belteri_szam, $kulteri_szam,
            $gep_darabszam, $karb_tipus, $karb_ciklus, $emelet, $helyseg,
            $hiba_leiras, $egyeb_munka_neve, $gepek_json
        ]);
        echo "OK";
    } catch (Throwable $e) {
        echo "Hiba: " . $e->getMessage();
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Northwind Munkanapló</title>
    <style>
        :root {
            --bg: #0f172a;
            --panel: #1e293b;
            --border: #334155;
            --text: #f8fafc;
            --muted: #94a3b8;
            --accent: #38bdf8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --input-bg: #0f172a;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 15px;
            padding-bottom: 80px;
            margin: 0;
        }

        .page-header {
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 15px;
        }

        .page-header h1 {
            margin: 0 0 5px 0;
            font-size: 24px;
            color: var(--text);
        }

        .page-header p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }

        .back {
            color: var(--accent);
            text-decoration: none;
            font-weight: bold;
            margin-bottom: 15px;
            display: inline-block;
        }

        .tabs {
            display: flex;
            overflow-x: auto;
            gap: 8px;
            margin-bottom: 25px;
            padding-bottom: 5px;
            scrollbar-width: none;
        }

        .tabs::-webkit-scrollbar {
            display: none;
        }

        .tab-btn {
            flex: 0 0 auto;
            padding: 12px 20px;
            background: var(--panel);
            border: 1px solid var(--border);
            color: var(--muted);
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: 0.2s;
            white-space: nowrap;
        }

        .tab-btn.active {
            background: var(--accent);
            color: #000;
            border-color: var(--accent);
            box-shadow: 0 0 10px rgba(56, 189, 248, 0.3);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        label {
            display: block;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 700;
            margin-top: 18px;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }

        input, select, textarea {
            width: 100%;
            padding: 14px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--input-bg);
            color: var(--text);
            font-size: 16px;
            box-sizing: border-box;
            transition: 0.2s;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2);
        }

        .box {
            background: var(--panel);
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
            border: 1px solid var(--border);
        }

        .multi-unit {
            background: rgba(0,0,0,0.2);
            padding: 15px;
            margin-top: 15px;
            border-left: 4px solid var(--accent);
            border-radius: 8px;
        }

        .idom-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(0,0,0,0.2);
            padding: 10px 15px;
            margin: 8px 0;
            border-radius: 8px;
            border: 1px solid var(--border);
            font-size: 15px;
            color: #cbd5e1;
            gap: 12px;
        }

        .idom-row input {
            width: 80px;
            padding: 10px;
            margin: 0;
            text-align: center;
        }

        .btn {
            background: var(--success);
            color: white;
            border: none;
            padding: 18px;
            width: 100%;
            border-radius: 10px;
            font-weight: 800;
            margin-top: 30px;
            font-size: 16px;
            cursor: pointer;
            text-transform: uppercase;
            transition: 0.2s;
        }

        .btn:active {
            transform: scale(0.98);
        }

        .btn-duplicate {
            background: var(--warning);
            color: #000;
            border: none;
            padding: 18px;
            width: 100%;
            border-radius: 10px;
            font-weight: 800;
            margin-top: 12px;
            font-size: 16px;
            cursor: pointer;
            text-transform: uppercase;
            transition: 0.2s;
        }

        .btn-duplicate:active {
            transform: scale(0.98);
        }

        .action-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }

        .s-btn {
            background: var(--panel);
            color: var(--accent);
            border: 1px solid var(--border);
            padding: 12px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .s-btn:active {
            background: rgba(56, 189, 248, 0.1);
            border-color: var(--accent);
        }

        .btn-danger {
            color: var(--danger);
        }

        .warning {
            display: none;
            color: white;
            background: rgba(239, 68, 68, 0.15);
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            text-align: center;
            font-weight: bold;
            border: 1px solid var(--danger);
            font-size: 14px;
        }

        .smart-box-orange {
            border-color: var(--warning);
            background: rgba(245, 158, 11, 0.05);
        }

        .smart-box-orange label {
            color: var(--warning);
        }

        .smart-box-orange input {
            border-color: var(--warning);
            color: var(--warning);
            font-size: 20px;
            font-weight: bold;
        }

        .smart-box-blue {
            border-color: var(--accent);
            background: rgba(56, 189, 248, 0.05);
        }

        .smart-box-blue label {
            color: var(--accent);
        }

        .btn-dictation {
            background: #334155;
            color: #fff;
            border: 1px solid var(--border);
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 8px;
            width: 100%;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: 0.2s;
        }

        .btn-dictation:active {
            transform: scale(0.99);
        }

        .btn-dictation.listening {
            background: var(--danger);
            border-color: var(--danger);
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>

    <div class="page-header">
        <a href="index.php" class="back">← Vissza a főmenübe</a>
        <h1>Munkalap & Adminisztráció</h1>
        <p>Válaszd ki a feladat típusát és töltsd ki az adatokat.</p>
    </div>

    <div class="tabs">
        <button class="tab-btn active" type="button" onclick="openTab(event, 'tab_munkanaplo')">Felmérés</button>
        <button class="tab-btn" type="button" onclick="openTab(event, 'tab_telepites')">Telepítés</button>
        <button class="tab-btn" type="button" onclick="openTab(event, 'tab_karbantartas')">Karbantartás</button>
        <button class="tab-btn" type="button" onclick="openTab(event, 'tab_javitas')">Javítás</button>
        <button class="tab-btn" type="button" onclick="openTab(event, 'tab_egyeb')">Egyéb</button>
    </div>

    <div class="action-row">
        <button type="button" class="s-btn" onclick="tgl()">🔍 Ügyfél betöltése</button>
        <button type="button" class="s-btn" onclick="elozmenyek()">📖 Előzmények</button>
    </div>

    <button type="button" class="s-btn btn-danger" onclick="urlapTorlese()" style="width: 100%; margin-top: -10px;">🧹 Űrlap teljes törlése (Új munka)</button>

    <div id="history_box" style="display:none; background: var(--panel); border: 1px solid var(--accent); padding: 15px; border-radius: 8px; margin-bottom: 20px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 13px; line-height: 1.5;"></div>

    <div id="sb" style="display:none; background: var(--panel); padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid var(--warning);">
        <input type="text" id="km" list="ulist" oninput="val(this.value)" placeholder="Kezdj el gépelni egy nevet...">
        <datalist id="ulist"></datalist>
    </div>

    <div id="tab_munkanaplo" class="tab-content active">
        <form onsubmit="hF(event, 'felmeres')">
            <label>Ügyfél Neve</label><input type="text" name="ugyfel" class="un" placeholder="Minta János" required>
            <label>Cím</label><input type="text" name="cim" class="uc" placeholder="Irányítószám, Település, Utca">
            <label>Email</label><input type="email" name="email" class="ue" placeholder="minta@email.hu">
            <label>Telefon</label><input type="tel" name="tel" class="ut" placeholder="+36 30 123 4567">

            <label>Munka foka</label>
            <select name="munka_foka">
                <option>alap</option>
                <option>közepes</option>
                <option>nehéz</option>
            </select>

            <div class="box">
                <label>Kültéri konfiguráció</label>
                <select id="mn_konfig" name="kulteri_konfig" onchange="tK('mn_konfig', 'mn_mono', 'mn_multi')">
                    <option value="mono">Monó szett</option>
                    <option value="multi">Multi (több beltéri)</option>
                </select>

                <div id="mn_mono">
                    <div id="mono_container">
                        <div class="multi-unit">
                            <label>1. Berendezés típusa</label>
                            <input type="text" name="mono_tipus_1" list="mono_gep_lista" placeholder="Válassz vagy gépelj...">
                            <label>Darabszám (szabadon beírható)</label>
                            <input type="number" name="mono_db_1" value="1" min="1">
                        </div>
                    </div>
                    <button type="button" class="s-btn" onclick="addMono()" style="margin-top:15px; width: 100%;">➕ TOVÁBBI MONO SZETT HOZZÁADÁSA</button>
                    <input type="hidden" name="mono_osszes" id="mono_osszes" value="1">
                </div>

                <div id="mn_multi" style="display:none;">
                    <label>Multi kültéri választó</label>
                    <input type="text" name="multi_kulteri" list="multi_lista" placeholder="Válassz vagy gépelj...">
                    <datalist id="multi_lista">
                        <option value="AIRSTAGE AOEG14KBCA2">
                        <option value="AIRSTAGE AOEG18KBCA2">
                        <option value="AIRSTAGE AOEG18KBCA3">
                        <option value="AIRSTAGE AOEG24KBCA3">
                        <option value="AIRSTAGE AOEG30KBTA4">
                    </datalist>

                    <label>Beltérik száma</label>
                    <select id="mn_b_szam" name="mn_b_szam" onchange="gB('mn_b_szam', 'mn_belterik', true)">
                        <option value="0">Válassz...</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                        <option value="6">6</option>
                        <option value="7">7</option>
                        <option value="8">8</option>
                    </select>
                    <div id="mn_belterik"></div>
                </div>
            </div>

            <div class="box">
                <label>Csövezés és anyagok</label>
                <select name="rez_par">
                    <option>6-10</option>
                    <option>6-12</option>
                    <option>10-15</option>
                    <option>10-16</option>
                </select>

                <input type="number" step="0.1" name="rez_hossz" placeholder="Becsült rézcső hossza (m)">

                <label>MT kábel</label>
                <select name="mt_kabel">
                    <option>4x1</option>
                    <option>4x1,5</option>
                    <option>5x1</option>
                    <option>5x1.5</option>
                </select>

                <label>Kábelcsatorna</label>
                <select name="csat_meret">
                    <option>kicsi</option>
                    <option>nagy</option>
                    <option>nem kell</option>
                </select>

                <div style="margin-top: 15px;">
                    <div class="idom-row"><span>Lapos sarok (db)</span> <input type="number" name="id_lapos" value="0"></div>
                    <div class="idom-row"><span>Külső ív (db)</span> <input type="number" name="id_kulso" value="0"></div>
                    <div class="idom-row"><span>Belső ív (db)</span> <input type="number" name="id_belso" value="0"></div>
                    <div class="idom-row"><span>Végzáró (db)</span> <input type="number" name="id_vegzaro" value="0"></div>
                    <div class="idom-row"><span>Fali átvezető (db)</span> <input type="number" name="id_atvezeto" value="0"></div>
                </div>
            </div>

            <div class="box">
                <label>Kondenzvíz</label>
                <select id="mn_kon_tipus" name="kon_tipus" onchange="warns()">
                    <option value="flexi">flexi cső</option>
                    <option value="pvc">PVC cső</option>
                </select>

                <div id="mn_pvc_warn" class="warning">⚠️ FIGYELEM: PVC-S TÁSKA KELL!</div>

                <select id="mn_kon_elv" name="kon_elvezetes" onchange="warns()">
                    <option value="egyutt">Klíma csövekkel együtt megy</option>
                    <option value="kulon">Külön elvezetést igényel</option>
                </select>

                <div id="mn_pvc_extra" style="display:none; margin-top:10px;">
                    <input type="number" step="0.1" name="pvc_csat_hossz" placeholder="25x25 kábelcsatorna (m)">
                </div>

                <label>Kondenzvíz szivattyú</label>
                <select name="kon_szivattyu">
                    <option>nem kell</option>
                    <option>kell</option>
                </select>
            </div>

            <div class="box">
                <label>Telepítés körülményei</label>
                <select name="fal_tipusa">
                    <option>vasbeton fal</option>
                    <option>tégla fal</option>
                    <option>könnyűszerkezet / egyéb</option>
                </select>

                <label>Kültéri helye</label>
                <select name="k_hely">
                    <option>ablak alá</option>
                    <option>erkély</option>
                    <option>oldalfalra magasra</option>
                    <option>egyéb</option>
                </select>

                <label>Konzol típusa</label>
                <select name="k_tarto">
                    <option>fali konzol</option>
                    <option>műanyag talp</option>
                    <option>tetőkonzol</option>
                    <option>egyéb</option>
                </select>

                <label>Nagy létra szükséges?</label>
                <select id="mn_letra" name="letra" onchange="warns()">
                    <option value="nem kell">Nem kell</option>
                    <option value="kell">Igen, kell</option>
                </select>

                <div id="mn_letra_warn" class="warning">⚠️ FIGYELEM: NAGY LÉTRA KELL!</div>

                <label>Elektromos betáp</label>
                <select id="mn_betap" name="betap" onchange="tV('mn_betap', 'mn_v_extra')">
                    <option value="dugvilla">Dugvilla (konnektor)</option>
                    <option value="villanyszerelő">Villanyszerelő szükséges</option>
                </select>

                <div id="mn_v_extra" style="display:none;">
                    <label>Ki intézi a villanyszerelést?</label>
                    <select name="villany_ki">
                        <option>Megrendelő intézi</option>
                        <option>Ridinger Zoltán</option>
                    </select>
                </div>
            </div>

            <label>Fényképek feltöltése (Felméréshez)</label>
            <input type="file" name="foto[]" multiple accept="image/*">

            <label>Megjegyzés</label>
            <textarea name="megjegyzes" id="felm_megj" placeholder="Ide jöhet bármi extra kérés vagy megfigyelés..." rows="3"></textarea>
            <button type="button" class="btn-dictation" id="dict-felm-megj" onclick="startDictation('felm_megj', 'dict-felm-megj')">
                🎙️ Diktálás indítása
            </button>

            <button type="submit" class="btn" onclick="window.isDuplazunk=false">💾 MENTÉS ÉS KÜLDÉS</button>
            <button type="submit" class="btn-duplicate" onclick="window.isDuplazunk=true">🔁 MENTÉS ÉS ŰRLAP MEGTARTÁSA</button>
        </form>
    </div>

    <div id="tab_telepites" class="tab-content">
        <form onsubmit="hF(event, 'telepites')">
            <label>Ügyfél Neve</label><input type="text" name="ugyfel" class="un" required>
            <label>Cím</label><input type="text" name="cim" class="uc">
            <label>Email cím</label><input type="email" name="email" class="ue">
            <label>Telefonszám</label><input type="tel" name="tel" class="ut">

            <label>Karbantartási emlékeztető (Számlázáshoz)</label>
            <select name="karb_ido">
                <option value="6">6 hónap</option>
                <option value="12" selected>12 hónap</option>
            </select>

            <div class="box smart-box-orange">
                <label>📏 Ténylegesen felhasznált rézcső (méter)</label>
                <input type="number" step="0.1" name="rezcso_hossz" placeholder="Pl. 4.5">
            </div>

            <div class="box">
                <label>Hűtőközeg rátöltés (gramm - ha volt)</label>
                <input type="number" name="hutokozeg" placeholder="Pl. 150">

                <label>Beltéri gyári szám</label>
                <input type="text" name="belteri_szam" placeholder="Gyári szám beírása vagy vonalkód olvasása...">

                <label>Kültéri gyári szám</label>
                <input type="text" name="kulteri_szam" placeholder="Gyári szám beírása vagy vonalkód olvasása...">
            </div>

            <label>Telepítés Fényképei (Munka után)</label>
            <input type="file" name="foto[]" multiple accept="image/*">

            <label>Megjegyzés / Naplózás</label>
            <textarea name="megjegyzes" id="tel_megj" rows="3"></textarea>
            <button type="button" class="btn-dictation" id="dict-tel-megj" onclick="startDictation('tel_megj', 'dict-tel-megj')">
                🎙️ Diktálás indítása
            </button>

            <label>Munka Állapota</label>
            <select name="munka_statusz" style="border: 2px solid var(--success); color: var(--success); font-weight: bold;">
                <option value="folyamatban" style="color: white;">⚠️ FOLYAMATBAN (Még vissza kell jönnöm)</option>
                <option value="lezarva" selected>✅ LEZÁRVA (A munka teljesen kész)</option>
            </select>

            <button type="submit" class="btn" onclick="window.isDuplazunk=false">💾 MENTÉS ÉS KÜLDÉS</button>
            <button type="submit" class="btn-duplicate" onclick="window.isDuplazunk=true">🔁 MENTÉS ÉS ŰRLAP MEGTARTÁSA</button>
        </form>
    </div>

    <div id="tab_karbantartas" class="tab-content">
        <form onsubmit="hF(event, 'karbantartas')">
            <label>Ügyfél Neve</label><input type="text" name="ugyfel" class="un" required>
            <label>Cím</label><input type="text" name="cim" class="uc">
            <label>Email cím</label><input type="email" name="email" class="ue">
            <label>Telefonszám</label><input type="tel" name="tel" class="ut">

            <div class="box" style="text-align: center;">
                <label style="margin-top: 0;">Karbantartott gépek száma (db)</label>
                <input type="number" name="gep_darabszam" value="1" min="1" inputmode="numeric" style="font-size: 28px; font-weight: 900; text-align: center; color: var(--accent); border-color: var(--accent);">
            </div>

            <div class="box smart-box-blue">
                <label>🏢 Épület típusa</label>
                <select id="building_type" onchange="updateRooms()">
                    <option value="haz">Családi ház / Lakás</option>
                    <option value="iroda">Irodaház / Közület</option>
                </select>

                <label>Szint / Emelet</label>
                <input type="text" name="emelet" list="emelet_lista" placeholder="Pl. Földszint, 1. emelet...">
                <datalist id="emelet_lista">
                    <option value="Földszint">
                    <option value="1. emelet">
                    <option value="2. emelet">
                    <option value="Tetőtér">
                    <option value="Alagsor">
                </datalist>

                <label>🚪 Helyiség azonosító</label>
                <select id="room_select" name="helyseg" onchange="checkCustomRoom()"></select>

                <div id="custom_room_div" style="display: none; margin-top: 15px;">
                    <label>✏️ Egyedi helyiség neve</label>
                    <input type="text" name="custom_room_name" placeholder="pl. Kazánház, Szerver 2...">
                </div>
            </div>

            <div class="box">
                <label>Karbantartás típusa</label>
                <select name="karb_tipus">
                    <option value="kis karbantartás">Kis karbantartás (Szűrő, gombaölő)</option>
                    <option value="zsákos mosás">Nagymosás (Zsákos, vegyszeres)</option>
                </select>

                <label>Következő karbantartás ciklusa</label>
                <select name="karb_ciklus">
                    <option value="6">6 hónap (Szerverterem / Ipari)</option>
                    <option value="12" selected>12 hónap (Normál lakossági)</option>
                </select>
            </div>

            <label>Fényképek (Karbantartás utáni állapot)</label>
            <input type="file" name="foto[]" multiple accept="image/*">

            <label>Megjegyzés</label>
            <textarea name="megjegyzes" id="karb_megj" placeholder="Ha valami hibát vagy rendellenességet tapasztaltál..." rows="3"></textarea>
            <button type="button" class="btn-dictation" id="dict-karb-megj" onclick="startDictation('karb_megj', 'dict-karb-megj')">🎙️ Hangalapú diktálás indítása</button>

            <label>Munka Állapota</label>
            <select name="munka_statusz" style="border: 2px solid var(--success); color: var(--success); font-weight: bold;">
                <option value="folyamatban" style="color: white;">⚠️ FOLYAMATBAN (Még vissza kell jönnöm)</option>
                <option value="lezarva" selected>✅ LEZÁRVA (A munka teljesen kész)</option>
            </select>

            <button type="submit" class="btn" onclick="window.isDuplazunk=false">💾 MENTÉS ÉS KÜLDÉS</button>
            <button type="submit" class="btn-duplicate" onclick="window.isDuplazunk=true">🔁 MENTÉS ÉS ŰRLAP MEGTARTÁSA</button>
        </form>
    </div>

    <div id="tab_javitas" class="tab-content">
        <form onsubmit="hF(event, 'javitas')">
            <label>Ügyfél Neve</label><input type="text" name="ugyfel" class="un" required>
            <label>Cím</label><input type="text" name="cim" class="uc">
            <label>Email cím</label><input type="email" name="email" class="ue">
            <label>Telefonszám</label><input type="tel" name="tel" class="ut">

            <div class="box">
                <label>Hiba leírása / Elvégzett javítás</label>
                <input type="text" name="hiba_leiras" id="jav_hiba" placeholder="Röviden a hibáról és a javításról...">
                <button type="button" class="btn-dictation" id="dict-jav-hiba" onclick="startDictation('jav_hiba', 'dict-jav-hiba')">🎙️ Hangalapú diktálás indítása (Hiba)</button>

                <label>Megjegyzés</label>
                <textarea name="megjegyzes" id="jav_megj" placeholder="Ide jöhet a bővebb szakmai infó, alkatrész igény..." rows="3"></textarea>
                <button type="button" class="btn-dictation" id="dict-jav-megj" onclick="startDictation('jav_megj', 'dict-jav-megj')">🎙️ Hangalapú diktálás indítása (Megjegyzés)</button>
            </div>

            <label>Fényképek a hibáról</label>
            <input type="file" name="foto[]" multiple accept="image/*">

            <label>Munka Állapota</label>
            <select name="munka_statusz" style="border: 2px solid var(--danger); color: var(--danger); font-weight: bold;">
                <option value="folyamatban" selected>⚠️ FOLYAMATBAN (Alkatrészre vár / Vissza kell jönnöm)</option>
                <option value="lezarva" style="color: white;">✅ LEZÁRVA (Sikeresen megjavítva)</option>
            </select>

            <button type="submit" class="btn" onclick="window.isDuplazunk=false">💾 MENTÉS ÉS KÜLDÉS</button>
            <button type="submit" class="btn-duplicate" onclick="window.isDuplazunk=true">🔁 MENTÉS ÉS ŰRLAP MEGTARTÁSA</button>
        </form>
    </div>

    <div id="tab_egyeb" class="tab-content">
        <form onsubmit="hF(event, 'egyeb')">
            <label>Ügyfél Neve</label><input type="text" name="ugyfel" class="un" required>
            <label>Cím</label><input type="text" name="cim" class="uc">
            <label>Email cím</label><input type="email" name="email" class="ue">
            <label>Telefonszám</label><input type="tel" name="tel" class="ut">

            <div class="box">
                <label>Munka pontos megnevezése</label>
                <input type="text" name="egyeb_munka_neve" id="egyeb_munka" placeholder="Pl.: Kültéri leszerelés szigetelés miatt...">
                <button type="button" class="btn-dictation" id="dict-egyeb-munka" onclick="startDictation('egyeb_munka', 'dict-egyeb-munka')">🎙️ Hangalapú diktálás indítása (Név)</button>

                <label>Megjegyzés</label>
                <textarea name="megjegyzes" id="egyeb_megj" placeholder="Bővebb leírás..." rows="3"></textarea>
                <button type="button" class="btn-dictation" id="dict-egyeb-megj" onclick="startDictation('egyeb_megj', 'dict-egyeb-megj')">🎙️ Hangalapú diktálás indítása (Leírás)</button>
            </div>

            <label>Fényképek</label>
            <input type="file" name="foto[]" multiple accept="image/*">

            <label>Munka Állapota</label>
            <select name="munka_statusz" style="border: 2px solid var(--success); color: var(--success); font-weight: bold;">
                <option value="folyamatban" style="color: white;">⚠️ FOLYAMATBAN (Még vissza kell jönnöm)</option>
                <option value="lezarva" selected>✅ LEZÁRVA (A munka teljesen kész)</option>
            </select>

            <button type="submit" class="btn" onclick="window.isDuplazunk=false">💾 MENTÉS ÉS KÜLDÉS</button>
            <button type="submit" class="btn-duplicate" onclick="window.isDuplazunk=true">🔁 MENTÉS ÉS ŰRLAP MEGTARTÁSA</button>
        </form>
    </div>

    <datalist id="mono_gep_lista">
        <option value="--- ECO KN SOROZAT ---">
        <option value="ASEH07KNCA/AOEH07KNCA (2.0 kW)">
        <option value="ASEH09KNCA/AOEH09KNCA (2.5 kW)">
        <option value="ASEH12KNCA/AOEH12KNCA (3.4 kW)">
        <option value="ASEH14KNCA/AOEH14KNCA (4.2 kW)">
        <option value="ASEH18KNCA/AOEH18KNCA (5.2 kW)">
        <option value="--- ECO KL SOROZAT ---">
        <option value="ASEH07KLTA/AOEH07KLTA (2.0 kW)">
        <option value="ASEH09KLTA/AOEH09KLTA (2.5 kW)">
        <option value="ASEH12KLTA/AOEH12KLTA (3.4 kW)">
        <option value="ASEH14KLTA/AOEH14KLTA (4.2 kW)">
        <option value="ASEH18KLTA/AOEH18KLTA (5.2 kW)">
        <option value="--- STANDARD KM SOROZAT ---">
        <option value="ASEH07KMCG/AOEH07KMCG (2.0 kW)">
        <option value="ASEH09KMCG/AOEH09KMCG (2.5 kW)">
        <option value="ASEH12KMCG/AOEH12KMCG (3.4 kW)">
        <option value="ASEH14KMCG/AOEH14KMCG (4.2 kW)">
        <option value="ASEH18KMCG/AOEH18KMCG (5.2 kW)">
        <option value="--- DESIGN KE SOROZAT ---">
        <option value="ASYG07KETE/AOYG07KETA (2.0 kW)">
        <option value="ASYG09KETE/AOYG09KETA (2.5 kW)">
        <option value="ASYG12KETE/AOYG12KETA (3.4 kW)">
        <option value="ASYG14KETE/AOYG14KETA (4.2 kW)">
        <option value="ASYG18KETE/AOYG18KETA (5.2 kW)">
        <option value="--- DESIGN KG SOROZAT ---">
        <option value="ASEH07KGTG/AOEH07KGCG (2.0 kW)">
        <option value="ASEH09KGTG/AOEH09KGCG (2.5 kW)">
        <option value="ASEH12KGTG/AOEH12KGCG (3.4 kW)">
        <option value="ASEH14KGTG/AOEH14KGCG (4.2 kW)">
        <option value="ASEH18KGTG/AOEH18KGCG (5.2 kW)">
        <option value="--- DESIGN KJ SOROZAT ---">
        <option value="ASEH07KJCAL/AOEH07KJCA (2.0 kW)">
        <option value="ASEH09KJCAL/AOEH09KJCA (2.5 kW)">
        <option value="ASEH12KJCAL/AOEH12KJCA (3.4 kW)">
        <option value="ASEH14KJCAL/AOEH14KJCA (4.2 kW)">
        <option value="ASEH18KJCAL/AOEH18KJCA (5.2 kW)">
    </datalist>

    <script>
        let uls = [];
        window.isDuplazunk = false;

        const roomsData = {
            haz: ['Nappali', 'Hálószoba', 'Gyerekszoba 1', 'Gyerekszoba 2', 'Gyerekszoba 3', 'Konyha', 'Étkező', 'Folyosó', 'Padlástér', 'Alagsor', 'egyeb'],
            iroda: ['Iroda 1', 'Iroda 2', 'Iroda 3', 'Tárgyaló', 'Szerverszoba', 'Konyha/Étkező', 'Folyosó', 'Recepció', 'Eladótér', 'Raktár', 'egyeb']
        };

        const roomLabels = {
            egyeb: '✏️ Egyéb (Kézzel beírom...)'
        };

        function updateRooms() {
            const bType = document.getElementById('building_type');
            const rSel = document.getElementById('room_select');
            if (!bType || !rSel) return;

            rSel.innerHTML = '';
            const rooms = roomsData[bType.value] || [];
            rooms.forEach(r => {
                const opt = document.createElement('option');
                opt.value = r;
                opt.innerText = roomLabels[r] || r;
                rSel.appendChild(opt);
            });

            checkCustomRoom();
        }

        function checkCustomRoom() {
            const rSel = document.getElementById('room_select');
            const cDiv = document.getElementById('custom_room_div');
            if (rSel && cDiv) {
                cDiv.style.display = (rSel.value === 'egyeb') ? 'block' : 'none';
            }
        }

        function elozmenyek() {
            const hb = document.getElementById('history_box');
            if (hb.style.display === 'block') {
                hb.style.display = 'none';
                return;
            }

            const activeForm = document.querySelector('.tab-content.active form');
            if (!activeForm) return;

            const nevInput = activeForm.querySelector('input[name="ugyfel"]');
            const cimInput = activeForm.querySelector('input[name="cim"]');
            const nev = nevInput ? nevInput.value.trim() : '';
            const cim = cimInput ? cimInput.value.trim() : '';

            if (!nev) {
                alert("⚠️ Kérlek, előbb írd be vagy húzd be az ügyfél nevét!");
                return;
            }

            hb.style.display = 'block';
            hb.innerText = "⏳ Töltés az adatbázisból...";

            const fd = new FormData();
            fd.append('ajax_history', '1');
            fd.append('nev', nev);
            fd.append('cim', cim);

            fetch('munkalap.php', { method: 'POST', body: fd })
                .then(r => r.text())
                .then(txt => { hb.innerText = txt; })
                .catch(() => { hb.innerText = "❌ Hiba a szerver elérésekor."; });
        }

        function urlapTorlese() {
            if (confirm("Biztosan törlöd a beírt adatokat és új munkát kezdesz?")) {
                localStorage.removeItem('nw_autosave');
                location.reload();
            }
        }

        setInterval(() => {
            const data = {};
            document.querySelectorAll('input, select, textarea').forEach(el => {
                if (el.type !== 'file' && el.name) {
                    data[el.name] = el.value;
                }
            });
            localStorage.setItem('nw_autosave', JSON.stringify(data));
        }, 10000);

        window.addEventListener('load', () => {
            updateRooms();
            warns();

            const saved = localStorage.getItem('nw_autosave');
            if (saved) {
                try {
                    const data = JSON.parse(saved);
                    document.querySelectorAll('input, select, textarea').forEach(el => {
                        if (el.type !== 'file' && el.name && data[el.name] !== undefined) {
                            el.value = data[el.name];
                        }
                    });

                    if (document.getElementById('mn_konfig')) {
                        tK('mn_konfig', 'mn_mono', 'mn_multi');
                    }

                    if (document.getElementById('mn_b_szam')) {
                        const db = parseInt(document.getElementById('mn_b_szam').value || '0', 10);
                        if (db > 0) {
                            gB('mn_b_szam', 'mn_belterik', true);
                            for (let i = 1; i <= db; i++) {
                                const kat = document.querySelector(`[name="b_kat_${i}"]`);
                                const pwr = document.querySelector(`[name="b_pwr_${i}"]`);
                                if (kat && data[`b_kat_${i}`] !== undefined) kat.value = data[`b_kat_${i}`];
                                if (pwr && data[`b_pwr_${i}`] !== undefined) pwr.value = data[`b_pwr_${i}`];
                            }
                        }
                    }

                    checkCustomRoom();
                    warns();

                    const t = document.createElement('div');
                    t.innerText = "💾 Befejezetlen piszkozat betöltve a memóriából!";
                    t.style = "position:fixed; bottom:20px; left:50%; transform:translateX(-50%); background:var(--success); color:white; padding:15px; border-radius:8px; z-index:9999; font-weight:bold; box-shadow: 0 4px 15px rgba(0,0,0,0.5); text-align: center;";
                    document.body.appendChild(t);
                    setTimeout(() => t.remove(), 4000);
                } catch (e) {
                    console.error('Autosave betöltési hiba:', e);
                }
            }
        });

        function tgl() {
            const b = document.getElementById('sb');
            b.style.display = (b.style.display === 'none') ? 'block' : 'none';

            if (b.style.display === 'block') {
                fetch('munkalap.php?ajax_ugyfelek=1&t=' + Date.now())
                    .then(r => r.json())
                    .then(d => {
                        uls = d;
                        const dl = document.getElementById('ulist');
                        dl.innerHTML = "";
                        d.forEach(u => {
                            const o = document.createElement('option');
                            const varolistaJelzo = (u.cim && u.cim.includes('VÁRÓLISTA')) ? ' ⚠️ (Új e-mail megkeresés!)' : '';
                            o.value = u.nev + (u.cim ? " - " + u.cim : "") + varolistaJelzo;
                            dl.appendChild(o);
                        });
                    });
            }
        }

        function val(v) {
            const tisztaV = v.replace(' ⚠️ (Új e-mail megkeresés!)', '');
            const u = uls.find(x => (x.nev + (x.cim ? " - " + x.cim : "")) === tisztaV);
            if (u) {
                document.querySelectorAll('.un').forEach(i => i.value = u.nev);
                document.querySelectorAll('.uc').forEach(i => i.value = u.cim || '');
                document.querySelectorAll('.ut').forEach(i => i.value = u.tel || '');
                document.querySelectorAll('.ue').forEach(i => i.value = u.email || '');
                document.getElementById('sb').style.display = 'none';
            }
        }

        function openTab(event, tabId) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            event.currentTarget.classList.add('active');
        }

        function tK(sId, mId, muId) {
            const v = document.getElementById(sId).value;
            document.getElementById(mId).style.display = (v === 'mono' ? 'block' : 'none');
            document.getElementById(muId).style.display = (v === 'multi' ? 'block' : 'none');
        }

        function gB(sId, cId, isSurvey) {
            const cont = document.getElementById(cId);
            cont.innerHTML = "";
            const num = parseInt(document.getElementById(sId).value || '0', 10);

            for (let i = 1; i <= num; i++) {
                const d = document.createElement('div');
                d.className = "multi-unit";

                if (isSurvey) {
                    d.innerHTML = `
                        <label>${i}. Beltéri kategória</label>
                        <select name="b_kat_${i}">
                            <option>alap beltéri</option>
                            <option>közepes beltéri</option>
                            <option>Design</option>
                            <option>fekete beltéri</option>
                        </select>
                        <label>Teljesítmény</label>
                        <select name="b_pwr_${i}">
                            <option>2 kW</option>
                            <option>2.5 kW</option>
                            <option>3.5 kW</option>
                            <option>4 kW</option>
                        </select>
                    `;
                } else {
                    d.innerHTML = `<label>${i}. Beltéri</label><input type="text" name="b_tipus_${i}" placeholder="Típus...">`;
                }

                cont.appendChild(d);
            }
        }

        function addMono() {
            const countInput = document.getElementById('mono_osszes');
            let count = parseInt(countInput.value || '1', 10) + 1;
            countInput.value = count;

            const container = document.getElementById('mono_container');
            const d = document.createElement('div');
            d.className = "multi-unit";
            d.innerHTML = `
                <label>${count}. Berendezés típusa</label>
                <input type="text" name="mono_tipus_${count}" list="mono_gep_lista" placeholder="Válassz vagy gépelj...">
                <label>Darabszám (szabadon beírható)</label>
                <input type="number" name="mono_db_${count}" value="1" min="1">
            `;
            container.appendChild(d);
        }

        function warns() {
            const konTipus = document.getElementById('mn_kon_tipus');
            const konElv = document.getElementById('mn_kon_elv');
            const letra = document.getElementById('mn_letra');

            const pvcWarn = document.getElementById('mn_pvc_warn');
            const pvcExtra = document.getElementById('mn_pvc_extra');
            const letraWarn = document.getElementById('mn_letra_warn');

            if (konTipus && pvcWarn) {
                pvcWarn.style.display = (konTipus.value === 'pvc' ? 'block' : 'none');
            }

            if (konElv && pvcExtra) {
                pvcExtra.style.display = (konElv.value === 'kulon' ? 'block' : 'none');
            }

            if (letra && letraWarn) {
                letraWarn.style.display = (letra.value === 'kell' ? 'block' : 'none');
            }
        }

        function tV(sId, eId) {
            document.getElementById(eId).style.display = (document.getElementById(sId).value === 'villanyszerelő' ? 'block' : 'none');
        }

        function compressImage(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.readAsDataURL(file);

                reader.onerror = () => reject(new Error('FileReader hiba'));

                reader.onload = event => {
                    const img = new Image();
                    img.src = event.target.result;

                    img.onerror = () => reject(new Error('Kép betöltési hiba'));

                    img.onload = () => {
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');

                        const MAX_WIDTH = 1200;
                        const MAX_HEIGHT = 1200;
                        let width = img.width;
                        let height = img.height;

                        if (width > height) {
                            if (width > MAX_WIDTH) {
                                height *= MAX_WIDTH / width;
                                width = MAX_WIDTH;
                            }
                        } else {
                            if (height > MAX_HEIGHT) {
                                width *= MAX_HEIGHT / height;
                                height = MAX_HEIGHT;
                            }
                        }

                        canvas.width = width;
                        canvas.height = height;
                        ctx.drawImage(img, 0, 0, width, height);

                        resolve(canvas.toDataURL('image/jpeg', 0.7).split(',')[1]);
                    };
                };
            });
        }

        function hF(e, m) {
            e.preventDefault();
            const form = e.target;
            const b = form.querySelector('.btn');
            const b2 = form.querySelector('.btn-duplicate');

            if (b) {
                b.dataset.orig = b.innerText;
                b.innerText = "⏳ TÖMÖRÍTÉS ÉS MENTÉS...";
                b.disabled = true;
            }

            if (b2) {
                b2.disabled = true;
            }

            const fd = new FormData(form);
            fd.append('mode', m);

            fetch('munkalap.php', { method: 'POST', body: fd })
                .then(r => r.text())
                .then(async t => {
                    if (t.trim() === "OK") {
                        const url = 'https://script.google.com/macros/s/AKfycbyGwd1BHK6V2P8jToWW3zytvc-ZrOyVZj6GIWsK1RmwAS_Eean5fzY4_OrSra12O2EJmA/exec';

                        const fileInput = form.querySelector('input[type="file"]');
                        const files = (fileInput && fileInput.files.length > 0)
                            ? Array.from(fileInput.files)
                            : [];

                        if (files.length > 0) {
                            let ugyfelNev = (fd.get('ugyfel') || 'Ismeretlen').toString().replace(/[^a-zA-Z0-9]/g, '_');
                            let helysegNev = (fd.get('helyseg') || 'Alap').toString();

                            if (helysegNev === 'egyeb') {
                                helysegNev = (fd.get('custom_room_name') || 'Egyeb').toString();
                            }

                            helysegNev = helysegNev.replace(/[^a-zA-Z0-9]/g, '_');
                            const munkaTipus = m.toUpperCase();

                            const processedFiles = [];
                            for (let i = 0; i < files.length; i++) {
                                const compressedBase64 = await compressImage(files[i]);
                                processedFiles.push({
                                    data: compressedBase64,
                                    name: `${ugyfelNev}_${helysegNev}_${munkaTipus}_${i + 1}.jpg`,
                                    mime: 'image/jpeg'
                                });
                            }

                            sendToGoogle(fd, processedFiles, url, form);
                        } else {
                            sendToGoogle(fd, [], url, form);
                        }
                    } else {
                        alert("SZERVER HIBA: " + t);
                        if (b) {
                            b.disabled = false;
                            b.innerText = b.dataset.orig;
                        }
                        if (b2) {
                            b2.disabled = false;
                        }
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("Hálózati hiba a szerver felé!");
                    if (b) {
                        b.disabled = false;
                        b.innerText = b.dataset.orig;
                    }
                    if (b2) {
                        b2.disabled = false;
                    }
                });
        }

        function sendToGoogle(fd, filesArray, url, form) {
            const data = {};
            fd.forEach((value, key) => {
                if (typeof value === 'string') {
                    data[key] = value;
                }
            });

            if (filesArray && filesArray.length > 0) {
                data['files_json'] = JSON.stringify(filesArray);
            }

            fetch(url, {
                method: 'POST',
                mode: 'no-cors',
                body: new URLSearchParams(data)
            })
            .then(() => {
                localStorage.removeItem('nw_autosave');

                if (window.isDuplazunk) {
                    alert('✅ Sikeresen rögzítve!\n\nAz űrlap adatait megtartottuk. Módosítsd, amit kell, és menthetsz újra!');

                    const b = form.querySelector('.btn');
                    const b2 = form.querySelector('.btn-duplicate');

                    if (b) {
                        b.innerText = b.dataset.orig;
                        b.disabled = false;
                    }
                    if (b2) {
                        b2.disabled = false;
                    }

                    const fileInput = form.querySelector('input[type="file"]');
                    if (fileInput) {
                        fileInput.value = "";
                    }
                } else {
                    alert('✅ Mentve az új adatbázisba és elküldve a Google-nek!');
                    location.reload();
                }
            })
            .catch(err => {
                console.error(err);
                alert('Szerver mentés ok, de hálózati hiba a Google felé!');
                location.reload();
            });
        }

        // --- FOLYAMATOS HANGALAPÚ DIKTÁLÁS MOTOR ---
        let activeRecognition = null;
        let activeBtnId = null;

        function startDictation(targetId, btnId) {
            if (!('webkitSpeechRecognition' in window)) {
                alert('A böngésződ nem támogatja a beépített diktálást. Kérlek, Chrome-ot vagy Safari-t használj!');
                return;
            }

            const btn = document.getElementById(btnId);
            if (!btn) return;

            if (!btn.dataset.origText) {
                btn.dataset.origText = btn.innerHTML;
            }

            const originalText = btn.dataset.origText;

            if (activeRecognition && activeBtnId === btnId) {
                activeRecognition.stop();
                return;
            }

            if (activeRecognition) {
                activeRecognition.stop();
            }

            const recognition = new webkitSpeechRecognition();
            recognition.lang = 'hu-HU';
            recognition.continuous = true;
            recognition.interimResults = false;

            activeRecognition = recognition;
            activeBtnId = btnId;

            btn.innerHTML = '🛑 Diktálás befejezése (Kattints ide)';
            btn.classList.add('listening');

            recognition.start();

            recognition.onresult = function(event) {
                const current = event.resultIndex;
                const transcript = event.results[current][0].transcript;
                const targetEl = document.getElementById(targetId);

                if (targetEl) {
                    targetEl.value += (targetEl.value ? ' ' : '') + transcript.trim() + '.';
                }
            };

            recognition.onerror = function(event) {
                console.error(event.error);
                if (event.error !== 'no-speech') {
                    alert('Hiba a hangfelismerés során: ' + event.error);
                }
                resetDictationButton(btn, originalText);
            };

            recognition.onend = function() {
                resetDictationButton(btn, originalText);
            };
        }

        function resetDictationButton(btn, originalText) {
            if (!btn) return;
            btn.innerHTML = originalText;
            btn.classList.remove('listening');
            activeRecognition = null;
            activeBtnId = null;
        }
    </script>
</body>
</html>