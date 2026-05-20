<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// 1. Biztonságos jelszóbeolvasás a környezeti változókból (GitHub Secret + cPanel .env kompatibilis)
$username = 'northwindhutestechnikakft@gmail.com';
$password = $_ENV['GMAIL_APP_PASSWORD'] ?? getenv('GMAIL_APP_PASSWORD') ?? ''; 

if ($password === '') {
    echo "❌ Hiba: A GMAIL_APP_PASSWORD biztonsági környezeti változó nincs beállítva!";
    exit;
}

$mailbox = '{imap.gmail.com:993/imap/ssl}INBOX';
$inbox = @imap_open($mailbox, $username, $password);

if (!$inbox) {
    echo "❌ Nem sikerült csatlakozni a Gmailhez. Ellenőrizd az alkalmazásjelszót a környezeti beállításokban!";
    exit;
}

$emails = imap_search($inbox, 'UNREAD');

if ($emails) {
    $pdo = db();
    $hozzaadott = 0;

    foreach ($emails as $email_number) {
        $overview = imap_fetch_overview($inbox, $email_number, 0);
        $message = imap_fetchbody($inbox, $email_number, 1); 
        
        $subject = isset($overview[0]->subject) ? imap_utf8($overview[0]->subject) : 'Nincs tárgy';
        $from = isset($overview[0]->from) ? imap_utf8($overview[0]->from) : '';
        
        preg_match('/<(.*?)>/', $from, $match);
        $sender_email = $match[1] ?? $from;
        $sender_name = preg_replace('/<.*?>/', '', $from);
        $sender_name = trim(str_replace('"', '', $sender_name));

        if ($overview[0]->encoding == 4) {
            $body = quoted_printable_decode($message);
        } elseif ($overview[0]->encoding == 3) {
            $body = base64_decode($message);
        } else {
            $body = $message;
        }
        
        $bodyUtf8 = mb_convert_encoding($body, 'UTF-8', 'auto');
        $teljes_szoveg = mb_strtolower($subject . ' ' . strip_tags($bodyUtf8));

        // Negatív szűrő (Spam)
        $kizart_szavak = ['hírlevél', 'leiratkozás', 'unsubscribe', 'reklám', 'seo', 'marketing', 'optimalizálás'];
        $is_spam = false;
        foreach ($kizart_szavak as $sz) {
            if (strpos($teljes_szoveg, $sz) !== false) {
                $is_spam = true; 
                break;
            }
        }

        // Pozitív szűrő
        $keresett_szavak = ['klíma', 'klima', 'légtechnika', 'karbantartás', 'javítás', 'javitas', 'felmérés', 'telepítés', 'telepites', 'szerelés', 'árajánlat', 'ajánlat', 'nem hűt', 'nem fűt', 'csöpög', 'hiba', 'kivitelezés'];
        $is_munka = false;
        
        if (!$is_spam) {
            foreach ($keresett_szavak as $sz) {
                if (strpos($teljes_szoveg, $sz) !== false) {
                    $is_munka = true; 
                    break;
                }
            }
        }

        if ($is_munka) {
            preg_match('/(?:\+36|06)[\s\-]?\d{1,2}[\s\-]?\d{3}[\s\-]?\d{3,4}/', $bodyUtf8, $tel_match);
            $phone = trim($tel_match[0] ?? '');

            $rovid_szoveg = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($bodyUtf8))), 0, 100) . '...';
            $cim_varolista = "VÁRÓLISTA | Tárgy: $subject | $rovid_szoveg";

            $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
            $stmt->execute([$sender_email]);
            $exists = $stmt->fetch();

            if ($exists) {
                $update = $pdo->prepare("UPDATE customers SET waitlist_info = ?, is_waitlist = 1 WHERE id = ?");
                $update->execute([$cim_varolista, $exists['id']]);
            } else {
                $insert = $pdo->prepare("INSERT INTO customers (name, email, phone, waitlist_info, is_waitlist) VALUES (?, ?, ?, ?, 1)");
                $insert->execute([$sender_name, $sender_email, $phone, $cim_varolista]);
            }
            $hozzaadott++;
        }
        imap_setflag_full($inbox, $email_number, "\\Seen");
    }
    echo "✅ Szinkronizáció kész! Új Várólistás ügyfelek: " . $hozzaadott;
} else {
    echo "Nincs új, olvasatlan levél a fiókban.";
}

imap_close($inbox);
?>