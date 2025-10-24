<?php
session_start();

// yetki kontrolu
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'firma_admin') {
    header("Location: login.php?message=" . urlencode("Sanırım yanlış yerdesiniz!!!"));
    exit();
}

$database_file = 'database.sqlite';
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

try {
    $db = new PDO("sqlite:$database_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // oturumdaki kullanicinin firma idsini dbden cekme
    $stmt_firma = $db->prepare("SELECT firma_id, (SELECT name FROM firms WHERE id = users.firma_id) as firma_name FROM users WHERE id = ?");
    $stmt_firma->execute([$user_id]);
    $user_data = $stmt_firma->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data || empty($user_data['firma_id'])) {
        throw new Exception("Firma bilgisi bulunamadı. Lütfen yöneticinizle iletişime geçin.");
    }
    
    $firma_id = $user_data['firma_id'];
    $firma_name = $user_data['firma_name'];

    // CRUD -create read update delete- kismi

    // sefer silme
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['trip_id'])) {
        $trip_id_to_delete = $_GET['trip_id'];
        $stmt_check_tickets = $db->prepare("SELECT COUNT(*) FROM tickets WHERE trip_id = ? AND is_cancelled = 0");
        $stmt_check_tickets->execute([$trip_id_to_delete]);
        $active_tickets = $stmt_check_tickets->fetchColumn();
        if ($active_tickets > 0) { // eger aktif bilet varsa seferi silmeye izin vermeyecek
            $error = "Bu seferin satılmış (" . $active_tickets . " adet) aktif bileti bulunduğu için silinemez.";
        } else {
        // sadece kendi firmasinin seferlerini silebilmesi icin
        $stmt_delete = $db->prepare("DELETE FROM trips WHERE id = ? AND firma_id = ?");
        $stmt_delete->execute([$trip_id_to_delete, $firma_id]);
        
        if ($stmt_delete->rowCount() > 0) {
            $success = "Sefer başarıyla silindi.";
        } else {
            $error = "Sefer silinemedi veya yetkiniz olmayan bir sefere erişmeye çalıştınız.";
        }
    }
}
    // sefer ekleme ve duzenleme
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_trip'])) {
        $trip_id = $_POST['trip_id'] ?? null;
        $departure_city = trim($_POST['departure_city']);
        $arrival_city = trim($_POST['arrival_city']);
        $trip_date = trim($_POST['trip_date']);
        $departure_time = trim($_POST['departure_time']);
        $price = floatval($_POST['price']);
        $total_seats = intval($_POST['total_seats']);
        
    
        if (empty($departure_city) || empty($arrival_city) || empty($trip_date) || empty($departure_time) || $price <= 0 || $total_seats <= 0) {
            $error = "Lütfen tüm alanları doğru şekilde doldurun.";
        } else {
            if (empty($trip_id)) {
                // ekleme islemi
                $sql = "INSERT INTO trips (firma_id, departure_city, arrival_city, trip_date, departure_time, price, total_seats) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$firma_id, $departure_city, $arrival_city, $trip_date, $departure_time, $price, $total_seats]);
                $success = "Yeni sefer başarıyla eklendi.";
            } else {
                // duzenleme islemi 
                $sql = "UPDATE trips SET departure_city = ?, arrival_city = ?, trip_date = ?, departure_time = ?, price = ?, total_seats = ? WHERE id = ? AND firma_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$departure_city, $arrival_city, $trip_date, $departure_time, $price, $total_seats, $trip_id, $firma_id]);
                $success = "Sefer başarıyla güncellendi.";
            }
        }
    }

    //  duzenleme icin sefer bilgisi cekme
    $trip_to_edit = null;
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['trip_id'])) {
        $trip_id_to_edit = $_GET['trip_id'];
        


        $stmt_edit = $db->prepare("SELECT * FROM trips WHERE id = ? AND firma_id = ?");
        $stmt_edit->execute([$trip_id_to_edit, $firma_id]);
        $trip_to_edit = $stmt_edit->fetch(PDO::FETCH_ASSOC);
        
        if (!$trip_to_edit) {
             $error = "Düzenlemek istediğiniz sefer bulunamadı veya yetkiniz yoktur.";
        }
    }

    // firma seferlerini listeleme
    $stmt_trips = $db->prepare("SELECT * FROM trips WHERE firma_id = ? ORDER BY trip_date DESC, departure_time ASC");
    $stmt_trips->execute([$firma_id]);
    $my_trips = $stmt_trips->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
}

