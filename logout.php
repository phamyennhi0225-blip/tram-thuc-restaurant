<?php
session_start();
session_unset();   // Xóa sạch biến
session_destroy(); // Hủy thẻ bài

header("Location: index.php");
exit();
?>