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

// Eğer settings tablosu yoksa oluştur
try {
    $stmt = $db->query("SHOW TABLES LIKE 'settings'");
    if ($stmt->rowCount() == 0) {
        // Tablo yok, oluştur
        $db->exec("CREATE TABLE settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) CHARACTER SET utf8 COLLATE utf8_general_ci");
    }
} catch (PDOException $e) {
    echo "<p>Tablo oluşturulurken hata oluştu: " . $e->getMessage() . "</p>";
}

// Ayarları kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $company_name = $_POST['company_name'] ?? '';
    $company_address = $_POST['company_address'] ?? '';
    $company_phone = $_POST['company_phone'] ?? '';
    $company_email = $_POST['company_email'] ?? '';
    $company_tax_number = $_POST['company_tax_number'] ?? '';
    $system_title = $_POST['system_title'] ?? 'Gelir Gider Takip Sistemi';
    $welcome_text = $_POST['welcome_text'] ?? 'Hoş geldiniz, Sistem Yöneticisi';
    $primary_color = $_POST['primary_color'] ?? '#3498db';
    $secondary_color = $_POST['secondary_color'] ?? '#2980b9';
    $success_color = $_POST['success_color'] ?? '#2ecc71';
    $danger_color = $_POST['danger_color'] ?? '#e74c3c';
    
    try {
        // Logo yükleme işlemi
        $logo_path = '';
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['company_logo']['name'];
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            
            if (in_array(strtolower($ext), $allowed)) {
                // Uploads klasörü yoksa oluştur
                if (!file_exists('uploads')) {
                    mkdir('uploads', 0777, true);
                }
                
                $new_filename = 'logo_' . time() . '.' . $ext;
                $destination = 'uploads/' . $new_filename;
                
                if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $destination)) {
                    $logo_path = $destination;
                }
            }
        }
        
        // Kayıt edilecek ayarlar
        $settings = [
            'company_name' => $company_name,
            'company_address' => $company_address,
            'company_phone' => $company_phone,
            'company_email' => $company_email,
            'company_tax_number' => $company_tax_number,
            'system_title' => $system_title,
            'welcome_text' => $welcome_text,
            'primary_color' => $primary_color,
            'secondary_color' => $secondary_color,
            'success_color' => $success_color,
            'danger_color' => $danger_color
        ];
        
        // Logo yolu varsa ekle
        if (!empty($logo_path)) {
            $settings['company_logo'] = $logo_path;
        }
        
        foreach ($settings as $key => $value) {
            // Önce bu ayarın var olup olmadığını kontrol et
            $stmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = :key");
            $stmt->bindParam(':key', $key);
            $stmt->execute();
            $exists = $stmt->fetchColumn();
            
            if ($exists) {
                // Güncelle
                $stmt = $db->prepare("UPDATE settings SET setting_value = :value WHERE setting_key = :key");
            } else {
                // Ekle
                $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)");
            }
            
            $stmt->bindParam(':key', $key);
            $stmt->bindParam(':value', $value);
            $stmt->execute();
        }
        
        // CSS dosyasını güncelle
        updateCustomCSS($primary_color, $secondary_color, $success_color, $danger_color);
        
        $success_message = "Ayarlar başarıyla kaydedildi.";
    } catch (PDOException $e) {
        $error_message = "Ayarlar kaydedilirken bir hata oluştu: " . $e->getMessage();
    }
}

// CSS dosyasını güncelleme fonksiyonu
function updateCustomCSS($primary_color, $secondary_color, $success_color, $danger_color) {
    $css_content = "/* Otomatik oluşturulan özel CSS - " . date('Y-m-d H:i:s') . " */
:root {
    --primary-color: {$primary_color};
    --secondary-color: {$secondary_color};
    --success-color: {$success_color};
    --danger-color: {$danger_color};
}

.header {
    background-color: var(--primary-color);
}

.nav {
    background-color: var(--secondary-color);
}

.nav a.active, .nav a:hover {
    background-color: #1c638d;
}

.btn {
    background-color: var(--primary-color);
}

.btn:hover {
    background-color: var(--secondary-color);
}

.btn-danger {
    background-color: var(--danger-color);
}

.btn-success {
    background-color: var(--success-color);
}

.income, .summary-value.income {
    color: var(--success-color);
}

.expense, .summary-value.expense {
    color: var(--danger-color);
}

.alert-success {
    background-color: var(--success-color);
}

.alert-danger {
    background-color: var(--danger-color);
}

h2 {
    border-bottom: 2px solid var(--primary-color);
}
";

    // assets/css klasörü yoksa oluştur
    if (!file_exists('assets/css')) {
        mkdir('assets/css', 0777, true);
    }
    
    // Özel CSS dosyasını oluştur
    file_put_contents('assets/css/custom.css', $css_content);
}

