<?php
session_start();


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?message=" . urlencode("Bu sayfaya erişim yetkiniz yoktur."));
    exit();
}

$database_file = 'database.sqlite';
$error = '';
$success = '';
$current_action = $_GET['tab'] ?? 'firms'; // hangi sayfa aktif diye kontrol 

try {
    $db = new PDO("sqlite:$database_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // dbdeki tum firma bilgileri cekme
    $stmt_firms_all = $db->query("SELECT id, name FROM firms ORDER BY name");
    $firms_list = $stmt_firms_all->fetchAll(PDO::FETCH_ASSOC);

    //crud kismi
    if ($current_action === 'firms') {
        // firma ekleme ve duzenleme 
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_firm'])) {
            $firm_id = $_POST['firm_id'] ?? null;
            $firm_name = trim($_POST['firm_name']);
            $logo_url = trim($_POST['logo_url'] ?? '');

            if (empty($firm_name)) {
                $error = "Firma adı boş bırakılamaz.";
            } else {
                if (empty($firm_id)) {
                    // ekleme
                    $sql = "INSERT INTO firms (name, logo_url) VALUES (?, ?)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$firm_name, $logo_url]);
                    $success = "Yeni firma başarıyla eklendi.";
                } else {
                    // duzenleme
                    $sql = "UPDATE firms SET name = ?, logo_url = ? WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$firm_name, $logo_url, $firm_id]);
                    $success = "Firma bilgileri güncellendi.";
                }
            }
        }
        
        // firma silme
        if (isset($_GET['action']) && $_GET['action'] === 'delete_firm' && isset($_GET['firm_id'])) {
     
            $stmt_delete = $db->prepare("DELETE FROM firms WHERE id = ?");
            $stmt_delete->execute([$_GET['firm_id']]);
            $success = "Firma başarıyla silindi.";
        }
    }

    // firma admini duzenleme
    if ($current_action === 'firma_admins') {
        // ekleme
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_firma_admin'])) {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $firma_id_to_assign = $_POST['firma_id'];

            
            if (empty($username) || empty($email) || empty($password) || empty($firma_id_to_assign)) {
                $error = "Lütfen tüm alanları doldurun ve bir firma seçin.";
            } elseif (strlen($password) < 6) {
                 $error = "Şifre en az 6 karakter olmalıdır.";
            } else {
                // sifreyi hashle ve firma admini roluyle tut
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $role = 'firma_admin';
                
                $sql = "INSERT INTO users (username, password, email, role, firma_id) VALUES (?, ?, ?, ?, ?)";
                try {
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$username, $hashed_password, $email, $role, $firma_id_to_assign]);
                    $success = "Yeni Firma Admin kullanıcı başarıyla oluşturuldu ve firmaya atandı.";
                } catch (PDOException $e) {
                    if ($e->getCode() == '23000') { 
                        $error = "Bu kullanıcı adı veya e-posta zaten kullanımda.";
                    } else {
                        $error = "Kullanıcı kaydı sırasında bir hata oluştu: " . $e->getMessage();
                    }
                }
            }
        }
    }
    
    // kupon kisminin crudu
    if ($current_action === 'coupons') {
        // ekleme ve duzenleme 
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_coupon'])) {
            $coupon_id = $_POST['coupon_id'] ?? null;
            $code = strtoupper(trim($_POST['code']));
            $discount_rate = floatval($_POST['discount_rate'] / 100); // % girilir 0.xx olarak kaydedilir
            $usage_limit = intval($_POST['usage_limit']);
            $expiry_date = trim($_POST['expiry_date']);

            if (empty($code) || $discount_rate <= 0 || $usage_limit <= 0 || empty($expiry_date)) {
                $error = "Lütfen tüm kupon alanlarını doğru doldurun.";
            } else {
                if (empty($coupon_id)) {
                    // ekleme
                    $sql = "INSERT INTO coupons (code, discount_rate, usage_limit, expiry_date) VALUES (?, ?, ?, ?)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$code, $discount_rate, $usage_limit, $expiry_date]);
                    $success = "Yeni indirim kuponu başarıyla eklendi.";
                } else {
                    // duzenleme
                    $sql = "UPDATE coupons SET code = ?, discount_rate = ?, usage_limit = ?, expiry_date = ? WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$code, $discount_rate, $usage_limit, $expiry_date, $coupon_id]);
                    $success = "Kupon başarıyla güncellendi.";
                }
            }
        }
        
        // silme
        if (isset($_GET['action']) && $_GET['action'] === 'delete_coupon' && isset($_GET['coupon_id'])) {
            $stmt_delete = $db->prepare("DELETE FROM coupons WHERE id = ?");
            $stmt_delete->execute([$_GET['coupon_id']]);
            $success = "Kupon başarıyla silindi.";
        }
    }
    
  

    // firma datasi cekme
    $stmt_list_firms = $db->query("SELECT id, name, logo_url FROM firms ORDER BY name");
    $all_firms = $stmt_list_firms->fetchAll(PDO::FETCH_ASSOC);

    $firm_to_edit = null;
    if (isset($_GET['action']) && $_GET['action'] === 'edit_firm' && isset($_GET['firm_id'])) {
        $stmt_edit = $db->prepare("SELECT * FROM firms WHERE id = ?");
        $stmt_edit->execute([$_GET['firm_id']]);
        $firm_to_edit = $stmt_edit->fetch(PDO::FETCH_ASSOC);
    }

    // firma adminleri ve firmasini cekme
    $sql_admins = "SELECT u.id, u.username, u.email, f.name as firma_name, u.created_at
                   FROM users u
                   LEFT JOIN firms f ON u.firma_id = f.id
                   WHERE u.role = 'firma_admin'
                   ORDER BY f.name, u.username";
    $stmt_list_admins = $db->query($sql_admins);
    $all_firma_admins = $stmt_list_admins->fetchAll(PDO::FETCH_ASSOC);

    // kupon duzenleme ve kupon datasi cekme
    $stmt_list_coupons = $db->query("SELECT id, code, discount_rate, usage_limit, used_count, expiry_date FROM coupons ORDER BY expiry_date DESC");
    $all_coupons = $stmt_list_coupons->fetchAll(PDO::FETCH_ASSOC);
    
    $coupon_to_edit = null;
    if (isset($_GET['action']) && $_GET['action'] === 'edit_coupon' && isset($_GET['coupon_id'])) {
        $stmt_edit = $db->prepare("SELECT id, code, discount_rate, usage_limit, expiry_date FROM coupons WHERE id = ?");
        $stmt_edit->execute([$_GET['coupon_id']]);
        $coupon_to_edit = $stmt_edit->fetch(PDO::FETCH_ASSOC);
        // % olarak gostermek icin 100 ile carp
        if ($coupon_to_edit) {
             $coupon_to_edit['discount_rate'] *= 100;
        }
    }

} catch (Exception $e) {
    $db->rollBack(); //hata varsa islemleri geri al
    $error = "Veritabanı hatası: " . $e->getMessage();
}

