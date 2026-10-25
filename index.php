<?php
session_start(); // Bắt buộc nằm ở dòng đầu tiên để đọc thẻ bài đăng nhập
require_once 'db.php';

/* ── Lấy dữ liệu từ DB ── */
// [SỬA]: Lấy cả 2 trạng thái để tương thích ngược với dữ liệu cũ
$sql = "SELECT m.menuItemId, m.menuItemName, m.price, m.description, m.image, c.categoryName 
        FROM MenuItems m
        JOIN MenuCategories c ON m.categoryId = c.categoryId
        WHERE m.menuItemStatus IN ('Đang bán', 'Hiển thị') AND c.categoryStatus = 'Active'
        ORDER BY c.categoryId ASC, m.menuItemName ASC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ── Nhóm món theo danh mục ── */
$menu_grouped = [];
foreach ($all_items as $item) {
    $cat = $item['categoryName'];
    if (!isset($menu_grouped[$cat])) $menu_grouped[$cat] = [];
    $menu_grouped[$cat][] = $item;
}

$default_images = [
    'Khai vị'     => 'images/default-khai-vi.jpg',
    'Món chính'   => 'images/default-mon-chinh.jpg',
    'Tráng miệng' => 'images/default-trang-mieng.jpg',
    'Đồ uống'     => 'images/default-do-uong.jpg',
];

