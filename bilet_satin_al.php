<?php
session_start();


// sadece giris yapmıs kullanicilar bilet alacak
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    // giris yapmamissa logine yonlendir
    header("Location: login.php?message=Lütfen Giriş Yapın");
    exit();
}

// db baglantisi
$database_file = 'database.sqlite';
$user_id = $_SESSION['user_id'];
$trip_id = $_GET['trip_id'] ?? null;

try {
    $db = new PDO("sqlite:$database_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (empty($trip_id) || !is_numeric($trip_id)) {
        throw new Exception("Geçersiz Sefer ID'si.");
    }

    // dbden kullanici bakiyesini ve sefer detaylarini cek

    $stmt_user = $db->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
    $user_balance = $user['balance'] ?? 800;

      $stmt_trip = $db->prepare("SELECT t.*, f.name as firma_name 
                               FROM trips t 
                               JOIN firms f ON t.firma_id = f.id 
                               WHERE t.id = ?");
    $stmt_trip->execute([$trip_id]);
    $trip = $stmt_trip->fetch(PDO::FETCH_ASSOC);
    if (!$trip) {
        throw new Exception("Sefer bulunamadı.");
    }
    
    // sefer saatinin gecip gecmedigini kontrol et
    $departure_datetime_str = $trip['trip_date'] . ' ' . $trip['departure_time'];
    $departure_timestamp = strtotime($departure_datetime_str);
    $current_timestamp = time();

    // 5 dakikalik bir sapma payi
    if ($departure_timestamp <= ($current_timestamp + (5 * 60))) { 
        throw new Exception("Bu seferin kalkış saati geçtiği için bilet satın alınamaz.");
    }
   
    
   
    $base_price = $trip['price'];
    $total_seats = $trip['total_seats'];

    // dolu koltuk nolari is_cancelled=0 olanlari yani dbden cekme
    $stmt_seats = $db->prepare("SELECT seat_number FROM tickets WHERE trip_id = ? AND is_cancelled = 0");
    $stmt_seats->execute([$trip_id]);
    $occupied_seats = $stmt_seats->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    // hata olursa index sayfasina
    header("Location: index.php?error=" . urlencode($e->getMessage()));
    exit();
}

$error = '';
$success = '';
$final_price = $base_price;
$applied_coupon = null;



// kupon kontrolleri
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apply_coupon'])) {
    $coupon_code = strtoupper(trim($_POST['coupon_code'] ?? ''));

    if (!empty($coupon_code)) {
        $stmt_coupon = $db->prepare("SELECT * FROM coupons WHERE code = ? AND expiry_date >= DATE('now') AND usage_limit > used_count");
        $stmt_coupon->execute([$coupon_code]);
        $coupon = $stmt_coupon->fetch(PDO::FETCH_ASSOC);

        if ($coupon) {
            $discount_amount = $base_price * $coupon['discount_rate'];
            $final_price = $base_price - $discount_amount;
            $applied_coupon = $coupon;
            $success = "Kupon başarıyla uygulandı! İndirim: " . number_format($discount_amount, 2) . " TL. Yeni Fiyat: " . number_format($final_price, 2) . " TL.";
        } else {
            $error = "Geçersiz, süresi dolmuş veya kullanım limiti dolmuş kupon kodu.";
        }
    }
}

