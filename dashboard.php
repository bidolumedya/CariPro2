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

// Toplam gelir hesapla
$stmt = $db->query("SELECT SUM(amount) as total FROM incomes");
$total_income = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;

// Toplam gider hesapla
$stmt = $db->query("SELECT SUM(amount) as total FROM expenses");
$total_expense = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;

// Net bakiye hesapla
$net_balance = $total_income - $total_expense;

// Cari alacakları hesapla (pozitif bakiyeler)
$stmt = $db->query("SELECT SUM(balance) as total FROM customers WHERE balance > 0");
$total_receivables = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;

// Cari borçları hesapla (negatif bakiyeler)
$stmt = $db->query("SELECT SUM(ABS(balance)) as total FROM customers WHERE balance < 0");
$total_payables = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;

// Son işlemleri al (gelir ve giderler)
$stmt = $db->query("
    (SELECT i.date, 'Gelir' as type, i.category, i.description, i.amount 
     FROM incomes i
     ORDER BY i.date DESC
     LIMIT 5)
    UNION ALL
    (SELECT e.date, 'Gider' as type, e.category, e.description, -e.amount
     FROM expenses e
     ORDER BY e.date DESC
     LIMIT 5)
    ORDER BY date DESC
    LIMIT 10
");
$recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Şablon başlığını ayarla
$page_title = "Gösterge Paneli";
$active_page = "dashboard";

// Üst kısmı dahil et
include "inc/header.php";
?>

<!-- Ana içerik -->
<div class="container">
    <div class="summary-box">
        <div class="summary-card">
            <h3>Toplam Gelir</h3>
            <div class="summary-value income"><?php echo number_format($total_income, 2, ',', '.') . ' ₺'; ?></div>
        </div>
        <div class="summary-card">
            <h3>Toplam Gider</h3>
            <div class="summary-value expense"><?php echo number_format($total_expense, 2, ',', '.') . ' ₺'; ?></div>
        </div>
        <div class="summary-card">
            <h3>Net Bakiye</h3>
            <div class="summary-value <?php echo $net_balance >= 0 ? 'income' : 'expense'; ?>"><?php echo number_format($net_balance, 2, ',', '.') . ' ₺'; ?></div>
        </div>
        <div class="summary-card">
            <h3>Cari Alacak</h3>
            <div class="summary-value income"><?php echo number_format($total_receivables, 2, ',', '.') . ' ₺'; ?></div>
        </div>
        <div class="summary-card">
            <h3>Cari Borç</h3>
            <div class="summary-value expense"><?php echo number_format($total_payables, 2, ',', '.') . ' ₺'; ?></div>
        </div>
    </div>

    <div class="card">
        <h2>Son İşlemler</h2>
        <table>
            <thead>
                <tr>
                    <th>Tarih</th>
                    <th>Tür</th>
                    <th>Kategori</th>
                    <th>Açıklama</th>
                    <th>Tutar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($recent_transactions) > 0): ?>
                    <?php foreach ($recent_transactions as $transaction): ?>
                        <tr>
                            <td><?php echo date('d.m.Y', strtotime($transaction['date'])); ?></td>
                            <td><?php echo $transaction['type']; ?></td>
                            <td><?php echo ucfirst($transaction['category']); ?></td>
                            <td><?php echo $transaction['description']; ?></td>
                            <td class="<?php echo $transaction['amount'] >= 0 ? 'income' : 'expense'; ?>">
                                <?php echo number_format(abs($transaction['amount']), 2, ',', '.') . ' ₺'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">Henüz kayıtlı işlem bulunmamaktadır.</td>
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