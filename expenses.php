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

// Gider kaydı ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_expense'])) {
    $date = $_POST['date'];
    $category = $_POST['category'];
    $supplier_id = (int)$_POST['supplier_id'];
    $amount = (float)$_POST['amount'];
    $description = $_POST['description'];
    
    if (!empty($date) && !empty($category) && $supplier_id > 0 && $amount > 0) {
        try {
            // Transaction başlat
            $db->beginTransaction();
            
            // Gider kaydını ekle
            $stmt = $db->prepare("INSERT INTO expenses (date, category, supplier_id, amount, description) VALUES (:date, :category, :supplier_id, :amount, :description)");
            $stmt->bindParam(':date', $date);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':supplier_id', $supplier_id);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':description', $description);
            $stmt->execute();
            
            // Tedarikçi bakiyesini güncelle
            $stmt = $db->prepare("UPDATE customers SET balance = balance - :amount WHERE id = :supplier_id");
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':supplier_id', $supplier_id);
            $stmt->execute();
            
            // Transaction'ı commit et
            $db->commit();
            
            $success_message = "Gider kaydı başarıyla eklendi.";
        } catch (PDOException $e) {
            // Hata durumunda rollback yap
            $db->rollBack();
            $error_message = "Gider kaydı eklenirken bir hata oluştu: " . $e->getMessage();
        }
    } else {
        $error_message = "Lütfen tüm alanları doldurunuz.";
    }
}

// Gider kaydı silme işlemi
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        // Transaction başlat
        $db->beginTransaction();
        
        // Önce gider kaydının detaylarını al
        $stmt = $db->prepare("SELECT supplier_id, amount FROM expenses WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $expense = $stmt->fetch(PDO::FETCH_ASSOC);
            $supplier_id = $expense['supplier_id'];
            $amount = $expense['amount'];
            
            // Tedarikçi bakiyesini güncelle
            $stmt = $db->prepare("UPDATE customers SET balance = balance + :amount WHERE id = :supplier_id");
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':supplier_id', $supplier_id);
            $stmt->execute();
            
            // Gider kaydını sil
            $stmt = $db->prepare("DELETE FROM expenses WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Transaction'ı commit et
            $db->commit();
            
            $success_message = "Gider kaydı başarıyla silindi.";
        }
    } catch (PDOException $e) {
        // Hata durumunda rollback yap
        $db->rollBack();
        $error_message = "Gider kaydı silinirken bir hata oluştu: " . $e->getMessage();
    }
}

// Tedarikçileri al (sadece tedarikçi ve her ikisi olanları)
try {
    // IN operatörünü kullanmak yerine OR operatörünü kullan
    $stmt = $db->query("SELECT id, name FROM customers WHERE type = 'tedarikçi' OR type = 'her ikisi' ORDER BY name");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<p>Tedarikçiler yüklenirken hata oluştu: " . $e->getMessage() . "</p>";
    $suppliers = [];
}

// Gider kayıtlarını al
try {
    $stmt = $db->query("
        SELECT e.*, c.name as supplier_name 
        FROM expenses e
        LEFT JOIN customers c ON e.supplier_id = c.id
        ORDER BY e.date DESC
    ");
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<p>Gider kayıtları yüklenirken hata oluştu: " . $e->getMessage() . "</p>";
    $expenses = [];
}

// Şablon başlığını ayarla
$page_title = "Gider Yönetimi";
$active_page = "expenses";

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
        <h2>Gider Ekle</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="date">Tarih</label>
                <input type="date" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
                <label for="category">Kategori</label>
                <select id="category" name="category" required>
                    <option value="">Seçiniz...</option>
                    <option value="kira">Kira</option>
                    <option value="elektrik">Elektrik</option>
                    <option value="su">Su</option>
                    <option value="internet">İnternet</option>
                    <option value="malzeme">Malzeme Alımı</option>
                    <option value="maaş">Maaş</option>
                    <option value="vergi">Vergi</option>
                    <option value="diğer">Diğer</option>
                </select>
            </div>
            <div class="form-group">
                <label for="supplier_id">Tedarikçi / Cari Hesap</label>
                <select id="supplier_id" name="supplier_id" required>
                    <option value="0">Seçiniz...</option>
                    <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?php echo $supplier['id']; ?>"><?php echo $supplier['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="amount">Tutar (₺)</label>
                <input type="number" id="amount" name="amount" step="0.01" min="0" placeholder="0.00" required>
            </div>
            <div class="form-group">
                <label for="description">Açıklama</label>
                <textarea id="description" name="description" rows="3" placeholder="Açıklama girin..."></textarea>
            </div>
            <button type="submit" name="save_expense" class="btn btn-danger">Gider Ekle</button>
        </form>
    </div>

    <div class="card">
        <h2>Gider Kayıtları</h2>
        <table>
            <thead>
                <tr>
                    <th>Tarih</th>
                    <th>Kategori</th>
                    <th>Tedarikçi</th>
                    <th>Açıklama</th>
                    <th>Tutar</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($expenses) > 0): ?>
                    <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td><?php echo date('d.m.Y', strtotime($expense['date'])); ?></td>
                            <td><?php echo ucfirst($expense['category']); ?></td>
                            <td><?php echo $expense['supplier_name']; ?></td>
                            <td><?php echo $expense['description']; ?></td>
                            <td class="expense"><?php echo number_format($expense['amount'], 2, ',', '.') . ' ₺'; ?></td>
                            <td>
                                <a href="expenses.php?delete=<?php echo $expense['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bu gider kaydını silmek istediğinize emin misiniz?')">Sil</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">Henüz kayıtlı gider bulunmamaktadır.</td>
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