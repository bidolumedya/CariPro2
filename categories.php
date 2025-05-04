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

// Kategori ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    $name = $_POST['name'];
    $type = $_POST['type'];
    
    if (!empty($name) && !empty($type)) {
        try {
            $stmt = $db->prepare("INSERT INTO categories (name, type) VALUES (:name, :type)");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':type', $type);
            $stmt->execute();
            
            $success_message = "Kategori başarıyla eklendi.";
        } catch (PDOException $e) {
            $error_message = "Kategori eklenirken bir hata oluştu: " . $e->getMessage();
        }
    } else {
        $error_message = "Lütfen tüm alanları doldurunuz.";
    }
}

// Kategori silme işlemi
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        // Önce bu kategoriye bağlı gelir/gider kaydı var mı kontrol et
        $stmt = $db->prepare("
            SELECT 
                (SELECT COUNT(*) FROM incomes WHERE category_id = :id) +
                (SELECT COUNT(*) FROM expenses WHERE category_id = :id) AS total_count
        ");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $related_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_count'];
        
        if ($related_count > 0) {
            $error_message = "Bu kategoriye ait gelir veya gider kayıtları bulunmaktadır. Önce ilgili kayıtları silmelisiniz.";
        } else {
            $stmt = $db->prepare("DELETE FROM categories WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $success_message = "Kategori başarıyla silindi.";
        }
    } catch (PDOException $e) {
        $error_message = "Kategori silinirken bir hata oluştu: " . $e->getMessage();
    }
}

// Kategori düzenleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $id = (int)$_POST['id'];
    $name = $_POST['name'];
    
    if (!empty($name) && $id > 0) {
        try {
            $stmt = $db->prepare("UPDATE categories SET name = :name WHERE id = :id");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $success_message = "Kategori başarıyla güncellendi.";
        } catch (PDOException $e) {
            $error_message = "Kategori güncellenirken bir hata oluştu: " . $e->getMessage();
        }
    } else {
        $error_message = "Lütfen bir kategori adı giriniz.";
    }
}

// Kategorileri al
try {
    $stmt = $db->query("SELECT * FROM categories ORDER BY type, name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<p>Kategoriler yüklenirken hata oluştu: " . $e->getMessage() . "</p>";
    $categories = [];
}

// Şablon başlığını ayarla
$page_title = "Kategori Yönetimi";
$active_page = "categories";

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
        <h2>Kategori Ekle</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="name">Kategori Adı</label>
                <input type="text" id="name" name="name" placeholder="Kategori adını girin" required>
            </div>
            <div class="form-group">
                <label for="type">Tür</label>
                <select id="type" name="type" required>
                    <option value="">Seçiniz...</option>
                    <option value="gelir">Gelir</option>
                    <option value="gider">Gider</option>
                </select>
            </div>
            <button type="submit" name="save_category" class="btn">Kategori Ekle</button>
        </form>
    </div>

    <div class="card">
        <h2>Gelir Kategorileri</h2>
        <table>
            <thead>
                <tr>
                    <th>Kategori Adı</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $incomeCategories = array_filter($categories, function($category) {
                    return $category['type'] === 'gelir';
                });
                
                if (count($incomeCategories) > 0): ?>
                    <?php foreach ($incomeCategories as $category): ?>
                        <tr>
                            <td>
                                <form method="POST" action="" class="edit-form">
                                    <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                    <input type="text" name="name" value="<?php echo $category['name']; ?>" class="inline-edit">
                                    <button type="submit" name="update_category" class="btn btn-sm">Kaydet</button>
                                </form>
                            </td>
                            <td>
                                <a href="categories.php?delete=<?php echo $category['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bu kategoriyi silmek istediğinize emin misiniz?')">Sil</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2" class="text-center">Henüz kayıtlı gelir kategorisi bulunmamaktadır.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Gider Kategorileri</h2>
        <table>
            <thead>
                <tr>
                    <th>Kategori Adı</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $expenseCategories = array_filter($categories, function($category) {
                    return $category['type'] === 'gider';
                });
                
                if (count($expenseCategories) > 0): ?>
                    <?php foreach ($expenseCategories as $category): ?>
                        <tr>
                            <td>
                                <form method="POST" action="" class="edit-form">
                                    <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                    <input type="text" name="name" value="<?php echo $category['name']; ?>" class="inline-edit">
                                    <button type="submit" name="update_category" class="btn btn-sm">Kaydet</button>
                                </form>
                            </td>
                            <td>
                                <a href="categories.php?delete=<?php echo $category['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bu kategoriyi silmek istediğinize emin misiniz?')">Sil</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2" class="text-center">Henüz kayıtlı gider kategorisi bulunmamaktadır.</td>
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

<style>
.edit-form {
    display: flex;
    align-items: center;
}

.inline-edit {
    flex: 1;
    margin-right: 10px;
}
</style>