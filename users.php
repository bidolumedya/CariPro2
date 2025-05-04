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

// Kullanıcı tablosunu kontrol et ve gerekirse oluştur
try {
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        // Tablo yok, oluştur
        $db->exec("CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            fullname VARCHAR(100),
            email VARCHAR(100),
            role ENUM('admin', 'manager', 'user') NOT NULL DEFAULT 'user',
            permissions TEXT,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) CHARACTER SET utf8 COLLATE utf8_general_ci");
        
        // İlk admin kullanıcısını ekle
        $admin_username = 'admin';
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $admin_fullname = 'Sistem Yöneticisi';
        $admin_email = 'admin@example.com';
        $admin_role = 'admin';
        
        $stmt = $db->prepare("INSERT INTO users (username, password, fullname, email, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$admin_username, $admin_password, $admin_fullname, $admin_email, $admin_role]);
    }
} catch (PDOException $e) {
    echo "<p>Tablo oluşturulurken hata oluştu: " . $e->getMessage() . "</p>";
}

// Kullanıcı ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    
    // İzinleri array olarak al ve JSON'a çevir
    $permissions = [];
    if (isset($_POST['permissions'])) {
        $permissions = $_POST['permissions'];
    }
    $permissions_json = json_encode($permissions);
    
    if (!empty($username) && !empty($password) && !empty($role)) {
        try {
            // Kullanıcı adı daha önce kullanılmış mı kontrol et
            $check_stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $check_stmt->execute([$username]);
            
            if ($check_stmt->fetchColumn() > 0) {
                $error_message = "Bu kullanıcı adı zaten kullanılmaktadır.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("INSERT INTO users (username, password, fullname, email, role, permissions) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hashed_password, $fullname, $email, $role, $permissions_json]);
                
                $success_message = "Kullanıcı başarıyla eklendi.";
            }
        } catch (PDOException $e) {
            $error_message = "Kullanıcı eklenirken bir hata oluştu: " . $e->getMessage();
        }
    } else {
        $error_message = "Lütfen kullanıcı adı, şifre ve rol alanlarını doldurunuz.";
    }
}

// Kullanıcı güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $user_id = $_POST['user_id'];
    $username = $_POST['edit_username'];
    $fullname = $_POST['edit_fullname'];
    $email = $_POST['edit_email'];
    $role = $_POST['edit_role'];
    $active = isset($_POST['edit_active']) ? 1 : 0;
    
    // İzinleri array olarak al ve JSON'a çevir
    $permissions = [];
    if (isset($_POST['edit_permissions'])) {
        $permissions = $_POST['edit_permissions'];
    }
    $permissions_json = json_encode($permissions);
    
    if (!empty($username) && !empty($role)) {
        try {
            // Kullanıcı adı başka bir kullanıcı tarafından kullanılıyor mu kontrol et
            $check_stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
            $check_stmt->execute([$username, $user_id]);
            
            if ($check_stmt->fetchColumn() > 0) {
                $error_message = "Bu kullanıcı adı başka bir kullanıcı tarafından kullanılmaktadır.";
            } else {
                if (!empty($_POST['edit_password'])) {
                    // Şifre değiştirilecekse
                    $hashed_password = password_hash($_POST['edit_password'], PASSWORD_DEFAULT);
                    
                    $stmt = $db->prepare("UPDATE users SET username = ?, password = ?, fullname = ?, email = ?, role = ?, permissions = ?, active = ? WHERE id = ?");
                    $stmt->execute([$username, $hashed_password, $fullname, $email, $role, $permissions_json, $active, $user_id]);
                } else {
                    // Şifre değiştirilmeyecekse
                    $stmt = $db->prepare("UPDATE users SET username = ?, fullname = ?, email = ?, role = ?, permissions = ?, active = ? WHERE id = ?");
                    $stmt->execute([$username, $fullname, $email, $role, $permissions_json, $active, $user_id]);
                }
                
                $success_message = "Kullanıcı başarıyla güncellendi.";
            }
        } catch (PDOException $e) {
            $error_message = "Kullanıcı güncellenirken bir hata oluştu: " . $e->getMessage();
        }
    } else {
        $error_message = "Lütfen kullanıcı adı ve rol alanlarını doldurunuz.";
    }
}

