<?php
session_start();
require_once 'db.php';

// Bảo vệ trang: Nếu chưa đăng nhập thì đuổi thẳng về login.php
if (!isset($_SESSION['userId'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['userId'];
$message = ''; $msg_type = '';

// Lấy thông tin hiện tại của user để hiển thị vào form
$stmt = $conn->prepare("SELECT * FROM Users WHERE userId = :userId");
$stmt->execute([':userId' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    
    // Nhận dữ liệu thông tin cá nhân
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);

    // Nhận dữ liệu mật khẩu (có thể rỗng)
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $update_password_flag = false;

    // 1. Kiểm tra Thông tin cá nhân cơ bản
    if (empty($fullname) || empty($phone) || empty($email)) {
        $message = "Vui lòng không để trống Họ tên, SĐT và Email!";
        $msg_type = "error";
    } elseif (!preg_match('/@gmail\.com$/i', $email)) {
        $message = "Hệ thống chỉ chấp nhận định dạng đuôi @gmail.com!";
        $msg_type = "error";
    } else {
        // Kiểm tra trùng lặp (ngoại trừ user hiện tại)
        $check_sql = "SELECT * FROM Users WHERE (email = :email OR phone = :phone) AND userId != :userId";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([':email' => $email, ':phone' => $phone, ':userId' => $userId]);

        if ($check_stmt->rowCount() > 0) {
            $message = "Email hoặc Số điện thoại đã được người khác sử dụng!";
            $msg_type = "error";
        } else {
            // 2. Kiểm tra xem người dùng có muốn đổi mật khẩu không
            if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $message = "Vui lòng điền đầy đủ 3 ô mật khẩu nếu bạn muốn thay đổi!";
                    $msg_type = "error";
                } elseif (!password_verify($current_password, $user['password'])) {
                    $message = "Mật khẩu hiện tại không chính xác!";
                    $msg_type = "error";
                } elseif ($new_password !== $confirm_password) {
                    $message = "Mật khẩu mới không khớp, vui lòng xác nhận lại!";
                    $msg_type = "error";
                } else {
                    $update_password_flag = true; // Bật cờ cho phép đổi mật khẩu
                }
            }

            // 3. Tiến hành lưu dữ liệu nếu không có bất kỳ lỗi nào
            if (empty($message)) {
                try {
                    if ($update_password_flag) {
                        // Cập nhật CẢ thông tin VÀ mật khẩu
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $sql = "UPDATE Users SET fullName = :fullname, phone = :phone, email = :email, password = :password WHERE userId = :userId";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([':fullname' => $fullname, ':phone' => $phone, ':email' => $email, ':password' => $hashed_password, ':userId' => $userId]);
                        $user['password'] = $hashed_password; // Cập nhật biến tạm
                    } else {
                        // CHỈ cập nhật thông tin cá nhân
                        $sql = "UPDATE Users SET fullName = :fullname, phone = :phone, email = :email WHERE userId = :userId";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([':fullname' => $fullname, ':phone' => $phone, ':email' => $email, ':userId' => $userId]);
                    }

                    $message = "Lưu hồ sơ thành công!";
                    $msg_type = "success";
                    
                    // Cập nhật lại Session và biến hiển thị trên màn hình
                    $_SESSION['fullName'] = $fullname;
                    $user['fullName'] = $fullname;
                    $user['phone'] = $phone;
                    $user['email'] = $email;

                } catch(PDOException $e) {
                    $message = "Lỗi hệ thống: " . $e->getMessage();
                    $msg_type = "error";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ Sơ Cá Nhân - Nhà Hàng Trạm Thực</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #fcf8f2; color: #333; margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; }
        
        /* NAVBAR */
        .navbar { background-color: #d35400; padding: 15px 50px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .navbar .logo { color: #fff; font-size: 24px; font-weight: bold; text-decoration: none; }
        .navbar .nav-links { display: flex; align-items: center; }
        .navbar .nav-links a { color: #fff; text-decoration: none; font-weight: 500; transition: color 0.2s; }
        .navbar .nav-links a:hover { color: #f1c40f; }

        /* KHU VỰC TIÊU ĐỀ CHÍNH */
        .page-title-section { width: 100%; max-width: 800px; margin: 35px auto 10px auto; padding: 0 20px; box-sizing: border-box; }
        .page-title-section h1 { color: #2c3e50; font-size: 28px; margin: 0; font-weight: bold; }

        /* KHU VỰC HỒ SƠ CÁ NHÂN */
        .main-content { flex: 1; padding: 10px 20px 50px 20px; display: flex; justify-content: center; }
        .profile-container { width: 100%; max-width: 800px; }
        
        .profile-card { background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); padding: 40px; border-top: 5px solid #d35400; }
        .profile-card h2 { color: #d35400; margin-top: 0; margin-bottom: 5px; font-size: 22px; display: flex; align-items: center; gap: 10px;}
        .profile-card p.desc { color: #7f8c8d; font-size: 14px; margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 15px;}
        
        /* ĐƯỜNG KẺ CHIA CÁC KHỐI TRONG CÙNG 1 FORM */
        .section-divider { border: 0; border-top: 1px dashed #ddd; margin: 40px 0 30px 0; }

        /* FORM */
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #2c3e50; font-size: 14px; }
        .form-group input { width: 100%; padding: 12px 15px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 15px; box-sizing: border-box; transition: all 0.3s; background: #fdfdfd;}
        .form-group input:focus { outline: none; border-color: #d35400; box-shadow: 0 0 0 4px rgba(211,84,0,0.1); background: #fff;}
        .form-group input:disabled { background: #eee; cursor: not-allowed; color: #7f8c8d; }
        
        /* CỤM NÚT BẤM (LƯU VÀ HỦY Ở CUỐI) */
        .btn-group { display: flex; gap: 15px; width: 100%; margin-top: 30px; }
        
        .btn-submit { flex: 1; background: #d35400; color: white; border: none; padding: 14px 20px; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; transition: all 0.3s; text-align: center; }
        .btn-submit:hover { background: #e67e22; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(211,84,0,0.2); }
        
        .btn-cancel { flex: 1; background: #fff; color: #7f8c8d; border: 2px solid #e0e0e0; padding: 14px 20px; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; transition: all 0.3s; text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center; box-sizing: border-box; }
        .btn-cancel:hover { border-color: #d35400; color: #d35400; background: #fffaf5; transform: translateY(-2px); }

        /* ALERT */
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 25px; font-size: 14px; font-weight: 500; }
        .alert.error { background-color: #fff2f2; color: #e74c3c; border-left: 4px solid #e74c3c; }
        .alert.success { background-color: #f0fdf4; color: #16a34a; border-left: 4px solid #16a34a; }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="index.php" class="logo">Nhà hàng Trạm Thực</a>
        <div class="nav-links">
            <a href="index.php" style="font-weight: bold; font-size: 16px;">
                <i class="fa-solid fa-house"></i> Về Trang chủ
            </a>
        </div>
    </nav>

    <div class="page-title-section">
        <h1>Quản lý hồ sơ cá nhân</h1>
    </div>

    <div class="main-content">
        <div class="profile-container">
            
            <div class="profile-card">
                
                <?php if (!empty($message)): ?>
                    <div class="alert <?= $msg_type ?>"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <form method="POST" action="profile.php">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <h2><i class="fa-solid fa-address-card"></i> Sửa thông tin cá nhân</h2>
                    <p class="desc">Đảm bảo thông tin liên lạc chính xác để chúng tôi phục vụ bạn tốt nhất khi đặt bàn.</p>

                    <div class="form-group">
                        <label>Mã khách hàng (Mặc định)</label>
                        <input type="text" value="<?= htmlspecialchars($user['userId']) ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label for="fullname">Họ và tên *</label>
                        <input type="text" id="fullname" name="fullname" required value="<?= htmlspecialchars($user['fullName']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="phone">Số điện thoại *</label>
                        <input type="tel" id="phone" name="phone" required value="<?= htmlspecialchars($user['phone']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="email">Địa chỉ Email *</label>
                        <input type="text" id="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>">
                    </div>

                    <hr class="section-divider">

                    <h2><i class="fa-solid fa-shield-halved"></i> Thay đổi mật khẩu</h2>
                    <p class="desc">Nếu không muốn thay đổi mật khẩu, vui lòng bỏ trống các ô bên dưới.</p>

                    <div class="form-group">
                        <label for="current_password">Mật khẩu hiện tại</label>
                        <input type="password" id="current_password" name="current_password" placeholder="Nhập mật khẩu hiện tại">
                    </div>

                    <div class="form-group">
                        <label for="new_password">Mật khẩu mới</label>
                        <input type="password" id="new_password" name="new_password" placeholder="Nhập mật khẩu mới">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Xác nhận mật khẩu mới</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Xác nhận mật khẩu mới">
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn-submit">Lưu Thay Đổi</button>
                        <a href="profile.php" class="btn-cancel">Hủy</a>
                    </div>
                </form>

            </div>

        </div>
    </div>

</body>
</html>