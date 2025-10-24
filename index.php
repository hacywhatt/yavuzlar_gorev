<?php
session_start();

// kullanicinin giris yapip yapmadigini rolunu kontorl icin session degisikliklerini kullaniyor
$is_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['role'] ?? 'Ziyaretçi'; // varsayilan olarak ziyaretci rolu atanir

//  db baglantisi
$database_file = 'database.sqlite';

try { // pdo -php data objects- sql sorgularini calistirabilmeye ve veri okumaya olanak saglar sql sorgularini manipule etmez veya db tarafindan desteklenmeyen ozellikleri benzestirme yaparak desteklemeye calismaz
    $db = new PDO("sqlite:$database_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// trips tablosundan mevcut sehirleri ceken sql kodu
$cities = [];
$stmt_cities = $db->query("SELECT DISTINCT departure_city AS city FROM trips UNION SELECT DISTINCT arrival_city FROM trips ORDER BY city");
$cities = $stmt_cities->fetchAll(PDO::FETCH_COLUMN);

$trips = [];
$search_performed = false;

// sefer arama
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search'])) {
    $departure = trim($_POST['departure'] ?? '');
    $arrival = trim($_POST['arrival'] ?? '');
    $date = trim($_POST['trip_date'] ?? '');
    
    $search_performed = true;
    $where_clauses = [];
    $params = [];

    $sql = "SELECT t.*, f.name as firma_name 
            FROM trips t 
            JOIN firms f ON t.firma_id = f.id 
            WHERE 1=1"; // baslangic kosulu

    if (!empty($departure)) {
        $where_clauses[] = "departure_city = ?";
        $params[] = $departure;
    }
    if (!empty($arrival)) {
        $where_clauses[] = "arrival_city = ?";
        $params[] = $arrival;
    }
    if (!empty($date)) {
        $where_clauses[] = "trip_date = ?";
        $params[] = $date;
    }

    if (!empty($where_clauses)) {
        $sql .= " AND " . implode(" AND ", $where_clauses);
    }
    $sql .= " AND (t.trip_date || ' ' || t.departure_time) > DATETIME('now', '+5 minutes')";
    $sql .= " ORDER BY trip_date ASC, departure_time ASC";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $search_error = "Sefer arama hatası: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yavuzlar Bilet</title>
    <style>
        body {
            font-family: "Consolas", "Courier New", monospace;
            background: radial-gradient(circle at center, #000000 60%, #001a00 100%);
            color: #00ff99;
            margin: 0;
            padding: 20px;
            overflow-x: hidden;
        }

        

        /* ust menu */
        .header-menu {
            position: relative;
            z-index: 1;
            margin-bottom: 25px;
            padding: 10px;
            background: rgba(0, 30, 0, 0.7);
            border: 1px solid #00ff99;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,255,100,0.3);
            text-align: center;
        }

        .header-menu a, .header-menu span {
            color: #00ff99;
            margin: 0 10px;
            text-decoration: none;
            font-weight: bold;
        }

        .header-menu a:hover {
            text-shadow: 0 0 10px #00ff99;
        }

        h1, h2 {
            text-align: center;
            color: #00ff99;
            text-shadow: 0 0 8px #00ff99;
            margin-top: 20px;
            z-index: 1;
        }

        form {
            position: relative;
            z-index: 1;
            background: rgba(0, 20, 0, 0.8);
            border: 1px solid #00ff99;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,255,100,0.3);
            padding: 25px;
            width: 90%;
            max-width: 700px;
            margin: 25px auto;
            color: #00ffaa;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 6px;
            color: #00ffaa;
        }

        select, input[type="date"] {
            width: 100%;
            padding: 8px;
            background-color: #001a00;
            border: 1px solid #00ff99;
            color: #00ff99;
            border-radius: 8px;
            margin-bottom: 15px;
            font-family: "Consolas", monospace;
        }

        select:focus, input:focus {
            outline: none;
            border-color: #00ffaa;
            box-shadow: 0 0 10px #00ff99;
        }

        button {
            background-color: #00ff99;
            color: #000;
            border: none;
            font-weight: bold;
            border-radius: 8px;
            padding: 10px 20px;
            cursor: pointer;
            font-family: "Consolas", monospace;
            transition: all 0.3s;
        }

        button:hover {
            background-color: #00e68a;
            box-shadow: 0 0 15px #00ff99;
        }

        /* seferler icin */
        .trip-card {
            background: rgba(0, 25, 0, 0.85);
            border: 1px solid #00ff99;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,255,100,0.3);
            padding: 15px;
            margin: 15px auto;
            width: 90%;
            max-width: 700px;
            position: relative;
            z-index: 1;
        }

        .trip-card h3 {
            margin-top: 0;
            color: #00ffaa;
            text-shadow: 0 0 8px #00ff99;
        }

        .trip-card p {
            color: #00ffb3;
            line-height: 1.6;
        }

        .trip-card a {
            color: #00ff99;
            font-weight: bold;
            text-decoration: none;
            margin-right: 15px;
        }

        .trip-card a:hover {
            text-shadow: 0 0 8px #00ff99;
        }

        p {
            text-align: center;
        }

        .error {
            color: #ff7675;
            text-align: center;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>
</head>
<body>
 

    <div class="header-menu">
        <?php if (!$is_logged_in): // ziyaretci ?>
            <a href="login.php">Giriş Yapın</a> | <a href="register.php">Kayıt Olun</a>
        <?php else: // giris yapmis kullanici ?>
            <span>Hoş geldiniz, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
            <a href="hesabim.php">Hesabım</a>
            <?php if ($user_role === 'firma_admin'): ?>
                <a href="firma_admin_panel.php">Firma Admin Paneli</a>
            <?php elseif ($user_role === 'admin'): ?>
                <a href="admin_panel.php">Admin Paneli</a>
            <?php endif; ?>
            <a href="logout.php">Çıkış Yapın</a>
        <?php endif; ?>
    </div>
    
    <h1> Otobüs Seferi Arayın</h1>
    
    <form method="POST" action="index.php">
        <input type="hidden" name="search" value="1">
        
        <label for="departure">Nereden:</label>
        <select id="departure" name="departure" required>
            <option value="">Kalkış Noktası Seçin</option>
            <?php foreach ($cities as $city): ?>
                <option value="<?php echo htmlspecialchars($city); ?>" 
                        <?php echo (isset($_POST['departure']) && $_POST['departure'] == $city) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($city); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <label for="arrival">Nereye:</label>
        <select id="arrival" name="arrival" required>
            <option value="">Varış Noktası Seçin</option>
             <?php foreach ($cities as $city): ?>
                <option value="<?php echo htmlspecialchars($city); ?>" 
                        <?php echo (isset($_POST['arrival']) && $_POST['arrival'] == $city) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($city); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <label for="trip_date">Tarih:</label>
        <input type="date" id="trip_date" name="trip_date" required 
               value="<?php echo htmlspecialchars($_POST['trip_date'] ?? date('Y-m-d')); ?>">
        
        <button type="submit">Sefer Ara</button>
    </form>
    
    <h2>Sefer Sonuçları</h2>

    <?php if (isset($search_error)): ?>
        <p class="error"><?php echo $search_error; ?></p>
    <?php elseif ($search_performed): ?>
        <?php if (!empty($trips)): ?>
            <?php 
            date_default_timezone_set('Europe/Istanbul');
            $now_plus_5_min = time() + (5 * 60); 
            $future_trips = [];
            
            foreach ($trips as $trip) {
                $departure_datetime_str = $trip['trip_date'] . ' ' . $trip['departure_time'];
                $departure_timestamp = strtotime($departure_datetime_str);
                
                if ($departure_timestamp > $now_plus_5_min) {
                    $future_trips[] = $trip;
                }
            }
            ?>
            
            <?php if (!empty($future_trips)): ?>
                <?php foreach ($future_trips as $trip): ?>
                    <div class="trip-card">
                        <h3><?php echo htmlspecialchars($trip['departure_city']); ?> → <?php echo htmlspecialchars($trip['arrival_city']); ?></h3>
                        <p>
                            <strong>Firma:</strong> <?php echo htmlspecialchars($trip['firma_name']); ?><br>
                            <strong>Tarih:</strong> <?php echo htmlspecialchars($trip['trip_date']); ?><br>
                            <strong>Kalkış Saati:</strong> <?php echo htmlspecialchars($trip['departure_time']); ?><br>
                            <strong>Fiyat:</strong> <?php echo number_format($trip['price'], 2); ?> TL<br>
                            <strong>Boş Koltuk:</strong> <?php echo htmlspecialchars($trip['total_seats']); ?>
                        </p>
                        <a href="sefer_detay.php?trip_id=<?php echo $trip['id']; ?>">Detayları Gör</a>
                        
                        <?php if ($is_logged_in && $user_role === 'user'): ?>
                            <a href="bilet_satin_al.php?trip_id=<?php echo $trip['id']; ?>">Bilet Satın Al</a>
                        <?php elseif (!$is_logged_in): ?>
                            <span style="color: gray;">(Bilet almak için <a href="login.php">Giriş Yapın</a>)</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Belirtilen kriterlere uygun sefer bulunamadı.</p>
            <?php endif; ?>
        <?php else: ?>
            <p>Belirtilen kriterlere uygun sefer bulunamadı.</p>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
