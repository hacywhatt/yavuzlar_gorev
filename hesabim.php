<?php
session_start();


// sadece giris yaomis kullanicilar bu sayfaya gelebilir-ve sadece userlar-
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    // giris yapilmadiysa ya da rol uygun degilse logine yonlendir
    header("Location: login.php?message=" . urlencode("Bu sayfaya erişim için giriş yapmanız gerekmektedir."));
    exit();
}

$database_file = 'database.sqlite';
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

try {
    $db = new PDO("sqlite:$database_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // dbden kullanicinin bilgisini cekme
    $stmt_user = $db->prepare("SELECT username, email, balance FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user_profile = $stmt_user->fetch(PDO::FETCH_ASSOC);

    // bilet iptal islemleri
    if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['ticket_id'])) {
        $ticket_id_to_cancel = $_GET['ticket_id'];

        $db->beginTransaction();
        try {
            // dbden bileti ve seferi cekme
            $stmt_ticket = $db->prepare("SELECT t.*, tr.trip_date, tr.departure_time, tr.price 
                                        FROM tickets t 
                                        JOIN trips tr ON t.trip_id = tr.id 
                                        WHERE t.id = ? AND t.user_id = ? AND t.is_cancelled = 0");
            $stmt_ticket->execute([$ticket_id_to_cancel, $user_id]);
            $ticket = $stmt_ticket->fetch(PDO::FETCH_ASSOC);

            if ($ticket) {
                // kalkis tarihini ve saatini birlestirme
                $departure_datetime_str = $ticket['trip_date'] . ' ' . $ticket['departure_time'];
                $departure_timestamp = strtotime($departure_datetime_str);
                
                // kalkis saatine 1 saatten az kaldiysa iptal edeme
                $cancellation_deadline = $departure_timestamp - (60 * 60); // 1 saat once
                $current_time = time();

                if ($current_time < $cancellation_deadline) {
                    $refund_amount = $ticket['purchase_price'];
                    
                    // bileti iptal et 
                    $stmt_cancel = $db->prepare("UPDATE tickets SET is_cancelled = 1, cancellation_time = DATETIME('now') WHERE id = ?");
                    $stmt_cancel->execute([$ticket_id_to_cancel]);
                    
                    // biletin ucretini kullanicinin hesabina iade et
                    $stmt_refund = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                    $stmt_refund->execute([$refund_amount, $user_id]);
                    
                    $db->commit();
                    $success = "Bilet başarıyla iptal edildi. " . number_format($refund_amount, 2) . " TL bakiyenize iade edildi.";
                    
                    // profil verilerini guncelleme
                    $user_profile['balance'] += $refund_amount;
                } else {
                    // 1 saatten az kaldiysa
                    $error = "Bilet iptal kuralı gereği, kalkışa son 1 saatten az kaldığı için biletiniz iptal edilemez.";
                }
            } else {
                $error = "İptal edilecek bilet bulunamadı veya daha önce iptal edilmiş.";
            }

        } catch (Exception $e) {
            $db->rollBack();
            $error = "Bilet iptal sırasında bir hata oluştu: " . $e->getMessage();
        }
    }

    //  kullanicinin biletlerini listeleme
    $sql_tickets = "SELECT 
                        t.id as ticket_id, t.seat_number, t.purchase_price, t.is_cancelled,
                        tr.departure_city, tr.arrival_city, tr.trip_date, tr.departure_time, tr.total_seats,
                        f.name as firma_name
                    FROM tickets t 
                    JOIN trips tr ON t.trip_id = tr.id 
                    JOIN firms f ON tr.firma_id = f.id
                    WHERE t.user_id = ? 
                    ORDER BY tr.trip_date DESC, tr.departure_time DESC";
                    
    $stmt_tickets = $db->prepare($sql_tickets);
    $stmt_tickets->execute([$user_id]);
    $my_tickets = $stmt_tickets->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Hesabım ve Biletlerim</title>
    <style>
    
        body {
            font-family: 'Consolas', 'Courier New', monospace;
            background: radial-gradient(circle at top, #000000 60%, #001100);
            color: #00ff88;
            margin: 0;
            padding: 30px;
            overflow-x: hidden;
        }

        h1, h2 {
            color: #00ff88;
            text-shadow: 0 0 10px #00ff88, 0 0 20px #00ff55;
        }

        a {
            color: #00ffaa;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
        }

        a:hover {
            color: #ffffff;
            text-shadow: 0 0 8px #00ff99;
        }

        p a {
            color: #00ffaa;
        }

        hr {
            border: none;
            height: 1px;
            background: linear-gradient(to right, transparent, #00ff55, transparent);
            margin: 30px 0;
        }

        .message {
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: bold;
            border-left: 3px solid;
        }

        .error {
            background-color: rgba(50, 0, 0, 0.8);
            color: #ff5555;
            border-color: #ff0000;
        }

        .success {
            background-color: rgba(0, 50, 20, 0.8);
            color: #00ff99;
            border-color: #00ff66;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(0, 25, 0, 0.9);
            border: 1px solid #00ff55;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 25px rgba(0,255,100,0.1);
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
        }

        th {
            background-color: #003300;
            color: #00ff99;
            border-bottom: 1px solid #00ff55;
        }

        tr:nth-child(even) {
            background-color: rgba(0, 40, 0, 0.8);
        }

        tr:nth-child(odd) {
            background-color: rgba(0, 30, 0, 0.7);
        }

        tr:hover {
            background-color: #004400;
            transition: 0.2s;
        }

        .cancelled {
            background-color: rgba(50, 0, 0, 0.7);
            color: #ff7777;
        }

        strong {
            color: #00ffaa;
        }

        .ticket-list {
            margin-top: 20px;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0, 30, 0, 0.8);
            padding: 15px 25px;
            border-radius: 10px;
            border: 1px solid #00ff55;
            margin-bottom: 30px;
            box-shadow: 0 0 20px rgba(0, 255, 136, 0.2);
            animation: glow 2s infinite alternate;
        }

        @keyframes glow {
            from { box-shadow: 0 0 10px #00ff55; }
            to { box-shadow: 0 0 30px #00ff99; }
        }


    </style>
</head>
<body>
    <canvas id="matrixRain"></canvas>

    <div class="panel-header">
        <h1>Hesabım ve Biletlerim</h1>
        <div>
            <a href="index.php">Ana Sayfa</a> |
            <a href="logout.php">Çıkış Yap</a>
        </div>
    </div>

    <?php 
    if (!empty($error)) echo "<div class='message error'>$error</div>";
    if (!empty($success)) echo "<div class='message success'>$success</div>";
    ?>

    <h2>> Profil ve Bakiye Bilgileri</h2>
    <p>
        <strong>Kullanıcı Adı:</strong> <?php echo htmlspecialchars($user_profile['username'] ?? 'N/A'); ?><br>
        <strong>E-posta:</strong> <?php echo htmlspecialchars($user_profile['email'] ?? 'N/A'); ?><br>
        <strong>Sanal Kredi Bakiyesi:</strong> <strong style="color: #00ff99;"><?php echo number_format($user_profile['balance'] ?? 0, 2); ?> TL</strong>
    </p>

    <hr>

    <h2>> Geçmiş Biletlerim</h2>
    <?php if (empty($my_tickets)): ?>
        <p>Henüz satın alınmış bir biletiniz bulunmamaktadır.</p>
    <?php else: ?>
        <div class="ticket-list">
            <table>
                <thead>
                    <tr>
                        <th>Firma</th>
                        <th>Güzergah</th>
                        <th>Tarih/Saat</th>
                        <th>Koltuk</th>
                        <th>Fiyat</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($my_tickets as $ticket): 
                        $is_cancelled = $ticket['is_cancelled'];
                        $departure_datetime_str = $ticket['trip_date'] . ' ' . $ticket['departure_time'];
                        $departure_timestamp = strtotime($departure_datetime_str);
                        $can_cancel = !$is_cancelled && (time() < ($departure_timestamp - (60 * 60)));
                    ?>
                        <tr class="<?php echo $is_cancelled ? 'cancelled' : ''; ?>">
                            <td><?php echo htmlspecialchars($ticket['firma_name']); ?></td>
                            <td><?php echo htmlspecialchars($ticket['departure_city']); ?> → <?php echo htmlspecialchars($ticket['arrival_city']); ?></td>
                            <td><?php echo htmlspecialchars($ticket['trip_date']) . ' ' . htmlspecialchars($ticket['departure_time']); ?></td>
                            <td><strong>#<?php echo htmlspecialchars($ticket['seat_number']); ?></strong></td>
                            <td><?php echo number_format($ticket['purchase_price'], 2); ?> TL</td>
                            <td><?php echo $is_cancelled ? '<span style="color:#ff6666;">İPTAL EDİLDİ</span>' : 'AKTİF'; ?></td>
                            <td>
                                <a href="pdf_bilet_indirme.php?ticket_id=<?php echo $ticket['ticket_id']; ?>">[PDF İndir]</a>
                                
                                <?php if ($can_cancel): ?>
                                    | <a href="hesabim.php?action=cancel&ticket_id=<?php echo $ticket['ticket_id']; ?>" 
                                        onclick="return confirm('Bu bileti iptal etmek istediğinizden emin misiniz? Bilet ücreti hesabınıza iade edilecektir.');"
                                        style="color: #ff6666;">[İptal Et]</a>
                                <?php elseif (!$is_cancelled): ?>
                                    | <span style="color: gray;" title="Kalkışa son 1 saatten az kaldı.">İptal Süresi Doldu</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>


    </script>
</body>
</html>
