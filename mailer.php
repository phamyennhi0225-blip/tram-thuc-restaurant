<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPmailer/src/Exception.php';
require 'PHPmailer/src/PHPMailer.php';
require 'PHPmailer/src/SMTP.php';

function sendEmail($toEmail, $toName, $subject, $content) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        
        $mail->Username   = 'pommaxpham@gmail.com'; 
        $mail->Password   = 'rhwl jfin unfn xlyh';     
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // ==============================================================
        // ĐOẠN CODE BỎ QUA KIỂM TRA CHỨNG CHỈ SSL 
        // ==============================================================
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom('pommaxpham@gmail.com', 'Nhà hàng Trạm Thực');
        
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $content;

        $mail->send();
        return true;
        
    } catch (Exception $e) {
        // [SỬA LẠI]: Bắt lỗi chi tiết để xem Google đang báo gì
        echo "<script>alert('Chi tiết lỗi từ Google: " . $mail->ErrorInfo . "'); window.location.href='login.php';</script>";
        exit();
    }
}
?>