// form verilerini doldurmak icin helper degiskenler
$form_title = $trip_to_edit ? "Sefer Düzenle" : "Yeni Sefer Ekle";
$current_trip_id = $trip_to_edit['id'] ?? '';
$current_dep_city = $trip_to_edit['departure_city'] ?? '';
$current_arr_city = $trip_to_edit['arrival_city'] ?? '';
$current_date = $trip_to_edit['trip_date'] ?? date('Y-m-d');
$current_time = $trip_to_edit['departure_time'] ?? '';
$current_price = $trip_to_edit['price'] ?? '';
$current_seats = $trip_to_edit['total_seats'] ?? '';
?>




<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($firma_name); ?> - Firma Admin Paneli</title>
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

        form {
            background: rgba(0, 20, 0, 0.8);
            padding: 25px;
            border-radius: 10px;
            border: 1px solid #00ff55;
            box-shadow: 0 0 25px rgba(0,255,100,0.1);
            margin-bottom: 40px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #00ff88;
        }

        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #00ff55;
            background-color: #001a00;
            color: #00ff88;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        input:focus {
            outline: none;
            box-shadow: 0 0 8px #00ff66;
            border-color: #00ffaa;
            background-color: #002200;
        }

        button {
            background-color: #00ff55;
            border: none;
            color: #000;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            padding: 10px 15px;
            transition: all 0.3s;
        }

        button:hover {
            background-color: #00ffaa;
            color: #000;
            box-shadow: 0 0 10px #00ff99;
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

        hr {
            border: none;
            height: 1px;
            background: linear-gradient(to right, transparent, #00ff55, transparent);
            margin: 40px 0;
        }

    
    </style>
</head>
<body>
   

    <div class="panel-header">
        <h1> Firma Admin Paneli - <?php echo htmlspecialchars($firma_name); ?></h1>
      
        <div>
            <a href="index.php">Ana Sayfa</a> | <a href="logout.php">Çıkış Yap</a>
        </div>
    </div>

    <?php 
    if (!empty($error)) echo "<div class='message error'>$error</div>";
    if (!empty($success)) echo "<div class='message success'>$success</div>";
    ?>

    <h2> <?php echo $form_title; ?></h2>
    <form method="POST" action="firma_admin_panel.php">
        <input type="hidden" name="trip_id" value="<?php echo htmlspecialchars($current_trip_id); ?>">

        <label>Kalkış Şehri:</label>
        <input type="text" name="departure_city" value="<?php echo htmlspecialchars($current_dep_city); ?>" required>

        <label>Varış Şehri:</label>
        <input type="text" name="arrival_city" value="<?php echo htmlspecialchars($current_arr_city); ?>" required>

        <label>Tarih:</label>
        <input type="date" name="trip_date" value="<?php echo htmlspecialchars($current_date); ?>" required>

        <label>Saat:</label>
        <input type="time" name="departure_time" value="<?php echo htmlspecialchars($current_time); ?>" required>

        <label>Fiyat (TL):</label>
        <input type="number" name="price" step="0.01" min="0.01" value="<?php echo htmlspecialchars($current_price); ?>" required>

        <label>Koltuk Sayısı:</label>
        <input type="number" name="total_seats" min="1" value="<?php echo htmlspecialchars($current_seats); ?>" required>

        <button type="submit" name="submit_trip">
            <?php echo $trip_to_edit ? "Seferi Güncelle" : "Sefer Ekle"; ?>
        </button>
        <?php if ($trip_to_edit): ?>
            <a href="firma_admin_panel.php">Vazgeç</a>
        <?php endif; ?>
    </form>

    <hr>

    <h2> Mevcut Seferler (<?php echo htmlspecialchars($firma_name); ?>)</h2>
    <?php if (empty($my_trips)): ?>
        <p>Henüz kayıtlı bir seferiniz bulunmamaktadır.</p>
    <?php else: ?>
        <div class="trip-list">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Güzergah</th>
                        <th>Tarih</th>
                        <th>Saat</th>
                        <th>Fiyat</th>
                        <th>Koltuk</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($my_trips as $trip): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($trip['id']); ?></td>
                            <td><?php echo htmlspecialchars($trip['departure_city']); ?> → <?php echo htmlspecialchars($trip['arrival_city']); ?></td>
                            <td><?php echo htmlspecialchars($trip['trip_date']); ?></td>
                            <td><?php echo htmlspecialchars($trip['departure_time']); ?></td>
                            <td><?php echo number_format($trip['price'], 2); ?> TL</td>
                            <td><?php echo htmlspecialchars($trip['total_seats']); ?></td>
                            <td>
                                <a href="firma_admin_panel.php?action=edit&trip_id=<?php echo $trip['id']; ?>">[DÜZENLE]</a> 
                                <a href="firma_admin_panel.php?action=delete&trip_id=<?php echo $trip['id']; ?>" onclick="return confirm('Bu seferi silmek istediğinizden emin misiniz?');">[SİL]</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</body>
</html>
