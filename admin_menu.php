<?php
session_start();
require_once 'db.php';

// Bảo mật phân quyền: Chỉ Admin mới được vào
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$message = ''; $msg_type = '';

// =========================================================
// R5.4. XỬ LÝ TÌM KIẾM MÓN ĂN (ĐỌC TỪ CỬA SỔ TÌM KIẾM)
// =========================================================
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';

// =========================================================
// R5.1 & R5.2: XỬ LÝ HÀNH ĐỘNG THÊM / SỬA / ĐỔI TRẠNG THÁI
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // -----------------------------------------------------
    // CHỨC NĂNG: THÊM MÓN MỚI (R5.1)
    // -----------------------------------------------------
    if ($action === 'add_item') {
        $menuItemName = trim($_POST['menuItemName']);
        $categoryId = $_POST['categoryId'];
        $price = (float)$_POST['price'];
        $description = trim($_POST['description']);
        
        // Xử lý upload ảnh
        $image_name = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image_name = time() . '_' . uniqid() . '.' . $ext; 
            move_uploaded_file($_FILES['image']['tmp_name'], 'uploads/' . $image_name);
        }

        try {
            // Tự động sinh khóa chính MI0001
            $id_query = $conn->query("SELECT menuItemId FROM MenuItems ORDER BY menuItemId DESC LIMIT 1");
            $last_item = $id_query->fetch(PDO::FETCH_ASSOC);
            if ($last_item) {
                $last_num = (int)substr($last_item['menuItemId'], 2);
                $new_menuItemId = 'MI' . str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
            } else {
                $new_menuItemId = 'MI0001';
            }

            $sql = "INSERT INTO MenuItems (menuItemId, categoryId, menuItemName, image, price, description, menuItemStatus) 
                    VALUES (:id, :catId, :name, :img, :price, :desc, 'Đang bán')";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':id' => $new_menuItemId,
                ':catId' => $categoryId,
                ':name' => $menuItemName,
                ':img' => $image_name,
                ':price' => $price,
                ':desc' => $description
            ]);

            $message = "Thêm món mới thành công! Mã món: " . $new_menuItemId;
            $msg_type = "success";
        } catch (PDOException $e) {
            $message = "Lỗi khi thêm món: " . $e->getMessage();
            $msg_type = "error";
        }
    }

    // -----------------------------------------------------
    // CHỨC NĂNG: SỬA THÔNG TIN & TRẠNG THÁI MÓN (R5.2 & R5.3)
    // -----------------------------------------------------
    if ($action === 'edit_item') {
        $menuItemId = $_POST['menuItemId'];
        $menuItemName = trim($_POST['menuItemName']);
        $categoryId = $_POST['categoryId'];
        $price = (float)$_POST['price'];
        $description = trim($_POST['description']);
        $menuItemStatus = $_POST['menuItemStatus'];

        try {
            $stmtOld = $conn->prepare("SELECT image FROM MenuItems WHERE menuItemId = :id");
            $stmtOld->execute([':id' => $menuItemId]);
            $oldItem = $stmtOld->fetch(PDO::FETCH_ASSOC);
            $image_name = $oldItem['image'] ?? '';

            if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $image_name = time() . '_' . uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], 'uploads/' . $image_name);
            }

            $sql = "UPDATE MenuItems 
                    SET categoryId = :catId, menuItemName = :name, image = :img, price = :price, description = :desc, menuItemStatus = :status, updateDate = NOW() 
                    WHERE menuItemId = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':catId' => $categoryId,
                ':name' => $menuItemName,
                ':img' => $image_name,
                ':price' => $price,
                ':desc' => $description,
                ':status' => $menuItemStatus,
                ':id' => $menuItemId
            ]);

            $message = "Đã cập nhật thông tin món " . htmlspecialchars($menuItemId) . " thành công!";
            $msg_type = "success";
        } catch (PDOException $e) {
            $message = "Lỗi khi chỉnh sửa: " . $e->getMessage();
            $msg_type = "error";
        }
    }
}

