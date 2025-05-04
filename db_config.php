<?php
// Veritabanı bağlantı bilgileri
$host = "localhost"; // Veritabanı sunucusu
$dbname = "bidolumedya_cari"; // Veritabanı adı
$username = "bidolumedya_cariuser"; // Veritabanı kullanıcı adı
$password = "vps-e%],bMuQ"; // Veritabanı şifresi

try {
    // PDO ile veritabanı bağlantısı oluştur
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // Hata modunu ayarla
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Veritabanı bağlantısında karakter seti ayarlarını yap
    $db->exec("SET NAMES utf8");
    $db->exec("SET CHARACTER SET utf8");
    $db->exec("SET COLLATION_CONNECTION = 'utf8_general_ci'");
} catch(PDOException $e) {
    // Hata oluşursa hata mesajını göster
    echo "Veritabanı bağlantı hatası: " . $e->getMessage();
    die();
}
?>