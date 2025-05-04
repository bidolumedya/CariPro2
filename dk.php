<?php
// Hata raporlamayı etkinleştir
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Dosya Kontrol</h1>";

// Kontrol edilecek dosyalar
$files = [
    'users.php',
    'backup.php',
    'inc/header.php',
    'settings.php',
    'backups'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        if (is_dir($file)) {
            echo "<p><strong>{$file}</strong>: Klasör mevcut ✓</p>";
            
            if (!is_writable($file)) {
                echo "<p style='color:red'>Uyarı: {$file} klasörüne yazma izniniz yok!</p>";
            }
        } else {
            echo "<p><strong>{$file}</strong>: Dosya mevcut ✓</p>";
            
            if (!is_readable($file)) {
                echo "<p style='color:red'>Uyarı: {$file} dosyasını okuma izniniz yok!</p>";
            }
        }
    } else {
        echo "<p style='color:red'><strong>{$file}</strong>: Dosya/Klasör bulunamadı! ✗</p>";
    }
}

// Veritabanı bağlantısını kontrol et
echo "<h2>Veritabanı Kontrol</h2>";

if (file_exists('db_config.php')) {
    require_once 'db_config.php';
    
    try {
        // Kullanıcılar tablosu kontrol
        $stmt = $db->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() > 0) {
            echo "<p><strong>users</strong> tablosu mevcut ✓</p>";
            
            // Tablo yapısını kontrol et
            $columns = [];
            $stmt = $db->query("SHOW COLUMNS FROM users");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = $row['Field'];
            }
            
            $required_columns = ['id', 'username', 'password', 'role', 'permissions', 'active'];
            $missing_columns = [];
            
            foreach ($required_columns as $column) {
                if (!in_array($column, $columns)) {
                    $missing_columns[] = $column;
                }
            }
            
            if (count($missing_columns) > 0) {
                echo "<p style='color:orange'>Uyarı: users tablosunda eksik sütunlar var: " . implode(', ', $missing_columns) . "</p>";
                echo "<p>Lütfen <strong>kullanici_tablosu_guncelleme.sql</strong> dosyasını veritabanınızda çalıştırın.</p>";
            }
        } else {
            echo "<p style='color:red'><strong>users</strong> tablosu bulunamadı! ✗</p>";
            echo "<p>Lütfen <strong>kullanici_tablosu_guncelleme.sql</strong> dosyasını veritabanınızda çalıştırın.</p>";
        }
        
        // Settings tablosu kontrol
        $stmt = $db->query("SHOW TABLES LIKE 'settings'");
        if ($stmt->rowCount() > 0) {
            echo "<p><strong>settings</strong> tablosu mevcut ✓</p>";
        } else {
            echo "<p style='color:orange'><strong>settings</strong> tablosu bulunamadı, ilk kullanımda otomatik oluşturulacak.</p>";
        }
        
    } catch (PDOException $e) {
        echo "<p style='color:red'>Veritabanı bağlantı hatası: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:red'><strong>db_config.php</strong> dosyası bulunamadı! ✗</p>";
}

// PHP sürüm kontrolü
echo "<h2>PHP Sürüm Kontrolü</h2>";
echo "<p>PHP Sürümü: " . phpversion() . "</p>";

if (version_compare(phpversion(), '7.0.0', '<')) {
    echo "<p style='color:red'>Uyarı: Bu sistem PHP 7.0 ve üzeri sürümlerde çalışacak şekilde tasarlanmıştır. Lütfen PHP sürümünüzü güncelleyin.</p>";
}

// Dizin yazma izinleri
echo "<h2>Dizin Yazma İzinleri</h2>";
$write_dirs = [
    '.',
    './backups',
    './uploads'
];

foreach ($write_dirs as $dir) {
    if (!file_exists($dir)) {
        echo "<p><strong>{$dir}</strong>: Klasör bulunamadı, oluşturuluyor...</p>";
        if (mkdir($dir, 0777, true)) {
            echo "<p style='color:green'>Klasör başarıyla oluşturuldu.</p>";
        } else {
            echo "<p style='color:red'>Klasör oluşturulamadı!</p>";
        }
    }
    
    if (is_writable($dir)) {
        echo "<p><strong>{$dir}</strong>: Yazma izni var ✓</p>";
    } else {
        echo "<p style='color:red'><strong>{$dir}</strong>: Yazma izni yok! ✗</p>";
        echo "<p>Lütfen bu dizine yazma izni verin (chmod 777 {$dir}).</p>";
    }
}

echo "<p>Sistem kontrol işlemi tamamlandı.</p>";