// =========================================================
// TRUY VẤN DỮ LIỆU HIỂN THỊ LÊN TRANG GIAO DIỆN
// =========================================================
$categories = $conn->query("SELECT * FROM MenuCategories ORDER BY categoryName ASC")->fetchAll(PDO::FETCH_ASSOC);

$sqlMenuItems = "
    SELECT m.*, c.categoryName 
    FROM MenuItems m
    JOIN MenuCategories c ON m.categoryId = c.categoryId
";
if (!empty($search_keyword)) {
    $sqlMenuItems .= " WHERE m.menuItemName LIKE :search OR c.categoryName LIKE :search";
}
$sqlMenuItems .= " ORDER BY c.categoryId ASC, m.menuItemName ASC";

$stmtMenu = $conn->prepare($sqlMenuItems);
if (!empty($search_keyword)) {
    $stmtMenu->execute([':search' => '%' . $search_keyword . '%']);
} else {
    $stmtMenu->execute();
}
$all_items = $stmtMenu->fetchAll(PDO::FETCH_ASSOC);

$menu_grouped = [];
foreach ($all_items as $item) {
    $cat = $item['categoryName'];
    if (!isset($menu_grouped[$cat])) {
        $menu_grouped[$cat] = [];
    }
    $menu_grouped[$cat][] = $item;
}

