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

// Gelir kaydı ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_income'])) {
    $date = $_POST['date'];
    $category_id = (int)$_POST['category_id'];
    $customer_name = trim($_POST['customer_name']);
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $description = $_POST['description'];
    
    // Ürün bilgilerini al
    $product_ids = isset($_POST['product_id']) ? $_POST['product_id'] : [];
    $new_product_names = isset($_POST['new_product_name']) ? $_POST['new_product_name'] : [];
    $quantities = isset($_POST['quantity']) ? $_POST['quantity'] : [];
    $prices = isset($_POST['price']) ? $_POST['price'] : [];
    
    // Toplam tutarı hesapla
    $total_amount = 0;
    foreach ($prices as $key => $price) {
        if (isset($quantities[$key]) && is_numeric($price) && is_numeric($quantities[$key])) {
            $total_amount += (float)$price * (float)$quantities[$key];
        }
    }
    
    if (!empty($date) && $category_id > 0 && !empty($customer_name) && $total_amount > 0) {
        try {
            // Transaction başlat
            $db->beginTransaction();
            
            // Eğer müşteri ID'si yoksa ve müşteri adı girildiyse, yeni müşteri oluştur
            if ($customer_id == 0 && !empty($customer_name)) {
                // Önce bu isimde müşteri var mı kontrol et
                $stmt = $db->prepare("SELECT id FROM customers WHERE name = :name");
                $stmt->bindParam(':name', $customer_name);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    // Varolan müşteriyi kullan
                    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                    $customer_id = $customer['id'];
                } else {
                    // Yeni müşteri oluştur
                    $stmt = $db->prepare("INSERT INTO customers (name, type) VALUES (:name, 'müşteri')");
                    $stmt->bindParam(':name', $customer_name);
                    $stmt->execute();
                    $customer_id = $db->lastInsertId();
                }
            }
            
            // Gelir kaydını ekle
            $stmt = $db->prepare("INSERT INTO incomes (date, category_id, customer_id, amount, description) VALUES (:date, :category_id, :customer_id, :amount, :description)");
            $stmt->bindParam(':date', $date);
            $stmt->bindParam(':category_id', $category_id);
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->bindParam(':amount', $total_amount);
            $stmt->bindParam(':description', $description);
            $stmt->execute();
            
            $income_id = $db->lastInsertId();
            
            // Ürün detaylarını kaydet
            foreach ($product_ids as $key => $product_id) {
                $quantity = (float)$quantities[$key];
                $price = (float)$prices[$key];
                $amount = $quantity * $price;
                
                // Yeni ürün ekle
                if ($product_id === 'new' && !empty($new_product_names[$key])) {
                    $new_product_name = trim($new_product_names[$key]);
                    
                    // Önce bu isimde ürün var mı kontrol et
                    $stmt = $db->prepare("SELECT id FROM products WHERE name = :name");
                    $stmt->bindParam(':name', $new_product_name);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        // Varolan ürünü kullan
                        $product = $stmt->fetch(PDO::FETCH_ASSOC);
                        $product_id = $product['id'];
                    } else {
                        // Yeni ürün oluştur
                        $stmt = $db->prepare("INSERT INTO products (name, price) VALUES (:name, :price)");
                        $stmt->bindParam(':name', $new_product_name);
                        $stmt->bindParam(':price', $price);
                        $stmt->execute();
                        $product_id = $db->lastInsertId();
                    }
                }
                
                if (!empty($product_id) && $product_id != 'new' && $quantity > 0 && $price > 0) {
                    $stmt = $db->prepare("INSERT INTO income_items (income_id, product_id, quantity, price, amount) VALUES (:income_id, :product_id, :quantity, :price, :amount)");
                    $stmt->bindParam(':income_id', $income_id);
                    $stmt->bindParam(':product_id', $product_id);
                    $stmt->bindParam(':quantity', $quantity);
                    $stmt->bindParam(':price', $price);
                    $stmt->bindParam(':amount', $amount);
                    $stmt->execute();
                }
            }
            
            // Müşteri bakiyesini güncelle
            $stmt = $db->prepare("UPDATE customers SET balance = balance + :amount WHERE id = :customer_id");
            $stmt->bindParam(':amount', $total_amount);
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->execute();
            
            // Transaction'ı commit et
            $db->commit();
            
            $success_message = "Gelir kaydı başarıyla eklendi.";
        } catch (PDOException $e) {
            // Hata durumunda rollback yap
            $db->rollBack();
            $error_message = "Gelir kaydı eklenirken bir hata oluştu: " . $e->getMessage();
        }
    } else {
        $error_message = "Lütfen tüm alanları doldurunuz ve en az bir ürün ekleyiniz.";
    }
}

