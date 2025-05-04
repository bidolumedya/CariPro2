<?php
// Hata raporlamayı etkinleştir
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Oturumu başlat
session_start();

// Kullanıcı giriş yapmamışsa login sayfasına yönlendir
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Veritabanı bağlantısını dahil et
require_once "db_config.php";

// Backup klasörü kontrolü
$backup_dir = 'backups';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

// Yedek al
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backup_dir . '/' . $filename;
    
    try {
        // Tüm tabloları al
        $tables = [];
        $result = $db->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        $output = "-- Veritabanı Yedekleme - " . date('Y-m-d H:i:s') . "\n";
        $output .= "-- Gelir Gider Takip Sistemi\n\n";
        
        // Her tablo için
        foreach ($tables as $table) {
            // Tablo yapısını al
            $result = $db->query("SHOW CREATE TABLE `$table`");
            $row = $result->fetch(PDO::FETCH_NUM);
            $output .= $row[1] . ";\n\n";
            
            // Tablo verilerini al
            $result = $db->query("SELECT * FROM `$table`");
            $rows = $result->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($rows) > 0) {
                // Insert ifadesi oluştur
                $output .= "INSERT INTO `$table` VALUES\n";
                
                $values = [];
                foreach ($rows as $row) {
                    $value = "(";
                    $columns = [];
                    
                    foreach ($row as $data) {
                        if ($data === null) {
                            $columns[] = "NULL";
                        } elseif (is_numeric($data)) {
                            $columns[] = $data;
                        } else {
                            $columns[] = $db->quote($data);
                        }
                    }
                    
                    $value .= implode(', ', $columns) . ")";
                    $values[] = $value;
                }
                
                $output .= implode(",\n", $values) . ";\n\n";
            }
        }
        
        // Dosyaya yaz
        if (file_put_contents($filepath, $output)) {
            $success_message = "Veritabanı başarıyla yedeklendi. <a href='$filepath' download>Yedek dosyasını indirmek için tıklayın</a>.";
        } else {
            $error_message = "Yedek dosyası oluşturulurken bir hata oluştu.";
        }
    } catch (PDOException $e) {
        $error_message = "Veritabanı yedeği alınırken bir hata oluştu: " . $e->getMessage();
    }
}

// Yedek geri yükle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] == 0) {
        $filename = $_FILES['backup_file']['tmp_name'];
        
        try {
            // SQL dosyasının içeriğini oku
            $sql = file_get_contents($filename);
            
            // SQL komutlarını çalıştır
            $db->exec($sql);
            
            $success_message = "Veritabanı başarıyla geri yüklendi.";
        } catch (PDOException $e) {
            $error_message = "Veritabanı geri yüklenirken bir hata oluştu: " . $e->getMessage();
        }
    } else {
        $error_message = "Lütfen geçerli bir yedek dosyası seçin.";
    }
}

// Mevcut yedekleri listele
$backups = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'sql') {
            $backups[] = [
                'file' => $file,
                'path' => $backup_dir . '/' . $file,
                'size' => filesize($backup_dir . '/' . $file),
                'date' => date('d.m.Y H:i:s', filemtime($backup_dir . '/' . $file))
            ];
        }
    }
    
    // Tarihe göre sırala (en yeniler önce)
    usort($backups, function($a, $b) {
        return filemtime($b['path']) - filemtime($a['path']);
    });
}

// Şablon başlığını ayarla
$page_title = "Veritabanı Yedekleme";
$active_page = "backup";

// Üst kısmı dahil et
include "inc/header.php";
?>

<!-- Ana içerik -->
<div class="container">
    <?php if (isset($success_message)): ?>
    <div class="alert alert-success">
        <?php echo $success_message; ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo $error_message; ?>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <h2>Veritabanı Yedekle</h2>
        <p>Veritabanınızın anlık bir yedeğini almak için aşağıdaki butonu kullanın.</p>
        
        <form method="POST" action="">
            <button type="submit" name="create_backup" class="btn btn-success">Yedek Oluştur</button>
        </form>
    </div>
    
    <div class="card">
        <h2>Yedek Geri Yükle</h2>
        <p>Önceden alınmış bir yedeği geri yüklemek için dosyayı seçin ve "Geri Yükle" butonuna tıklayın.</p>
        <div class="alert alert-danger">
            <strong>Uyarı!</strong> Geri yükleme işlemi, mevcut veritabanınızdaki tüm verilerin üzerine yazacaktır. Bu işlem geri alınamaz.
        </div>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="backup_file">Yedek Dosyası (.sql)</label>
                <input type="file" id="backup_file" name="backup_file" accept=".sql" required>
            </div>
            <button type="submit" name="restore_backup" class="btn btn-danger" onclick="return confirm('Veritabanınızı geri yüklemek istediğinize emin misiniz? Bu işlem mevcut verilerin üzerine yazacaktır.')">Geri Yükle</button>
        </form>
    </div>
    
    <div class="card">
        <h2>Mevcut Yedekler</h2>
        <?php if (count($backups) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Dosya Adı</th>
                        <th>Boyut</th>
                        <th>Tarih</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td><?php echo $backup['file']; ?></td>
                            <td><?php echo formatBytes($backup['size']); ?></td>
                            <td><?php echo $backup['date']; ?></td>
                            <td>
                                <a href="<?php echo $backup['path']; ?>" download class="btn btn-sm">İndir</a>
                                <a href="?delete=<?php echo urlencode($backup['file']); ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bu yedek dosyasını silmek istediğinize emin misiniz?')">Sil</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Henüz hiç yedek alınmamış.</p>
        <?php endif; ?>
    </div>
</div>

<?php
// Alt kısmı dahil et
include "inc/footer.php";

// Byte formatını insan okunabilir hale getir
function formatBytes($bytes, $precision = 2) { 
    $units = ['B', 'KB', 'MB', 'GB', 'TB']; 
    
    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow]; 
}