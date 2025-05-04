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

// Ürün ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $name = $_POST['name'];
    $price = (float)$_POST['price'];
    $description = $_POST['description'];
    
    if (!empty($name) && $price > 0) {
        try {
            $stmt = $db->prepare("INSERT INTO products (name, price, description) VALUES (:name, :price, :description)");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':description', $description);
            $stmt->execute();
            
            $success_message = "Ürün başarıyla eklendi.";
        } catch (PDOException $e) {
            $error_message = "Ürün eklenirken bir hata oluştu: " . $e->getMessage();
        }
    } else {
        $error_message = "Lütfen ürün adı ve fiyatını doldurunuz.";
    }
}

// Ürün silme işlemi
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        // Önce bu ürüne bağlı gelir detayı var mı kontrol et
        $stmt = $db->prepare("SELECT COUNT(*) AS total_count FROM income_items WHERE product_id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $related_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_count'];
        
        if ($related_count > 0) {
            $error_message = "Bu ürüne ait gelir kayıtları bulunmaktadır. Önce ilgili kayıtları silmelisiniz.";
        } else {
            $stmt = $db->prepare("DELETE FROM products WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $success_message = "Ürün başarıyla silindi.";
        }
    } catch (PDOException $e) {
        $error_message = "Ürün silinirken bir hata oluştu: " . $e->getMessage();
    }
}

// Ürün düzenleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $id = (int)$_POST['id'];
    $name = $_POST['name'];
    $price = (float)$_POST['price'];
    $description = $_POST['description'];
    
    if (!empty($name) && $price > 0 && $id > 0) {
        try {
            $stmt = $db->prepare("UPDATE products SET name = :name, price = :price, description = :description WHERE id = :id");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $success_message = "Ürün başarıyla güncellendi.";
        } catch (PDOException $e) {
            $error_message = "Ürün güncellenirken bir hata oluştu: " . $e->getMessage();
        }
    } else {
        $error_message = "Lütfen ürün adı ve fiyatını doldurunuz.";
    }
}

// Ürünleri al
try {
    $stmt = $db->query("SELECT * FROM products ORDER BY name");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<p>Ürünler yüklenirken hata oluştu: " . $e->getMessage() . "</p>";
    $products = [];
}

// Şablon başlığını ayarla
$page_title = "Ürün Yönetimi";
$active_page = "products";

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
        <h2>Ürün Ekle</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="name">Ürün Adı</label>
                <input type="text" id="name" name="name" placeholder="Ürün adını girin" required>
            </div>
            <div class="form-group">
                <label for="price">Fiyat (₺)</label>
                <input type="number" id="price" name="price" step="0.01" min="0" placeholder="0.00" required>
            </div>
            <div class="form-group">
                <label for="description">Açıklama</label>
                <textarea id="description" name="description" rows="3" placeholder="Ürün açıklaması..."></textarea>
            </div>
            <button type="submit" name="save_product" class="btn">Ürün Ekle</button>
        </form>
    </div>

    <div class="card">
        <h2>Ürünler</h2>
        <table>
            <thead>
                <tr>
                    <th>Ürün Adı</th>
                    <th>Fiyat</th>
                    <th>Açıklama</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo $product['name']; ?></td>
                            <td class="income"><?php echo number_format($product['price'], 2, ',', '.') . ' ₺'; ?></td>
                            <td><?php echo $product['description']; ?></td>
                            <td>
                                <button class="btn btn-sm edit-btn" data-id="<?php echo $product['id']; ?>" 
                                        data-name="<?php echo $product['name']; ?>"
                                        data-price="<?php echo $product['price']; ?>"
                                        data-description="<?php echo htmlspecialchars($product['description']); ?>">Düzenle</button>
                                <a href="products.php?delete=<?php echo $product['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bu ürünü silmek istediğinize emin misiniz?')">Sil</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center">Henüz kayıtlı ürün bulunmamaktadır.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Düzenleme Modalı -->
<div id="editModal" class="modal hidden">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Ürün Düzenle</h2>
        <form method="POST" action="">
            <input type="hidden" id="edit_id" name="id">
            <div class="form-group">
                <label for="edit_name">Ürün Adı</label>
                <input type="text" id="edit_name" name="name" required>
            </div>
            <div class="form-group">
                <label for="edit_price">Fiyat (₺)</label>
                <input type="number" id="edit_price" name="price" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label for="edit_description">Açıklama</label>
                <textarea id="edit_description" name="description" rows="3"></textarea>
            </div>
            <button type="submit" name="update_product" class="btn">Güncelle</button>
        </form>
    </div>
</div>

<?php
// Alt kısmı dahil et
include "inc/footer.php";
?>

<style>
/* Modal Stili */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal.show {
    display: block;
}

.modal-content {
    background-color: white;
    margin: 10% auto;
    padding: 20px;
    border-radius: 5px;
    width: 50%;
    max-width: 500px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: #333;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Düzenleme modalını al
    var modal = document.getElementById('editModal');
    var span = document.getElementsByClassName("close")[0];
    
    // Düzenle butonlarına tıklama olayı ekle
    var editButtons = document.querySelectorAll('.edit-btn');
    editButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var name = this.getAttribute('data-name');
            var price = this.getAttribute('data-price');
            var description = this.getAttribute('data-description');
            
            // Form alanlarını doldur
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_price').value = price;
            document.getElementById('edit_description').value = description;
            
            // Modalı göster
            modal.classList.add('show');
        });
    });
    
    // Kapama butonuna tıklama olayı
    span.onclick = function() {
        modal.classList.remove('show');
    }
    
    // Modalın dışına tıklayınca kapat
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.classList.remove('show');
        }
    }
});
</script>