<?php
// Oturum başlat
session_start();

// Kullanıcı giriş yapmış mı kontrol et
if (isset($_SESSION['user_id'])) {
    // Kullanıcı giriş yapmış, ana sayfaya yönlendir
    header("Location: dashboard.php");
    exit;
}

// Giriş formu gönderildi mi kontrol et
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Veritabanı bağlantısını dahil et
    require_once "db_config.php";
    
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    try {
        // Kullanıcı adına göre kullanıcıyı veritabanından bul
        $stmt = $db->prepare("SELECT id, username, password, fullname FROM users WHERE username = :username");
        $stmt->bindParam(":username", $username);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Şifreyi doğrula - ya hash ile ya da direkt olarak admin123 ile karşılaştır
            if (password_verify($password, $user['password']) || $password === 'admin123') {
                // Şifre doğru, oturumu başlat
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['fullname'] = $user['fullname'] ?? $user['username'];
                
                // Ana sayfaya yönlendir
                header("Location: dashboard.php");
                exit;
            } else {
                $login_error = "Kullanıcı adı veya şifre hatalı!";
            }
        } else {
            $login_error = "Kullanıcı adı veya şifre hatalı!";
        }
    } catch(PDOException $e) {
        $login_error = "Veritabanı hatası: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş - Gelir Gider Takip Sistemi</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div id="loginForm" class="card">
            <h2>Gelir Gider Takip Sistemi</h2>
            <form method="POST" action="">
                <?php if (isset($login_error)): ?>
                <div class="alert alert-danger">
                    <?php echo $login_error; ?>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="username">Kullanıcı Adı</label>
                    <input type="text" id="username" name="username" placeholder="Kullanıcı adınızı girin" required>
                </div>
                <div class="form-group">
                    <label for="password">Şifre</label>
                    <input type="password" id="password" name="password" placeholder="Şifrenizi girin" required>
                </div>
                <button type="submit" class="btn">Giriş Yap</button>
            </form>
        </div>
    </div>
</body>
</html>