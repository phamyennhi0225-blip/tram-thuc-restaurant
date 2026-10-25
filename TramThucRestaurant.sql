-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: localhost
-- Thời gian đã tạo: Th10 25, 2026 lúc 01:38 PM
-- Phiên bản máy phục vụ: 10.4.28-MariaDB
-- Phiên bản PHP: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `TramThucRestaurant`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `MenuCategories`
--

CREATE TABLE `MenuCategories` (
  `categoryId` char(4) NOT NULL,
  `categoryName` varchar(50) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `categoryStatus` varchar(15) NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `MenuCategories`
--

INSERT INTO `MenuCategories` (`categoryId`, `categoryName`, `image`, `categoryStatus`) VALUES
('CA01', 'Khai vị', 'khai-vi.jpg', 'Active'),
('CA02', 'Món chính', 'mon-chinh.jpg', 'Active'),
('CA03', 'Tráng miệng', 'trang-mieng.jpg', 'Active'),
('CA04', 'Đồ uống', 'do-uong.jpg', 'Active'),
('CA05', 'Món chay', 'mon-chay.jpg', 'Inactive');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `MenuItems`
--

CREATE TABLE `MenuItems` (
  `menuItemId` char(6) NOT NULL,
  `categoryId` char(4) NOT NULL,
  `menuItemName` varchar(100) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `price` decimal(10,0) NOT NULL CHECK (`price` > 0),
  `discountPrice` int(11) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `menuItemStatus` varchar(15) NOT NULL DEFAULT 'Hiển thị',
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `MenuItems`
--

INSERT INTO `MenuItems` (`menuItemId`, `categoryId`, `menuItemName`, `image`, `price`, `discountPrice`, `description`, `menuItemStatus`, `createDate`, `updateDate`) VALUES
('MI0001', 'CA01', 'Súp cua nấm hương', 'sup-cua.jpg', 65000, 10000, 'Súp cua thanh mát nấu cùng nấm hương', 'Đang bán', '2026-05-19 11:44:01', '2026-05-31 16:22:26'),
('MI0002', 'CA01', 'Gỏi ngó sen tôm thịt', 'goi-sen.jpg', 120000, 10000, 'Gỏi ngó sen chua ngọt, tôm sú và thịt ba chỉ', 'Đang bán', '2026-05-19 11:44:01', '2026-05-31 16:33:52'),
('MI0003', 'CA02', 'Gà nướng lu', 'ga-lu.jpg\r\n', 250000, NULL, 'Gà ta nướng lu giòn da, tẩm ướp gia vị Tây Bắc', 'Đang bán', '2026-05-19 11:44:01', '2026-05-23 15:05:20'),
('MI0004', 'CA02', 'Cá chép om dưa', 'https://images.unsplash.com/photo-1580476262798-bddd9f4b7369?q=80&w=400', 180000, NULL, 'Cá chép om cùng dưa chua và thịt ba chỉ', 'Đang bán', '2026-05-19 11:44:01', '2026-05-23 15:05:20'),
('MI0005', 'CA02', 'Bò lúc lắc khoai tây', 'https://images.unsplash.com/photo-1544025162-d76694265947?q=80&w=400', 150000, NULL, 'Thịt bò mềm xào lúc lắc, ăn kèm khoai tây chiên', 'Tạm ẩn', '2026-05-19 11:44:01', '2026-05-19 12:41:30'),
('MI0006', 'CA03', 'Chè hạt sen nhãn nhục', 'che-hat-sen-nhan.jpg', 35000, NULL, 'Chè hạt sen long nhãn thanh mát, an thần', 'Đang bán', '2026-05-19 11:44:01', '2026-05-23 15:18:52'),
('MI0007', 'CA03', 'Trái cây thập cẩm', 'https://images.unsplash.com/photo-1610832958506-aa56368176cf?q=80&w=400', 50000, NULL, 'Dưa hấu, ổi, xoài, thơm theo mùa', 'Đang bán', '2026-05-19 11:44:01', '2026-05-23 15:05:20'),
('MI0008', 'CA04', 'Trà sen vàng', 'tra-sen.jpg\r\n', 45000, NULL, 'Trà sen thanh lọc cơ thể', 'Đang bán', '2026-05-19 11:44:01', '2026-05-23 15:05:20'),
('MI0009', 'CA04', 'Nước ép dưa hấu', 'nuoc-ep-dua-hau.jpg', 40000, NULL, 'Nước ép nguyên chất, không đường', 'Đang bán', '2026-05-19 11:44:01', '2026-05-23 15:05:20'),
('MI0010', 'CA01', 'Nem rán Hà Nội', 'nem-ran.jpg', 85000, NULL, 'Nem rán truyền thống vỏ giòn, nhân thịt băm mộc nhĩ', 'Đang bán', '2026-05-19 12:33:07', '2026-05-23 15:05:20'),
('MI0011', 'CA02', 'Bún hải sản', 'https://images.unsplash.com/photo-1555126634-323283e090fa?q=80&w=400', 100000, NULL, 'Bún chua cay đậm vị, tôm sú, mực ống và nghêu', 'Đang bán', '2026-05-19 12:33:07', '2026-05-23 15:05:20'),
('MI0012', 'CA02', 'Tôm sú hấp bia', 'tom-su.jpg', 220000, NULL, 'Tôm sú tươi sống hấp cùng bia và sả thơm lừng', 'Đang bán', '2026-05-19 12:33:07', '2026-05-23 15:05:20'),
('MI0013', 'CA03', 'Bánh Flan Caramel', 'banh-flan.jpg', 25000, NULL, 'Bánh Flan béo ngậy, cà phê đắng nhẹ', 'Đang bán', '2026-05-19 12:33:07', '2026-05-23 15:05:20'),
('MI0014', 'CA04', 'Sinh tố bơ', 'sinh-to-bo1.jpg', 55000, NULL, 'Bơ sáp Đà Lạt xay nhuyễn cùng sữa đặc', 'Đang bán', '2026-05-19 12:33:07', '2026-05-23 15:05:32');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `Reservations`
--

CREATE TABLE `Reservations` (
  `reservationId` char(6) NOT NULL,
  `userId` char(6) NOT NULL,
  `tableId` char(4) DEFAULT NULL,
  `numberOfGuests` int(11) NOT NULL CHECK (`numberOfGuests` > 0),
  `numberOfChildren` int(11) NOT NULL DEFAULT 0,
  `specialRequirements` text DEFAULT NULL,
  `reservationTime` datetime NOT NULL,
  `notes` varchar(300) DEFAULT NULL,
  `bookingStatus` varchar(20) NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `Reservations`
--

INSERT INTO `Reservations` (`reservationId`, `userId`, `tableId`, `numberOfGuests`, `numberOfChildren`, `specialRequirements`, `reservationTime`, `notes`, `bookingStatus`, `createdAt`) VALUES
('RV0001', 'US0002', NULL, 4, 0, NULL, '2026-05-25 19:00:00', 'Tiệc sinh nhật, cần trang trí nhẹ', 'Đã xác nhận', '2026-05-19 11:44:01'),
('RV0002', 'US0003', NULL, 2, 0, NULL, '2026-05-26 18:30:00', 'Thích ngồi gần cửa sổ', 'Chờ xác nhận', '2026-05-19 11:44:01'),
('RV0003', 'US0005', NULL, 4, 0, NULL, '2026-05-19 11:30:00', 'Có trẻ em đi cùng', 'Đang phục vụ', '2026-05-19 11:44:01'),
('RV0004', 'US0002', NULL, 2, 0, NULL, '2026-05-10 18:00:00', NULL, 'Hoàn thành', '2026-05-19 11:44:01'),
('RV0005', 'US0004', NULL, 5, 0, NULL, '2026-05-18 19:00:00', 'Khách đổi ý', 'Đã hủy', '2026-05-19 11:44:01'),
('RV0006', 'US0006', NULL, 5, 0, NULL, '2026-05-20 18:00:00', '', 'Đã hủy', '2026-05-20 17:49:08'),
('RV0007', 'US0006', 'TB01', 15, 0, NULL, '2026-05-23 03:03:00', '', 'Đã hủy', '2026-05-22 10:36:38'),
('RV0008', 'US0008', NULL, 5, 0, NULL, '2026-05-22 11:30:00', '', 'Đã hủy', '2026-05-22 10:57:46'),
('RV0009', 'US0006', NULL, 5, 0, NULL, '2026-05-22 12:30:00', '', 'Hoàn thành', '2026-05-22 11:04:28'),
('RV0010', 'US0006', NULL, 5, 0, NULL, '2026-05-23 23:12:00', '', 'Chờ xác nhận', '2026-05-22 11:07:39'),
('RV0011', 'US0006', 'TB02', 4, 0, NULL, '2026-05-29 12:32:00', '', 'Đã xác nhận', '2026-05-29 10:20:42'),
('RV0012', 'US0006', NULL, 5, 0, NULL, '2026-05-29 12:30:00', '', 'Đã hủy', '2026-05-29 11:24:39'),
('RV0013', 'US0006', NULL, 5, 0, NULL, '2026-05-29 21:00:00', '', 'Chờ xác nhận', '2026-05-29 11:25:49'),
('RV0014', 'US0006', 'TB03', 5, 0, NULL, '2026-05-29 15:30:00', '', 'Đã xác nhận', '2026-05-29 11:32:52'),
('RV0015', 'US0006', NULL, 4, 3, '213213', '2026-06-01 12:30:00', '2112321', 'Chờ xác nhận', '2026-05-31 16:40:44'),
('RV0016', 'US0006', NULL, 5, 1, 'djnfds', '2026-06-01 13:30:00', 'dáda', 'Chờ xác nhận', '2026-05-31 16:43:57');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `Tables`
--

CREATE TABLE `Tables` (
  `tableId` char(4) NOT NULL,
  `tableNumber` int(11) NOT NULL CHECK (`tableNumber` > 0),
  `capacity` int(11) NOT NULL CHECK (`capacity` > 0),
  `tableType` varchar(20) NOT NULL DEFAULT 'Thường',
  `tableStatus` varchar(20) NOT NULL DEFAULT 'Còn trống'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `Tables`
--

INSERT INTO `Tables` (`tableId`, `tableNumber`, `capacity`, `tableType`, `tableStatus`) VALUES
('TB01', 1, 2, 'Thường', 'Đã đặt'),
('TB02', 2, 2, 'Thường', 'Đã đặt'),
('TB03', 3, 4, 'Thường', 'Đã đặt'),
('TB04', 4, 4, 'Thường', 'Còn trống'),
('TB05', 5, 6, 'Thường', 'Đang phục vụ'),
('TB06', 6, 6, 'VIP', 'Còn trống'),
('TB07', 7, 8, 'Thường', 'Còn trống'),
('TB08', 8, 10, 'VIP', 'Còn trống'),
('TB09', 9, 12, 'VIP', 'Còn trống'),
('TB10', 10, 15, 'VIP', 'Còn trống'),
('TB11', 11, 18, 'VIP', 'Còn trống'),
('TB12', 12, 20, 'VIP', 'Còn trống');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `Users`
--

CREATE TABLE `Users` (
  `userId` char(6) NOT NULL,
  `fullName` varchar(100) NOT NULL,
  `phone` char(10) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'Customer',
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `otp_code` varchar(10) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `Users`
--

INSERT INTO `Users` (`userId`, `fullName`, `phone`, `email`, `password`, `role`, `status`, `createdAt`, `otp_code`, `otp_expiry`) VALUES
('US0001', 'Nguyễn Văn An', '0901234567', 'an.nguyen@huongque.vn', '123456', 'Admin', 'Active', '2026-05-19 11:44:01', NULL, NULL),
('US0002', 'Trần Thị Bích', '0912345678', 'bich.tran@gmail.com', '123456', 'Customer', 'Active', '2026-05-19 11:44:01', NULL, NULL),
('US0003', 'Lê Văn Cường', '0923456789', 'cuong.le@gmail.com', '123456', 'Customer', 'Active', '2026-05-19 11:44:01', NULL, NULL),
('US0004', 'Phạm Thị Dung', '0934567890', 'dung.pham@gmail.com', '123456', 'Customer', 'Locked', '2026-05-19 11:44:01', NULL, NULL),
('US0005', 'Hoàng Văn Em', '0945678901', 'em.hoang@gmail.com', '123456', 'Customer', 'Active', '2026-05-19 11:44:01', NULL, NULL),
('US0006', 'Phạm Yến Nhi', '0123456789', 'phamyennhi@gmail.com', '$2y$10$GI5cOj3fX/.C0WM2a8UwZeV2TH3W/e1Ym1VcoBNmtGr78Kz2GhTy2', 'Customer', 'Active', '2026-05-20 13:48:56', NULL, NULL),
('US0008', 'Nhi', '0123456777', 'pommaxpham@gmail.com', '$2y$10$OcpXITNQfRrUEbzVjgdLxu9FOJ5frBG2kKK.eKiQw12yn0mVcW4QW', 'Admin', 'Active', '2026-05-21 23:14:23', '274550', '2026-05-29 13:56:27'),
('US0009', 'sweetynhi', '0989124567', 'phamyennhi0225@gmail.com', '$2y$10$ocwc1wYGekLg7rMOeeEuDez82781RB4sDsRn.Wvjb5OZpsqv4IVEK', 'Customer', 'Active', '2026-05-21 23:28:28', NULL, NULL);

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `MenuCategories`
--
ALTER TABLE `MenuCategories`
  ADD PRIMARY KEY (`categoryId`),
  ADD UNIQUE KEY `categoryName` (`categoryName`);

--
-- Chỉ mục cho bảng `MenuItems`
--
ALTER TABLE `MenuItems`
  ADD PRIMARY KEY (`menuItemId`),
  ADD KEY `categoryId` (`categoryId`);

--
-- Chỉ mục cho bảng `Reservations`
--
ALTER TABLE `Reservations`
  ADD PRIMARY KEY (`reservationId`),
  ADD KEY `tableId` (`tableId`),
  ADD KEY `reservations_ibfk_1` (`userId`);

--
-- Chỉ mục cho bảng `Tables`
--
ALTER TABLE `Tables`
  ADD PRIMARY KEY (`tableId`),
  ADD UNIQUE KEY `tableNumber` (`tableNumber`);

--
-- Chỉ mục cho bảng `Users`
--
ALTER TABLE `Users`
  ADD PRIMARY KEY (`userId`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `MenuItems`
--
ALTER TABLE `MenuItems`
  ADD CONSTRAINT `menuitems_ibfk_1` FOREIGN KEY (`categoryId`) REFERENCES `huongquerestaurant`.`MenuCategories` (`categoryId`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `Reservations`
--
ALTER TABLE `Reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`tableId`) REFERENCES `huongquerestaurant`.`Tables` (`tableId`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
