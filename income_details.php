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

// Gelir ID'sini kontrol et
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: incomes.php");
    exit;
}

$income_id = (int)$_GET['id'];

// Gelir kaydını al
try {
    $stmt = $db->prepare("
        SELECT i.*, c.name as customer_name, cat.name as category_name
        FROM incomes i
        LEFT JOIN customers c ON i.customer_id = c.id
        LEFT JOIN categories cat ON i.category_id = cat.id
        WHERE i.id = :id
    ");
    $stmt->bindParam(':id', $income_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        header("Location: incomes.php");
        exit;
    }
    
    $income = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Gelir kaydı yüklenirken bir hata oluştu: " . $e->getMessage());
}

// Gelir detaylarını (ürünleri) al
try {
    $stmt = $db->prepare("
        SELECT ii.*, p.name as product_name
        FROM income_items ii
        LEFT JOIN products p ON ii.product_id = p.id
        WHERE ii.income_id = :income_id
    ");
    $stmt->bindParam(':income_id', $income_id);
    $stmt->execute();
    $income_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Gelir detayları yüklenirken bir hata oluştu: " . $e->getMessage());
}

// Şablon başlığını ayarla
$page_title = "Gelir Detayları";
$active_page = "incomes";

// Üst kısmı dahil et
include "inc/header.php";
?>

<!-- Ana içerik -->
<div class="container">
    <div class="card">
        <h2>Gelir Detayları</h2>
        <div class="details-container">
            <div class="details-group">
                <div class="details-label">Tarih:</div>
                <div class="details-value"><?php echo date('d.m.Y', strtotime($income['date'])); ?></div>
            </div>
            <div class="details-group">
                <div class="details-label">Kategori:</div>
                <div class="details-value"><?php echo $income['category_name']; ?></div>
            </div>
            <div class="details-group">
                <div class="details-label">Müşteri:</div>
                <div class="details-value"><?php echo $income['customer_name']; ?></div>
            </div>
            <div class="details-group">
                <div class="details-label">Toplam Tutar:</div>
                <div class="details-value income"><?php echo number_format($income['amount'], 2, ',', '.') . ' ₺'; ?></div>
            </div>
            <div class="details-group">
                <div class="details-label">Açıklama:</div>
                <div class="details-value"><?php echo $income['description'] ?: 'Açıklama yok'; ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Ürünler</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Ürün</th>
                    <th>Miktar</th>
                    <th>Birim Fiyat</th>
                    <th>Tutar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($income_items) > 0): ?>
                    <?php $i = 1; foreach ($income_items as $item): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo $item['product_name']; ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo number_format($item['price'], 2, ',', '.') . ' ₺'; ?></td>
                            <td class="income"><?php echo number_format($item['amount'], 2, ',', '.') . ' ₺'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">Bu gelir kaydı için ürün detayı bulunmamaktadır.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="button-container">
        <a href="incomes.php" class="btn">Gelir Listesine Dön</a>
        <a href="incomes.php?delete=<?php echo $income_id; ?>" class="btn btn-danger" onclick="return confirm('Bu gelir kaydını silmek istediğinize emin misiniz?')">Gelir Kaydını Sil</a>
    </div>
</div>

<?php
// Alt kısmı dahil et
include "inc/footer.php";
?>

<style>
.details-container {
    margin-bottom: 20px;
}

.details-group {
    display: flex;
    margin-bottom: 10px;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.details-label {
    font-weight: bold;
    width: 150px;
    color: #333;
}

.details-value {
    flex: 1;
}

.button-container {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
}
</style>