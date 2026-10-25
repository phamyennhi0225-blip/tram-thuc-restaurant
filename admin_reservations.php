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
// XÁC ĐỊNH NGÀY ĐANG XEM (LỌC THEO NGÀY)
// =========================================================
// Nếu có thao tác POST (Cập nhật), giữ nguyên ngày đang xem. Nếu không, lấy từ URL hoặc lấy ngày hôm nay.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_booking') {
    $filter_date = $_POST['current_filter_date'] ?? date('Y-m-d');
} else {
    $filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d');
}

// =========================================================
// XỬ LÝ CẬP NHẬT TRẠNG THÁI & GÁN BÀN TỰ ĐỘNG ĐỒNG BỘ
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_booking') {
    $reservationId = $_POST['reservationId'];
    $bookingStatus = $_POST['bookingStatus'];
    $tableId = !empty($_POST['tableId']) ? $_POST['tableId'] : NULL;

    try {
        $stmtOld = $conn->prepare("SELECT tableId FROM Reservations WHERE reservationId = :id");
        $stmtOld->execute([':id' => $reservationId]);
        $oldBooking = $stmtOld->fetch(PDO::FETCH_ASSOC);
        $oldTableId = $oldBooking['tableId'] ?? null;

        $sql = "UPDATE Reservations SET bookingStatus = :bookingStatus, tableId = :tableId WHERE reservationId = :reservationId";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':bookingStatus' => $bookingStatus,
            ':tableId' => $tableId,
            ':reservationId' => $reservationId
        ]);

        if (($bookingStatus === 'Đã hủy' || $bookingStatus === 'Hoàn thành') && $oldTableId) {
            $clearTable = $conn->prepare("UPDATE Tables SET tableStatus = 'Còn trống' WHERE tableId = :tId");
            $clearTable->execute([':tId' => $oldTableId]);
        } 
        elseif ($tableId) {
            $tableStatus = 'Còn trống';
            if ($bookingStatus === 'Đã xác nhận') {
                $tableStatus = 'Đã đặt';
            } elseif ($bookingStatus === 'Đang phục vụ') {
                $tableStatus = 'Đang phục vụ';
            }
            $updateTable = $conn->prepare("UPDATE Tables SET tableStatus = :ts WHERE tableId = :tId");
            $updateTable->execute([':ts' => $tableStatus, ':tId' => $tableId]);
        }

        $message = "Đã cập nhật thành công trạng thái đơn " . htmlspecialchars($reservationId) . "!";
        $msg_type = "success";
    } catch (PDOException $e) {
        $message = "Lỗi hệ thống: " . $e->getMessage();
        $msg_type = "error";
    }
}

// =========================================================
// TRUY VẤN DỮ LIỆU ĐỂ HIỂN THỊ (LỌC THEO BIẾN $filter_date)
// =========================================================
// Lấy đơn đặt bàn CHỈ TRONG NGÀY ĐƯỢC CHỌN, sắp xếp theo giờ đến từ sớm nhất đến muộn nhất
$sqlReservations = "
    SELECT r.*, u.fullName, u.phone, t.tableNumber 
    FROM Reservations r 
    JOIN Users u ON r.userId = u.userId 
    LEFT JOIN Tables t ON r.tableId = t.tableId 
    WHERE DATE(r.reservationTime) = :filter_date
    ORDER BY r.reservationTime ASC
";
$stmtRes = $conn->prepare($sqlReservations);
$stmtRes->execute([':filter_date' => $filter_date]);
$reservations = $stmtRes->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách bàn còn trống
$stmtTables = $conn->query("SELECT tableId, tableNumber, capacity, tableStatus FROM Tables WHERE tableStatus = 'Còn trống' ORDER BY tableNumber ASC");
$tables = $stmtTables->fetchAll(PDO::FETCH_ASSOC);

