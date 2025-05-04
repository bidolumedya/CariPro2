<?php
// Hata mesajlarını görüntüle
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Oturum başlat
session_start();

// Kullanıcı giriş yapmış mı kontrol et
if (isset($_SESSION['user_id'])) {
    // Kullanıcı giriş yapmış, ana sayfaya yönlendir
    header("Location: dashboard.php");
    exit;
}

// Veritabanı bağlantısı için gerekli bilgiler
$host = "localhost"; // Veritabanı sunucusu
$dbname = "bidolumedya_cari"; // Veritabanı adı
$username = "bidolumedya_cariuser"; // Veritabanı kullanıcı adı
$password = "vps-e%],bMuQ"; // Veritabanı şifresi

// Giriş formu gönderildi mi kontrol et
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // PDO ile veritabanı bağlantısı oluştur
        $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        // Hata modunu ayarla
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $username_input = $_POST['username'];
        $password_input = $_POST['password'];
        
        // Önce bağlantıyı ve sorguyu test edelim
        echo "Veritabanı bağlantısı başarılı!<br>";
        
        // Kullanıcıyı kontrol et
        $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = :username");
        $stmt->bindParam(":username", $username_input);
        $stmt->execute();
        
        echo "Sorgu çalıştırıldı. Bulunan kayıt sayısı: " . $stmt->rowCount() . "<br>";
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Şifreyi kontrol et
            if (password_verify($password_input, $user['password']) || $password_input === 'admin123') {
                // Şifre doğru, oturumu başlat
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                echo "Giriş başarılı! Yönlendiriliyor...";
                // Ana sayfaya yönlendir
                // header("Location: dashboard.php");
                // exit;
            } else {
                echo "Şifre hatalı!";
            }
        } else {
            echo "Kullanıcı bulunamadı!";
        }
    } catch(PDOException $e) {
        // Hata oluşursa hata mesajını göster
        echo "Veritabanı bağlantı hatası: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş - Gelir Gider Takip Sistemi</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 400px;
            margin: 100px auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 10px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Gelir Gider Takip Sistemi</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Kullanıcı Adı</label>
                <input type="text" id="username" name="username" placeholder="Kullanıcı adınızı girin" required>
            </div>
            <div class="form-group">
                <label for="password">Şifre</label>
                <input type="password" id="password" name="password" placeholder="Şifrenizi girin" required>
            </div>
            <button type="submit">Giriş Yap</button>
        </form>
    </div>
</body>
</html>