<?php

//db baglantisi
$database_file = 'database.sqlite';

try {
    
    $db = new PDO("sqlite:$database_file");
    // hata mesaji
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// form gonderim kontorlu
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // form verileri
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // doğrulama
    if (empty($username) || empty($email) || empty($password) || empty($password_confirm)) {
        $error = "Lütfen tüm alanları doldurun.";
    } elseif ($password !== $password_confirm) {
        $error = "Şifreler eşleşmiyor.";
    } elseif (strlen($password) < 6) {
        $error = "Şifreniz en az 6 karakter olmalıdır.";
    } else {
        // sifreyi db ye hashkeyerek kaydeder
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // varsayilan oalarak rol user atanır
        $role = 'user'; 

        // kullanicinin zaten olup olmadigi kontrol edilir
        $stmt_check = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $stmt_check->execute([$username, $email]);
        $count = $stmt_check->fetchColumn();

        if ($count > 0) {
            $error = "Bu kullanıcı adı veya e-posta adresi zaten kullanımda.";
        } else {
            //kullanicinin db ye kaydi
            $sql = "INSERT INTO users (username, email, password, role, balance) VALUES (?, ?, ?, ?, ?)";
            
            try {
                $stmt = $db->prepare($sql);
                // yeni kullanicinin sanal kredisi varsayilan olarak 800 
                $stmt->execute([$username, $email, $hashed_password, $role, 800]);
                
                $success = "Kayıt işlemi başarıyla tamamlandı. Giriş sayfasına yönlendiriliyorsunuz...";
                // kayittan sonra login sayfasina yonlendirme
                header("Refresh: 3; url=login.php"); 
                exit();
                
            } catch (PDOException $e) {
            
                $error = "Kayıt sırasında bir hata oluştu: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>[ Kayıt Olun ]</title>
    <style>
        
        body {
            font-family: "Consolas", "Courier New", monospace;
            background: radial-gradient(circle at center, #000000 60%, #001a00 100%);
            color: #00ff99;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            overflow: hidden;
        }

        /* form kutusu */
        .register-container {
            z-index: 1;
            background: rgba(0, 20, 0, 0.8);
            border: 2px solid #00ff99;
            border-radius: 12px;
            padding: 35px 45px;
            box-shadow: 0 0 25px rgba(0, 255, 100, 0.5);
            width: 400px;
            text-align: center;
            animation: fadeIn 1s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        h1 {
            color: #00ff99;
            text-shadow: 0 0 10px #00ff99;
            margin-bottom: 25px;
        }

        label {
            display: block;
            text-align: left;
            color: #00ff99;
            margin-bottom: 5px;
        }

        input {
            width: 100%;
            padding: 10px;
            margin-bottom: 18px;
            background: #001a00;
            border: 1px solid #00ff99;
            color: #00ff99;
            border-radius: 6px;
            font-size: 14px;
            transition: box-shadow 0.3s, background 0.3s;
        }

        input:focus {
            outline: none;
            box-shadow: 0 0 10px #00ff99;
            background: #002b00;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #00ff99;
            color: #000;
            border: none;
            font-weight: bold;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        button:hover {
            background: #00e68a;
            box-shadow: 0 0 15px #00ff99;
            transform: scale(1.03);
        }

        p {
            color: #00ff99;
            font-size: 13px;
        }

        a {
            color: #00e6b8;
            text-decoration: none;
            font-weight: bold;
        }

        a:hover {
            color: #00ffaa;
            text-shadow: 0 0 5px #00ff99;
        }

        .error {
            color: #ff4d4d;
            background: rgba(255, 0, 0, 0.1);
            border: 1px solid #ff4d4d;
            padding: 8px;
            border-radius: 6px;
            margin-bottom: 12px;
        }

        .success {
            color: #00ffaa;
            background: rgba(0, 255, 100, 0.1);
            border: 1px solid #00ffaa;
            padding: 8px;
            border-radius: 6px;
            margin-bottom: 12px;
        }

      
    </style>
</head>
<body>
   

    <div class="register-container">
        <h1>[ Kayıt Ol ]</h1>
        
        <?php 
        if (isset($error)) {
            echo "<p class='error'>$error</p>";
        }
        if (isset($success)) {
            echo "<p class='success'>$success</p>";
        }
        ?>

        <form method="POST" action="register.php">
            <label for="username">Kullanıcı Adı:</label>
            <input type="text" id="username" name="username" required>

            <label for="email">E-posta:</label>
            <input type="email" id="email" name="email" required>

            <label for="password">Şifre:</label>
            <input type="password" id="password" name="password" required>

            <label for="password_confirm">Şifre Tekrarı:</label>
            <input type="password" id="password_confirm" name="password_confirm" required>

            <button type="submit">Kayıt Ol</button>
        </form>

        <p>Zaten hesabınız var mı? <a href="login.php">Giriş Yapın</a></p>
    </div>
</body>
</html>