// firma duzenleme
$firm_form_title = $firm_to_edit ? "Firma Düzenle" : "Yeni Firma Ekle";
$current_firm_id = $firm_to_edit['id'] ?? '';
$current_firm_name = $firm_to_edit['name'] ?? '';
$current_logo_url = $firm_to_edit['logo_url'] ?? '';

//kupon duzenleme 
$coupon_form_title = $coupon_to_edit ? "Kupon Düzenle" : "Yeni Kupon Oluştur";
$current_coupon_id = $coupon_to_edit['id'] ?? '';
$current_coupon_code = $coupon_to_edit['code'] ?? '';
$current_discount_rate = $coupon_to_edit['discount_rate'] ?? '';
$current_usage_limit = $coupon_to_edit['usage_limit'] ?? '';
$current_expiry_date = $coupon_to_edit['expiry_date'] ?? date('Y-m-d', strtotime('+1 month'));

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Admin Paneli</title>
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
            text-shadow: 0 0 5px #00ff99;
        }

        h1, h2, h3 {
            color: #00ff99;
            text-shadow: 0 0 8px #00ff99;
        }

        /* sekmeler */
        .tabs {
            margin-bottom: 20px;
            border-bottom: 1px solid #00ff99;
            padding-bottom: 10px;
        }

        .tabs a {
            padding: 10px 20px;
            border: 1px solid #00ff99;
            background-color: transparent;
            color: #00ff99;
            margin-right: 5px;
            border-radius: 5px;
        }

        .tabs a.active {
            background-color: #00ff99;
            color: #000;
            font-weight: bold;
            box-shadow: 0 0 10px #00ff99;
        }

        /* formlar */
        form {
            background-color: rgba(0, 30, 0, 0.7);
            border: 1px solid #00ff99;
            padding: 15px;
            border-radius: 10px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 0 10px #00ff99;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #00ff99;
        }

        input, select {
            width: 100%;
            padding: 8px;
            background-color: #001a00;
            color: #00ff99;
            border: 1px solid #00ff99;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        input:focus, select:focus {
            outline: none;
            box-shadow: 0 0 8px #00ff99;
        }

        button {
            background-color: #00ff99;
            color: #000;
            font-weight: bold;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover {
            background-color: #00ffaa;
            box-shadow: 0 0 10px #00ff99;
        }

        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: rgba(0, 20, 0, 0.7);
            border: 1px solid #00ff99;
            box-shadow: 0 0 10px #00ff99;
        }

        th, td {
            border: 1px solid #00ff99;
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #003300;
            text-shadow: 0 0 3px #00ff99;
        }

        tr:hover {
            background-color: #002200;
        }

      
        p[style*="color: red"] {
            color: #ff4444 !important;
            text-shadow: 0 0 5px #ff0000;
        }
        p[style*="color: green"] {
            color: #00ff99 !important;
            text-shadow: 0 0 5px #00ff99;
        }
    </style>
