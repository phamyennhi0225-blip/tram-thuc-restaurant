<?php
session_start();
require_once 'db.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['reset_userId'])) {
    header("Location: forgot_password.php");
    exit();
}

$userId = $_SESSION['reset_userId'];
$message = ''; $msg_type = '';
$otp_verified = false;

$stmt = $conn->prepare("SELECT * FROM Users WHERE userId = :userId");
$stmt->execute([':userId' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action']) && $_POST['action'] == 'verify_otp') {
        $user_otp = trim($_POST['otp_code']);
        if ($user_otp === $user['otp_code'] && strtotime($user['otp_expiry']) >= time()) {
            $otp_verified = true;
        } else {
            $message = "Mã OTP không chính xác hoặc đã hết hạn!";
            $msg_type = "error";
        }
    }

    if (isset($_POST['action']) && $_POST['action'] == 'reset_password') {
        $otp_verified = true;
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $message = "Mật khẩu xác nhận không trùng khớp!";
            $msg_type = "error";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE Users SET password = :password, otp_code = NULL, otp_expiry = NULL WHERE userId = :userId");
            $update_stmt->execute([':password' => $hashed_password, ':userId' => $userId]);
            unset($_SESSION['reset_userId']);
            header("Location: login.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xác Thực OTP</title>
    <style>
        body { font-family: sans-serif; background: #fcf8f2; display: flex; justify-content: center; padding-top: 50px; }
        .box { background: #fff; padding: 30px; border-radius: 10px; width: 400px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .alert { padding: 10px; background: #fee; color: red; margin-bottom: 10px; border-radius: 5px; }
        input { width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #d35400; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <div class="box">
        <?php if (!empty($message)): ?><div class="alert"><?= $message ?></div><?php endif; ?>
        
        <?php if (!$otp_verified): ?>
            <h3>Nhập mã OTP</h3>
            <form method="POST">
                <input type="hidden" name="action" value="verify_otp">
                <input type="text" name="otp_code" required placeholder="Nhập 6 số">
                <button type="submit">Xác Minh</button>
            </form>
        <?php else: ?>
            <h3>Đặt mật khẩu mới</h3>
            <form method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="password" name="new_password" required placeholder="Mật khẩu mới">
                <input type="password" name="confirm_password" required placeholder="Nhập lại mật khẩu">
                <button type="submit">Cập Nhật</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>