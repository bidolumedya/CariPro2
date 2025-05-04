<?php
// Hata raporlamayı etkinleştir (sorun çözüldükten sonra kaldırılabilir)
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

// Cari hesap ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_customer'])) {
    $name = $_POST['name'];
    $type = $_POST['type'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    
    if (!empty($name) && !empty($type)) {
        try {
            $stmt = $db->prepare("INSERT INTO customers (name, type, phone, email, address) VALUES (:name, :type, :phone, :email, :address)");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':address', $address);
            $stmt->execute();
            
            $success_message = "Cari hesap başarıyla eklendi.";
        } catch (PDOException $e) {
            $error_message = "Cari hesap eklenirken bir hata oluştu: " . $e->getMessage();
        }
    } else {
        $error_message = "Lütfen ad/firma ve tür alanlarını doldurunuz.";
    }
}

// Cari hesap silme işlemi
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        // Önce bu cari hesaba bağlı gelir/gider kaydı var mı kontrol et
        $stmt = $db->prepare("
            SELECT 
                (SELECT COUNT(*) FROM incomes WHERE customer_id = :id) +
                (SELECT COUNT(*) FROM expenses WHERE supplier_id = :id) AS total_count
        ");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $related_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_count'];
        
        if ($related_count > 0) {
            $error_message = "Bu cari hesaba ait gelir veya gider kayıtları bulunmaktadır. Önce ilgili kayıtları silmelisiniz.";
        } else {
            $stmt = $db->prepare("DELETE FROM customers WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $success_message = "Cari hesap başarıyla silindi.";
        }
    } catch (PDOException $e) {
        $error_message = "Cari hesap silinirken bir hata oluştu: " . $e->getMessage();
    }
}

// Cari hesapları al
try {
    $stmt = $db->query("SELECT * FROM customers ORDER BY name");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<p>Cari hesaplar yüklenirken hata oluştu: " . $e->getMessage() . "</p>";
    $customers = [];
}

// Şablon başlığını ayarla
$page_title = "Cari Hesap Yönetimi";
$active_page = "customers";

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
        <h2>Cari Hesap Ekle</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="name">Ad Soyad / Firma Adı</label>
                <input type="text" id="name" name="name" placeholder="Ad Soyad veya Firma Adı" required>
            </div>
            <div class="form-group">
                <label for="type">Tür</label>
                <select id="type" name="type" required>
                    <option value="">Seçiniz...</option>
                    <option value="müşteri">Müşteri</option>
                    <option value="tedarikçi">Tedarikçi</option>
                    <option value="her ikisi">Her İkisi</option>
                </select>
            </div>
            <div class="form-group">
                <label for="phone">Telefon</label>
                <input type="text" id="phone" name="phone" placeholder="Telefon Numarası">
            </div>
            <div class="form-group">
                <label for="email">E-posta</label>
                <input type="email" id="email" name="email" placeholder="E-posta Adresi">
            </div>
            <button type="submit" name="save_customer" class="btn">Cari Hesap Ekle</button>
        </form>
    </div>

    <div class="card">
        <h2>Cari Hesaplar</h2>
        <table>
            <thead>
                <tr>
                    <th>Ad Soyad / Firma</th>
                    <th>Tür</th>
                    <th>Telefon</th>
                    <th>E-posta</th>
                    <th>Bakiye</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($customers) > 0): ?>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?php echo $customer['name']; ?></td>
                            <td><?php echo ucfirst($customer['type']); ?></td>
                            <td><?php echo $customer['phone']; ?></td>
                            <td><?php echo $customer['email']; ?></td>
                            <td class="<?php echo $customer['balance'] >= 0 ? 'income' : 'expense'; ?>">
                                <?php echo number_format(abs($customer['balance']), 2, ',', '.') . ' ₺'; ?>
                            </td>
                            <td>
                                <a href="customers.php?delete=<?php echo $customer['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bu cari hesabı silmek istediğinize emin misiniz?')">Sil</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">Henüz kayıtlı cari hesap bulunmamaktadır.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Alt kısmı dahil et
include "inc/footer.php";
?>