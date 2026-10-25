<?php
session_start();
require_once 'db.php';

$message = '';
$msg_type = '';

// =====================================================
// Đọc tham số redirect từ URL (?redirect=booking)
// =====================================================
if (isset($_GET['redirect'])) {
    // Đến từ nút "Đặt Bàn Ngay" → lưu redirect vào session
    $_SESSION['redirect_after_login'] = $_GET['redirect'];
} elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Vào login.php trực tiếp (không có ?redirect) → xóa session cũ
    // Tránh trường hợp session còn tồn đọng từ lần bấm "Đặt Bàn" trước đó
    unset($_SESSION['redirect_after_login']);
}
$redirect_target = $_SESSION['redirect_after_login'] ?? '';

// Nếu vào từ trang đặt bàn → hiện thông báo yêu cầu đăng nhập
if ($redirect_target === 'booking' && $_SERVER["REQUEST_METHOD"] !== "POST") {
    $message = "Bạn cần đăng nhập để đặt bàn. Vui lòng đăng nhập để tiếp tục!";
    $msg_type = "info";
}

// Khởi tạo biến lưu thông tin đăng nhập (ưu tiên dữ liệu vừa gõ, sau đó Cookie)
$login_id  = isset($_POST['login_id'])  ? trim($_POST['login_id'])  : (isset($_COOKIE['user_login']) ? $_COOKIE['user_login'] : '');
$saved_pwd = isset($_COOKIE['user_pwd']) ? $_COOKIE['user_pwd'] : '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST['password'];
    $remember = isset($_POST['remember']); 

    if (empty($login_id) || empty($password)) {
        $message  = "Vui lòng nhập đầy đủ thông tin đăng nhập!";
        $msg_type = "error";
    } else {
        $sql  = "SELECT * FROM Users WHERE email = :login_id OR phone = :login_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':login_id' => $login_id]);

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // =========================================================
            // Xử lý đồng bộ dữ liệu cũ, chỉ chặn tài khoản 'Locked'
            // =========================================================
            $currentStatus = $user['status'] ?? 'Active';
            
            if ($currentStatus === 'Locked') {
                $message  = "Tài khoản của bạn đã bị khóa do vi phạm. Vui lòng liên hệ Hotline (090.123.4567) để được hỗ trợ!";
                $msg_type = "error";
            } elseif (password_verify($password, $user['password'])) {

                // Xử lý cookie ghi nhớ
                if ($remember) {
                    setcookie("user_login", $login_id, time() + (86400 * 30), "/");
                    setcookie("user_pwd",   $password,  time() + (86400 * 30), "/");
                } else {
                    setcookie("user_login", "", time() - 3600, "/");
                    setcookie("user_pwd",   "", time() - 3600, "/");
                }

                // Lưu thông tin vào session
                $_SESSION['userId']   = $user['userId'];
                $_SESSION['fullName'] = $user['fullName'];
                $_SESSION['role']     = $user['role'];

                // =========================================================
                // LOGIC ĐIỀU HƯỚNG PHÂN QUYỀN
                // =========================================================
                if ($user['role'] === 'Admin') {
                    // Nếu tài khoản có quyền Admin -> Đi thẳng vào trang quản trị
                    $go_to = 'admin_dashboard.php';
                } else {
                    // Nếu là khách hàng bình thường -> Đi tới trang đặt bàn hoặc trang chủ
                    $go_to = ($redirect_target === 'booking') ? 'booking.php' : 'index.php';
                }

                unset($_SESSION['redirect_after_login']); // Dọn session redirect
                header("Location: " . $go_to);
                exit();

            } else {
                $message  = "Mật khẩu không chính xác, vui lòng thử lại!";
                $msg_type = "error";
            }
        } else {
            $message  = "Email hoặc Số điện thoại này chưa được đăng ký!";
            $msg_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - Nhà hàng Trạm Thực</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #fcf8f2; color: #333; margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; }
        .navbar { background-color: #d35400; padding: 15px 50px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .navbar .logo { color: #fff; font-size: 24px; font-weight: bold; text-decoration: none; }
        .navbar .nav-links { display: flex; align-items: center; }
        .navbar .nav-links a { color: #fff; text-decoration: none; margin-left: 20px; font-weight: 500; border-bottom: 2px solid transparent; padding-bottom: 4px; transition: all 0.2s ease; }
        .navbar .nav-links a:hover { color: #fff; }
        .navbar .nav-links a.active { font-weight: bold; border-bottom: 2px solid #fff; }
        .navbar .nav-links a.btn-booking { background: #fff; color: #d35400 !important; padding: 8px 15px; border-radius: 5px; font-weight: bold; border-bottom: none !important; }
        
        .main-content { flex: 1; display: flex; justify-content: center; align-items: center; padding: 40px 20px; }
        .login-container { background: #fff; width: 100%; max-width: 450px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); padding: 40px; border-top: 5px solid #d35400; }
        .login-container h2 { text-align: center; color: #d35400; margin-top: 0; margin-bottom: 10px; font-size: 28px; }
        .login-container p.subtitle { text-align: center; color: #7f8c8d; margin-bottom: 30px; font-size: 15px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #2c3e50; font-size: 14px; }
        .form-group input[type="text"], .form-group input[type="password"], .form-group input[type="email"] { width: 100%; padding: 14px 15px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 15px; box-sizing: border-box; transition: all 0.3s; background: #fdfdfd; }
        .form-group input:focus { outline: none; border-color: #d35400; box-shadow: 0 0 0 4px rgba(211,84,0,0.1); background: #fff; }
        
        .btn-submit { width: 100%; background: linear-gradient(135deg, #d35400, #f39c12); color: white; border: none; padding: 15px; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; transition: all 0.3s; margin-top: 5px; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(211,84,0,0.4); }
        
        .register-link { text-align: center; margin-top: 25px; font-size: 14px; color: #7f8c8d; }
        .register-link a { color: #d35400; text-decoration: none; font-weight: bold; transition: 0.3s; }
        .register-link a:hover { color: #e67e22; text-decoration: underline; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 25px; text-align: center; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .alert.error { background-color: #fff2f2; color: #e74c3c; border-left: 4px solid #e74c3c; }
        .alert.info  { background-color: #fff8e1; color: #e67e22; border-left: 4px solid #e67e22; }
        
        .form-utilities { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; font-size: 14.5px; }
        .form-utilities label { display: flex; align-items: center; font-weight: 500; cursor: pointer; margin: 0; color: #7f8c8d; }
        .form-utilities input[type="checkbox"] { width: 16px; height: 16px; margin-right: 8px; cursor: pointer; accent-color: #d35400; }
        .form-utilities a { color: #d35400; text-decoration: none; font-weight: 600; transition: 0.3s; }
        .form-utilities a:hover { color: #e67e22; text-decoration: underline; }

        /* =========================================
           CSS CHO MODAL QUÊN MẬT KHẨU
           ========================================= */
        .modal-overlay { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; animation: fadeIn 0.3s; }
        .modal-box { background: #fff; width: 100%; max-width: 400px; padding: 35px 30px; border-radius: 12px; box-shadow: 0 15px 40px rgba(0,0,0,0.2); position: relative; text-align: center; }
        .modal-box h3 { color: #d35400; margin-top: 0; font-size: 24px; margin-bottom: 10px; }
        .modal-box p { color: #7f8c8d; font-size: 14px; margin-bottom: 25px; line-height: 1.5; }
        .close-modal { position: absolute; top: 15px; right: 18px; font-size: 22px; color: #aaa; cursor: pointer; transition: 0.2s; }
        .close-modal:hover { color: #e74c3c; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="logo">Nhà hàng Trạm Thực</a>
        <div class="nav-links">
            <a href="index.php">Trang Chủ</a>
            <a href="register.php">Đăng Ký</a>
            <a href="login.php" class="active">Đăng Nhập</a>
            <a href="javascript:void(0)" onclick="alert('Bạn đang ở trang Đăng nhập rồi, hãy điền thông tin để tiếp tục đặt bàn nhé!');" class="btn-booking">
                Đặt Bàn Ngay
            </a>
        </div>
    </nav>

    <div class="main-content">
        <div class="login-container">
            <h2>Đăng Nhập</h2>

            <?php if ($redirect_target === 'booking'): ?>
                <p class="subtitle">Đăng nhập để hoàn tất đặt bàn của bạn</p>
            <?php else: ?>
                <p class="subtitle">Mừng bạn quay trở lại với Trạm Thực</p>
            <?php endif; ?>

            <?php if (!empty($message)): ?>
                <div class="alert <?= $msg_type ?>">
                    <i class="fa-solid <?= $msg_type === 'info' ? 'fa-circle-info' : 'fa-circle-exclamation' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="login_id">Email hoặc Số điện thoại</label>
                    <input type="text" id="login_id" name="login_id" required placeholder="Nhập email hoặc số điện thoại" value="<?= htmlspecialchars($login_id) ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Mật khẩu</label>
                    <input type="password" id="password" name="password" required placeholder="Nhập mật khẩu" value="<?= htmlspecialchars($saved_pwd) ?>">
                </div>

                <div class="form-utilities">
                    <label>
                        <input type="checkbox" name="remember" <?= !empty($saved_pwd) ? 'checked' : '' ?>>
                        Ghi nhớ đăng nhập
                    </label>
                    <a href="javascript:void(0)" id="forgotPwdLink">Quên mật khẩu?</a>
                </div>

                <button type="submit" class="btn-submit">
                    <?= $redirect_target === 'booking' ? 'Đăng Nhập & Đặt Bàn' : 'Đăng Nhập Vào Hệ Thống' ?>
                </button>
            </form>

            <div class="register-link">
                Chưa có tài khoản? <a href="register.php">Đăng ký thành viên mới</a>
            </div>
        </div>
    </div>

    <div id="forgotPwdModal" class="modal-overlay">
        <div class="modal-box">
            <i class="fa-solid fa-xmark close-modal" id="closeModal"></i>
            
            <h3>Khôi Phục Mật Khẩu</h3>
            <p>Vui lòng nhập địa chỉ Email đã đăng ký để nhận mã OTP xác thực.</p>
            
            <form method="POST" action="forgot_password.php">
                <div class="form-group" style="text-align: left;">
                    <input type="email" name="email" required placeholder="Nhập địa chỉ Email (@gmail.com)..." style="width: 100%; padding: 13px 15px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 15px; background: #f9f9f9;">
                </div>
                <button type="submit" class="btn-submit" style="margin-top: 0; border-radius: 8px;">Gửi Mã OTP Ngay</button>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('forgotPwdModal');
        const link = document.getElementById('forgotPwdLink');
        const closeBtn = document.getElementById('closeModal');

        // Hiện modal khi bấm vào Quên mật khẩu
        link.addEventListener('click', function(e) {
            e.preventDefault();
            modal.classList.add('show');
        });

        // Ẩn modal khi bấm dấu X
        closeBtn.addEventListener('click', function() {
            modal.classList.remove('show');
        });

        // Ẩn modal khi bấm ra vùng đen bên ngoài
        window.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        });
    </script>
</body>
</html>