// Mevcut ayarları yükle
try {
    // Settings tablosu var mı kontrol et
    $stmt = $db->query("SHOW TABLES LIKE 'settings'");
    $settings = [];
    
    if ($stmt->rowCount() > 0) {
        // Tablo var, ayarları al
        $stmt = $db->query("SELECT * FROM settings");
        $settings_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($settings_data as $setting) {
            $settings[$setting['setting_key']] = $setting['setting_value'];
        }
    }
} catch (PDOException $e) {
    echo "<p>Ayarlar yüklenirken hata oluştu: " . $e->getMessage() . "</p>";
    $settings = [];
}

// Şablon başlığını ayarla
$page_title = "Sistem Ayarları";
$active_page = "settings";

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
        <h2>Sistem Ayarları</h2>
        <ul class="tab-nav">
            <li class="tab-link active" data-tab="general-settings">Genel Ayarlar</li>
            <li class="tab-link" data-tab="appearance-settings">Görünüm Ayarları</li>
        </ul>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <div id="general-settings" class="tab-content active">
                <h3>Firma Bilgileri</h3>
                <div class="form-group">
                    <label for="company_name">Firma Adı</label>
                    <input type="text" id="company_name" name="company_name" value="<?php echo isset($settings['company_name']) ? $settings['company_name'] : ''; ?>" placeholder="Firma adınızı girin">
                </div>
                <div class="form-group">
                    <label for="company_address">Firma Adresi</label>
                    <textarea id="company_address" name="company_address" rows="3" placeholder="Firma adresini girin"><?php echo isset($settings['company_address']) ? $settings['company_address'] : ''; ?></textarea>
                </div>
                <div class="form-group">
                    <label for="company_phone">Telefon</label>
                    <input type="text" id="company_phone" name="company_phone" value="<?php echo isset($settings['company_phone']) ? $settings['company_phone'] : ''; ?>" placeholder="Telefon numarasını girin">
                </div>
                <div class="form-group">
                    <label for="company_email">E-posta</label>
                    <input type="email" id="company_email" name="company_email" value="<?php echo isset($settings['company_email']) ? $settings['company_email'] : ''; ?>" placeholder="E-posta adresini girin">
                </div>
                <div class="form-group">
                    <label for="company_tax_number">Vergi Numarası</label>
                    <input type="text" id="company_tax_number" name="company_tax_number" value="<?php echo isset($settings['company_tax_number']) ? $settings['company_tax_number'] : ''; ?>" placeholder="Vergi numarasını girin">
                </div>
                <div class="form-group">
                    <label for="company_logo">Firma Logosu</label>
                    <input type="file" id="company_logo" name="company_logo" accept="image/*">
                    <?php if (isset($settings['company_logo']) && !empty($settings['company_logo'])): ?>
                        <div class="current-logo">
                            <p>Mevcut Logo:</p>
                            <img src="<?php echo $settings['company_logo']; ?>" alt="Firma Logosu" style="max-width: 200px; max-height: 100px;">
                        </div>
                    <?php endif; ?>
                </div>

                <h3>Sistem Metinleri</h3>
                <div class="form-group">
                    <label for="system_title">Sistem Başlığı</label>
                    <input type="text" id="system_title" name="system_title" value="<?php echo isset($settings['system_title']) ? $settings['system_title'] : 'Gelir Gider Takip Sistemi'; ?>" placeholder="Sistem başlığını girin">
                    <small>Header kısmında görünecek başlık</small>
                </div>
                <div class="form-group">
                    <label for="welcome_text">Hoş Geldiniz Metni</label>
                    <input type="text" id="welcome_text" name="welcome_text" value="<?php echo isset($settings['welcome_text']) ? $settings['welcome_text'] : 'Hoş geldiniz, Sistem Yöneticisi'; ?>" placeholder="Hoş geldiniz metni">
                    <small>Header kısmında kullanıcı adının yanında görünecek metin</small>
                </div>
            </div>
            
            <div id="appearance-settings" class="tab-content">
                <h3>Renk Ayarları</h3>
                <div class="color-settings">
                    <div class="form-group color-group">
                        <label for="primary_color">Ana Renk</label>
                        <input type="color" id="primary_color" name="primary_color" value="<?php echo isset($settings['primary_color']) ? $settings['primary_color'] : '#3498db'; ?>">
                        <small>Header, butonlar ve başlıklar için ana renk</small>
                    </div>
                    <div class="form-group color-group">
                        <label for="secondary_color">İkincil Renk</label>
                        <input type="color" id="secondary_color" name="secondary_color" value="<?php echo isset($settings['secondary_color']) ? $settings['secondary_color'] : '#2980b9'; ?>">
                        <small>Menü çubuğu ve buton hover rengi</small>
                    </div>
                    <div class="form-group color-group">
                        <label for="success_color">Başarı Rengi</label>
                        <input type="color" id="success_color" name="success_color" value="<?php echo isset($settings['success_color']) ? $settings['success_color'] : '#2ecc71'; ?>">
                        <small>Gelir, başarı bildirimleri ve olumlu göstergeler için</small>
                    </div>
                    <div class="form-group color-group">
                        <label for="danger_color">Tehlike Rengi</label>
                        <input type="color" id="danger_color" name="danger_color" value="<?php echo isset($settings['danger_color']) ? $settings['danger_color'] : '#e74c3c'; ?>">
                        <small>Gider, hata bildirimleri ve olumsuz göstergeler için</small>
                    </div>
                </div>
                
                <div class="color-preview">
                    <h4>Önizleme</h4>
                    <div class="preview-box" id="colorPreview">
                        <div class="preview-header"></div>
                        <div class="preview-menu"></div>
                        <div class="preview-container">
                            <div class="preview-card">
                                <div class="preview-title"></div>
                                <div class="preview-content"></div>
                                <div class="preview-buttons">
                                    <button type="button" class="preview-btn"></button>
                                    <button type="button" class="preview-btn-success"></button>
                                    <button type="button" class="preview-btn-danger"></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-action">
                <button type="submit" name="save_settings" class="btn">Ayarları Kaydet</button>
            </div>
        </form>
    </div>
    
    <div class="card">
        <h2>Sistem Yönetimi</h2>
        <div class="settings-links">
            <a href="categories.php" class="settings-link">
                <div class="settings-icon">
                    <i class="fas fa-folder"></i>
                </div>
                <div class="settings-title">Kategori Yönetimi</div>
                <div class="settings-description">Gelir ve gider kategorilerini düzenleyin</div>
            </a>
            <a href="products.php" class="settings-link">
                <div class="settings-icon">
                    <i class="fas fa-box"></i>
                </div>
                <div class="settings-title">Ürün Yönetimi</div>
                <div class="settings-description">Ürün ve hizmetleri düzenleyin</div>
            </a>
            <a href="users.php" class="settings-link">
                <div class="settings-icon">
                    <i class="fas fa-users-cog"></i>
                </div>
                <div class="settings-title">Kullanıcı Yönetimi</div>
                <div class="settings-description">Kullanıcıları ve yetkilerini düzenleyin</div>
            </a>
            <a href="backup.php" class="settings-link">
                <div class="settings-icon">
                    <i class="fas fa-database"></i>
                </div>
                <div class="settings-title">Yedekleme</div>
                <div class="settings-description">Veritabanı yedeklerini alın ve geri yükleyin</div>
            </a>
        </div>
    </div>