// Tính toán ngày Hôm qua và Ngày mai cho nút bấm điều hướng
$prev_date = date('Y-m-d', strtotime($filter_date . ' -1 day'));
$next_date = date('Y-m-d', strtotime($filter_date . ' +1 day'));
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản Lý Đặt Bàn - Trạm Thực Admin</title>
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
        .sidebar .menu a.logout { margin-top: auto; border-top: 1px solid #34495e; color: #e74c3c; padding: 20px; }
        .sidebar .menu a.logout:hover { background-color: #c0392b; color: #fff; }
        .main-content { flex: 1; padding: 30px 40px; overflow-y: auto; }
        .header h2 { margin: 0; color: var(--dark); font-size: 28px; }
        .header p { margin: 5px 0 20px 0; color: #7f8c8d; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .alert.success { background: #e8f5e9; color: #2ecc71; border-left: 4px solid #2ecc71; }
        .alert.error { background: #ffebee; color: #e74c3c; border-left: 4px solid #e74c3c; }

        /* THANH ĐIỀU HƯỚNG LỌC NGÀY */
        .filter-bar { background: #fff; padding: 15px 25px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.03); display: flex; justify-content: space-between; align-items: center; border: 1px solid #edf2f7; }
        .filter-form { display: flex; align-items: center; gap: 15px; }
        .filter-form input[type="date"] { padding: 10px 15px; border: 1px solid #dcdde1; border-radius: 8px; font-size: 15px; outline: none; font-family: inherit; cursor: pointer; }
        .filter-form input[type="date"]:focus { border-color: var(--primary); }
        .btn-date { background: #f4f7f6; color: var(--dark); padding: 10px 15px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; transition: 0.2s; border: 1px solid #edf2f7; display: flex; align-items: center; gap: 8px; }
        .btn-date:hover { background: #e2e8f0; }
        .btn-today { background: #e3f2fd; color: #3498db; border-color: #bbdefb; }
        .btn-today:hover { background: #bbdefb; }

        .table-container { background: #fff; border-radius: 16px; padding: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.03); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 14px; border-bottom: 1px solid #edf2f7; text-align: left; }
        th { background: #f8f9fa; color: #7f8c8d; font-size: 13px; text-transform: uppercase; }
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .status-badge.pending { background: #fff8e1; color: #f39c12; }
        .status-badge.confirmed { background: #e3f2fd; color: #3498db; }
        .status-badge.serving { background: #e8f5e9; color: #2ecc71; }
        .status-badge.cancelled { background: #ffebee; color: #e74c3c; }
        .btn-edit { background: var(--primary); color: #fff; border: none; padding: 7px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-edit:hover { background: #e67e22; }
        .modal-overlay { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: #fff; width: 400px; padding: 30px; border-radius: 12px; position: relative; }
        .close-modal { position: absolute; top: 20px; right: 20px; font-size: 22px; color: #aaa; cursor: pointer; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; }
        .btn-submit { width: 100%; background: var(--dark); color: white; border: none; padding: 12px; border-radius: 6px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="brand">Trạm Thực <span>Admin</span></div>
        <div class="menu">
            <a href="admin_dashboard.php"><i class="fa-solid fa-chart-pie"></i> Trang tổng quan</a>
            <a href="admin_reservations.php" class="active"><i class="fa-solid fa-calendar-check"></i> Quản lý đặt bàn</a>
            <a href="admin_menu.php"><i class="fa-solid fa-utensils"></i> Quản lý thực đơn</a>
            <a href="admin_users.php"><i class="fa-solid fa-users"></i> Quản lý người dùng</a>
            <a href="admin_reports.php"><i class="fa-solid fa-chart-line"></i> Báo cáo thống kê</a>
            <a href="logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất hệ thống</a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <h2>Quản lý Đơn đặt bàn</h2>
            <p>Duyệt đơn hàng và phân bổ bàn trống theo từng ngày</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert <?= $msg_type ?>">
                <i class="fa-solid <?= $msg_type === 'success' ? 'fa-check-circle' : 'fa-circle-exclamation' ?>"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="filter-bar">
            <form method="GET" action="admin_reservations.php" class="filter-form">
                <label for="filter_date" style="font-weight: 600; color: var(--dark);"><i class="fa-regular fa-calendar-days"></i> Lịch khách đến:</label>
                <input type="date" name="filter_date" id="filter_date" value="<?= htmlspecialchars($filter_date) ?>" onchange="this.form.submit()">
            </form>
            <div class="filter-form">
                <a href="?filter_date=<?= $prev_date ?>" class="btn-date"><i class="fa-solid fa-chevron-left"></i> Ngày trước</a>
                <a href="?filter_date=<?= date('Y-m-d') ?>" class="btn-date btn-today">Hôm nay</a>
                <a href="?filter_date=<?= $next_date ?>" class="btn-date">Ngày sau <i class="fa-solid fa-chevron-right"></i></a>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Mã Đơn</th>
                        <th>Khách Hàng</th>
                        <th>SĐT</th>
                        <th>Giờ Đến</th>
                        <th>Số Người</th>
                        <th>Bàn Số</th>
                        <th>Trạng Thái</th>
                        <th>Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($reservations) > 0): ?>
                        <?php foreach ($reservations as $row): ?>
                            <?php 
                                $statusClass = 'pending';
                                if ($row['bookingStatus'] === 'Đã xác nhận') $statusClass = 'confirmed';
                                if ($row['bookingStatus'] === 'Đang phục vụ' || $row['bookingStatus'] === 'Hoàn thành') $statusClass = 'serving';
                                if ($row['bookingStatus'] === 'Đã hủy') $statusClass = 'cancelled';
                            ?>
                            <tr>
                                <td><strong><?= $row['reservationId'] ?></strong></td>
                                <td><?= htmlspecialchars($row['fullName']) ?></td>
                                <td><?= htmlspecialchars($row['phone']) ?></td>
                                <td><strong style="color:var(--primary);"><?= date('H:i', strtotime($row['reservationTime'])) ?></strong></td>
                                <td><?= $row['numberOfGuests'] ?></td>
                                <td>
                                    <?= $row['tableNumber'] ? '<strong style="color:var(--primary);">Bàn '.$row['tableNumber'].'</strong>' : '<span style="color:#aaa;font-style:italic;">Chưa gán</span>' ?>
                                </td>
                                <td><span class="status-badge <?= $statusClass ?>"><?= $row['bookingStatus'] ?></span></td>
                                <td>
                                    <?php if ($row['bookingStatus'] !== 'Đã hủy'): ?>
                                        <button class="btn-edit" onclick="openUpdateModal('<?= $row['reservationId'] ?>', '<?= $row['bookingStatus'] ?>', '<?= $row['tableId'] ?>')">
                                            <i class="fa-solid fa-pen-to-square"></i> Cập nhật
                                        </button>
                                    <?php else: ?>
                                        <span style="color: #ccc; font-style: italic;"><i class="fa-solid fa-lock"></i> Đã đóng</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align: center; color: #aaa; padding: 40px; font-size: 15px;">Không có lượt đặt bàn nào trong ngày <strong><?= date('d/m/Y', strtotime($filter_date)) ?></strong>.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="updateModal" class="modal-overlay">
        <div class="modal-box">
            <i class="fa-solid fa-xmark close-modal" onclick="closeUpdateModal()"></i>
            <h3>Cập Nhật Đơn #<span id="displayResId"></span></h3>
            <form method="POST" action="admin_reservations.php">
                <input type="hidden" name="action" value="update_booking">
                <input type="hidden" name="reservationId" id="inputResId">
                <input type="hidden" name="current_filter_date" value="<?= htmlspecialchars($filter_date) ?>">

                <div class="form-group">
                    <label>Trạng thái</label>
                    <select name="bookingStatus" id="inputStatus">
                        <option value="Chờ xác nhận">Chờ xác nhận</option>
                        <option value="Đã xác nhận">Đã xác nhận (Duyệt)</option>
                        <option value="Đang phục vụ">Đang phục vụ</option>
                        <option value="Hoàn thành">Hoàn thành</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Gán bàn (Chỉ hiện bàn trống)</label>
                    <select name="tableId" id="inputTable">
                        <option value="">-- Chưa gán bàn --</option>
                        <?php foreach ($tables as $t): ?>
                            <option value="<?= $t['tableId'] ?>">Bàn số <?= $t['tableNumber'] ?> (Tối đa <?= $t['capacity'] ?> người)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-submit">Lưu Cập Nhật</button>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('updateModal');
        function openUpdateModal(id, status, tableId) {
            document.getElementById('displayResId').innerText = id;
            document.getElementById('inputResId').value = id;
            document.getElementById('inputStatus').value = status;
            document.getElementById('inputTable').value = tableId;
            modal.classList.add('show');
        }
        function closeUpdateModal() { modal.classList.remove('show'); }
        
        window.addEventListener('click', function(e) {
            if (e.target === modal) closeUpdateModal();
        });
    </script>
</body>
</html>