<?php
session_start();
require_once 'db.php';

// Đặt múi giờ Việt Nam để tính toán thời gian chính xác
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Bảo vệ trang: Yêu cầu đăng nhập
if (!isset($_SESSION['userId'])) {
    header("Location: login.php?redirect=booking");
    exit();
}

// CHẶN ADMIN KHÔNG CHO VÀO TRANG ĐẶT BÀN CỦA KHÁCH
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') {
    header("Location: admin_dashboard.php");
    exit();
}

$userId = $_SESSION['userId'];

// ==========================================
// XỬ LÝ: HỦY ĐẶT BÀN
// ==========================================
if (isset($_GET['cancel_id'])) {
    $cancelId = $_GET['cancel_id'];
    
    $stmtCheck = $conn->prepare("SELECT reservationTime, bookingStatus FROM Reservations WHERE reservationId = :reservationId AND userId = :userId");
    $stmtCheck->execute([':reservationId' => $cancelId, ':userId' => $userId]);
    $bookingCheck = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($bookingCheck) {
        $status = $bookingCheck['bookingStatus'];
        $timeDifference = strtotime($bookingCheck['reservationTime']) - time();

        if ($status == 'Chờ xác nhận') {
            $stmtCancel = $conn->prepare("UPDATE Reservations SET bookingStatus = 'Đã hủy' WHERE reservationId = :reservationId");
            $stmtCancel->execute([':reservationId' => $cancelId]);
            $_SESSION['flash_message'] = "Đã hủy bàn thành công!";
            $_SESSION['flash_type'] = "success";
        } elseif ($status == 'Đã xác nhận') {
            if ($timeDifference >= 7200) {
                $stmtCancel = $conn->prepare("UPDATE Reservations SET bookingStatus = 'Đã hủy' WHERE reservationId = :reservationId");
                $stmtCancel->execute([':reservationId' => $cancelId]);
                $_SESSION['flash_message'] = "Đã hủy bàn thành công!";
                $_SESSION['flash_type'] = "success";
            } else {
                $_SESSION['flash_message'] = "Không thể hủy! Đơn đã được xác nhận, bạn chỉ có thể hủy trước giờ đến ít nhất 2 tiếng.";
                $_SESSION['flash_type'] = "error";
            }
        }
    }
    header("Location: booking.php");
    exit();
}

