<?php
session_start();
require_once 'db.php';
require_once 'mailer.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    if (empty($email)) {
        echo "<script>alert('Vui lòng nhập địa chỉ Email!'); window.location.href='login.php';</script>";
        exit();
    } elseif (!preg_match('/@gmail\.com$/i', $email)) {
        echo "<script>alert('Hệ thống chỉ chấp nhận email đuôi @gmail.com!'); window.location.href='login.php';</script>";
        exit();
    } else {
        $sql = "SELECT * FROM Users WHERE email = :email";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $otp = rand(100000, 999999);
            $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            $update_sql = "UPDATE Users SET otp_code = :otp_code, otp_expiry = :otp_expiry WHERE userId = :userId";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->execute([':otp_code' => $otp, ':otp_expiry' => $expiry, ':userId' => $user['userId']]);

            $subject = "[Trạm Thực] Mã xác thực OTP khôi phục mật khẩu";
            $content = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333; max-width: 500px; border: 1px solid #eee; border-radius: 10px;'>
                    <h2 style='color: #d35400; text-align: center;'>Nhà hàng Trạm Thực</h2>
                    <p>Xin chào <strong>{$user['fullName']}</strong>,</p>
                    <p>Hệ thống nhận được yêu cầu cấp lại mật khẩu cho tài khoản của bạn. Dưới đây là mã OTP xác thực của bạn:</p>
                    <div style='background: #fdf2e9; padding: 15px; text-align: center; font-size: 26px; font-weight: bold; color: #d35400; border-radius: 8px; letter-spacing: 5px; margin: 20px 0;'>
                        {$otp}
                    </div>
                    <p style='color: #e74c3c; font-size: 13px; font-style: italic;'>Mã số này có hiệu lực trong vòng 5 phút. Tuyệt đối không chia sẻ mã này cho người khác.</p>
                </div>
            ";

            // GỌI HÀM GỬI EMAIL
            if (sendEmail($user['email'], $user['fullName'], $subject, $content)) {
                // Nếu gửi thành công -> Sang thẳng trang nhập OTP
                $_SESSION['reset_userId'] = $user['userId'];
                echo "<script>alert('Gửi OTP thành công! Vui lòng kiểm tra Email của bạn.'); window.location.href='verify_otp.php';</script>";
                exit();
            } else {
                // Nếu gửi thất bại -> Báo lỗi bằng Popup và đưa về lại trang Login
                echo "<script>alert('Lỗi: Hệ thống không thể gửi Email lúc này. Vui lòng kiểm tra lại file mailer.php!'); window.location.href='login.php';</script>";
                exit();
            }
        } else {
            echo "<script>alert('Email này chưa được đăng ký trên hệ thống!'); window.location.href='login.php';</script>";
            exit();
        }
    }
}
?>