</head>
<body>
    <p><a href="index.php">Ana Sayfa</a> | <a href="logout.php">Çıkış Yap</a></p>
    <h1> Admin Paneli</h1>

    <?php 
    if (!empty($error)) { echo "<p style='color: red;'>$error</p>"; }
    if (!empty($success)) { echo "<p style='color: green;'>$success</p>"; }
    ?>

    <div class="tabs">
        <a href="admin_panel.php?tab=firms" class="<?php echo $current_action === 'firms' ? 'active' : ''; ?>">Firmalar</a>
        <a href="admin_panel.php?tab=firma_admins" class="<?php echo $current_action === 'firma_admins' ? 'active' : ''; ?>">Firma Adminleri</a>
        <a href="admin_panel.php?tab=coupons" class="<?php echo $current_action === 'coupons' ? 'active' : ''; ?>">Kupon Yönetimi</a>
    </div>

    <div>
        <?php if ($current_action === 'firms'): ?>
            <h2><?php echo $firm_form_title; ?></h2>
            <form method="POST" action="admin_panel.php?tab=firms">
                <input type="hidden" name="firm_id" value="<?php echo htmlspecialchars($current_firm_id); ?>">
                <label>Firma Adı:</label>
                <input type="text" name="firm_name" value="<?php echo htmlspecialchars($current_firm_name); ?>" required>


                <button type="submit" name="submit_firm"><?php echo $firm_to_edit ? "Firmayı Güncelle" : "Firma Ekle"; ?></button>
                <?php if ($firm_to_edit): ?>
                    <a href="admin_panel.php?tab=firms">Vazgeç</a>
                <?php endif; ?>
            </form>

            <h3>Mevcut Otobüs Firmaları</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Firma Adı</th>
                        
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_firms as $firm): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($firm['id']); ?></td>
                            <td><?php echo htmlspecialchars($firm['name']); ?></td>
                            
                            <td>
                                <a href="admin_panel.php?tab=firms&action=edit_firm&firm_id=<?php echo $firm['id']; ?>">Düzenle</a> | 
                                <a href="admin_panel.php?tab=firms&action=delete_firm&firm_id=<?php echo $firm['id']; ?>" onclick="return confirm('Bu firmayı silmek istediğinizden emin misiniz?');" style="color: red;">Sil</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($current_action === 'firma_admins'): ?>
            <h2>Yeni Firma Admin Ekle/Ata</h2>
            <form method="POST" action="admin_panel.php?tab=firma_admins">
                <label>Kullanıcı Adı:</label>
                <input type="text" name="username" required>

                <label>E-posta:</label>
                <input type="email" name="email" required>

                <label>Şifre:</label>
                <input type="password" name="password" required>

                <label>Atanacak Firma:</label>
                <select name="firma_id" required>
                    <option value="">Firma Seçiniz</option>
                    <?php foreach ($firms_list as $firm): ?>
                        <option value="<?php echo $firm['id']; ?>"><?php echo htmlspecialchars($firm['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="submit_firma_admin">Firma Admin Oluştur</button>
            </form>

            <h3>Mevcut Firma Adminleri</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kullanıcı Adı</th>
                        <th>E-posta</th>
                        <th>Firma</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_firma_admins as $admin): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($admin['id']); ?></td>
                            <td><?php echo htmlspecialchars($admin['username']); ?></td>
                            <td><?php echo htmlspecialchars($admin['email']); ?></td>
                            <td><?php echo htmlspecialchars($admin['firma_name'] ?? 'ATANMAMIŞ'); ?></td>
                            <td>Silme işlemi users tablosu üzerinden yapılır.</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($current_action === 'coupons'): ?>
            <h2><?php echo $coupon_form_title; ?></h2>
            <form method="POST" action="admin_panel.php?tab=coupons">
                <input type="hidden" name="coupon_id" value="<?php echo htmlspecialchars($current_coupon_id); ?>">

                <label>Kupon Kodu:</label>
                <input type="text" name="code" value="<?php echo htmlspecialchars($current_coupon_code); ?>" required>

                <label>İndirim Oranı (%):</label>
                <input type="number" name="discount_rate" step="0.01" min="0.01" value="<?php echo htmlspecialchars($current_discount_rate); ?>" required>

                <label>Kullanım Limiti:</label>
                <input type="number" name="usage_limit" min="1" value="<?php echo htmlspecialchars($current_usage_limit); ?>" required>

                <label>Son Kullanma Tarihi:</label>
                <input type="date" name="expiry_date" value="<?php echo htmlspecialchars($current_expiry_date); ?>" required>

                <button type="submit" name="submit_coupon"><?php echo $coupon_to_edit ? "Kuponu Güncelle" : "Kupon Oluştur"; ?></button>
                <?php if ($coupon_to_edit): ?>
                    <a href="admin_panel.php?tab=coupons">Vazgeç</a>
                <?php endif; ?>
            </form>

            <h3>Mevcut Kuponlar</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kod</th>
                        <th>İndirim</th>
                        <th>Kullanım Limiti</th>
                        <th>Kullanım Sayısı</th>
                        <th>Son Tarih</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_coupons as $coupon): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($coupon['id']); ?></td>
                            <td><?php echo htmlspecialchars($coupon['code']); ?></td>
                            <td>%<?php echo number_format($coupon['discount_rate'] * 100, 2); ?></td>
                            <td><?php echo htmlspecialchars($coupon['usage_limit']); ?></td>
                            <td><?php echo htmlspecialchars($coupon['used_count']); ?></td>
                            <td><?php echo htmlspecialchars($coupon['expiry_date']); ?></td>
                            <td>
                                <a href="admin_panel.php?tab=coupons&action=edit_coupon&coupon_id=<?php echo $coupon['id']; ?>">Düzenle</a> | 
                                <a href="admin_panel.php?tab=coupons&action=delete_coupon&coupon_id=<?php echo $coupon['id']; ?>" onclick="return confirm('Bu kuponu silmek istediğinizden emin misiniz?');" style="color: red;">Sil</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>

