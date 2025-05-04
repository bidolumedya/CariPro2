<?php
// Hata raporlamayı etkinleştir
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Veritabanı bağlantısı
require_once 'db_config.php';

// Kurulum durumu
$status = [
    'users_table' => false,
    'settings_table' => false,
    'backups_dir' => false,
    'uploads_dir' => false
];

// Gerekli dizinleri oluştur
$required_dirs = ['backups', 'uploads'];
foreach ($required_dirs as $dir) {
    if (!file_exists($dir)) {
        if (mkdir($dir, 0777, true)) {
            $status[$dir . '_dir'] = true;
        }
    } else {
        $status[$dir . '_dir'] = true;
    }
}

// Users tablosu kontrolü ve oluşturma
try {
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        // Tablo yok, oluştur
        $db->exec("
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                fullname VARCHAR(100),
                email VARCHAR(100),
                role ENUM('admin', 'manager', 'user') NOT NULL DEFAULT 'user',
                permissions TEXT,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) CHARACTER SET utf8 COLLATE utf8_general_ci
        ");
        
        // Admin kullanıcısını ekle
        $admin_username = 'admin';
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $admin_fullname = 'Sistem Yöneticisi';
        $admin_email = 'admin@example.com';
        $admin_role = 'admin';
        
        $stmt = $db->prepare("INSERT INTO users (username, password, fullname, email, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$admin_username, $admin_password, $admin_fullname, $admin_email, $admin_role]);
        
        $status['users_table'] = 'created';
    } else {
        // Tablo var, sütunları kontrol et
        $columns = [];
        $stmt = $db->query("SHOW COLUMNS FROM users");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['Field'];
        }
        
        $required_columns = ['role', 'permissions', 'active'];
        $missing_columns = [];
        
        foreach ($required_columns as $column) {
            if (!in_array($column, $columns)) {
                $missing_columns[] = $column;
            }
        }
        
        if (count($missing_columns) > 0) {
            // Eksik sütunları ekle
            if (in_array('role', $missing_columns)) {
                $db->exec("ALTER TABLE users ADD COLUMN role ENUM('admin', 'manager', 'user') NOT NULL DEFAULT 'user' AFTER email");
            }
            
            if (in_array('permissions', $missing_columns)) {
                $db->exec("ALTER TABLE users ADD COLUMN permissions TEXT AFTER role");
            }
            
            if (in_array('active', $missing_columns)) {
                $db->exec("ALTER TABLE users ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER permissions");
            }
            
            $status['users_table'] = 'updated';
        } else {
            $status['users_table'] = 'exists';
        }
    }
} catch (PDOException $e) {
    $error = "Users tablosu oluşturulurken hata: " . $e->getMessage();
}

// Settings tablosu kontrolü ve oluşturma
try {
    $stmt = $db->query("SHOW TABLES LIKE 'settings'");
    if ($stmt->rowCount() == 0) {
        // Tablo yok, oluştur
        $db->exec("
            CREATE TABLE settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) CHARACTER SET utf8 COLLATE utf8_general_ci
        ");
        
        $status['settings_table'] = 'created';
    } else {
        $status['settings_table'] = 'exists';
    }
} catch (PDOException $e) {
    $error = "Settings tablosu oluşturulurken hata: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gelir Gider Takip Sistemi - Kurulum</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #3498db;
            margin-top: 0;
        }
        h2 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            margin-top: 30px;
        }
        .status-item {
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .status-success {
            border-left: 5px solid #2ecc71;
        }
        .status-warning {
            border-left: 5px solid #f39c12;
        }
        .status-error {
            border-left: 5px solid #e74c3c;
        }
        .btn {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            font-size: 16px;
            cursor: pointer;
            margin-top: 20px;
        }
        .btn:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gelir Gider Takip Sistemi - Kurulum</h1>
        
        <?php if (isset($error)): ?>
            <div class="status-item status-error">
                <strong>Hata:</strong> <?php echo $error; ?>
            </div>
        <?php else: ?>
            <div class="status-item status-success">
                <strong>Veritabanı Bağlantısı:</strong> Başarılı ✓
            </div>
        <?php endif; ?>
        
        <h2>Kurulum Durumu</h2>
        
        <div class="status-item <?php echo $status['users_table'] ? 'status-success' : 'status-error'; ?>">
            <strong>Kullanıcılar Tablosu:</strong> 
            <?php
                if ($status['users_table'] === 'created') {
                    echo 'Başarıyla oluşturuldu ✓';
                } elseif ($status['users_table'] === 'updated') {
                    echo 'Başarıyla güncellendi ✓';
                } elseif ($status['users_table'] === 'exists') {
                    echo 'Zaten mevcut ✓';
                } else {
                    echo 'Oluşturulamadı ✗';
                }
            ?>
        </div>
        
        <div class="status-item <?php echo $status['settings_table'] ? 'status-success' : 'status-error'; ?>">
            <strong>Ayarlar Tablosu:</strong> 
            <?php
                if ($status['settings_table'] === 'created') {
                    echo 'Başarıyla oluşturuldu ✓';
                } elseif ($status['settings_table'] === 'exists') {
                    echo 'Zaten mevcut ✓';
                } else {
                    echo 'Oluşturulamadı ✗';
                }
            ?>
        </div>
        
        <div class="status-item <?php echo $status['backups_dir'] ? 'status-success' : 'status-error'; ?>">
            <strong>Yedekleme Klasörü:</strong> 
            <?php echo $status['backups_dir'] ? 'Başarıyla oluşturuldu ✓' : 'Oluşturulamadı ✗'; ?>
        </div>
        
        <div class="status-item <?php echo $status['uploads_dir'] ? 'status-success' : 'status-error'; ?>">
            <strong>Yükleme Klasörü:</strong> 
            <?php echo $status['uploads_dir'] ? 'Başarıyla oluşturuldu ✓' : 'Oluşturulamadı ✗'; ?>
        </div>
        
        <h2>Kurulum Tamamlandı</h2>
        
        <?php if ($status['users_table'] && $status['settings_table'] && $status['backups_dir'] && $status['uploads_dir']): ?>
            <div class="status-item status-success">
                <p>Sistem başarıyla kuruldu! Aşağıdaki bilgilerle giriş yapabilirsiniz:</p>
                <p><strong>Kullanıcı Adı:</strong> admin</p>
                <p><strong>Şifre:</strong> admin123</p>
                <p>Güvenlik nedeniyle giriş yaptıktan sonra şifrenizi değiştirmeniz önerilir.</p>
            </div>
            
            <a href="index.php" class="btn">Giriş Sayfasına Git</a>
        <?php else: ?>
            <div class="status-item status-warning">
                <p>Kurulum tamamlanamadı. Lütfen yukarıdaki hataları düzeltin ve sayfayı yenileyin.</p>
            </div>
            
            <a href="kurulum.php" class="btn">Yeniden Dene</a>
        <?php endif; ?>
    </div>
</body>
</html>