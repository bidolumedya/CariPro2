<?php
// Oturumu başlat
session_start();

// Kullanıcı giriş yapmamışsa login sayfasına yönlendir
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Veritabanı bağlantısını dahil et
require_once "db_config.php";

// Filtre için başlangıç ve bitiş tarihlerini ayarla
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01'); // Bu ayın başlangıcı
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d'); // Bugün

// Rapor oluşturuldu mu kontrol et
$report_generated = false;

// Rapor oluşturma işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    $report_generated = true;
    
    // Filtrelenmiş gelir kayıtlarını al
    $stmt = $db->prepare("
        SELECT i.*, c.name as customer_name 
        FROM incomes i
        LEFT JOIN customers c ON i.customer_id = c.id
        WHERE i.date BETWEEN :start_date AND :end_date
        ORDER BY i.date
    ");
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $filtered_incomes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filtrelenmiş gider kayıtlarını al
    $stmt = $db->prepare("
        SELECT e.*, c.name as supplier_name 
        FROM expenses e
        LEFT JOIN customers c ON e.supplier_id = c.id
        WHERE e.date BETWEEN :start_date AND :end_date
        ORDER BY e.date
    ");
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $filtered_expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Toplam değerleri hesapla
    $total_income = 0;
    foreach ($filtered_incomes as $income) {
        $total_income += $income['amount'];
    }
    
    $total_expense = 0;
    foreach ($filtered_expenses as $expense) {
        $total_expense += $expense['amount'];
    }
    
    $balance = $total_income - $total_expense;
    
    // Kategori bazında gelirler
    $income_by_category = [];
    foreach ($filtered_incomes as $income) {
        $category = $income['category'];
        if (!isset($income_by_category[$category])) {
            $income_by_category[$category] = 0;
        }
        $income_by_category[$category] += $income['amount'];
    }
    
    // Kategori bazında giderler
    $expense_by_category = [];
    foreach ($filtered_expenses as $expense) {
        $category = $expense['category'];
        if (!isset($expense_by_category[$category])) {
            $expense_by_category[$category] = 0;
        }
        $expense_by_category[$category] += $expense['amount'];
    }
}

// Şablon başlığını ayarla
$page_title = "Raporlar";
$active_page = "reports";

// Üst kısmı dahil et
include "inc/header.php";
?>

<!-- Ana içerik -->
<div class="container">
    <div class="card">
        <h2>Gelir Gider Raporu</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="start_date">Başlangıç Tarihi</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" required>
            </div>
            <div class="form-group">
                <label for="end_date">Bitiş Tarihi</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" required>
            </div>
            <button type="submit" name="generate_report" class="btn">Rapor Oluştur</button>
        </form>
    </div>

    <?php if ($report_generated): ?>
    <div class="card">
        <h2>Rapor Sonuçları</h2>
        <p>
            <strong><?php echo date('d.m.Y', strtotime($start_date)); ?></strong> ile 
            <strong><?php echo date('d.m.Y', strtotime($end_date)); ?></strong> 
            tarihleri arasındaki finansal rapor
        </p>
        
        <div class="summary-box">
            <div class="summary-card">
                <h3>Dönem Geliri</h3>
                <div class="summary-value income"><?php echo number_format($total_income, 2, ',', '.') . ' ₺'; ?></div>
            </div>
            <div class="summary-card">
                <h3>Dönem Gideri</h3>
                <div class="summary-value expense"><?php echo number_format($total_expense, 2, ',', '.') . ' ₺'; ?></div>
            </div>
            <div class="summary-card">
                <h3>Dönem Bakiyesi</h3>
                <div class="summary-value <?php echo $balance >= 0 ? 'income' : 'expense'; ?>"><?php echo number_format($balance, 2, ',', '.') . ' ₺'; ?></div>
            </div>
        </div>

        <h3>Kategori Bazında Gelirler</h3>
        <table>
            <thead>
                <tr>
                    <th>Kategori</th>
                    <th>Toplam</th>
                    <th>Yüzde</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($income_by_category) > 0): ?>
                    <?php foreach ($income_by_category as $category => $amount): ?>
                        <tr>
                            <td><?php echo ucfirst($category); ?></td>
                            <td class="income"><?php echo number_format($amount, 2, ',', '.') . ' ₺'; ?></td>
                            <td><?php echo number_format(($amount / $total_income) * 100, 2, ',', '.') . '%'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center">Bu dönemde gelir kaydı bulunmamaktadır.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h3>Kategori Bazında Giderler</h3>
        <table>
            <thead>
                <tr>
                    <th>Kategori</th>
                    <th>Toplam</th>
                    <th>Yüzde</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($expense_by_category) > 0): ?>
                    <?php foreach ($expense_by_category as $category => $amount): ?>
                        <tr>
                            <td><?php echo ucfirst($category); ?></td>
                            <td class="expense"><?php echo number_format($amount, 2, ',', '.') . ' ₺'; ?></td>
                            <td><?php echo number_format(($amount / $total_expense) * 100, 2, ',', '.') . '%'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center">Bu dönemde gider kaydı bulunmamaktadır.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php
// Alt kısmı dahil et
include "inc/footer.php";
?>