function getAdminImage($img) {
    $img = trim($img);
    if ($img === '') return '';
    if (file_exists('uploads/' . $img)) return 'uploads/' . $img;
    if (file_exists('images/' . $img)) return 'images/' . $img;
    return '';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản Lý Thực Đơn - Trạm Thực Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #d35400; --dark: #2c3e50; --bg: #f4f7f6; --text: #333; }
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; background-color: var(--bg); color: var(--text); display: flex; height: 100vh; overflow: hidden; }
        
        .sidebar { width: 260px; background-color: var(--dark); color: #fff; display: flex; flex-direction: column; box-shadow: 4px 0 10px rgba(0,0,0,0.1); }
        .sidebar .brand { padding: 25px 20px; text-align: center; font-size: 22px; font-weight: 800; background: #1a252f; border-bottom: 1px solid #34495e; }
        .sidebar .brand span { color: var(--primary); }
        .sidebar .menu { flex: 1; display: flex; flex-direction: column; padding: 20px 0; }
        .sidebar .menu a { display: flex; align-items: center; padding: 15px 25px; color: #bdc3c7; text-decoration: none; font-weight: 500; gap: 12px; }
        .sidebar .menu a.active, .sidebar .menu a:hover { background-color: rgba(255,255,255,0.05); color: #fff; border-left: 4px solid var(--primary); }
        .sidebar .menu a i { width: 20px; text-align: center; font-size: 18px; }
        .sidebar .menu a.logout { margin-top: auto; border-top: 1px solid #34495e; color: #e74c3c; padding: 20px; }
        .sidebar .menu a.logout:hover { background-color: #c0392b; color: #fff; }

        .main-content { flex: 1; padding: 30px 40px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .header h2 { margin: 0; color: var(--dark); font-size: 28px; }
        
        .control-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; gap: 20px; }
        .search-form { display: flex; flex: 1; max-width: 450px; }
        .search-input { width: 100%; padding: 11px 15px; border: 1px solid #dcdde1; border-radius: 8px 0 0 8px; font-size: 14.5px; outline: none; }
        .search-btn { background: var(--dark); color: #fff; border: none; padding: 0 20px; border-radius: 0 8px 8px 0; cursor: pointer; transition: 0.2s; }
        .search-btn:hover { background: var(--primary); }
        
        .btn-add-trigger { background: var(--primary); color: #fff; padding: 11px 20px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 14.5px; transition: 0.2s; }
        .btn-add-trigger:hover { background: #e67e22; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(211,84,0,0.2); }

        /* =========================================
           CSS THÔNG BÁO TỰ ẨN
           ========================================= */
        .alert { 
            padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: 600; 
            display: flex; align-items: center; gap: 10px;
            animation: slideIn 0.4s ease, fadeOut 0.6s ease 3.5s forwards; 
        }
        .alert.success { background: #e8f5e9; color: #2ecc71; border-left: 4px solid #2ecc71; }
        .alert.error { background: #ffebee; color: #e74c3c; border-left: 4px solid #e74c3c; }

        @keyframes slideIn { 
            from { opacity: 0; transform: translateY(-8px); } 
            to { opacity: 1; transform: translateY(0); } 
        }
        @keyframes fadeOut { 
            from { opacity: 1; max-height: 80px; margin-bottom: 20px; padding: 15px; } 
            to { opacity: 0; max-height: 0; margin-bottom: 0; padding: 0; overflow: hidden; border: none; } 
        }

        .filter-tabs { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #edf2f7; }
        .filter-btn { padding: 9px 18px; border: 1px solid var(--primary); background: transparent; color: var(--primary); border-radius: 8px; font-size: 14px; font-weight: bold; cursor: pointer; transition: all 0.3s ease; }
        .filter-btn:hover, .filter-btn.active { background: var(--primary); color: #fff; box-shadow: 0 4px 10px rgba(211,84,0,0.2); }
        
        .category-block { animation: fadeIn 0.4s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .category-title { color: var(--dark); border-left: 5px solid var(--primary); padding-left: 12px; margin: 15px 0 15px 0; font-size: 18px; }
        
        .table-container { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.03); margin-bottom: 30px; border: 1px solid #edf2f7; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 14px; border-bottom: 1px solid #edf2f7; text-align: left; vertical-align: middle; }
        th { background: #f8f9fa; color: #7f8c8d; font-size: 12.5px; text-transform: uppercase; }
        .dish-img { width: 65px; height: 50px; object-fit: cover; border-radius: 6px; background-color: #f0f0f0; border: 1px solid #eee; }
        
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .status-badge.active { background: #e8f5e9; color: #2ecc71; }
        .status-badge.inactive { background: #ffebee; color: #e74c3c; }

        .btn-action-edit { background: none; border: 1px solid #dcdde1; color: var(--dark); padding: 6px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 13px; }
        .btn-action-edit:hover { border-color: var(--primary); color: var(--primary); background: #fdf5ef; }

        .modal-overlay { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: #fff; width: 480px; padding: 30px; border-radius: 12px; position: relative; max-height: 90vh; overflow-y: auto; }
        .modal-box h3 { color: var(--dark); margin-top: 0; font-size: 22px; margin-bottom: 20px; border-bottom: 2px solid #f4f7f6; padding-bottom: 12px; }
        .close-modal { position: absolute; top: 20px; right: 20px; font-size: 22px; color: #aaa; cursor: pointer; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 11px; border: 1px solid #ddd; border-radius: 6px; font-size: 14.5px; box-sizing: border-box; font-family: inherit; }
        .btn-submit { width: 100%; background: var(--dark); color: white; border: none; padding: 13px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 15px; margin-top: 10px; }
        .btn-submit:hover { background: var(--primary); }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="brand">Trạm Thực <span>Admin</span></div>
        <div class="menu">
            <a href="admin_dashboard.php"><i class="fa-solid fa-chart-pie"></i> Trang tổng quan</a>
            <a href="admin_reservations.php"><i class="fa-solid fa-calendar-check"></i> Quản lý đặt bàn</a>
            <a href="admin_menu.php" class="active"><i class="fa-solid fa-utensils"></i> Quản lý thực đơn</a>
            <a href="admin_users.php"><i class="fa-solid fa-users"></i> Quản lý người dùng</a>
            <a href="admin_reports.php"><i class="fa-solid fa-chart-line"></i> Báo cáo thống kê</a>
            <a href="logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất hệ thống</a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <h2>Quản lý Thực đơn món ăn</h2>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert <?= $msg_type ?>" id="flash-alert">
                <i class="fa-solid <?= $msg_type === 'success' ? 'fa-check-circle' : 'fa-circle-exclamation' ?>"></i>
                <?= $message ?>
            </div>
            <script>
                // Hàm tự động xóa thông báo khỏi HTML sau khi chạy xong CSS Animation
                setTimeout(function() {
                    var alertBox = document.getElementById('flash-alert');
                    if (alertBox) {
                        alertBox.remove();
                    }
                }, 4100); // Đợi 4.1 giây (đủ để hiệu ứng fadeOut chạy xong)
            </script>
        <?php endif; ?>

        <div class="control-bar">
            <form method="GET" action="admin_menu.php" class="search-form">
                <input type="text" name="search" class="search-input" placeholder="Tìm tên món hoặc tên danh mục..." value="<?= htmlspecialchars($search_keyword) ?>">
                <button type="submit" class="search-btn"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
            <button class="btn-add-trigger" onclick="openAddModal()"><i class="fa-solid fa-plus"></i> Thêm món ăn mới</button>
        </div>

        <?php if (count($menu_grouped) > 0): ?>
            <div class="filter-tabs">
                <button class="filter-btn active" data-filter="all">🌟 Tất cả các món</button>
                <?php foreach (array_keys($menu_grouped) as $cat_name): ?>
                    <button class="filter-btn" data-filter="<?= md5($cat_name) ?>">
                        <?= htmlspecialchars($cat_name) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php foreach ($menu_grouped as $cat_name => $items): ?>
                <?php $cat_id = md5($cat_name); ?>
                <div class="category-block" id="<?= $cat_id ?>">
                    <h3 class="category-title"><i class="fa-solid fa-layer-group"></i> <?= htmlspecialchars($cat_name) ?></h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Mã</th>
                                    <th>Hình Ảnh</th>
                                    <th>Tên Món Ăn</th>
                                    <th>Giá Bán</th>
                                    <th>Trạng Thái</th>
                                    <th>Thao Tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $row): ?>
                                    <?php 
                                        $statusClass = ($row['menuItemStatus'] === 'Đang bán' || $row['menuItemStatus'] === 'Hiển thị') ? 'active' : 'inactive';
                                        $src = getAdminImage($row['image']);
                                    ?>
                                    <tr>
                                        <td><strong><?= $row['menuItemId'] ?></strong></td>
                                        <td>
                                            <?php if ($src !== ''): ?>
                                                <img src="<?= htmlspecialchars($src) ?>" class="dish-img">
                                            <?php else: ?>
                                                <div class="dish-img" style="display:flex;align-items:center;justify-content:center;font-size:11px;color:#aaa;">Không ảnh</div>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?= htmlspecialchars($row['menuItemName']) ?></strong></td>
                                        <td><strong style="color:var(--primary);"><?= number_format($row['price'], 0, ',', '.') ?> đ</strong></td>
                                        <td><span class="status-badge <?= $statusClass ?>"><?= $row['menuItemStatus'] ?></span></td>
                                        <td>
                                            <button class="btn-action-edit" onclick="openEditModal(
                                                '<?= $row['menuItemId'] ?>', 
                                                '<?= htmlspecialchars($row['menuItemName'], ENT_QUOTES) ?>', 
                                                '<?= $row['categoryId'] ?>', 
                                                '<?= $row['price'] ?>', 
                                                '<?= htmlspecialchars($row['description'], ENT_QUOTES) ?>', 
                                                '<?= $row['menuItemStatus'] ?>'
                                            )"><i class="fa-solid fa-sliders"></i> Chỉnh sửa</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="table-container">
                <p style="text-align: center; color: #aaa; padding: 40px; margin: 0;">Không tìm thấy món ăn nào phù hợp với yêu cầu.</p>
            </div>
        <?php endif; ?>

    </div>

    <div id="addModal" class="modal-overlay">
        <div class="modal-box">
            <i class="fa-solid fa-xmark close-modal" onclick="closeAddModal()"></i>
            <h3>Thêm Món Ăn Mới</h3>
            <form method="POST" action="admin_menu.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_item">
                
                <div class="form-group">
                    <label>Tên món ăn *</label>
                    <input type="text" name="menuItemName" required placeholder="Ví dụ: Lẩu riêu cua bắp bò">
                </div>

                <div class="form-group">
                    <label>Danh mục phân loại *</label>
                    <select name="categoryId" required>
                        <option value="">-- Chọn nhóm món ăn --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['categoryId'] ?>"><?= htmlspecialchars($cat['categoryName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Giá bán (VND) *</label>
                    <input type="number" name="price" min="0" required placeholder="Ví dụ: 150000">
                </div>

                <div class="form-group">
                    <label>Hình ảnh món ăn</label>
                    <input type="file" name="image" accept="image/*">
                </div>

                <div class="form-group">
                    <label>Mô tả món ăn</label>
                    <textarea name="description" rows="3" placeholder="Thành phần, hương vị khẩu phần..."></textarea>
                </div>

                <button type="submit" class="btn-submit">Thêm Mới Món Ăn</button>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal-overlay">
        <div class="modal-box">
            <i class="fa-solid fa-xmark close-modal" onclick="closeEditModal()"></i>
            <h3>Chỉnh Sửa Món #<span id="displayItemId" style="color:var(--primary);"></span></h3>
            <form method="POST" action="admin_menu.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_item">
                <input type="hidden" name="menuItemId" id="editItemId">

                <div class="form-group">
                    <label>Tên món ăn *</label>
                    <input type="text" name="menuItemName" id="editName" required>
                </div>

                <div class="form-group">
                    <label>Danh mục phân loại *</label>
                    <select name="categoryId" id="editCategory" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['categoryId'] ?>"><?= htmlspecialchars($cat['categoryName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Giá bán (VND) *</label>
                    <input type="number" name="price" id="editPrice" min="0" required>
                </div>

                <div class="form-group">
                    <label>Thay đổi hình ảnh (Để trống nếu giữ nguyên)</label>
                    <input type="file" name="image" accept="image/*">
                </div>

                <div class="form-group">
                    <label>Trạng thái *</label>
                    <select name="menuItemStatus" id="editStatus">
                        <option value="Đang bán">Đang bán (Sẵn sàng phục vụ)</option>
                        <option value="Ngừng bán">Ngừng bán (Hết nguyên liệu / Tạm dừng)</option>
                        <option value="Hiển thị" style="display:none;"></option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Mô tả món ăn</label>
                    <textarea name="description" id="editDescription" rows="3"></textarea>
                </div>

                <button type="submit" class="btn-submit">Lưu Thay Đổi</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const filterBtns = document.querySelectorAll('.filter-btn[data-filter]');
            const catBlocks  = document.querySelectorAll('.category-block');
            
            filterBtns.forEach(btn => {
                btn.addEventListener('click', function () {
                    filterBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    const target = this.getAttribute('data-filter');
                    
                    catBlocks.forEach(cat => {
                        if (target === 'all' || cat.id === target) {
                            cat.style.display = 'block';
                        } else {
                            cat.style.display = 'none';
                        }
                    });
                });
            });
        });

        const addModal = document.getElementById('addModal');
        function openAddModal() { addModal.classList.add('show'); }
        function closeAddModal() { addModal.classList.remove('show'); }

        const editModal = document.getElementById('editModal');
        function openEditModal(id, name, catId, price, desc, status) {
            document.getElementById('displayItemId').innerText = id;
            document.getElementById('editItemId').value = id;
            document.getElementById('editName').value = name;
            document.getElementById('editCategory').value = catId;
            document.getElementById('editPrice').value = price;
            document.getElementById('editDescription').value = desc;
            
            const statusSelect = document.getElementById('editStatus');
            if (status === 'Hiển thị') {
                statusSelect.value = 'Đang bán';
            } else {
                statusSelect.value = status;
            }
            
            editModal.classList.add('show');
        }
        function closeEditModal() { editModal.classList.remove('show'); }

        window.addEventListener('click', function(e) {
            if (e.target === addModal) closeAddModal();
            if (e.target === editModal) closeEditModal();
        });
    </script>
</body>
</html>