// Gelir kaydı silme işlemi
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        // Transaction başlat
        $db->beginTransaction();
        
        // Önce gelir kaydının detaylarını al
        $stmt = $db->prepare("SELECT customer_id, amount FROM incomes WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $income = $stmt->fetch(PDO::FETCH_ASSOC);
            $customer_id = $income['customer_id'];
            $amount = $income['amount'];
            
            // Müşteri bakiyesini güncelle
            $stmt = $db->prepare("UPDATE customers SET balance = balance - :amount WHERE id = :customer_id");
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->execute();
            
            // Gelir detaylarını sil (income_items tablosundaki kayıtlar CASCADE ile otomatik silinecek)
            $stmt = $db->prepare("DELETE FROM incomes WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Transaction'ı commit et
            $db->commit();
            
            $success_message = "Gelir kaydı başarıyla silindi.";
        }
    } catch (PDOException $e) {
        // Hata durumunda rollback yap
        $db->rollBack();
        $error_message = "Gelir kaydı silinirken bir hata oluştu: " . $e->getMessage();
    }
}

// Kategorileri al (sadece gelir kategorileri)
try {
    $stmt = $db->query("SELECT id, name FROM categories WHERE type = 'gelir' ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<p>Kategoriler yüklenirken hata oluştu: " . $e->getMessage() . "</p>";
    $categories = [];
}

// Müşterileri al (sadece müşteri ve her ikisi olanları)
try {
    // IN operatörünü kullanmak yerine OR operatörünü kullan
    $stmt = $db->query("SELECT id, name FROM customers WHERE type = 'müşteri' OR type = 'her ikisi' ORDER BY name");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<p>Müşteriler yüklenirken hata oluştu: " . $e->getMessage() . "</p>";
    $customers = [];
}

// Ürünleri al
try {
    $stmt = $db->query("SELECT id, name, price FROM products ORDER BY name");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<p>Ürünler yüklenirken hata oluştu: " . $e->getMessage() . "</p>";
    $products = [];
}

// Gelir kayıtlarını al
try {
    $stmt = $db->query("
        SELECT i.*, c.name as customer_name, cat.name as category_name
        FROM incomes i
        LEFT JOIN customers c ON i.customer_id = c.id
        LEFT JOIN categories cat ON i.category_id = cat.id
        ORDER BY i.date DESC
    ");
    $incomes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<p>Gelir kayıtları yüklenirken hata oluştu: " . $e->getMessage() . "</p>";
    $incomes = [];
}

// Şablon başlığını ayarla
$page_title = "Gelir Yönetimi";
$active_page = "incomes";

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
        <h2>Gelir Ekle</h2>
        <form method="POST" action="" id="incomeForm">
            <div class="form-group">
                <label for="date">Tarih</label>
                <input type="date" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
                <label for="category_id">Kategori</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Seçiniz...</option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>"><?php echo $category['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="customer_search">Müşteri / Cari Hesap</label>
                <div class="customer-search-container">
                    <input type="text" id="customer_search" name="customer_name" placeholder="Müşteri adı yazın veya seçin..." required>
                    <input type="hidden" id="customer_id" name="customer_id" value="0">
                    <div id="customerResults" class="dropdown-results"></div>
                </div>
                <small>Not: Müşteri bulunamazsa otomatik olarak yeni müşteri oluşturulacaktır.</small>
            </div>
            
            <div class="form-group">
                <label>Ürünler</label>
                <div id="productRows">
                    <div class="product-row">
                        <div class="product-select">
                            <select name="product_id[]" class="product-select-input" required>
                                <option value="">Ürün Seçin...</option>
                                <option value="new">+ Yeni Ürün Ekle</option>
                                <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['price']; ?>"><?php echo $product['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="new_product_name[]" class="new-product-input hidden" placeholder="Yeni ürün adı">
                        </div>
                        <div class="product-quantity">
                            <input type="number" name="quantity[]" min="1" value="1" step="0.01" class="quantity-input" required>
                        </div>
                        <div class="product-price">
                            <input type="number" name="price[]" min="0" step="0.01" class="price-input" placeholder="0.00" required>
                        </div>
                        <div class="product-total">
                            <input type="text" class="total-display" value="0.00 ₺" readonly>
                        </div>
                        <div class="product-actions">
                            <button type="button" class="btn btn-danger btn-sm remove-product">X</button>
                        </div>
                    </div>
                </div>
                <button type="button" id="addProductRow" class="btn btn-sm">+ Ürün Ekle</button>
            </div>
            
            <div class="form-group">
                <label>Toplam Tutar</label>
                <div class="total-amount-display">
                    <span id="grandTotal">0.00 ₺</span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Açıklama</label>
                <textarea id="description" name="description" rows="3" placeholder="Açıklama girin..."></textarea>
            </div>
            <button type="submit" name="save_income" class="btn btn-success">Gelir Ekle</button>
        </form>
    </div>

    <div class="card">
        <h2>Gelir Kayıtları</h2>
        <table>
            <thead>
                <tr>
                    <th>Tarih</th>
                    <th>Kategori</th>
                    <th>Müşteri</th>
                    <th>Açıklama</th>
                    <th>Tutar</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($incomes) > 0): ?>
                    <?php foreach ($incomes as $income): ?>
                        <tr>
                            <td><?php echo date('d.m.Y', strtotime($income['date'])); ?></td>
                            <td><?php echo $income['category_name']; ?></td>
                            <td><?php echo $income['customer_name']; ?></td>
                            <td><?php echo $income['description']; ?></td>
                            <td class="income"><?php echo number_format($income['amount'], 2, ',', '.') . ' ₺'; ?></td>
                            <td>
                                <a href="income_details.php?id=<?php echo $income['id']; ?>" class="btn btn-sm">Detay</a>
                                <a href="incomes.php?delete=<?php echo $income['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bu gelir kaydını silmek istediğinize emin misiniz?')">Sil</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">Henüz kayıtlı gelir bulunmamaktadır.</td>
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
/* Ürün satırı stilleri */
#productRows {
    margin-bottom: 10px;
}

