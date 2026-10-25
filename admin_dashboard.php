<?php
session_start();
require_once 'db.php';

// Kiểm tra quyền Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION['fullName'] ?? 'Quản trị viên';

// TRUY VẤN SỐ LIỆU TỔNG QUAN (KHÁI QUÁT)
try {
    // 1. Tổng đơn đặt bàn toàn hệ thống
    $stmtBook = $conn->query("SELECT COUNT(*) FROM Reservations");
    $totalBookings = $stmtBook->fetchColumn();

    // 2. Số lượng món ăn
    $stmtMenu = $conn->query("SELECT COUNT(*) FROM MenuItems");
    $totalMenuItems = $stmtMenu->fetchColumn();

    // 3. Khách hàng đã đăng ký
    $stmtUser = $conn->query("SELECT COUNT(*) FROM Users WHERE role = 'Customer'");
    $totalCustomers = $stmtUser->fetchColumn();

    // 4. [MỚI] Đơn đặt bàn ĐANG CHỜ DUYỆT (Cần xử lý)
    $stmtPending = $conn->query("SELECT COUNT(*) FROM Reservations WHERE bookingStatus = 'Chờ xác nhận'");
    $pendingBookings = $stmtPending->fetchColumn();

    // 5. [MỚI] Đơn đặt bàn TRONG NGÀY HÔM NAY (Khách sẽ đến hôm nay)
    $stmtToday = $conn->query("SELECT COUNT(*) FROM Reservations WHERE DATE(reservationTime) = CURDATE()");
    $todayBookings = $stmtToday->fetchColumn();

} catch (PDOException $e) {
    die("Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Nhà hàng Trạm Thực</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #d35400; --dark: #2c3e50; --bg: #f4f7f6; --text: #333; --light-text: #7f8c8d; }
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; background-color: var(--bg); color: var(--text); display: flex; height: 100vh; overflow: hidden; }
        
        /* --- SIDEBAR CHUYÊN NGHIỆP --- */
        .sidebar { width: 260px; background-color: var(--dark); color: #fff; display: flex; flex-direction: column; box-shadow: 4px 0 10px rgba(0,0,0,0.1); z-index: 10; }
        .sidebar .brand { padding: 25px 20px; text-align: center; font-size: 22px; font-weight: 800; background: #1a252f; border-bottom: 1px solid #34495e; letter-spacing: 1px; }
        .sidebar .brand span { color: var(--primary); }
        .sidebar .menu { flex: 1; display: flex; flex-direction: column; padding: 20px 0; overflow-y: auto; }
        .sidebar .menu a { display: flex; align-items: center; padding: 15px 25px; color: #bdc3c7; text-decoration: none; font-weight: 500; transition: all 0.3s ease; gap: 12px; }
        .sidebar .menu a:hover, .sidebar .menu a.active { background-color: rgba(255,255,255,0.05); color: #fff; border-left: 4px solid var(--primary); }
        .sidebar .menu a i { width: 20px; font-size: 18px; text-align: center; }
        
        /* Đẩy nút Đăng xuất xuống đáy Sidebar */
        .sidebar .menu a.logout { margin-top: auto; border-top: 1px solid #34495e; color: #e74c3c; padding-top: 20px; padding-bottom: 20px; border-left: 4px solid transparent; }
        .sidebar .menu a.logout:hover { background-color: #c0392b; color: #fff; border-left-color: #c0392b; }

        /* --- MAIN CONTENT KHÁI QUÁT --- */
        .main-content { flex: 1; padding: 30px 40px; overflow-y: auto; }
        
        /* Header & Lời chào */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h2 { margin: 0; color: var(--dark); font-size: 28px; font-weight: 700; }
        .admin-profile { display: flex; align-items: center; gap: 10px; font-weight: 600; color: var(--dark); background: #fff; padding: 8px 20px; border-radius: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .admin-profile i { font-size: 22px; color: var(--primary); }

        /* Banner cảnh báo nhanh */
        .welcome-banner { background: linear-gradient(135deg, #d35400, #e67e22); color: #fff; padding: 25px 30px; border-radius: 16px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 10px 20px rgba(211,84,0,0.2); }
        .welcome-banner div h3 { margin: 0 0 10px 0; font-size: 24px; }
        .welcome-banner div p { margin: 0; font-size: 15px; opacity: 0.9; }
        .welcome-banner .btn-action { background: #fff; color: var(--primary); padding: 10px 20px; text-decoration: none; border-radius: 8px; font-weight: bold; transition: 0.3s; }
        .welcome-banner .btn-action:hover { background: #fdf2e9; transform: translateY(-2px); }

        /* Thẻ Thống Kê Chung */
        .cards-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-bottom: 30px; }
        .card { background: #fff; padding: 25px; border-radius: 16px; box-shadow: 0 5px 15px rgba(0,0,0,0.03); display: flex; align-items: center; justify-content: space-between; transition: 0.3s; border: 1px solid #edf2f7; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        .card-info h4 { margin: 0 0 8px 0; font-size: 14px; color: var(--light-text); text-transform: uppercase; letter-spacing: 1px; }
        .card-info .number { font-size: 32px; font-weight: 800; color: var(--dark); }
        .card-icon { width: 60px; height: 60px; border-radius: 15px; display: flex; justify-content: center; align-items: center; font-size: 28px; }
        
        .bg-blue { background: rgba(52, 152, 219, 0.1); color: #3498db; }
        .bg-orange { background: rgba(230, 126, 34, 0.1); color: #e67e22; }
        .bg-green { background: rgba(46, 204, 113, 0.1); color: #2ecc71; }

        /* Chia cột: Trạng thái & Lối tắt */
        .dashboard-row { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        
        /* Bảng Khái quát vận hành */
        .widget-box { background: #fff; border-radius: 16px; padding: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.03); border: 1px solid #edf2f7; }
        .widget-box h3 { margin-top: 0; color: var(--dark); font-size: 18px; border-bottom: 2px solid #f4f7f6; padding-bottom: 15px; margin-bottom: 20px; }
        
        .status-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #f8f9fa; border-radius: 10px; margin-bottom: 15px; }
        .status-item .label { display: flex; align-items: center; gap: 12px; font-weight: 600; color: #495057; }
        .status-item .label i { font-size: 20px; }
        .status-item .value { font-size: 22px; font-weight: 800; color: var(--dark); }

        /* Nút thao tác nhanh */
        .quick-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .quick-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; padding: 20px; background: #f8f9fa; border-radius: 12px; text-decoration: none; color: var(--dark); font-weight: 600; transition: 0.3s; border: 1px solid transparent; }
        .quick-btn i { font-size: 24px; color: var(--primary); }
        .quick-btn:hover { background: #fff; border-color: var(--primary); color: var(--primary); box-shadow: 0 5px 15px rgba(211,84,0,0.1); transform: translateY(-3px); }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="brand">Trạm Thực <span>Admin</span></div>
        <div class="menu">
            <a href="admin_dashboard.php" class="active"><i class="fa-solid fa-chart-pie"></i> Trang tổng quan</a>
            <a href="admin_reservations.php"><i class="fa-solid fa-calendar-check"></i> Quản lý đặt bàn</a>
            <a href="admin_menu.php"><i class="fa-solid fa-utensils"></i> Quản lý thực đơn</a>
            <a href="admin_users.php"><i class="fa-solid fa-users"></i> Quản lý người dùng</a>
            <a href="admin_reports.php"><i class="fa-solid fa-chart-line"></i> Báo cáo thống kê</a>
            
            <a href="logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất hệ thống</a>
        </div>
    </div>

    <div class="main-content">
        
        <div class="header">
            <h2>Hệ thống quản trị</h2>
            <div class="admin-profile">
                <i class="fa-solid fa-circle-user"></i>
                Xin chào, <?= htmlspecialchars($adminName) ?>
            </div>
        </div>

        <div class="welcome-banner">
            <div>
                <h3>Tình trạng vận hành hôm nay</h3>
                <p>Bạn hiện có <strong><?= $pendingBookings ?> đơn đặt bàn mới</strong> đang chờ được xác nhận và xếp bàn.</p>
            </div>
            <?php if($pendingBookings > 0): ?>
                <a href="admin_reservations.php" class="btn-action">Xử lý ngay</a>
            <?php endif; ?>
        </div>

        <div class="cards-grid">
            <div class="card">
                <div class="card-info">
                    <h4>Tổng Lượt Đặt Bàn</h4>
                    <div class="number"><?= $totalBookings ?></div>
                </div>
                <div class="card-icon bg-blue"><i class="fa-solid fa-book-bookmark"></i></div>
            </div>
            
            <div class="card">
                <div class="card-info">
                    <h4>Món Ăn Thực Đơn</h4>
                    <div class="number"><?= $totalMenuItems ?></div>
                </div>
                <div class="card-icon bg-orange"><i class="fa-solid fa-bowl-food"></i></div>
            </div>

            <div class="card">
                <div class="card-info">
                    <h4>Khách Hàng Thành Viên</h4>
                    <div class="number"><?= $totalCustomers ?></div>
                </div>
                <div class="card-icon bg-green"><i class="fa-solid fa-user-group"></i></div>
            </div>
        </div>

        <div class="dashboard-row">
            
            <div class="widget-box">
                <h3><i class="fa-solid fa-bell-concierge"></i> Tổng quan trong ngày</h3>
                
                <div class="status-item">
                    <div class="label"><i class="fa-solid fa-clock" style="color: #f39c12;"></i> Đang chờ xác nhận</div>
                    <div class="value" style="color: #f39c12;"><?= $pendingBookings ?></div>
                </div>
                
                <div class="status-item">
                    <div class="label"><i class="fa-solid fa-calendar-day" style="color: #2ecc71;"></i> Lịch khách đến hôm nay</div>
                    <div class="value" style="color: #2ecc71;"><?= $todayBookings ?></div>
                </div>
            </div>

            <div class="widget-box">
                <h3><i class="fa-solid fa-bolt"></i> Lối tắt thao tác nhanh</h3>
                <div class="quick-actions">
                    <a href="admin_reservations.php" class="quick-btn">
                        <i class="fa-solid fa-calendar-plus"></i>
                        Duyệt đơn đặt bàn
                    </a>
                    <a href="admin_menu.php" class="quick-btn">
                        <i class="fa-solid fa-folder-plus"></i>
                        Thêm món mới
                    </a>
                    <a href="admin_users.php" class="quick-btn">
                        <i class="fa-solid fa-user-shield"></i>
                        Quản lý tài khoản
                    </a>
                    <a href="admin_reports.php" class="quick-btn">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                        Xem doanh thu
                    </a>
                </div>
            </div>

        </div>

    </div>

</body>
</html>