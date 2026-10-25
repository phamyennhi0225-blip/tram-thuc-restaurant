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
// XỬ LÝ TÌM KIẾM TÀI KHOẢN
// =========================================================
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';

// =========================================================
// XỬ LÝ KHÓA / MỞ KHÓA TÀI KHOẢN (ĐỒNG BỘ VỚI DATABASE)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $targetUserId = $_POST['userId'];
    $newStatus = $_POST['status']; // Sẽ nhận giá trị 'Active' hoặc 'Locked' từ form

    try {
        // Cập nhật trạng thái, chỉ áp dụng cho Customer
        $sql = "UPDATE Users SET status = :status WHERE userId = :id AND role = 'Customer'";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':status' => $newStatus,
            ':id' => $targetUserId
        ]);

        if ($newStatus === 'Locked') {
            $message = "Đã KHÓA tài khoản " . htmlspecialchars($targetUserId) . " thành công!";
            $msg_type = "success";
        } else {
            $message = "Đã MỞ KHÓA tài khoản " . htmlspecialchars($targetUserId) . "!";
            $msg_type = "success";
        }
    } catch (PDOException $e) {
        $message = "Lỗi hệ thống: " . $e->getMessage();
        $msg_type = "error";
    }
}

// =========================================================
// TRUY VẤN DANH SÁCH KHÁCH HÀNG
// =========================================================
// Lấy đúng những tài khoản có role là 'Customer' (theo ảnh DB)
$sqlUsers = "SELECT userId, fullName, phone, email, status FROM Users WHERE role = 'Customer'";

if (!empty($search_keyword)) {
    $sqlUsers .= " AND (fullName LIKE :search OR phone LIKE :search OR email LIKE :search)";
}
$sqlUsers .= " ORDER BY userId DESC";

