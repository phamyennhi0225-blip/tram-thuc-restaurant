<?php
session_start();
require_once 'db.php'; // Gọi file kết nối CSDL

$message = '';
$msg_type = '';
$is_success = false; // Cờ hiệu kiểm tra trạng thái đăng ký thành công

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    // 1. Kiểm tra rỗng
    if (empty($email) || empty($fullname) || empty($phone) || empty($password) || empty($confirm_password)) {
        $message = "Lỗi, vui lòng nhập lại: Vui lòng điền đầy đủ các thông tin bắt buộc!";
        $msg_type = "error";
    } 
    // 2. Kiểm tra CHÍNH XÁC đuôi @gmail.com bằng PHP (Không phân biệt hoa thường)
    elseif (!preg_match('/@gmail\.com$/i', $email)) {
        $message = "Định dạng email không hợp lệ, vui lòng nhập lại!";
        $msg_type = "error";
    } 
    // 3. Kiểm tra mật khẩu trùng khớp
    elseif ($password !== $confirm_password) {
        $message = "Mật khẩu không khớp, vui lòng nhập lại!";
        $msg_type = "error";
    } 
    else {
        // 4. Kiểm tra xem Email HOẶC Số điện thoại đã tồn tại chưa
        $check_sql = "SELECT * FROM Users WHERE email = :email OR phone = :phone";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([':email' => $email, ':phone' => $phone]);

        if ($check_stmt->rowCount() > 0) {
            $existing_user = $check_stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing_user['email'] === $email) {
                $message = "Email này đã được đăng ký sử dụng, vui lòng nhập lại!";
            } else {
                $message = "Số điện thoại này đã được đăng ký, vui lòng nhập lại!";
            }
            $msg_type = "error";
        } else {
            // 5. THUẬT TOÁN TẠO MÃ USER ID TỰ ĐỘNG
            $id_query = "SELECT userId FROM Users ORDER BY userId DESC LIMIT 1";
            $id_stmt = $conn->query($id_query);
            $last_user = $id_stmt->fetch(PDO::FETCH_ASSOC);

            if ($last_user) {
                $last_id_num = (int)substr($last_user['userId'], 2);
                $new_id_num = $last_id_num + 1;
                $new_userId = 'US' . str_pad($new_id_num, 4, '0', STR_PAD_LEFT);
            } else {
                $new_userId = 'US0001';
            }

            // 6. Mã hóa mật khẩu và thiết lập trạng thái
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'Customer'; 
            $status = 'Active'; 

            // 7. Thêm bản ghi mới vào CSDL
            $insert_sql = "INSERT INTO Users (userId, fullName, phone, email, password, role, status) 
                           VALUES (:userId, :fullname, :phone, :email, :password, :role, :status)";
            $insert_stmt = $conn->prepare($insert_sql);
            
            try {
                $insert_stmt->execute([
                    ':userId' => $new_userId,
                    ':fullname' => $fullname,
                    ':phone' => $phone,
                    ':email' => $email,
                    ':password' => $hashed_password,
                    ':role' => $role,
                    ':status' => $status
                ]);
                $message = "Chào mừng bạn đến với Nhà hàng Trạm Thực.";
                $msg_type = "success";
                $is_success = true; // Bật cờ hiệu để chuyển giao diện
            } catch(PDOException $e) {
                $message = "Lỗi hệ thống: " . $e->getMessage();
                $msg_type = "error";
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
    <title>Đăng Ký Thành Viên - Nhà hàng Trạm Thực</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #fcf8f2; color: #333; margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; }
        
        /* NAVBAR */
        .navbar { background-color: #d35400; padding: 15px 50px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .navbar .logo { color: #fff; font-size: 24px; font-weight: bold; text-decoration: none; }
        .navbar .nav-links { display: flex; align-items: center; }
        .navbar .nav-links a { color: #fff; text-decoration: none; margin-left: 20px; font-weight: 500; border-bottom: 2px solid transparent; padding-bottom: 4px; transition: all 0.2s ease;}
        .navbar .nav-links a:hover { color: #ffffffff; }
        .navbar .nav-links a.active { font-weight: bold; color: #ffffffff; border-bottom: 2px solid #ffffffff; }
        .navbar .nav-links a.btn-booking { background: #fff; color: #d35400 !important; padding: 8px 15px; border-radius: 5px; font-weight: bold; border-bottom: none !important; }

        .main-content { flex: 1; display: flex; justify-content: center; align-items: center; padding: 40px 20px; }
        
        /* CONTAINER CHUNG */
        .register-container { background: #fff; width: 100%; max-width: 500px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); padding: 40px; border-top: 5px solid #d35400; position: relative;}
        .register-container h2 { text-align: center; color: #d35400; margin-top: 0; margin-bottom: 10px; font-size: 28px; }
        .register-container p.subtitle { text-align: center; color: #7f8c8d; margin-bottom: 30px; font-size: 15px; }
        
        /* FORM INPUT */
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #2c3e50; font-size: 14px; }
        .form-group input { width: 100%; padding: 14px 15px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 15px; box-sizing: border-box; transition: all 0.3s; background: #fdfdfd;}
        .form-group input:focus { outline: none; border-color: #d35400; box-shadow: 0 0 0 4px rgba(211,84,0,0.1); background: #fff;}
        
        .btn-submit { width: 100%; background: #d35400; color: #fff; border: none; padding: 15px; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; transition: all 0.3s; margin-top: 10px; display: block; text-align: center; text-decoration: none;}
        .btn-submit:hover { background: #e67e22; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(211,84,0,0.2);}
        
        .login-link { text-align: center; margin-top: 25px; font-size: 14px; color: #7f8c8d; }
        .login-link a { color: #d35400; text-decoration: none; font-weight: bold; transition: 0.3s;}
        .login-link a:hover { color: #e67e22; text-decoration: underline; }

        /* THÔNG BÁO LỖI */
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 25px; text-align: center; font-size: 14px; font-weight: 500; }
        .alert.error { background-color: #fff2f2; color: #e74c3c; border-left: 4px solid #e74c3c; }

        /* HOẠT ẢNH GIAO DIỆN THÀNH CÔNG */
        @keyframes popUp {
            0% { transform: scale(0.8); opacity: 0; }
            60% { transform: scale(1.05); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }

        @keyframes pulseGlow {
            0% { box-shadow: 0 0 0 0 rgba(46, 204, 113, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(46, 204, 113, 0); }
            100% { box-shadow: 0 0 0 0 rgba(46, 204, 113, 0); }
        }

        .success-box { text-align: center; padding: 20px 0; animation: popUp 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }
        
        .success-icon-wrapper {
            display: inline-flex; justify-content: center; align-items: center; width: 80px; height: 80px;
            background-color: #2ecc71; border-radius: 50%; margin: 0 auto 25px auto; animation: pulseGlow 2s infinite;
        }

        .success-icon-wrapper i { font-size: 40px; color: white; }
        .success-box h2 { color: #2c3e50; font-size: 32px; margin-bottom: 12px; }
        .success-box p.success-msg { color: #7f8c8d; font-size: 16px; margin-bottom: 35px; line-height: 1.5; }
        
        .success-actions { display: flex; gap: 15px; }
        .success-actions a { flex: 1; padding: 14px; border-radius: 8px; font-weight: 600; font-size: 15px; text-decoration: none; transition: all 0.3s ease; }
        
        .success-actions .btn-login-now { background: linear-gradient(135deg, #d35400, #f39c12); color: white; box-shadow: 0 4px 15px rgba(211, 84, 0, 0.3); }
        .success-actions .btn-login-now:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(211, 84, 0, 0.4); }
        
        .success-actions .btn-home { background: #fff; color: #7f8c8d; border: 2px solid #e0e0e0; }
        .success-actions .btn-home:hover { border-color: #d35400; color: #d35400; background: #fffaf5; transform: translateY(-3px); }
    </style>
</head>
<body>

    <nav class="navbar">
    <a href="index.php" class="logo">Nhà hàng Trạm Thực</a>
    <div class="nav-links">
        <a href="index.php">Trang Chủ</a>
        <a href="register.php" class="active">Đăng Ký</a>
        <a href="login.php">Đăng Nhập</a>
        
        <a href="login.php" class="btn-booking" style="background:#fff;color:#d35400;padding:8px 15px;border-radius:5px;font-weight:bold;margin-left:20px;">
            Đặt Bàn Ngay
        </a>
    </div>
</nav>

    <div class="main-content">
        <div class="register-container">
            
            <?php if ($is_success): ?>
                <div class="success-box">
                    <div class="success-icon-wrapper">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <h2>Đăng ký thành công!</h2>
                    <p class="success-msg"><?php echo htmlspecialchars($message); ?></p>
                    
                    <div class="success-actions">
                        <a href="login.php" class="btn-login-now">Đăng Nhập Ngay</a>
                        <a href="index.php" class="btn-home">Về Trang Chủ</a>
                    </div>
                </div>

            <?php else: ?>
                <h2>Tạo Tài Khoản</h2>
                <p class="subtitle">Trở thành thành viên để nhận ngay ưu đãi đặt bàn</p>

                <div id="alert-box" class="alert error" style="display: <?php echo (!empty($message) && $msg_type == 'error') ? 'block' : 'none'; ?>;">
                    <span id="alert-text"><?php echo htmlspecialchars($message); ?></span>
                </div>

                <form method="POST" action="register.php" onsubmit="return validateRegisterForm();">
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="text" id="email" name="email" required placeholder="Ví dụ: khachhang@gmail.com" value="<?php echo htmlspecialchars($email); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="fullname">Họ và tên *</label>
                        <input type="text" id="fullname" name="fullname" required placeholder="Nhập đầy đủ họ tên" value="<?php echo htmlspecialchars($fullname); ?>">
                    </div>

                    <div class="form-group">
                        <label for="phone">Số điện thoại *</label>
                        <input type="tel" id="phone" name="phone" required placeholder="0901234567" value="<?php echo htmlspecialchars($phone); ?>">
                    </div>

                    <div class="form-group">
                        <label for="password">Mật khẩu *</label>
                        <input type="password" id="password" name="password" required placeholder="Nhập mật khẩu">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Nhập lại mật khẩu *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="Xác nhận lại mật khẩu">
                    </div>

                    <button type="submit" class="btn-submit">Đăng ký ngay</button>
                </form>

                <div class="login-link">
                    Đã có tài khoản? <a href="login.php">Đăng nhập tại đây</a>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
        function validateRegisterForm() {
            var email = document.getElementById('email').value.trim();
            var password = document.getElementById('password').value;
            var confirmPassword = document.getElementById('confirm_password').value;
            var alertBox = document.getElementById('alert-box');
            var alertText = document.getElementById('alert-text');

            // 1. Kiểm tra CHÍNH XÁC đuôi @gmail.com bằng JavaScript
            if (!email.toLowerCase().endsWith('@gmail.com')) {
                alertText.innerText = "Định dạng email không hợp lệ, vui lòng nhập lại!";
                alertBox.className = "alert error";
                alertBox.style.display = "block";
                window.scrollTo(0, 0);
                return false; 
            }

            // 2. Kiểm tra mật khẩu xác nhận
            if (password !== confirmPassword) {
                alertText.innerText = "Mật khẩu không khớp, vui lòng nhập lại!";
                alertBox.className = "alert error";
                alertBox.style.display = "block";
                window.scrollTo(0, 0);
                return false; 
            }

            return true; // Cho phép submit
        }
    </script>

</body>
</html>