function getItemImage(?string $dbImage, string $default): string {
    if ($dbImage === null) return $default;
    
    $dbImage = trim($dbImage);
    if ($dbImage === '') return $default;
    if (strpos($dbImage, 'http') === 0) return $dbImage;
    
    // [SỬA]: AI tìm ảnh tự động quét 2 thư mục
    if (file_exists('uploads/' . $dbImage)) {
        return 'uploads/' . $dbImage; // Ưu tiên ảnh mới Admin up
    } elseif (file_exists('images/' . $dbImage)) {
        return 'images/' . $dbImage;  // Quét ảnh cũ 
    }
    
    return $default;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhà Hàng Trạm Thực - Kết Nối Tinh Hoa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #fcf8f2; color: #333; margin: 0; padding: 0; }

        /* ── Navbar ── */
        .navbar {
            background-color: #d35400; padding: 15px 50px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .navbar .logo { color: #fff; font-size: 24px; font-weight: bold; text-decoration: none; }
        .navbar .nav-links { display: flex; align-items: center; }
        .navbar .nav-links a {
            color: #fff; text-decoration: none; margin-left: 20px;
            font-weight: 500; transition: color 0.2s;
        }
        .navbar .nav-links a:hover { color: #f1c40f; }
        .navbar .nav-links a.active {
            font-weight: bold;
            text-decoration: underline;
            text-underline-offset: 6px;
        }

        /* CSS ACCOUNT DROPDOWN */
        .noti-link { font-size: 20px; color: #fff; margin-left: 20px; position: relative; display: flex; align-items: center; }
        .noti-badge { position: absolute; top: 0; right: 0; width: 8px; height: 8px; background-color: #e74c3c; border-radius: 50%; }
        
        .user-dropdown { position: relative; margin-left: 0; padding: 10px 0; cursor: pointer; }
        .user-trigger { display: flex; align-items: center; gap: 8px; color: #fff; font-weight: 600; user-select: none; }
        .user-trigger i.fa-circle-user { font-size: 22px; }
        .user-trigger .arrow-icon { font-size: 11px; transition: transform 0.3s; }
        
        .dropdown-menu {
            position: absolute; 
            top: calc(100% + 15px);
            right: 0; background-color: #fff; min-width: 180px;
            border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); 
            display: none;
            z-index: 9999; overflow: hidden; border: 1px solid #eee;
        }
        .dropdown-menu.show { display: block; animation: dropDownAnim 0.2s ease-out forwards; }
        .arrow-icon.rotate { transform: rotate(180deg); color: #f1c40f; }

        @keyframes dropDownAnim {
            0% { opacity: 0; transform: translateY(-10px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        
        .dropdown-menu a.dropdown-item {
            color: #2c3e50 !important; padding: 14px 20px !important; margin: 0 !important;
            display: flex !important; align-items: center; gap: 10px; font-size: 14.5px;
            font-weight: bold; border-bottom: 1px solid #eee !important; transition: background 0.2s; text-decoration: none;
        }
        .dropdown-menu a.dropdown-item:hover { background-color: #fcf8f2; color: #d35400 !important; }
        .dropdown-menu a.logout-item {
            color: #e74c3c !important; padding: 14px 20px !important; margin: 0 !important;
            display: flex !important; align-items: center; gap: 10px; font-size: 14.5px;
            font-weight: bold; border-bottom: none !important; transition: background 0.2s;
        }
        .dropdown-menu a.logout-item:hover { background-color: #fff5f5; color: #c0392b !important; }

        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }

        /* ── Hero Banner ── */
        .hero-banner {
            background: linear-gradient(rgba(0,0,0,0.50), rgba(0,0,0,0.50)),
                        url('images/banner.jpg') no-repeat center center / cover;
            color: white; text-align: center; padding: 110px 20px;
            border-radius: 12px; margin-bottom: 30px;
        }
        .hero-banner h1 { margin: 0 0 15px 0; font-size: 44px; text-shadow: 2px 2px 6px rgba(0,0,0,0.6); }
        .hero-banner p  { font-size: 20px; font-style: italic; margin: 0; text-shadow: 1px 1px 4px rgba(0,0,0,0.5); }

        /* ── Intro Section ── */
        .intro-section {
            background: #fff; padding: 30px; border-radius: 12px;
            border: 1px solid #f3e5d8; margin-bottom: 40px; text-align: center;
        }
        .intro-section h2 {
            color: #d35400; margin-top: 0;
            border-bottom: 2px solid #f3e5d8; padding-bottom: 10px; font-size: 24px;
        }
        .intro-short { color: #6b5846; margin-top: 0; margin-bottom: 20px; font-size: 15px; line-height: 1.7; }

        .space-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 20px; margin-top: 10px; align-items: stretch;
        }
        .space-item {
            background: #fafafa; border: 1px solid #eee; border-radius: 10px;
            overflow: hidden; cursor: pointer;
            transition: box-shadow 0.25s, transform 0.2s;
            display: flex; flex-direction: column;
        }
        .space-item:hover { box-shadow: 0 6px 18px rgba(211,84,0,0.18); transform: translateY(-3px); }
        .space-item-thumb { width: 100%; height: 160px; overflow: hidden; flex-shrink: 0; }
        .space-item-thumb img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.35s ease; }
        .space-item:hover .space-item-thumb img { transform: scale(1.07); }
        .space-item-body { padding: 14px 15px 16px; flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .space-item-body i { font-size: 22px; color: #e67e22; margin-bottom: 6px; display: block; }
        .space-item-body h3 { margin: 0; font-size: 15px; color: #2c3e50; }
        .space-hint { font-size: 12px; color: #aaa; margin-top: 4px; }

        /* ── Modal ── */
        .modal { display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.70); align-items: center; justify-content: center; }
        .modal.open { display: flex; }
        .modal-content { position: relative; width: 780px; height: 460px; max-width: 96vw; max-height: 92vh; background: #fff; border-radius: 14px; overflow: hidden; display: flex; flex-direction: row; box-shadow: 0 20px 56px rgba(0,0,0,0.45); }
        .modal-slideshow { flex: 0 0 50%; height: 100%; position: relative; background: #222; overflow: hidden; }
        .modal-slide { position: absolute; inset: 0; opacity: 0; transition: opacity 0.45s ease; }
        .modal-slide.active { opacity: 1; z-index: 1; }
        .modal-slide img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .slide-btn { position: absolute; top: 50%; z-index: 5; transform: translateY(-50%); width: 32px; height: 32px; border-radius: 50%; background: rgba(0,0,0,0.50); color: #fff; border: none; font-size: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
        .slide-btn:hover { background: #d35400; }
        .slide-btn.prev { left: 8px; }
        .slide-btn.next { right: 8px; }
        .slide-dots { position: absolute; bottom: 10px; left: 0; right: 0; z-index: 5; display: flex; justify-content: center; gap: 6px; }
        .slide-dot { width: 7px; height: 7px; border-radius: 50%; background: rgba(255,255,255,0.45); border: none; cursor: pointer; transition: background 0.2s, transform 0.2s; padding: 0; }
        .slide-dot.active { background: #fff; transform: scale(1.3); }
        .modal-slideshow.single .slide-btn, .modal-slideshow.single .slide-dots { display: none; }
        .modal-body { flex: 1; padding: 36px 30px; display: flex; flex-direction: column; justify-content: center; overflow-y: auto; }
        .modal-body h3  { margin: 0 0 10px 0; color: #d35400; font-size: 20px; line-height: 1.35; }
        .modal-price    { color: #e74c3c; font-weight: bold; font-size: 22px; margin: 0 0 18px 0; }
        .modal-desc     { color: #555; font-size: 14px; line-height: 1.8; margin: 0; }
        .modal-close-btn { position: absolute; top: 10px; right: 12px; width: 30px; height: 30px; border-radius: 50%; background: rgba(0,0,0,0.45); color: #fff; border: none; font-size: 16px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s; z-index: 10; }
        .modal-close-btn:hover { background: #d35400; }

        /* ── Menu Section ── */
        .menu-section { background: #fff; padding: 35px 25px; border-radius: 12px; border: 1px solid #f3e5d8; margin-bottom: 40px; }
        .menu-header { text-align: center; margin-bottom: 30px; }
        .menu-header h2 { color: #d35400; font-size: 28px; margin: 0 0 10px 0; }
        .filter-tabs { display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; margin-bottom: 30px; }
        .filter-btn { padding: 10px 20px; border: 1px solid #d35400; background: transparent; color: #d35400; border-radius: 25px; font-size: 15px; font-weight: bold; cursor: pointer; transition: all 0.3s ease; }
        .filter-btn:hover, .filter-btn.active { background: #d35400; color: #fff; box-shadow: 0 4px 10px rgba(211,84,0,0.3); }
        .category-title { color: #2c3e50; border-left: 5px solid #d35400; padding-left: 10px; margin: 20px 0; font-size: 20px; }
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .menu-card { background: #fff; border-radius: 10px; overflow: hidden; border: 1px solid #eee; box-shadow: 0 2px 5px rgba(0,0,0,0.04); display: flex; flex-direction: column; }
        .food-img-wrapper { width: 100%; height: 180px; overflow: hidden; }
        .food-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s; }
        .menu-card:hover .food-img { transform: scale(1.06); }
        .food-info { padding: 15px; display: flex; flex-direction: column; flex-grow: 1; }
        .food-title-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .food-name  { font-weight: bold; font-size: 15px; color: #2c3e50; }
        .food-price { color: #e74c3c; font-weight: bold; font-size: 15px; }
        .food-desc  { font-size: 13px; color: #7f8c8d; font-style: italic; margin: 0 0 12px 0; flex-grow: 1; }

        /* ── Footer ── */
        footer { background-color: #2c3e50; color: #bdc3c7; padding: 40px 20px 20px; margin-top: 60px; }
        .footer-container { max-width: 1000px; margin: 0 auto; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 30px; }
        .footer-col { flex: 1; min-width: 250px; }
        .footer-col h3 { color: #fff; margin-top: 0; font-size: 18px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 15px; }
        .hotline-box { background: #e67e22; color: #fff; display: inline-block; padding: 10px 20px; border-radius: 30px; font-size: 22px; font-weight: bold; margin-bottom: 15px; }
        .social-icons a { display: inline-flex; align-items: center; justify-content: center; width: 45px; height: 45px; background: #34495e; color: #fff; border-radius: 50%; font-size: 20px; margin: 0 8px; text-decoration: none; transition: 0.3s; }
        .social-icons a:hover { background: #e67e22; transform: translateY(-3px); }
        .footer-bottom { text-align: center; padding-top: 20px; margin-top: 30px; border-top: 1px solid #34495e; font-size: 13px; }

        /* ── Responsive Mobile ── */
        @media (max-width: 768px) {
            .navbar { flex-direction: column; gap: 15px; padding: 15px; }
            .modal-content { flex-direction: column; height: auto; max-height: 90vh; }
            .modal-slideshow { flex: none; height: 250px; }
            .modal-body { padding: 20px; overflow-y: auto; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="logo">Nhà hàng Trạm Thực</a>
    <div class="nav-links">
        
        <?php if (isset($_SESSION['userId'])): ?>
            <div class="user-dropdown" id="userAccountBtn">
                <div class="user-trigger">
                    <i class="fa-regular fa-circle-user"></i>
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['fullName'] ?? 'Tài khoản'); ?></span>
                    <i class="fa-solid fa-chevron-down arrow-icon" id="userArrow"></i>
                </div>
                <div class="dropdown-menu" id="userMenu">
                    <a href="profile.php" class="dropdown-item">
                        <i class="fa-solid fa-user-pen"></i> Hồ sơ cá nhân
                    </a>
                    <a href="logout.php" class="logout-item">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i> Đăng xuất
                    </a>
                </div>
            </div>

            <a href="#" class="noti-link">
                <i class="fa-regular fa-bell"></i>
                <span class="noti-badge"></span>
            </a>

            <a href="booking.php" class="btn-booking" style="background:#fff;color:#d35400;padding:8px 15px;border-radius:5px;font-weight:bold;margin-left:20px;">
                Đặt Bàn Ngay
            </a>
            
        <?php else: ?>
            <a href="index.php" class="active">Trang Chủ</a>
            <a href="register.php">Đăng Ký</a>
            <a href="login.php">Đăng Nhập</a>

            <a href="login.php?redirect=booking" class="btn-booking" style="background:#fff;color:#d35400;padding:8px 15px;border-radius:5px;font-weight:bold;margin-left:20px;">
                Đặt Bàn Ngay
            </a>
        <?php endif; ?>

    </div>
</nav>

<div class="container">

    <header class="hero-banner">
        <h1>Nhà hàng Trạm Thực</h1>
        <p>Tinh hoa ẩm thực Việt – Không gian lưu giữ bản vị</p>
    </header>

    <section class="intro-section">
        <h2>Giới thiệu về Nhà hàng</h2>
        <p class="intro-short">
            Giữa nhịp sống hối hả của phố thị, Trạm Thực chính là điểm dừng chân lý tưởng để bạn nuông chiều
            vị giác. Với không gian đương đại tinh tế và quy trình phục vụ công nghệ chỉn chu, chúng tôi tự hào
            mang đến trải nghiệm "Chạm vị đương đại – Gói trọn chất thực" – nâng tầm món ăn Việt nhưng vẫn giữ
            được sự mộc mạc, tươi ngon. Ghé Trạm Thực ngay hôm nay để thưởng thức và trải nghiệm bạn nhé!
        </p>

        <div class="space-grid">
            <div class="space-item" data-key="san-vuon">
                <div class="space-item-thumb">
                    <img src="images/san-vuon.jpg" alt="Sảnh Sân Vườn" onerror="this.src='images/default-space.jpg'">
                </div>
                <div class="space-item-body">
                    <i class="fa-solid fa-tree"></i>
                    <h3>Sảnh sân vườn</h3>
                    <p class="space-hint"><i class="fa-solid fa-images" style="font-size:11px;"></i> Nhấn để xem ảnh</p>
                </div>
            </div>

            <div class="space-item" data-key="vip">
                <div class="space-item-thumb">
                    <img src="images/phong-vip.jpg" alt="Hệ Thống Phòng VIP" onerror="this.src='images/default-space.jpg'">
                </div>
                <div class="space-item-body">
                    <i class="fa-solid fa-door-closed"></i>
                    <h3>Hệ thống phòng VIP</h3>
                    <p class="space-hint"><i class="fa-solid fa-images" style="font-size:11px;"></i> Nhấn để xem ảnh</p>
                </div>
            </div>

            <div class="space-item" data-key="khu-vui">
                <div class="space-item-thumb">
                    <img src="images/khu-vui-choi.jpg" alt="Khu Vui Chơi Trẻ Em" onerror="this.src='images/default-space.jpg'">
                </div>
                <div class="space-item-body">
                    <i class="fa-solid fa-child-reaching"></i>
                    <h3>Khu vui chơi trẻ em</h3>
                    <p class="space-hint"><i class="fa-solid fa-images" style="font-size:11px;"></i> Nhấn để xem ảnh</p>
                </div>
            </div>

            <div class="space-item" data-key="bai-do-xe">
                <div class="space-item-thumb">
                    <img src="images/bai-do-xe.jpg" alt="Bãi Đỗ Xe Rộng Rãi" onerror="this.src='images/default-space.jpg'">
                </div>
                <div class="space-item-body">
                    <i class="fa-solid fa-car"></i>
                    <h3>Bãi đỗ xe rộng rãi</h3>
                    <p class="space-hint"><i class="fa-solid fa-images" style="font-size:11px;"></i> Nhấn để xem ảnh</p>
                </div>
            </div>
        </div>
    </section>

    <section class="menu-section">
        <div class="menu-header">
            <h2>Thực đơn nhà hàng</h2>
            <p style="color:#7f8c8d;font-style:italic;">Chọn danh mục bên dưới để khám phá món ăn yêu thích</p>
        </div>

        <div class="filter-tabs">
            <button class="filter-btn active" data-filter="all">🌟 Tất cả</button>
            <?php foreach (array_keys($menu_grouped) as $cat_name): ?>
                <button class="filter-btn" data-filter="<?= md5($cat_name) ?>">
                    <?= htmlspecialchars($cat_name ?? '') ?>
                </button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($menu_grouped as $cat_name => $items): ?>
            <?php
                $cat_id  = md5($cat_name);
                $default = $default_images[$cat_name] ?? 'images/default-mon-an.jpg';
            ?>
            <div class="category-block" id="<?= $cat_id ?>">
                <h3 class="category-title"><?= htmlspecialchars($cat_name ?? '') ?></h3>
                <div class="menu-grid">
                    <?php foreach ($items as $item): ?>
                        <?php $src = getItemImage($item['image'] ?? null, $default); ?>
                        <div class="menu-card">
                            <div class="food-img-wrapper">
                                <img class="food-img"
                                     src="<?= htmlspecialchars($src) ?>"
                                     alt="<?= htmlspecialchars($item['menuItemName'] ?? '') ?>"
                                     onerror="this.src='<?= htmlspecialchars($default) ?>'">
                            </div>
                            <div class="food-info">
                                <div class="food-title-row">
                                    <span class="food-name"><?= htmlspecialchars($item['menuItemName'] ?? '') ?></span>
                                    <span class="food-price"><?= number_format((float)($item['price'] ?? 0), 0, ',', '.') ?>đ</span>
                                </div>
                                <p class="food-desc"><?= htmlspecialchars($item['description'] ?? '') ?></p>
                                <button class="filter-btn view-detail"
                                        data-name="<?= htmlspecialchars($item['menuItemName'] ?? '') ?>"
                                        data-price="<?= number_format((float)($item['price'] ?? 0), 0, ',', '.') ?>"
                                        data-desc="<?= htmlspecialchars($item['description'] ?? '') ?>"
                                        data-img="<?= htmlspecialchars($src) ?>">
                                    Xem chi tiết
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

</div>

<div id="detail-modal" class="modal">
    <div class="modal-content">
        <button class="modal-close-btn" id="modal-close" title="Đóng">✕</button>
        <div class="modal-slideshow" id="modal-slideshow">
            <button class="slide-btn prev" id="slide-prev">&#8249;</button>
            <button class="slide-btn next" id="slide-next">&#8250;</button>
            <div class="slide-dots" id="slide-dots"></div>
        </div>
        <div class="modal-body">
            <h3 id="modal-title"></h3>
            <p class="modal-price" id="modal-price"></p>
            <p class="modal-desc"  id="modal-desc"></p>
        </div>
    </div>
</div>

<footer>
    <div class="footer-container">
        <div class="footer-col" style="flex:1.5;">
            <h3>Nhà Hàng Trạm Thực</h3>
            <p><i class="fa-solid fa-location-dot" style="color:#e67e22;margin-right:8px;"></i> Số 123 Quan Hoa, Cầu Giấy, Hà Nội</p>
            <p><i class="fa-solid fa-clock" style="color:#e67e22;margin-right:8px;"></i> 08:00 AM – 22:00 PM (Tất cả các ngày)</p>
        </div>
        <div class="footer-col" style="text-align:center;">
            <h3>Hotline</h3>
            <div class="hotline-box"><i class="fa-solid fa-phone-volume"></i> 090.123.4567</div>
        </div>
        <div class="footer-col" style="text-align:center;">
            <h3>Mạng Xã Hội</h3>
            <div class="social-icons">
                <a href="#" title="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#" title="Tiktok"><i class="fa-brands fa-tiktok"></i></a>
                <a href="#" title="Instagram"><i class="fa-brands fa-instagram"></i></a>
                <a href="#" title="Youtube"><i class="fa-brands fa-youtube"></i></a>
            </div>
        </div>
    </div>
    <div class="footer-bottom">&copy; 2026 Website Nhà Hàng Trạm Thực. All Rights Reserved.</div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    
    // DROPDOWN MENU
    const userBtn  = document.getElementById('userAccountBtn');
    const userMenu = document.getElementById('userMenu');
    const userArrow = document.getElementById('userArrow');

    if (userBtn) {
        userBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenu.classList.toggle('show');
            userArrow.classList.toggle('rotate');
        });
    }
    document.addEventListener('click', function() {
        if (userMenu && userMenu.classList.contains('show')) {
            userMenu.classList.remove('show');
            userArrow.classList.remove('rotate');
        }
    });

    // SPACE INTRO MAP
    const introMap = {
        'san-vuon': {
            title : 'Sảnh sân vườn',
            desc  : 'Nếu bạn thích không gian mở, vừa ăn vừa hóng gió thì sảnh sân vườn là lựa chọn hợp lý nhất. Khu vực này được thiết kế với rất nhiều cây xanh và khoảng cách giữa các bàn cực kỳ rộng rãi.',
            images: ['images/san-vuon-1.jpg', 'images/san-vuon-2.jpg', 'images/san-vuon-3.jpg']
        },
        'vip': {
            title : 'Hệ thống phòng VIP',
            desc  : 'Cần bàn chuyện công việc hay tổ chức tiệc gia đình không muốn ồn ào? Hệ thống phòng riêng của nhà hàng sẽ giải quyết trọn vẹn vấn đề này.',
            images: ['images/phong-vip-1.jpg', 'images/phong-vip-2.jpg', 'images/phong-vip-3.jpg']
        },
        'khu-vui': {
            title : 'Khu vui chơi trẻ em',
            desc  : 'Nhà có con nhỏ đi ăn hàng thường rất ngại vì các bé ngồi một lúc là chán và chạy nhảy khắp nơi. Nhà hàng bố trí hẳn một khu vui chơi mini ngay trong khuôn viên.',
            images: ['images/khu-vui-1.jpg', 'images/khu-vui-2.jpg', 'images/khu-vui-3.jpg']
        },
        'bai-do-xe': {
            title : 'Bãi đỗ xe rộng rãi',
            desc  : 'Điểm cộng cực lớn cho những ai đi ô tô là nhà hàng có bãi đỗ xe siêu rộng ngay phía trước. Đội ngũ bảo vệ luôn túc trực để hướng dẫn và trông giữ xe.',
            images: ['images/bai-do-xe-1.jpg', 'images/bai-do-xe-2.jpg', 'images/bai-do-xe-3.jpg']
        }
    };

    const filterBtns = document.querySelectorAll('.filter-btn[data-filter]');
    const catBlocks  = document.querySelectorAll('.category-block');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const target = this.getAttribute('data-filter');
            catBlocks.forEach(cat => {
                cat.style.display = (target === 'all' || cat.id === target) ? 'block' : 'none';
            });
        });
    });

    document.querySelectorAll('.space-item').forEach(el => {
        el.addEventListener('click', () => {
            const d = introMap[el.getAttribute('data-key')];
            if (d) openModal(d.images, d.title, '', d.desc);
        });
    });

    document.querySelectorAll('.view-detail').forEach(btn => {
        btn.addEventListener('click', () => {
            openModal(
                [btn.getAttribute('data-img')],
                btn.getAttribute('data-name'),
                btn.getAttribute('data-price') + 'đ',
                btn.getAttribute('data-desc')
            );
        });
    });

    const modal       = document.getElementById('detail-modal');
    const slideshowEl = document.getElementById('modal-slideshow');
    const dotsEl      = document.getElementById('slide-dots');
    const prevBtn     = document.getElementById('slide-prev');
    const nextBtn     = document.getElementById('slide-next');
    let slides = [], dots = [], idx = 0, autoTimer = null;

    function openModal(images, title, price, desc) {
        clearInterval(autoTimer);
        slideshowEl.querySelectorAll('.modal-slide').forEach(s => s.remove());
        dotsEl.innerHTML = '';
        slides = []; dots = []; idx = 0;
        const imgs = (images && images.length) ? images : [''];
        imgs.forEach((src, i) => {
            const slide = document.createElement('div');
            slide.className = 'modal-slide' + (i === 0 ? ' active' : '');
            const img = document.createElement('img');
            img.src = src; img.alt = title || '';
            img.onerror = function() { this.style.background = '#ddd'; };
            slide.appendChild(img);
            slideshowEl.insertBefore(slide, prevBtn);
            slides.push(slide);
            const dot = document.createElement('button');
            dot.className = 'slide-dot' + (i === 0 ? ' active' : '');
            dot.addEventListener('click', () => goTo(i));
            dotsEl.appendChild(dot);
            dots.push(dot);
        });
        if (imgs.length <= 1) {
            slideshowEl.classList.add('single');
        } else {
            slideshowEl.classList.remove('single');
            autoTimer = setInterval(() => goTo((idx + 1) % slides.length), 3500);
        }
        document.getElementById('modal-title').textContent = title || '';
        document.getElementById('modal-price').textContent = price || '';
        document.getElementById('modal-desc').textContent  = desc  || '';
        modal.classList.add('open');
    }

    function goTo(newIdx) {
        if (!slides.length) return;
        slides[idx].classList.remove('active');
        dots[idx] && dots[idx].classList.remove('active');
        idx = (newIdx + slides.length) % slides.length;
        slides[idx].classList.add('active');
        dots[idx] && dots[idx].classList.add('active');
    }

    prevBtn.addEventListener('click', () => { clearInterval(autoTimer); goTo(idx - 1); });
    nextBtn.addEventListener('click', () => { clearInterval(autoTimer); goTo(idx + 1); });

    function closeModal() { clearInterval(autoTimer); modal.classList.remove('open'); }
    document.getElementById('modal-close').addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', e => {
        if (!modal.classList.contains('open')) return;
        if (e.key === 'Escape')     closeModal();
        if (e.key === 'ArrowLeft')  { clearInterval(autoTimer); goTo(idx - 1); }
        if (e.key === 'ArrowRight') { clearInterval(autoTimer); goTo(idx + 1); }
    });
    let touchX = 0;
    slideshowEl.addEventListener('touchstart', e => { touchX = e.changedTouches[0].screenX; });
    slideshowEl.addEventListener('touchend',   e => {
        const diff = touchX - e.changedTouches[0].screenX;
        if (Math.abs(diff) > 40) { clearInterval(autoTimer); goTo(idx + (diff > 0 ? 1 : -1)); }
    });
});
</script>
</body>
</html>