// Kullanıcı silme işlemi
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    
    // Giriş yapan kullanıcı kendini silmeye çalışıyorsa engelle
    if ($user_id == $_SESSION['user_id']) {
        $error_message = "Kendinizi silemezsiniz!";
    } else {
        try {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            $success_message = "Kullanıcı başarıyla silindi.";
        } catch (PDOException $e) {
            $error_message = "Kullanıcı silinirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Kullanıcıları al
try {
    $stmt = $db->query("SELECT * FROM users ORDER BY username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<p>Kullanıcılar yüklenirken hata oluştu: " . $e->getMessage() . "</p>";
    $users = [];
}

// Şablon başlığını ayarla
$page_title = "Kullanıcı Yönetimi";
$active_page = "users";

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
        <h2>Kullanıcı Ekle</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Kullanıcı Adı</label>
                <input type="text" id="username" name="username" placeholder="Kullanıcı adı girin" required>
            </div>
            <div class="form-group">
                <label for="password">Şifre</label>
                <input type="password" id="password" name="password" placeholder="Şifre girin" required>
            </div>
            <div class="form-group">
                <label for="fullname">Ad Soyad</label>
                <input type="text" id="fullname" name="fullname" placeholder="Ad soyad girin">
            </div>
            <div class="form-group">
                <label for="email">E-posta</label>
                <input type="email" id="email" name="email" placeholder="E-posta adresi girin">
            </div>
            <div class="form-group">
                <label for="role">Rol</label>
                <select id="role" name="role" required>
                    <option value="">Seçiniz...</option>
                    <option value="admin">Yönetici</option>
                    <option value="manager">Müdür</option>
                    <option value="user">Kullanıcı</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>İzinler</label>
                <div class="permissions-container">
                    <div class="permission-item">
                        <input type="checkbox" id="perm_income" name="permissions[]" value="income">
                        <label for="perm_income">Gelir Yönetimi</label>
                    </div>
                    <div class="permission-item">
                        <input type="checkbox" id="perm_expense" name="permissions[]" value="expense">
                        <label for="perm_expense">Gider Yönetimi</label>
                    </div>
                    <div class="permission-item">
                        <input type="checkbox" id="perm_customer" name="permissions[]" value="customer">
                        <label for="perm_customer">Cari Hesap Yönetimi</label>
                    </div>
                    <div class="permission-item">
                        <input type="checkbox" id="perm_report" name="permissions[]" value="report">
                        <label for="perm_report">Raporlar</label>
                    </div>
                    <div class="permission-item">
                        <input type="checkbox" id="perm_setting" name="permissions[]" value="setting">
                        <label for="perm_setting">Ayarlar</label>
                    </div>
                </div>
                <small>Not: Yönetici (Admin) rolündeki kullanıcılar tüm izinlere sahiptir.</small>
            </div>
            
            <button type="submit" name="save_user" class="btn">Kullanıcı Ekle</button>
        </form>
    </div>

    <div class="card">
        <h2>Kullanıcılar</h2>
        <table>
            <thead>
                <tr>
                    <th>Kullanıcı Adı</th>
                    <th>Ad Soyad</th>
                    <th>E-posta</th>
                    <th>Rol</th>
                    <th>Durum</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($users) > 0): ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['username']; ?></td>
                            <td><?php echo $user['fullname']; ?></td>
                            <td><?php echo $user['email']; ?></td>
                            <td>
                                <?php
                                    switch ($user['role']) {
                                        case 'admin':
                                            echo 'Yönetici';
                                            break;
                                        case 'manager':
                                            echo 'Müdür';
                                            break;
                                        case 'user':
                                            echo 'Kullanıcı';
                                            break;
                                        default:
                                            echo $user['role'];
                                    }
                                ?>
                            </td>
                            <td><?php echo $user['active'] ? '<span class="status-active">Aktif</span>' : '<span class="status-inactive">Pasif</span>'; ?></td>
                            <td>
                                <button class="btn btn-sm edit-user-btn" data-id="<?php echo $user['id']; ?>">Düzenle</button>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <a href="users.php?delete=<?php echo $user['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bu kullanıcıyı silmek istediğinize emin misiniz?')">Sil</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">Henüz kayıtlı kullanıcı bulunmamaktadır.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Kullanıcı Düzenle Modal -->
<div id="editUserModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Kullanıcı Düzenle</h2>
        <form method="POST" action="">
            <input type="hidden" id="edit_user_id" name="user_id">
            <div class="form-group">
                <label for="edit_username">Kullanıcı Adı</label>
                <input type="text" id="edit_username" name="edit_username" required>
            </div>
            <div class="form-group">
                <label for="edit_password">Şifre</label>
                <input type="password" id="edit_password" name="edit_password" placeholder="Değiştirmek istemiyorsanız boş bırakın">
                <small>Değiştirmek istemiyorsanız boş bırakın</small>
            </div>
            <div class="form-group">
                <label for="edit_fullname">Ad Soyad</label>
                <input type="text" id="edit_fullname" name="edit_fullname">
            </div>
            <div class="form-group">
                <label for="edit_email">E-posta</label>
                <input type="email" id="edit_email" name="edit_email">
            </div>
            <div class="form-group">
                <label for="edit_role">Rol</label>
                <select id="edit_role" name="edit_role" required>
                    <option value="">Seçiniz...</option>
                    <option value="admin">Yönetici</option>
                    <option value="manager">Müdür</option>
                    <option value="user">Kullanıcı</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>İzinler</label>
                <div class="permissions-container" id="edit_permissions_container">
                    <div class="permission-item">
                        <input type="checkbox" id="edit_perm_income" name="edit_permissions[]" value="income">
                        <label for="edit_perm_income">Gelir Yönetimi</label>
                    </div>
                    <div class="permission-item">
                        <input type="checkbox" id="edit_perm_expense" name="edit_permissions[]" value="expense">
                        <label for="edit_perm_expense">Gider Yönetimi</label>
                    </div>
                    <div class="permission-item">
                        <input type="checkbox" id="edit_perm_customer" name="edit_permissions[]" value="customer">
                        <label for="edit_perm_customer">Cari Hesap Yönetimi</label>
                    </div>
                    <div class="permission-item">
                        <input type="checkbox" id="edit_perm_report" name="edit_permissions[]" value="report">
                        <label for="edit_perm_report">Raporlar</label>
                    </div>
                    <div class="permission-item">
                        <input type="checkbox" id="edit_perm_setting" name="edit_permissions[]" value="setting">
                        <label for="edit_perm_setting">Ayarlar</label>
                    </div>
                </div>
                <small>Not: Yönetici (Admin) rolündeki kullanıcılar tüm izinlere sahiptir.</small>
            </div>
            
            <div class="form-group">
                <label for="edit_active">Durum</label>
                <div class="checkbox-wrapper">
                    <input type="checkbox" id="edit_active" name="edit_active">
                    <label for="edit_active">Aktif</label>
                </div>
            </div>
            
            <button type="submit" name="update_user" class="btn">Kaydet</button>
        </form>
    </div>
</div>

<style>
/* Kullanıcı yönetimi için stiller */
.permissions-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
    margin-top: 5px;
}

.permission-item {
    display: flex;
    align-items: center;
}

.permission-item input[type="checkbox"] {
    margin-right: 8px;
}

.status-active {
    color: #2ecc71;
    font-weight: bold;
}

.status-inactive {
    color: #e74c3c;
}

.checkbox-wrapper {
    display: flex;
    align-items: center;
}

.checkbox-wrapper input[type="checkbox"] {
    margin-right: 8px;
}

/* Modal stilleri */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 20px;
    width: 60%;
    max-width: 600px;
    border-radius: 5px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
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
    // Kullanıcı düzenleme modalı
    const modal = document.getElementById('editUserModal');
    const editButtons = document.querySelectorAll('.edit-user-btn');
    const closeButton = document.querySelector('.close');
    
    // Kullanıcı verileri
    const users = <?php echo json_encode($users); ?>;
    
    // Düzenle butonlarına tıklama olayı
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-id');
            openEditModal(userId);
        });
    });
    
    // Modal'ı kapat
    closeButton.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    // Modal dışına tıklama
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    // Kullanıcı düzenle modalını aç
    function openEditModal(userId) {
        const user = users.find(u => u.id == userId);
        
        if (user) {
            // Form alanlarını doldur
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_password').value = '';
            document.getElementById('edit_fullname').value = user.fullname || '';
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_active').checked = user.active == 1;
            
            // İzinleri temizle
            document.querySelectorAll('#edit_permissions_container input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // İzinleri ayarla
            if (user.permissions) {
                try {
                    const permissions = JSON.parse(user.permissions);
                    permissions.forEach(perm => {
                        const checkbox = document.getElementById('edit_perm_' + perm);
                        if (checkbox) {
                            checkbox.checked = true;
                        }
                    });
                } catch (e) {
                    console.error('İzinler JSON formatında değil:', e);
                }
            }
            
            // Modal'ı göster
            modal.style.display = 'block';
        }
    }
    
    // Rol değiştiğinde izin alanlarını güncelle
    function updatePermissionFields() {
        const roleSelect = document.getElementById('role');
        const permissionsContainer = document.querySelector('.permissions-container');
        
        if (roleSelect.value === 'admin') {
            permissionsContainer.classList.add('disabled');
            document.querySelectorAll('.permissions-container input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = true;
                checkbox.disabled = true;
            });
        } else {
            permissionsContainer.classList.remove('disabled');
            document.querySelectorAll('.permissions-container input[type="checkbox"]').forEach(checkbox => {
                checkbox.disabled = false;
            });
        }
    }
    
    // Düzenleme formunda rol değiştiğinde izin alanlarını güncelle
    function updateEditPermissionFields() {
        const roleSelect = document.getElementById('edit_role');
        const permissionsContainer = document.getElementById('edit_permissions_container');
        
        if (roleSelect.value === 'admin') {
            permissionsContainer.classList.add('disabled');
            document.querySelectorAll('#edit_permissions_container input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = true;
                checkbox.disabled = true;
            });
        } else {
            permissionsContainer.classList.remove('disabled');
            document.querySelectorAll('#edit_permissions_container input[type="checkbox"]').forEach(checkbox => {
                checkbox.disabled = false;
            });
        }
    }
    
    // Rol değişikliğini dinle
    document.getElementById('role').addEventListener('change', updatePermissionFields);
    document.getElementById('edit_role').addEventListener('change', updateEditPermissionFields);
    
    // Sayfa yüklendiğinde izin alanlarını güncelle
    updatePermissionFields();
});
</script>