.product-row {
    display: flex;
    margin-bottom: 5px;
    align-items: center;
}

.product-select {
    flex: 3;
    margin-right: 10px;
    position: relative;
}

.product-quantity {
    flex: 1;
    margin-right: 10px;
}

.product-price {
    flex: 1;
    margin-right: 10px;
}

.product-total {
    flex: 1;
    margin-right: 10px;
}

.product-actions {
    flex: 0;
}

.total-amount-display {
    background-color: #f8f8f8;
    padding: 10px;
    border-radius: 4px;
    font-size: 18px;
    font-weight: bold;
    text-align: right;
}

#grandTotal {
    color: #2ecc71;
}

.total-display {
    background-color: #f8f8f8;
    border: none;
    text-align: right;
    font-weight: bold;
}

/* Müşteri arama sonuçları */
.customer-search-container {
    position: relative;
}

.dropdown-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background-color: white;
    border: 1px solid #ddd;
    border-top: none;
    border-radius: 0 0 4px 4px;
    max-height: 200px;
    overflow-y: auto;
    z-index: 100;
    display: none;
}

.dropdown-results div {
    padding: 10px;
    cursor: pointer;
}

.dropdown-results div:hover {
    background-color: #f1f1f1;
}

.hidden {
    display: none;
}

.new-product-input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 16px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Müşteri arama işlemleri
    const customerSearchInput = document.getElementById('customer_search');
    const customerIdInput = document.getElementById('customer_id');
    const customerResults = document.getElementById('customerResults');
    
    // Müşteri listesini al
    const customers = <?php echo json_encode($customers); ?>;
    
    // Müşteri arama alanına her yazıldığında sonuçları göster
    customerSearchInput.addEventListener('input', function() {
        const searchValue = this.value.toLowerCase();
        
        // Eğer arama alanı boşsa sonuçları gizle
        if (searchValue === '') {
            customerResults.style.display = 'none';
            customerIdInput.value = '0'; // Müşteri ID'sini sıfırla
            return;
        }
        
        // Eşleşen müşterileri bul
        const matches = customers.filter(customer => 
            customer.name.toLowerCase().includes(searchValue)
        );
        
        // Sonuçları göster
        customerResults.innerHTML = '';
        
        if (matches.length > 0) {
            matches.forEach(customer => {
                const div = document.createElement('div');
                div.textContent = customer.name;
                div.addEventListener('click', function() {
                    customerSearchInput.value = customer.name;
                    customerIdInput.value = customer.id;
                    customerResults.style.display = 'none';
                });
                customerResults.appendChild(div);
            });
            customerResults.style.display = 'block';
        } else {
            // Eşleşme yoksa "Yeni müşteri oluştur" seçeneği göster
            const div = document.createElement('div');
            div.textContent = '"' + searchValue + '" adında yeni müşteri oluştur';
            div.addEventListener('click', function() {
                customerSearchInput.value = searchValue;
                customerIdInput.value = '0'; // Yeni müşteri için ID sıfır
                customerResults.style.display = 'none';
            });
            customerResults.appendChild(div);
            customerResults.style.display = 'block';
        }
    });
    
    // Arama alanı dışına tıklandığında sonuçları gizle
    document.addEventListener('click', function(e) {
        if (!customerSearchInput.contains(e.target) && !customerResults.contains(e.target)) {
            customerResults.style.display = 'none';
        }
    });
    
    // Ürün işlemleri
    const addProductRowBtn = document.getElementById('addProductRow');
    const productRowsContainer = document.getElementById('productRows');
    const products = <?php echo json_encode($products); ?>;
    
    // Yeni ürün satırı ekle
    addProductRowBtn.addEventListener('click', function() {
        addNewProductRow();
    });
    
    // Yeni ürün satırı oluşturma fonksiyonu
    function addNewProductRow() {
        const newRow = document.createElement('div');
        newRow.className = 'product-row';
        newRow.innerHTML = `
            <div class="product-select">
                <select name="product_id[]" class="product-select-input" required>
                    <option value="">Ürün Seçin...</option>
                    <option value="new">+ Yeni Ürün Ekle</option>
                    ${products.map(product => `<option value="${product.id}" data-price="${product.price}">${product.name}</option>`).join('')}
                </select>
                <input type="text" name="new_product_name[]" class="new-product-input hidden" placeholder="Yeni ürün adı">
            </div>
            <div class="product-quantity">
                <input type="number" name="quantity[]" min="1" value="1" step="0.01" class="quantity-input" required>
            </div>
            <div class="product-price">
                <input type="number" name="price[]" min="0" step="0.01" class="price-input" placeholder="0.00" required>
            </div>
            <div class="product-total">
                <input type="text" class="total-display" value="0.00 ₺" readonly>
            </div>
            <div class="product-actions">
                <button type="button" class="btn btn-danger btn-sm remove-product">X</button>
            </div>
        `;
        
        productRowsContainer.appendChild(newRow);
        
        // Yeni satırdaki olayları etkinleştir
        setupProductRowEvents(newRow);
        updateGrandTotal();
    }
    
    // İlk satırdaki olayları etkinleştir
    document.querySelectorAll('.product-row').forEach(row => {
        setupProductRowEvents(row);
    });
    
    // Ürün satırı olaylarını ayarla
    function setupProductRowEvents(row) {
        const productSelect = row.querySelector('.product-select-input');
        const newProductInput = row.querySelector('.new-product-input');
        const quantityInput = row.querySelector('.quantity-input');
        const priceInput = row.querySelector('.price-input');
        const totalDisplay = row.querySelector('.total-display');
        const removeBtn = row.querySelector('.remove-product');
        
        // Ürün seçildiğinde fiyatı otomatik doldur
        productSelect.addEventListener('change', function() {
            if (this.value === 'new') {
                // Yeni ürün ekleme modu
                newProductInput.classList.remove('hidden');
                productSelect.classList.add('hidden');
                priceInput.value = '';
                priceInput.focus();
                updateRowTotal(row);
            } else if (this.value) {
                // Mevcut ürün seçildi
                newProductInput.classList.add('hidden');
                productSelect.classList.remove('hidden');
                const selectedOption = this.options[this.selectedIndex];
                const price = selectedOption.getAttribute('data-price');
                priceInput.value = price;
                updateRowTotal(row);
            } else {
                // Ürün seçilmedi
                newProductInput.classList.add('hidden');
                productSelect.classList.remove('hidden');
                priceInput.value = '';
                totalDisplay.value = '0.00 ₺';
                updateGrandTotal();
            }
        });
        
        // Miktar veya fiyat değiştiğinde satır toplamını güncelle
        quantityInput.addEventListener('input', function() {
            updateRowTotal(row);
        });
        
        priceInput.addEventListener('input', function() {
            updateRowTotal(row);
        });
        
        // Ürün satırı silme butonu
        removeBtn.addEventListener('click', function() {
            if (document.querySelectorAll('.product-row').length > 1) {
                row.remove();
                updateGrandTotal();
            } else {
                alert('En az bir ürün satırı olmalıdır!');
            }
        });
    }
    
    // Ürün satırı toplamını güncelle
    function updateRowTotal(row) {
        const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        const total = quantity * price;
        
        row.querySelector('.total-display').value = formatCurrency(total);
        updateGrandTotal();
    }
    
    // Genel toplamı güncelle
    function updateGrandTotal() {
        let grandTotal = 0;
        
        document.querySelectorAll('.product-row').forEach(row => {
            const quantityInput = row.querySelector('.quantity-input');
            const priceInput = row.querySelector('.price-input');
            
            if (quantityInput.value && priceInput.value) {
                const quantity = parseFloat(quantityInput.value);
                const price = parseFloat(priceInput.value);
                grandTotal += quantity * price;
            }
        });
        
        document.getElementById('grandTotal').textContent = formatCurrency(grandTotal);
    }
    
    // Para formatını ayarla
    function formatCurrency(amount) {
        return amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,').replace('.', ',') + ' ₺';
    }
    
    // Form gönderilmeden önce kontrol
    document.getElementById('incomeForm').addEventListener('submit', function(e) {
        // En az bir ürün seçili mi kontrol et
        let validProducts = 0;
        
        document.querySelectorAll('.product-row').forEach(row => {
            const productSelect = row.querySelector('.product-select-input');
            const newProductInput = row.querySelector('.new-product-input');
            
            if ((productSelect.value && productSelect.value !== 'new') || 
                (productSelect.value === 'new' && newProductInput.value.trim() !== '')) {
                validProducts++;
            }
        });
        
        if (validProducts === 0) {
            e.preventDefault();
            alert('Lütfen en az bir ürün seçiniz veya yeni ürün ekleyiniz!');
        }
    });
    
    // Sayfa yüklendiğinde ilk satırı ekle
    if (document.querySelectorAll('.product-row').length === 0) {
        addNewProductRow();
    }
});
</script>