$stmtUsers = $conn->prepare($sqlUsers);
if (!empty($search_keyword)) {
    $stmtUsers->execute([':search' => '%' . $search_keyword . '%']);
} else {
    $stmtUsers->execute();
}
$usersList = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản Lý Người Dùng - Trạm Thực Admin</title>
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
        .header p { margin: 5px 0 20px 0; color: #7f8c8d; }
        
        .control-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; gap: 20px; }
        .search-form { display: flex; flex: 1; max-width: 500px; }
        .search-input { width: 100%; padding: 11px 15px; border: 1px solid #dcdde1; border-radius: 8px 0 0 8px; font-size: 14.5px; outline: none; }
        .search-input:focus { border-color: var(--primary); }
        .search-btn { background: var(--dark); color: #fff; border: none; padding: 0 20px; border-radius: 0 8px 8px 0; cursor: pointer; transition: 0.2s; }
        .search-btn:hover { background: var(--primary); }

        .alert { 
            padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: 600; 
            display: flex; align-items: center; gap: 10px;
            animation: slideIn 0.4s ease, fadeOut 0.6s ease 3.5s forwards; 
        }
        .alert.success { background: #e8f5e9; color: #2ecc71; border-left: 4px solid #2ecc71; }
        .alert.error { background: #ffebee; color: #e74c3c; border-left: 4px solid #e74c3c; }

        @keyframes slideIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeOut { from { opacity: 1; max-height: 80px; margin-bottom: 20px; padding: 15px; } to { opacity: 0; max-height: 0; margin-bottom: 0; padding: 0; overflow: hidden; border: none; } }

        .table-container { background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.03); border: 1px solid #edf2f7; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; border-bottom: 1px solid #edf2f7; text-align: left; vertical-align: middle; }
        th { background: #f8f9fa; color: #7f8c8d; font-size: 13px; text-transform: uppercase; }
        
        .user-info { display: flex; flex-direction: column; gap: 4px; }
        .user-email { font-size: 13px; color: #7f8c8d; }
        
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; display: inline-block; }
        .status-badge.active { background: #e8f5e9; color: #2ecc71; }
        .status-badge.banned { background: #ffebee; color: #e74c3c; }

        .btn-action { background: none; border: 1px solid #dcdde1; color: var(--dark); padding: 7px 14px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; }
        .btn-action:hover { border-color: var(--primary); color: var(--primary); background: #fdf5ef; }
        .btn-action.btn-lock:hover { border-color: #e74c3c; color: #e74c3c; background: #fff5f5; }

        .modal-overlay { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: #fff; width: 420px; padding: 30px; border-radius: 12px; position: relative; }
        .modal-box h3 { color: var(--dark); margin-top: 0; font-size: 22px; margin-bottom: 10px; }
        .modal-box p.desc { color: #7f8c8d; font-size: 14.5px; margin-bottom: 20px; line-height: 1.5; }
        .close-modal { position: absolute; top: 20px; right: 20px; font-size: 22px; color: #aaa; cursor: pointer; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; }
        .form-group select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; font-family: inherit; }
        
        .btn-submit { width: 100%; background: var(--dark); color: white; border: none; padding: 14px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 15px; }
        .btn-submit:hover { background: var(--primary); }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="brand">Trạm Thực <span>Admin</span></div>
        <div class="menu">
            <a href="admin_dashboard.php"><i class="fa-solid fa-chart-pie"></i> Trang tổng quan</a>
            <a href="admin_reservations.php"><i class="fa-solid fa-calendar-check"></i> Quản lý đặt bàn</a>
            <a href="admin_menu.php"><i class="fa-solid fa-utensils"></i> Quản lý thực đơn</a>
            <a href="admin_users.php" class="active"><i class="fa-solid fa-users"></i> Quản lý người dùng</a>
            <a href="admin_reports.php"><i class="fa-solid fa-chart-line"></i> Báo cáo thống kê</a>
            <a href="logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất hệ thống</a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <div>
                <h2>Quản lý Tài khoản Khách hàng</h2>
                <p>Kiểm soát và ngăn chặn các tài khoản có hành vi spam đặt bàn</p>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert <?= $msg_type ?>" id="flash-alert">
                <i class="fa-solid <?= $msg_type === 'success' ? 'fa-check-circle' : 'fa-circle-exclamation' ?>"></i>
                <?= $message ?>
            </div>
            <script>
                setTimeout(function() {
                    var alertBox = document.getElementById('flash-alert');
                    if (alertBox) alertBox.remove();
                }, 4100);
            </script>
        <?php endif; ?>

        <div class="control-bar">
            <form method="GET" action="admin_users.php" class="search-form">
                <input type="text" name="search" class="search-input" placeholder="Nhập tên, SĐT hoặc Email cần tìm..." value="<?= htmlspecialchars($search_keyword) ?>">
                <button type="submit" class="search-btn"><i class="fa-solid fa-magnifying-glass"></i> Tìm kiếm</button>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Mã KH</th>
                        <th>Thông tin liên hệ</th>
                        <th>Số điện thoại</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($usersList) > 0): ?>
                        <?php foreach ($usersList as $user): ?>
                            <?php 
                                // Ánh xạ dữ liệu DB sang ngôn ngữ hiển thị
                                $dbStatus = $user['status'] ?? 'Active'; // Mặc định nếu null là Active
                                $displayStatus = ($dbStatus === 'Locked') ? 'Bị khóa' : 'Hoạt động';
                                $statusClass = ($dbStatus === 'Locked') ? 'banned' : 'active';
                            ?>
                            <tr>
                                <td><strong><?= $user['userId'] ?></strong></td>
                                <td>
                                    <div class="user-info">
                                        <strong><?= htmlspecialchars($user['fullName']) ?></strong>
                                        <span class="user-email"><i class="fa-regular fa-envelope"></i> <?= htmlspecialchars($user['email']) ?></span>
                                    </div>
                                </td>
                                <td><strong><?= htmlspecialchars($user['phone']) ?></strong></td>
                                <td><span class="status-badge <?= $statusClass ?>"><?= $displayStatus ?></span></td>
                                <td>
                                    <button class="btn-action <?= $dbStatus === 'Active' ? 'btn-lock' : '' ?>" onclick="openStatusModal('<?= $user['userId'] ?>', '<?= htmlspecialchars($user['fullName'], ENT_QUOTES) ?>', '<?= $dbStatus ?>')">
                                        <?php if ($dbStatus === 'Active'): ?>
                                            <i class="fa-solid fa-lock"></i> Khóa tài khoản
                                        <?php else: ?>
                                            <i class="fa-solid fa-unlock"></i> Mở khóa
                                        <?php endif; ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; color: #aaa; padding: 40px;">Không tìm thấy tài khoản nào khớp với từ khóa.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="statusModal" class="modal-overlay">
        <div class="modal-box">
            <i class="fa-solid fa-xmark close-modal" onclick="closeStatusModal()"></i>
            <h3>Cập nhật Trạng thái</h3>
            <p class="desc">Thiết lập trạng thái cho tài khoản: <strong id="displayUserName" style="color:var(--dark);"></strong></p>
            
            <form method="POST" action="admin_users.php">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="userId" id="inputUserId">

                <div class="form-group">
                    <label>Trạng thái truy cập</label>
                    <select name="status" id="inputStatus">
                        <option value="Active">🟢 Hoạt động (Cho phép đăng nhập & đặt bàn)</option>
                        <option value="Locked">🔴 Bị khóa (Cấm đăng nhập hệ thống)</option>
                    </select>
                </div>

                <button type="submit" class="btn-submit">Xác nhận Lưu</button>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('statusModal');
        
        function openStatusModal(id, name, dbStatus) {
            document.getElementById('inputUserId').value = id;
            document.getElementById('displayUserName').innerText = name + ' (' + id + ')';
            document.getElementById('inputStatus').value = dbStatus; // Tự động chọn đúng Active hoặc Locked
            modal.classList.add('show');
        }
        
        function closeStatusModal() { 
            modal.classList.remove('show'); 
        }

        window.addEventListener('click', function(e) {
            if (e.target === modal) closeStatusModal();
        });
    </script>
</body>
</html>