// ==========================================
// XỬ LÝ: GỬI FORM ĐẶT BÀN
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'book_table') {
    $numberOfGuests       = (int)$_POST['numberOfGuests'];
    // [MỚI] Số trẻ em — mặc định 0 nếu không điền
    $numberOfChildren     = isset($_POST['numberOfChildren']) ? (int)$_POST['numberOfChildren'] : 0;
    $bookingDate          = $_POST['bookingDate'];
    $bookingTime          = $_POST['bookingTime'];
    // [MỚI] Yêu cầu đặt bàn
    $specialRequirements  = trim($_POST['specialRequirements'] ?? '');
    $notes                = trim($_POST['notes']);

    $reservationTime      = $bookingDate . ' ' . $bookingTime . ':00';
    $selectedDateTimestamp = strtotime($reservationTime);

    // ==========================================
    // KIỂM TRA ĐIỀU KIỆN LOGIC DỮ LIỆU ĐẦU VÀO
    // ==========================================
    if ($numberOfGuests < 1) {
        $_SESSION['flash_message'] = "Số lượng người phải lớn hơn 0!";
        $_SESSION['flash_type'] = "error";
    } elseif ($numberOfGuests > 20) {
        $_SESSION['flash_message'] = "Số lượng khách tối đa là 20 người. Vui lòng liên hệ trực tiếp nếu có nhu cầu đặt bàn lớn hơn.";
        $_SESSION['flash_type'] = "error";
    } elseif ($numberOfChildren < 0) {
        // [MỚI] Kiểm tra số trẻ em không âm
        $_SESSION['flash_message'] = "Số lượng trẻ em không hợp lệ!";
        $_SESSION['flash_type'] = "error";
    } elseif ($numberOfChildren > $numberOfGuests) {
        // [MỚI] Số trẻ em không vượt tổng số khách
        $_SESSION['flash_message'] = "Số lượng trẻ em không thể lớn hơn tổng số khách!";
        $_SESSION['flash_type'] = "error";
    } elseif ($selectedDateTimestamp < time()) {
        $_SESSION['flash_message'] = "Không thể đặt bàn cho thời gian trong quá khứ!";
        $_SESSION['flash_type'] = "error";
    } elseif ($bookingTime < '08:00' || $bookingTime > '21:00') {
        $_SESSION['flash_message'] = "Rất tiếc, nhà hàng chỉ nhận đón khách trong khung giờ từ 08:00 sáng đến 21:00 tối để đảm bảo chất lượng phục vụ. Vui lòng chọn lại giờ!";
        $_SESSION['flash_type'] = "error";
    } else {
        // Sinh mã Đơn đặt bàn tự động
        $id_query   = "SELECT reservationId FROM Reservations ORDER BY reservationId DESC LIMIT 1";
        $id_stmt    = $conn->query($id_query);
        $last_booking = $id_stmt->fetch(PDO::FETCH_ASSOC);

        if ($last_booking) {
            $last_id_num      = (int)substr($last_booking['reservationId'], 2);
            $new_reservationId = 'RV' . str_pad($last_id_num + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $new_reservationId = 'RV0001';
        }

        // [MỚI] Thêm numberOfChildren và specialRequirements vào INSERT
        $insert_sql = "INSERT INTO Reservations 
                        (reservationId, userId, tableId, numberOfGuests, numberOfChildren, reservationTime, specialRequirements, notes, bookingStatus) 
                       VALUES 
                        (:reservationId, :userId, NULL, :numberOfGuests, :numberOfChildren, :reservationTime, :specialRequirements, :notes, 'Chờ xác nhận')";
        $insert_stmt = $conn->prepare($insert_sql);
        
        try {
            $insert_stmt->execute([
                ':reservationId'      => $new_reservationId,
                ':userId'             => $userId,
                ':numberOfGuests'     => $numberOfGuests,
                ':numberOfChildren'   => $numberOfChildren,
                ':reservationTime'    => $reservationTime,
                ':specialRequirements'=> $specialRequirements,
                ':notes'              => $notes
            ]);

            $_SESSION['flash_message'] = "Đặt bàn thành công! Hệ thống đã ghi nhận lịch của bạn.";
            $_SESSION['flash_type'] = "success";
        } catch(PDOException $e) {
            $_SESSION['flash_message'] = "Lỗi hệ thống: " . $e->getMessage();
            $_SESSION['flash_type'] = "error";
        }
    }
    // PRG: Redirect sau POST để tránh gửi lại form khi F5
    header("Location: booking.php");
    exit();
}

// Đọc flash message
$message  = '';
$msg_type = '';
if (isset($_SESSION['flash_message'])) {
    $message  = $_SESSION['flash_message'];
    $msg_type = $_SESSION['flash_type'];
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// Lấy thông tin khách hàng và lịch sử
$stmtUser = $conn->prepare("SELECT fullName, phone, email FROM Users WHERE userId = :userId");
$stmtUser->execute([':userId' => $userId]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

$stmtHistory = $conn->prepare("SELECT * FROM Reservations WHERE userId = :userId ORDER BY createdAt DESC");
$stmtHistory->execute([':userId' => $userId]);
$historyList = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đặt Bàn - Nhà Hàng Trạm Thực</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 16px; background-color: #fcf8f2; color: #333; margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; }
        .navbar { background-color: #d35400; padding: 15px 50px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .navbar .logo { color: #fff; font-size: 26px; font-weight: bold; text-decoration: none; }
        .navbar .nav-links a { color: #fff; text-decoration: none; font-weight: bold; font-size: 17px; transition: color 0.2s; }
        .navbar .nav-links a:hover { color: #f1c40f; }
        .page-title { width: 100%; max-width: 1500px; margin: 35px auto 10px auto; padding: 0 30px; color: #2c3e50; font-size: 30px; font-weight: bold; }
        .main-content { flex: 1; padding: 10px 30px 50px 30px; display: flex; justify-content: center; }
        .booking-container { width: 100%; max-width: 1500px; display: flex; gap: 30px; align-items: flex-start; flex-wrap: wrap; }
        
        .booking-form-card { flex: 1.1; min-width: 420px; background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); padding: 40px; border-top: 5px solid #d35400; }
        .booking-form-card h2 { color: #d35400; margin-top: 0; margin-bottom: 5px; font-size: 23px; display: flex; align-items: center; gap: 10px; }
        .booking-form-card p.desc { color: #7f8c8d; font-size: 15px; margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        
        .form-group { margin-bottom: 20px; }
        .form-row { display: flex; gap: 15px; }
        .form-row .form-group { flex: 1; }
        
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #2c3e50; font-size: 15px; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 12px 15px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 16px; box-sizing: border-box; transition: all 0.3s; font-family: inherit; background: #fff; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: #d35400; box-shadow: 0 0 0 4px rgba(211,84,0,0.1); }
        .form-group input:read-only { background: #f9f9f9; color: #7f8c8d; cursor: not-allowed; border-color: #eee; }

        /* [MỚI] Tag gợi ý cho yêu cầu đặt bàn */
        .req-tags { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
        .req-tag { padding: 5px 12px; border: 1px solid #d35400; border-radius: 20px; font-size: 13px; color: #d35400; cursor: pointer; transition: 0.2s; user-select: none; background: #fff; }
        .req-tag:hover, .req-tag.active { background: #d35400; color: #fff; }
        
        .btn-submit { width: 100%; background: #d35400; color: white; border: none; padding: 15px; border-radius: 8px; font-size: 17px; font-weight: bold; cursor: pointer; transition: all 0.3s; margin-top: 10px; }
        .btn-submit:hover { background: #e67e22; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(211,84,0,0.2); }

        .history-card { flex: 2; min-width: 550px; background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); padding: 40px; border-top: 5px solid #2c3e50; }
        .history-card h2 { color: #2c3e50; margin-top: 0; margin-bottom: 25px; font-size: 23px; display: flex; align-items: center; gap: 10px; }
        
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 15px; }
        th { background-color: #fcf8f2; color: #d35400; padding: 15px; font-weight: bold; border-bottom: 2px solid #f3e5d8; font-size: 15px; white-space: nowrap; }
        td { padding: 15px; border-bottom: 1px solid #eee; color: #2c3e50; vertical-align: middle; }
        tr:hover { background-color: #fafafa; }

        /* [MỚI] Hiển thị số trẻ em và yêu cầu trong bảng */
        .guest-detail { font-size: 13px; color: #7f8c8d; margin-top: 3px; }
        .guest-detail i { color: #e67e22; margin-right: 3px; }
        .req-cell { max-width: 160px; }
        .req-text { color: #555; font-size: 13.5px; font-style: italic; }
        .notes-cell { max-width: 130px; }
        .notes-text { color: #555; font-size: 13.5px; font-style: italic; }
        .notes-empty { color: #bbb; font-size: 13px; font-style: italic; }
        
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 13px; font-weight: bold; white-space: nowrap; display: inline-block; }
        .status-cho-xac-nhan { background: #fff3cd; color: #f39c12; }
        .status-da-xac-nhan { background: #d4edda; color: #27ae60; }
        .status-da-huy { background: #f8d7da; color: #c0392b; }

        .btn-cancel { background: #fff; color: #e74c3c; border: 1px solid #e74c3c; padding: 6px 12px; border-radius: 5px; font-size: 13px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; transition: 0.2s; }
        .btn-cancel:hover { background: #e74c3c; color: #fff; }
        .text-muted { color: #aaa; font-size: 13px; font-style: italic; }

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 25px; font-size: 15px; font-weight: 500; display: flex; align-items: center; gap: 10px; animation: slideIn 0.4s ease, fadeOut 0.6s ease 4.5s forwards; }
        .alert.error { background-color: #fff2f2; color: #e74c3c; border-left: 4px solid #e74c3c; }
        .alert.success { background-color: #f0fdf4; color: #16a34a; border-left: 4px solid #16a34a; }

        @keyframes slideIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeOut { from { opacity: 1; max-height: 80px; margin-bottom: 25px; padding: 15px; } to { opacity: 0; max-height: 0; margin-bottom: 0; padding: 0; overflow: hidden; } }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="index.php" class="logo">Nhà hàng Trạm Thực</a>
        <div class="nav-links">
            <a href="index.php"><i class="fa-solid fa-house"></i> Về Trang Chủ</a>
        </div>
    </nav>

    <div class="page-title">Đặt Bàn Trực Tuyến</div>

    <div class="main-content">
        <div class="booking-container">
            
            <div class="booking-form-card">
                <h2><i class="fa-solid fa-calendar-check"></i> Thông tin đặt bàn</h2>
                <p class="desc">Vui lòng điền thời gian dự kiến, chúng tôi sẽ chuẩn bị bàn chu đáo nhất.</p>

                <?php if (!empty($message)): ?>
                    <div class="alert <?= $msg_type ?>" id="flash-alert">
                        <i class="fa-solid <?= $msg_type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                        <?= htmlspecialchars($message) ?>
                    </div>
                    <script>
                        setTimeout(function() {
                            var el = document.getElementById('flash-alert');
                            if (el) el.remove();
                        }, 5100);
                    </script>
                <?php endif; ?>

                <form method="POST" action="booking.php">
                    <input type="hidden" name="action" value="book_table">
                    
                    <div class="form-group">
                        <label>Người đặt (Tự động)</label>
                        <input type="text" value="<?= htmlspecialchars($user['fullName']) ?> - <?= htmlspecialchars($user['phone']) ?>" readonly>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="bookingDate">Ngày đến *</label>
                            <input type="date" id="bookingDate" name="bookingDate" required min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label for="bookingTime">Giờ đến * <span style="color:#7f8c8d; font-weight:normal; font-size:13px;">(08:00 - 21:00)</span></label>
                            <input type="time" id="bookingTime" name="bookingTime" min="08:00" max="21:00" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="numberOfGuests">Số lượng khách * <span style="color:#7f8c8d; font-weight:normal; font-size:13px;">(tối đa 20)</span></label>
                            <input type="number" id="numberOfGuests" name="numberOfGuests" min="1" max="20" required placeholder="Ví dụ: 4">
                        </div>
                        <!-- [MỚI] Trường số trẻ em -->
                        <div class="form-group">
                            <label for="numberOfChildren">
                                <i class="fa-solid fa-child" style="color:#e67e22;"></i>
                                Số trẻ em <span style="color:#7f8c8d; font-weight:normal; font-size:13px;">(dưới 12 tuổi)</span>
                            </label>
                            <input type="number" id="numberOfChildren" name="numberOfChildren" min="0" max="20" value="0" placeholder="Ví dụ: 1">
                        </div>
                    </div>

                    <!-- [MỚI] Trường yêu cầu đặt bàn -->
                    <div class="form-group">
                        <label for="specialRequirements">
                            <i class="fa-solid fa-star" style="color:#e67e22;"></i>
                            Yêu cầu đặt bàn <span style="color:#7f8c8d; font-weight:normal; font-size:13px;">(Không bắt buộc)</span>
                        </label>
                        <!-- Tag gợi ý nhanh -->
                        <div class="req-tags" id="reqTags">
                            <span class="req-tag" data-value="Bàn gần cửa sổ">🪟 Bàn gần cửa sổ</span>
                            <span class="req-tag" data-value="Phòng riêng">🚪 Phòng riêng</span>
                            <span class="req-tag" data-value="Ghế trẻ em">🪑 Ghế trẻ em</span>
                            <span class="req-tag" data-value="Ít tiếng ồn">🔇 Ít tiếng ồn</span>
                            <span class="req-tag" data-value="Tổ chức sinh nhật">🎂 Sinh nhật</span>
                            <span class="req-tag" data-value="Không hải sản">🚫 Không hải sản</span>
                        </div>
                        <textarea id="specialRequirements" name="specialRequirements" rows="3" placeholder="Ví dụ: Muốn bàn gần cửa sổ, cần ghế trẻ em, tổ chức sinh nhật..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="notes">Ghi chú thêm <span style="color:#7f8c8d; font-weight:normal; font-size:13px;">(Không bắt buộc)</span></label>
                        <textarea id="notes" name="notes" rows="2" placeholder="Ví dụ: Dị ứng thực phẩm, yêu cầu đặc biệt khác..."></textarea>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-calendar-check"></i> Xác Nhận Đặt Bàn
                    </button>
                </form>
            </div>

            <div class="history-card">
                <h2><i class="fa-solid fa-clock-rotate-left"></i> Lịch sử đặt bàn của bạn</h2>
                
                <?php if (count($historyList) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Mã Đơn</th>
                                <th>Thời gian đến</th>
                                <th>Khách</th>
                                <th>Yêu cầu</th>
                                <th>Trạng thái</th>
                                <th>Ghi chú</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historyList as $row): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['reservationId']) ?></strong></td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($row['reservationTime'])) ?><br>
                                        <span style="color:#d35400; font-weight:bold;"><?= date('H:i', strtotime($row['reservationTime'])) ?></span>
                                    </td>
                                    <td>
                                        <!-- [MỚI] Hiển thị số người lớn và trẻ em -->
                                        <span><?= htmlspecialchars($row['numberOfGuests']) ?> người</span>
                                        <?php if (!empty($row['numberOfChildren']) && $row['numberOfChildren'] > 0): ?>
                                            <div class="guest-detail">
                                                <i class="fa-solid fa-child"></i><?= htmlspecialchars($row['numberOfChildren']) ?> trẻ em
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <!-- [MỚI] Cột yêu cầu đặt bàn -->
                                    <td class="req-cell">
                                        <?php if (!empty(trim($row['specialRequirements'] ?? ''))): ?>
                                            <span class="req-text"><?= htmlspecialchars($row['specialRequirements']) ?></span>
                                        <?php else: ?>
                                            <span class="notes-empty">— Không có —</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                            if ($row['bookingStatus'] == 'Chờ xác nhận') {
                                                echo '<span class="status-badge status-cho-xac-nhan">Chờ xác nhận</span>';
                                            } elseif (in_array($row['bookingStatus'], ['Đã xác nhận', 'Đang phục vụ', 'Hoàn thành'])) {
                                                echo '<span class="status-badge status-da-xac-nhan">Đã xác nhận</span>';
                                            } elseif ($row['bookingStatus'] == 'Đã hủy') {
                                                echo '<span class="status-badge status-da-huy">Đã hủy</span>';
                                            }
                                        ?>
                                    </td>
                                    <td class="notes-cell">
                                        <?php if (!empty(trim($row['notes']))): ?>
                                            <span class="notes-text"><?= htmlspecialchars($row['notes']) ?></span>
                                        <?php else: ?>
                                            <span class="notes-empty">— Không có —</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $canCancel  = false;
                                        $cancelNote = '';
                                        
                                        if ($row['bookingStatus'] == 'Chờ xác nhận') {
                                            $canCancel = true;
                                        } elseif ($row['bookingStatus'] == 'Đã xác nhận') {
                                            $diff      = strtotime($row['reservationTime']) - time();
                                            $canCancel = $diff >= 7200;
                                            if (!$canCancel) $cancelNote = 'Dưới 2 tiếng';
                                        } elseif (in_array($row['bookingStatus'], ['Đang phục vụ', 'Hoàn thành'])) {
                                            $cancelNote = 'Không khả dụng';
                                        }

                                        if ($canCancel): ?>
                                            <a href="booking.php?cancel_id=<?= $row['reservationId'] ?>" class="btn-cancel" onclick="return confirm('Bạn có chắc chắn muốn hủy lịch đặt bàn này không?');">Hủy bàn</a>
                                        <?php elseif ($cancelNote): ?>
                                            <span class="text-muted"><?= $cancelNote ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Không khả dụng</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #7f8c8d; text-align: center; padding: 20px; font-size: 16px;">Bạn chưa có lịch sử đặt bàn nào.</p>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script>
    // ==========================================
    // [MỚI] Xử lý tag gợi ý cho yêu cầu đặt bàn
    // ==========================================
    document.querySelectorAll('.req-tag').forEach(function(tag) {
        tag.addEventListener('click', function() {
            const textarea = document.getElementById('specialRequirements');
            const value    = this.dataset.value;

            // Toggle active class
            this.classList.toggle('active');

            // Thêm hoặc xóa giá trị trong textarea
            let current = textarea.value.split(',').map(s => s.trim()).filter(Boolean);
            const idx   = current.indexOf(value);
            if (idx === -1) {
                current.push(value);
            } else {
                current.splice(idx, 1);
            }
            textarea.value = current.join(', ');
        });
    });

    // Đồng bộ trạng thái tag khi người dùng tự nhập vào textarea
    document.getElementById('specialRequirements').addEventListener('input', function() {
        const current = this.value.split(',').map(s => s.trim());
        document.querySelectorAll('.req-tag').forEach(function(tag) {
            if (current.includes(tag.dataset.value)) {
                tag.classList.add('active');
            } else {
                tag.classList.remove('active');
            }
        });
    });
    </script>

</body>
</html>