// bilet alma islemleri
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['purchase'])) {
    $selected_seat = $_POST['seat_number'] ?? null;
    $final_price_on_purchase = $_POST['final_price'] ?? $base_price; // kupondan sonra fiyat formdan alinir

    

    if (empty($selected_seat) || !is_numeric($selected_seat) || $selected_seat < 1 || $selected_seat > $total_seats) {
        $error = "Lütfen geçerli bir koltuk numarası seçin.";
    } elseif (in_array($selected_seat, $occupied_seats)) {
        $error = "Seçtiğiniz koltuk ne yazık ki dolu.";
    } elseif ($user_balance < $final_price_on_purchase) {
        $error = "Yetersiz sanal kredi bakiyesi! Mevcut: " . number_format($user_balance, 2) . " TL. Gerekli: " . number_format($final_price_on_purchase, 2) . " TL.";
    } else {
        //islemler basariliysa
        $db->beginTransaction();
        try {
            // bilet kaydi
            $coupon_id = $applied_coupon['id'] ?? null;

            $stmt_ticket = $db->prepare("INSERT INTO tickets (user_id, trip_id, seat_number, purchase_price) VALUES (?, ?, ?, ?)");
            $stmt_ticket->execute([$user_id, $trip_id, $selected_seat, $final_price_on_purchase]);
            $ticket_id = $db->lastInsertId();

            // bakiye guncelleme
            $new_balance = $user_balance - $final_price_on_purchase;
            $stmt_balance = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
            $stmt_balance->execute([$new_balance, $user_id]);

        

            if ($coupon_id) {
                // kupon kullanim kaydi
                $stmt_usage = $db->prepare("INSERT INTO coupon_usages (user_id, coupon_id, ticket_id) VALUES (?, ?, ?)");
                $stmt_usage->execute([$user_id, $coupon_id, $ticket_id]);
                
                

                $stmt_coupon_update = $db->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?");
                $stmt_coupon_update->execute([$coupon_id]);
            }

            $db->commit();
            
            
            header("Location: hesabim.php?success=" . urlencode("Biletiniz başarıyla satın alındı! Koltuk: $selected_seat"));
            exit();

        } catch (Exception $e) {
            $db->rollBack();
            $error = "Bilet satın alma sırasında bir hata oluştu: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Bilet Satın Alın - <?php echo htmlspecialchars($trip['departure_city']); ?> &rarr; <?php echo htmlspecialchars($trip['arrival_city']); ?></title>
    <style>
        body {
            font-family: "Consolas", "Courier New", monospace;
            background: radial-gradient(circle at center, #000000 60%, #001a00 100%);
            color: #00ff99;
            margin: 0;
            padding: 20px;
        }

        h1, h2, h3 {
            color: #00ffaa;
            text-shadow: 0 0 8px #00ff99;
            text-align: center;
            margin-top: 15px;
        }

        
        form {
            background: rgba(0, 20, 0, 0.8);
            border: 1px solid #00ff99;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0, 255, 100, 0.3);
            padding: 25px;
            width: 90%;
            max-width: 600px;
            margin: 25px auto;
            color: #00ffaa;
        }

      
        p {
            color: #00ffb3;
            text-align: center;
            line-height: 1.6;
        }
        
        strong {
            color: #00ffcc;
        }

        /* kupon girme yeri */
        input[type="text"] {
            padding: 8px;
            background-color: #001a00;
            border: 1px solid #00ff99;
            color: #00ff99;
            border-radius: 6px;
            font-family: "Consolas", monospace;
            margin-right: 10px;
        }

        /* butonlar */
        button {
            background-color: #00ff99;
            color: #000;
            border: none;
            font-weight: bold;
            border-radius: 8px;
            padding: 8px 15px;
            cursor: pointer;
            font-family: "Consolas", monospace;
            transition: all 0.3s;
        }

        button:hover {
            background-color: #00e68a;
            box-shadow: 0 0 10px #00ff99;
        }

        .error {
            color: #ff4d4d;
            border: 1px solid #ff4d4d;
            background: rgba(255, 0, 0, 0.1);
            padding: 10px;
            border-radius: 6px;
            text-align: center;
            margin: 10px auto;
            max-width: 600px;
        }
        .success {
            color: #00ffaa;
            border: 1px solid #00ffaa;
            background: rgba(0, 255, 100, 0.1);
            padding: 10px;
            border-radius: 6px;
            text-align: center;
            margin: 10px auto;
            max-width: 600px;
        }


        /* koltuklar*/
        .seat-map { 
            display: flex; 
            flex-wrap: wrap; 
            width: 300px; 
            margin: 20px auto; 
            background: #002200;
            border: 1px solid #00ff99;
            padding: 10px;
            border-radius: 8px;
        }
        .seat { 
            width: 40px; 
            height: 40px; 
            margin: 5px; 
            border: 1px solid #00ff99; 
            text-align: center; 
            line-height: 40px; 
            cursor: pointer; 
            border-radius: 4px;
            transition: background-color 0.1s;
        }
        .available { 
            background-color: #004400; 
            color: #00ffcc;
        }
        .available:hover {
             background-color: #006600; 
        }
        .occupied { 
            background-color: #440000; 
            cursor: not-allowed; 
            color: #ff4d4d;
            text-decoration: line-through;
        }
        .selected { 
            background-color: #00ffaa; 
            border: 2px solid #00ffaa;
            color: #000;
            box-shadow: 0 0 10px #00ffaa;
        }
        
       
        #display_price {
            color: #ff0000ff; 
           
        }
        /* ust bar */
        .top-menu {
            text-align: right;
            margin-bottom: 20px;
        }
        .top-menu a {
            color: #ff0000ff; 
            margin-left: 15px;
            text-decoration: none;
            font-weight: bold;
            text-shadow: 0 0 5px #00000080;
        }
        .top-menu a:hover {
            color: #fff;
            text-shadow: 0 0 10px #fff;
        }
    </style>
</head>
<body>
    <div class="top-menu">
        <a href="index.php">Ana Sayfaya Dön</a>
        <a href="logout.php">Çıkış Yap</a>
    </div>
    <h1>Yavuzlar Bilet</h1>
    
    <p style='color: #00ffcc;'>Mevcut Bakiyeniz: <?php echo number_format($user_balance, 2); ?> TL</p>
    
    <?php 
    if (!empty($error)) { echo "<p class='error'>$error</p>"; }
    if (!empty($success)) { echo "<p class='success'>$success</p>"; }
    ?>

    <form method="POST" action="bilet_satin_al.php?trip_id=<?php echo $trip_id; ?>">
        
        <h2>Sefer Bilgileri</h2>
        <div style="text-align: center; line-height: 1.8;">
            <strong>Firma:</strong> <?php echo htmlspecialchars($trip['firma_name']); ?><br>
            <strong>Güzergah:</strong> <?php echo htmlspecialchars($trip['departure_city']); ?> &rarr; <?php echo htmlspecialchars($trip['arrival_city']); ?><br>
            <strong>Tarih/Saat:</strong> <?php echo htmlspecialchars($trip['trip_date']); ?> / <?php echo htmlspecialchars($trip['departure_time']); ?><br>
            <strong>Baz Fiyat:</strong> <?php echo number_format($base_price, 2); ?> TL<br>
            <strong>Koltuk Sayısı:</strong> <?php echo $total_seats; ?>
        </div>
        <hr style="border-color: #003300; margin: 20px 0;">

        <h3>Kupon Kodu Uygula</h3>
        <div style="text-align: center;">
            <label for="coupon_code" style="display: inline-block; margin-right: 10px;">Kupon Kodu:</label>
            <input type="text" id="coupon_code" name="coupon_code" value="<?php echo htmlspecialchars($_POST['coupon_code'] ?? ''); ?>" style="width: 150px;">
            <button type="submit" name="apply_coupon">Uygula</button>
        </div>
        <hr style="border-color: #003300; margin: 20px 0;">

        <h3>Koltuk Seçimi</h3>
        <input type="hidden" name="final_price" id="final_price_input" value="<?php echo number_format($final_price, 2, '.', ''); ?>">
        <input type="hidden" name="seat_number" id="selected_seat_input" required>
        
        <div class="seat-map">
            <?php for ($i = 1; $i <= $total_seats; $i++): ?>
                <?php 
                    
                    $is_occupied = in_array((string)$i, array_map('strval', $occupied_seats));
                    $class = $is_occupied ? 'occupied' : 'available';
                ?>
                <div class="seat <?php echo $class; ?>" 
                     data-seat="<?php echo $i; ?>" 
                     <?php echo $is_occupied ? 'title="Dolu Koltuk"' : 'onclick="selectSeat(' . $i . ')"'; ?>>
                    <?php echo $i; ?>
                </div>
            <?php endfor; ?>
        </div>
        
        <p>Seçilen Koltuk: <strong id="display_seat" style="color: #ff0000ff;">Seçiniz</strong></p>
        
        <hr style="border-color: #003300; margin: 20px 0;">
        
        <h3>Ödeme Özeti</h3>
        <p style="font-size: 1.1em;">
            Son Ödenecek Fiyat: <strong style="color: darkred; font-size: 1.2em;">
                <span id="display_price"><?php echo number_format($final_price, 2); ?></span> TL
            </strong>
        </p>

        <button type="submit" name="purchase" id="purchase_button" disabled style="width: 100%;">Bilet Satın Al ve Onayla</button>
    </form>

    <script>
        const occupiedSeats = <?php echo json_encode(array_map('strval', $occupied_seats)); ?>;
        const selectedSeatInput = document.getElementById('selected_seat_input');
        const displaySeat = document.getElementById('display_seat');
        const purchaseButton = document.getElementById('purchase_button');
        const finalPriceInput = document.getElementById('final_price_input');
        const displayPrice = document.getElementById('display_price');

        // kupon uygulandiktan sonraki fiyati guncellemek icin
        displayPrice.textContent = parseFloat(finalPriceInput.value).toFixed(2).replace('.', ',').replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');

        function selectSeat(seatNumber) {
            // dolu koltuklari secmeyi engelle
            if (occupiedSeats.includes(String(seatNumber))) return; 

            // onceki secimi kaldir
            document.querySelectorAll('.seat.selected').forEach(s => {
                s.classList.remove('selected');
            });

            // yeni secimi uygula
            const newSelection = document.querySelector(`.seat[data-seat="${seatNumber}"]`);
            if (newSelection) {
                newSelection.classList.add('selected');
                selectedSeatInput.value = seatNumber;
                displaySeat.textContent = seatNumber;
                purchaseButton.disabled = false; // koltuk secilince butonu aktif et
            }
        }
        
        // baslangicta butonu devre disi birak
        purchaseButton.disabled = true;

    </script>
</body>
</html>