</div>

<?php
// Alt kısmı dahil et
include "inc/footer.php";
?>

<style>
/* Sistem Yönetimi kısmı */
.settings-links {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.settings-link {
    display: block;
    background-color: #f8f8f8;
    border-radius: 5px;
    padding: 20px;
    text-decoration: none;
    color: #333;
    border: 1px solid #ddd;
    transition: all 0.3s ease;
}

.settings-link:hover {
    background-color: #e9e9e9;
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.settings-icon {
    font-size: 24px;
    margin-bottom: 15px;
    color: #3498db;
}

.settings-title {
    font-weight: bold;
    margin-bottom: 10px;
    color: #2c3e50;
}

.settings-description {
    font-size: 14px;
    color: #7f8c8d;
}

/* İkon stilleri */
.icon-category::before {
    content: "📂";
}

.icon-product::before {
    content: "📦";
}

.icon-user::before {
    content: "👤";
}

.icon-backup::before {
    content: "💾";
}

/* Tab menü için stiller */
.tab-nav {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 0 0 20px 0;
    border-bottom: 1px solid #ddd;
}

.tab-link {
    padding: 10px 20px;
    cursor: pointer;
    margin-right: 5px;
    border-radius: 5px 5px 0 0;
}

.tab-link.active {
    background-color: #f5f5f5;
    border: 1px solid #ddd;
    border-bottom: none;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* Renk ayarları */
.color-settings {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.color-group {
    text-align: center;
}

.color-group input[type="color"] {
    width: 100%;
    height: 40px;
    cursor: pointer;
}

/* Renk önizleme kutusu */
.color-preview {
    background-color: #f9f9f9;
    padding: 15px;
    border-radius: 5px;
    margin-top: 20px;
}

.preview-box {
    width: 100%;
    height: 300px;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.preview-header {
    height: 60px;
    background-color: var(--primary-color, #3498db);
}

.preview-menu {
    height: 40px;
    background-color: var(--secondary-color, #2980b9);
}

.preview-container {
    height: 200px;
    background-color: #f5f5f5;
    padding: 20px;
}

.preview-card {
    background-color: white;
    height: 100%;
    border-radius: 5px;
    padding: 15px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.preview-title {
    height: 20px;
    width: 70%;
    background-color: #f1f1f1;
    margin-bottom: 15px;
    position: relative;
}

.preview-title::after {
    content: "";
    position: absolute;
    bottom: -5px;
    left: 0;
    width: 100%;
    height: 2px;
    background-color: var(--primary-color, #3498db);
}

.preview-content {
    height: 80px;
    background-color: #f9f9f9;
    margin-bottom: 15px;
}

.preview-buttons {
    display: flex;
    gap: 10px;
}

.preview-btn, .preview-btn-success, .preview-btn-danger {
    height: 30px;
    border-radius: 4px;
    flex: 1;
}

.preview-btn {
    background-color: var(--primary-color, #3498db);
}

.preview-btn-success {
    background-color: var(--success-color, #2ecc71);
}

.preview-btn-danger {
    background-color: var(--danger-color, #e74c3c);
}

/* Form action */
.form-action {
    margin-top: 20px;
    text-align: center;
}

/* Mevcut logo */
.current-logo {
    margin-top: 10px;
    padding: 10px;
    border: 1px dashed #ddd;
    display: inline-block;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab menü işlemleri
    const tabLinks = document.querySelectorAll('.tab-link');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabLinks.forEach(link => {
        link.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Tüm tab linkleri ve içeriklerinin aktif sınıfını kaldır
            tabLinks.forEach(l => l.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            // İlgili tab link ve içeriğini aktif yap
            this.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });
    
    // Renk değişikliği önizlemesi
    const primaryColorInput = document.getElementById('primary_color');
    const secondaryColorInput = document.getElementById('secondary_color');
    const successColorInput = document.getElementById('success_color');
    const dangerColorInput = document.getElementById('danger_color');
    const colorPreview = document.getElementById('colorPreview');
    
    // Renk değişikliğini önizlemeye yansıt
    function updateColorPreview() {
        const primaryColor = primaryColorInput.value;
        const secondaryColor = secondaryColorInput.value;
        const successColor = successColorInput.value;
        const dangerColor = dangerColorInput.value;
        
        colorPreview.style.setProperty('--primary-color', primaryColor);
        colorPreview.style.setProperty('--secondary-color', secondaryColor);
        colorPreview.style.setProperty('--success-color', successColor);
        colorPreview.style.setProperty('--danger-color', dangerColor);
    }
    
    primaryColorInput.addEventListener('input', updateColorPreview);
    secondaryColorInput.addEventListener('input', updateColorPreview);
    successColorInput.addEventListener('input', updateColorPreview);
    dangerColorInput.addEventListener('input', updateColorPreview);
    
    // Sayfa yüklendiğinde renkleri ayarla
    updateColorPreview();
});
</script>