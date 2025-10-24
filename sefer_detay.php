<?php
session_start();


$is_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['role'] ?? 'ziyaretci'; // varsayılan olarak ziyaretci

 
$database_file = 'database.sqlite';
$trip_id = $_GET['trip_id'] ?? null;
$error = '';

try {
    $db = new PDO("sqlite:$database_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (empty($trip_id) || !is_numeric($trip_id)) {
        throw new Exception("Geçersiz Sefer ID'si belirtildi.");
    }

    //   sefer detayini dbden cekme
    $stmt_trip = $db->prepare("SELECT t.*, f.name as firma_name 
                               FROM trips t 
                               JOIN firms f ON t.firma_id = f.id 
                               WHERE t.id = ?");
    $stmt_trip->execute([$trip_id]);
    $trip = $stmt_trip->fetch(PDO::FETCH_ASSOC);

    if (!$trip) {
        throw new Exception("Belirtilen sefer bulunamadı.");
    }
    
    // dolu koltuklari cekme is_cancelled=0 olanlari yani
    $stmt_seats = $db->prepare("SELECT seat_number FROM tickets WHERE trip_id = ? AND is_cancelled = 0");
    $stmt_seats->execute([$trip_id]);
    $occupied_seats = $stmt_seats->fetchAll(PDO::FETCH_COLUMN);

    $available_seats_count = $trip['total_seats'] - count($occupied_seats);

} catch (Exception $e) {
    $error = "Hata: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Sefer Detayları</title>
    <style>
        
        body {
            background-color: #000;
            color: #00ff99;
            font-family: 'Courier New', monospace;
            margin: 20px;
            animation: matrix-bg 5s infinite alternate;
        }

        @keyframes matrix-bg {
            from { background-color: #000; }
            to { background-color: #001100; }
        }

        a {
            color: #00ff99;
            text-decoration: none;
            transition: 0.3s;
        }
        a:hover {
            color: #00ffaa;
            text-shadow: 0 0 8px #00ff99;
        }

        h1, h2, h3 {
            color: #00ff99;
            text-shadow: 0 0 10px #00ff99;
        }

        hr {
            border: 0;
            border-top: 1px solid #00ff99;
            margin: 25px 0;
        }

        strong {
            color: #00ffaa;
        }

       
        .seat-map {
            display: flex;
            flex-wrap: wrap;
            width: 350px;
            margin-top: 20px;
            border: 1px solid #00ff99;
            padding: 10px;
            border-radius: 10px;
            box-shadow: 0 0 15px #00ff99;
            background-color: rgba(0, 30, 0, 0.8);
        }

        .seat {
            width: 40px;
            height: 40px;
            margin: 5px;
            text-align: center;
            line-height: 40px;
            font-size: 14px;
            border: 1px solid #00ff99;
            border-radius: 5px;
            box-shadow: 0 0 5px #00ff99;
            transition: 0.3s;
        }

        .seat.available {
            background-color: #002200;
            color: #00ff99;
        }

        .seat.occupied {
            background-color: #330000;
            color: #ff4444;
            font-weight: bold;
            box-shadow: 0 0 5px #ff0000;
        }

        .seat.available:hover {
            background-color: #004400;
            box-shadow: 0 0 8px #00ff99;
        }

       
        .legend {
            margin-top: 15px;
        }

        .legend div {
            display: inline-block;
            margin-right: 20px;
            color: #00ff99;
        }

        .legend .seat {
            width: 25px;
            height: 25px;
            line-height: 25px;
            font-size: 12px;
            box-shadow: none;
        }

       
        .btn {
            display: inline-block;
            background-color: #00ff99;
            color: #000;
            font-weight: bold;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            box-shadow: 0 0 10px #00ff99;
            transition: 0.3s;
        }

        .btn:hover {
            background-color: #00ffaa;
            box-shadow: 0 0 15px #00ff99;
        }


        p[style*="color: red"] {
            color: #ff4444 !important;
            text-shadow: 0 0 5px #ff0000;
        }
        p[style*="color: gray"] {
            color: #00cc88 !important;
            opacity: 0.8;
        }
        p[style*="color: orange"] {
            color: #ffaa00 !important;
            text-shadow: 0 0 5px #ffaa00;
        }
    </style>
</head>
<body>
    <p><a href="index.php">⟵ Ana Sayfa</a></p>
    <h1> Sefer Detayları</h1>
    
    <?php 
    if (!empty($error)) { 
        echo "<p style='color: red;'>$error</p>"; 
        echo "<p><a href='index.php'>Sefer Listesine Dön</a></p>";
        exit(); 
    }
    ?>

    <h2><?php echo htmlspecialchars($trip['departure_city']); ?> ⟶ <?php echo htmlspecialchars($trip['arrival_city']); ?></h2>
    <p>
        <strong>Firma:</strong> <?php echo htmlspecialchars($trip['firma_name']); ?><br>
        <strong>Tarih:</strong> <?php echo htmlspecialchars($trip['trip_date']); ?><br>
        <strong>Kalkış Saati:</strong> <?php echo htmlspecialchars($trip['departure_time']); ?><br>
        <strong>Fiyat:</strong> <strong style="color: #00ffaa;"><?php echo number_format($trip['price'], 2); ?> TL</strong><br>
        <strong>Toplam Koltuk:</strong> <?php echo htmlspecialchars($trip['total_seats']); ?><br>
        <strong>Boş Koltuk:</strong> <strong style="color: #00ffcc;"><?php echo $available_seats_count; ?></strong>
    </p>

    <hr>
    

    
    <div class="seat-map">
        <?php for ($i = 1; $i <= $trip['total_seats']; $i++): ?>
            <?php 
                $is_occupied = in_array($i, $occupied_seats);
                $class = $is_occupied ? 'occupied' : 'available';
            ?>
            <div class="seat <?php echo $class; ?>" 
                 title="<?php echo $is_occupied ? 'Dolu' : 'Boş'; ?>">
                <?php echo $i; ?>
            </div>
        <?php endfor; ?>
    </div>

    <hr>

    <?php if ($is_logged_in && $user_role === 'user'): ?>
        <p>
            <a href="bilet_satin_al.php?trip_id=<?php echo $trip['id']; ?>" class="btn">
                Bilet Satın Al
            </a>
        </p>
    <?php else: ?>
        <p style="color: gray;">
            Bilet satın almak için lütfen <a href="login.php">Giriş Yapın</a>.
        </p>
        <?php if ($is_logged_in && $user_role !== 'user'): ?>
             <p style="color: orange;">(Not: Giriş yaptınız, ancak sadece 'User' rolüne sahip kullanıcılar bilet satın alabilir.)</p>
        <?php endif; ?>
    <?php endif; ?>

</body>
</html>
