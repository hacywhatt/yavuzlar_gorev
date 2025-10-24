<?php
//tum giris yapan kullanicilarin durumu sessionla takip edilcek
session_start();

//eger kullanici zaten giris yapmissa anasayfaya yonlendir
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// db baglanti ayari
$database_file = 'database.sqlite';

try {
    // php database ogject ile sqlite dbye baglanma (pdo php uygualamarinda veri erisimi soyutlama katmani sunar, sql sorgularini calistirabilmeye ve verileri okumaya olanak saglar, sql sorgusunu manipule etmez veya db tarafindan desteklenmeyen ozellikleri benzestirme yaparak desteklemeye calismaz)
    $db = new PDO("sqlite:$database_file");
    // hata mesaji
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("veritabani baglanti hatasi: " . $e->getMessage());
}

$error = '';

// form gonderimi kontrolu
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "lutfen kullanici adi ve sifrenizi girin";
    } else {
        // kKullaniciyi dbde aramak icin
        $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        //  kullanici kontrolu ve sifre dogrulama
        if ($user) {
            // sifreyi, dbdeki hashlenmis sifre ile karsilastirir
            if (password_verify($password, $user['password'])) {
                
                
                // session ata
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // kullanicinin rolune gore admin paneli ya da firma admin paneli
                if ($user['role'] === 'admin') {
                    header("Location: admin_panel.php");
                } elseif ($user['role'] === 'firma_admin') {
                    header("Location: firma_admin_panel.php");
                } else {
                    // user ve ziyaretci icin ana sayfa
                    header("Location: index.php");
                }
                exit();
                
            } else {
                // sifre yanlissa
                $error = "Kullanıcı adı veya şifre yanlış";
            }
        } else {
            // kullanici yoksa
            $error = "Kullanıcı bulunamadı.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>[ Giriş Yapın ]</title>
    <style>
        
        body {
            font-family: 'Courier New', monospace;
            background: radial-gradient(circle at center, #0a0a0a 0%, #000 100%);
            color: #00ff88;
            height: 100vh;
            margin: 0;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* giris kutusu */
        .login-container {
            position: relative;
            background: rgba(0, 0, 0, 0.85);
            border: 1px solid #00ff88;
            box-shadow: 0 0 20px #00ff88;
            border-radius: 12px;
            padding: 40px 50px;
            width: 350px;
            z-index: 2;
            text-align: center;
        }

        .login-container::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 12px;
            box-shadow: 0 0 40px #00ff88;
            opacity: 0.2;
            filter: blur(10px);
            z-index: -1;
        }

        h1 {
            color: #00ff88;
            text-shadow: 0 0 10px #00ff88;
            margin-bottom: 25px;
        }

        label {
            display: block;
            text-align: left;
            margin-bottom: 5px;
            font-weight: bold;
            color: #00ffcc;
        }

        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #00ff88;
            background-color: #0f0f0f;
            color: #00ff88;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 15px;
            transition: box-shadow 0.3s, border-color 0.3s;
        }

        input:focus {
            outline: none;
            box-shadow: 0 0 10px #00ff88;
            border-color: #00ffaa;
        }

        button {
            width: 100%;
            padding: 10px;
            background-color: #00ff88;
            border: none;
            color: #0a0a0a;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
        }

        button:hover {
            background-color: #0a0a0a;
            color: #00ff88;
            border: 1px solid #00ff88;
            box-shadow: 0 0 15px #00ff88;
        }

        p {
            font-size: 13px;
            color: #00ffcc;
        }

        a {
            color: #00ffaa;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
            color: #00ffcc;
        }

        .error {
            color: #ff4d4d;
            background: #330000;
            border: 1px solid #ff4d4d;
            padding: 8px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
      
    </style>
</head>
<body>
    <div class="login-container">
        <h1>[ Giriş Yapın ]<span class="cursor"></span></h1>

        <?php 
        if (!empty($error)) {
            echo "<p class='error'>$error</p>";
        }
        ?>

        <form method="POST" action="login.php">
            <label for="username">Kullanıcı Adı:</label>
            <input type="text" id="username" name="username" required 
                   value="<?php echo htmlspecialchars($username ?? ''); ?>">

            <label for="password">Şifre:</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Giriş Yapın</button>
        </form>

        <p>Hesabınız yok mu? <a href="register.php">Kayıt Olun</a></p>
        <p>Giriş yapmadan <a href="index.php">Seferleri Listele</a></p>
    </div>
</body>
</html>
