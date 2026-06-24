-- =====================================================
-- ITP4523M - Internet & Multimedia Applications Development
-- Group Project Database Setup Script
-- Database: projectDB
-- Engine: InnoDB
-- =====================================================

-- Drop existing database if exists
DROP DATABASE IF EXISTS `projectDB`;

-- Create new database with UTF-8 character set
CREATE DATABASE `projectDB` CHARACTER SET utf8 COLLATE utf8_general_ci;

-- Use the database
USE `projectDB`;

-- =====================================================
-- TABLE: Customer
-- =====================================================
DROP TABLE IF EXISTS `Customer`;
CREATE TABLE `Customer` (
    `CustomerID` INT NOT NULL AUTO_INCREMENT,
    `CustomerName` VARCHAR(100) NOT NULL,
    `CompanyName` VARCHAR(100) DEFAULT NULL,
    `Password` VARCHAR(255) NOT NULL,
    `ContactNumber` VARCHAR(20) NOT NULL,
    `Address` TEXT NOT NULL,
    `Email` VARCHAR(100) NOT NULL,
    `RegistrationDate` DATE NOT NULL,
    PRIMARY KEY (`CustomerID`)
) ENGINE = InnoDB;

-- Sample Customers
INSERT INTO `Customer` (`CustomerID`, `CustomerName`, `Password`, `ContactNumber`, `Address`, `Email`, `RegistrationDate`) VALUES
(1001, 'John Smith', MD5('password123'), '852-91234567', '15 Canton Road, Tsim Sha Tsui, Hong Kong', 'john.smith@email.com', '2024-01-15'),
(1002, 'Sarah Wong', MD5('password456'), '852-92345678', '28 Nathan Road, Kowloon, Hong Kong', 'sarah.wong@email.com', '2024-02-20'),
(1003, 'Michael Chan', MD5('password789'), '852-93456789', '42 Hollywood Road, Central, Hong Kong', 'michael.chan@email.com', '2024-03-10'),
(1004, 'Emily Leung', MD5('emily2024'), '852-94567890', '7 Hoi Wang Road, Mongkok, Hong Kong', 'emily.leung@email.com', '2024-04-05'),
(1005, 'David Lau', MD5('david123'), '852-95678901', '33 Lockhart Road, Wan Chai, Hong Kong', 'david.lau@email.com', '2024-05-12');

-- =====================================================
-- TABLE: Material
-- =====================================================
DROP TABLE IF EXISTS `Material`;
CREATE TABLE `Material` (
    `MaterialID` INT NOT NULL AUTO_INCREMENT,
    `MaterialName` VARCHAR(100) NOT NULL,
    `PhysicalQuantity` DECIMAL(10,2) NOT NULL,
    `Unit` VARCHAR(20) NOT NULL,
    `ReorderLevel` DECIMAL(10,2) DEFAULT 0,
    `LastUpdated` DATE NOT NULL,
    PRIMARY KEY (`MaterialID`)
) ENGINE = InnoDB;

-- Sample Materials
INSERT INTO `Material` (`MaterialID`, `MaterialName`, `PhysicalQuantity`, `Unit`, `ReorderLevel`, `LastUpdated`) VALUES
(1, 'Solid Oak Wood', 1500.00, 'Board Feet', 300, '2024-12-01'),
(2, 'Walnut Veneer', 800.00, 'Square Meters', 200, '2024-12-01'),
(3, 'Leather - Brown', 500.00, 'Square Feet', 100, '2024-12-01'),
(4, 'Cotton Fabric - Grey', 1200.00, 'Square Feet', 250, '2024-12-01'),
(5, 'Tempered Glass', 300.00, 'Square Feet', 80, '2024-12-01'),
(6, 'Aluminum Frame', 600.00, 'Linear Feet', 150, '2024-12-01'),
(7, 'Steel Screws', 5000.00, 'Pieces', 1000, '2024-12-01'),
(8, 'Wood Glue', 200.00, 'Liters', 50, '2024-12-01'),
(9, 'Polyurethane Foam', 400.00, 'Cubic Feet', 100, '2024-12-01'),
(10, 'Stainless Steel Legs', 250.00, 'Pairs', 60, '2024-12-01');

-- =====================================================
-- TABLE: Furniture
-- =====================================================
DROP TABLE IF EXISTS `Furniture`;
CREATE TABLE `Furniture` (
    `FurnitureID` INT NOT NULL AUTO_INCREMENT,
    `FurnitureName` VARCHAR(100) NOT NULL,
    `FurnitureDescription` TEXT NOT NULL,
    `FurnitureImage` VARCHAR(255) NOT NULL,
    `Price` DECIMAL(10,2) NOT NULL,
    `StockQuantity` INT NOT NULL DEFAULT 0,
    `Category` VARCHAR(50) NOT NULL,
    `CreatedDate` DATE NOT NULL,
    PRIMARY KEY (`FurnitureID`)
) ENGINE = InnoDB;

-- Sample Furniture Products
INSERT INTO `Furniture` (`FurnitureID`, `FurnitureName`, `FurnitureDescription`, `FurnitureImage`, `Price`, `StockQuantity`, `Category`, `CreatedDate`) VALUES
(101, 'Executive Oak Desk', 'Premium solid oak desk with modern design. Features cable management and spacious work surface.', 'executive_oak_desk.jpg', 899.00, 12, 'Desks', '2024-01-10'),
(102, 'Leather Recliner Chair', 'Comfortable leather recliner with adjustable positions. Perfect for home theater or living room.', 'leather_recliner.jpg', 549.00, 8, 'Chairs', '2024-01-10'),
(103, 'Walnut Dining Table', 'Elegant walnut dining table that seats 6-8 people. Scratch-resistant finish.', 'walnut_dining_table.jpg', 1299.00, 5, 'Tables', '2024-01-15'),
(104, 'Modern Bookshelf', 'Sleek wall-mounted bookshelf with 5 adjustable shelves. Space-saving design.', 'modern_bookshelf.jpg', 299.00, 0, 'Storage', '2024-02-01'),
(105, 'Grey Fabric Sofa', '3-seater fabric sofa with high-density foam cushions. Removable and washable covers.', 'grey_fabric_sofa.jpg', 799.00, 10, 'Sofas', '2024-02-10'),
(106, 'Glass Coffee Table', 'Tempered glass top with aluminum frame coffee table. Minimalist and modern.', 'glass_coffee_table.jpg', 349.00, 15, 'Tables', '2024-03-01'),
(107, 'Ergonomic Office Chair', 'Adjustable office chair with lumbar support. Breathable mesh back.', 'ergonomic_chair.jpg', 399.00, 0, 'Chairs', '2024-03-15'),
(108, 'Solid Wood Bed Frame', 'Queen size bed frame made from solid pine wood. Includes headboard and slats.', 'wood_bed_frame.jpg', 649.00, 7, 'Beds', '2024-04-01');

-- =====================================================
-- TABLE: FurnitureImage (Multiple product views / gallery)
-- =====================================================
DROP TABLE IF EXISTS `FurnitureImage`;
CREATE TABLE `FurnitureImage` (
    `ImageID` INT NOT NULL AUTO_INCREMENT,
    `FurnitureID` INT NOT NULL,
    `ImagePath` VARCHAR(255) NOT NULL,
    `SortOrder` INT DEFAULT 0,
    `IsPrimary` TINYINT(1) DEFAULT 0,
    PRIMARY KEY (`ImageID`),
    FOREIGN KEY (`FurnitureID`) REFERENCES `Furniture`(`FurnitureID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB;

-- Populate product views from existing subfolder images
INSERT INTO `FurnitureImage` (`FurnitureID`, `ImagePath`, `SortOrder`, `IsPrimary`) VALUES
-- Executive Oak Desk (101)
(101, 'Executive Oak Desk/Executive Oak Desk (1).png', 1, 1),
(101, 'Executive Oak Desk/Executive Oak Desk (2).png', 2, 0),
(101, 'Executive Oak Desk/Executive Oak Desk (3).png', 3, 0),
(101, 'Executive Oak Desk/Executive Oak Desk (4).png', 4, 0),
-- Leather Recliner Chair (102)
(102, 'Leather Recliner Chair/Leather Recliner Chair (1).png', 1, 1),
(102, 'Leather Recliner Chair/Leather Recliner Chair (2).png', 2, 0),
(102, 'Leather Recliner Chair/Leather Recliner Chair (3).png', 3, 0),
(102, 'Leather Recliner Chair/Leather Recliner Chair (4).png', 4, 0),
-- Walnut Dining Table (103)
(103, 'Walnut Dining Table/Walnut Dining Table (1).png', 1, 1),
(103, 'Walnut Dining Table/Walnut Dining Table (2).png', 2, 0),
(103, 'Walnut Dining Table/Walnut Dining Table (3).png', 3, 0),
(103, 'Walnut Dining Table/Walnut Dining Table (4).png', 4, 0),
-- Modern Bookshelf (104)
(104, 'Modern Bookshelf/Modern Bookshelf (1).png', 1, 1),
(104, 'Modern Bookshelf/Modern Bookshelf (2).png', 2, 0),
(104, 'Modern Bookshelf/Modern Bookshelf (3).png', 3, 0),
(104, 'Modern Bookshelf/Modern Bookshelf (4).png', 4, 0),
-- Grey Fabric Sofa (105)
(105, 'Grey Fabric Sofa/Grey Fabric Sofa (1).png', 1, 1),
(105, 'Grey Fabric Sofa/Grey Fabric Sofa (2).png', 2, 0),
(105, 'Grey Fabric Sofa/Grey Fabric Sofa (3).png', 3, 0),
(105, 'Grey Fabric Sofa/Grey Fabric Sofa (4).png', 4, 0),
-- Glass Coffee Table (106)
(106, 'Glass Coffee Table/Glass Coffee Table (1).png', 1, 1),
(106, 'Glass Coffee Table/Glass Coffee Table (2).png', 2, 0),
(106, 'Glass Coffee Table/Glass Coffee Table (3).png', 3, 0),
(106, 'Glass Coffee Table/Glass Coffee Table (4).png', 4, 0),
-- Ergonomic Office Chair (107)
(107, 'Ergonomic Office Chair/Ergonomic Office Chair (1).png', 1, 1),
(107, 'Ergonomic Office Chair/Ergonomic Office Chair (2).png', 2, 0),
(107, 'Ergonomic Office Chair/Ergonomic Office Chair (3).png', 3, 0),
(107, 'Ergonomic Office Chair/Ergonomic Office Chair (4).png', 4, 0),
-- Solid Wood Bed Frame (108)
(108, 'Solid Wood Bed Frame/Solid Wood Bed Frame (1).png', 1, 1),
(108, 'Solid Wood Bed Frame/Solid Wood Bed Frame (2).png', 2, 0),
(108, 'Solid Wood Bed Frame/Solid Wood Bed Frame (3).png', 3, 0),
(108, 'Solid Wood Bed Frame/Solid Wood Bed Frame (4).png', 4, 0);

-- =====================================================
-- TABLE: FurnitureColor (Per-product color options)
-- =====================================================
DROP TABLE IF EXISTS `FurnitureColor`;
CREATE TABLE `FurnitureColor` (
    `ColorID` INT NOT NULL AUTO_INCREMENT,
    `FurnitureID` INT NOT NULL,
    `ColorName` VARCHAR(50) NOT NULL,
    `ColorHex` VARCHAR(7) NOT NULL,
    `AdditionalPrice` DECIMAL(10,2) DEFAULT 0.00,
    `SortOrder` INT DEFAULT 0,
    PRIMARY KEY (`ColorID`),
    FOREIGN KEY (`FurnitureID`) REFERENCES `Furniture`(`FurnitureID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB;

INSERT INTO `FurnitureColor` (`FurnitureID`, `ColorName`, `ColorHex`, `AdditionalPrice`, `SortOrder`) VALUES
(101, 'Dark Oak', '#5c3a21', 0.00, 1),
(101, 'Walnut', '#6b3a1f', 0.00, 2),
(101, 'Cherry', '#7b2020', 25.00, 3),
(101, 'Matte Black', '#2d2d2d', 15.00, 4),
(102, 'Brown Leather', '#8b5a2b', 0.00, 1),
(102, 'Black Leather', '#1a1a1a', 0.00, 2),
(102, 'Cream Leather', '#f5e6d3', 30.00, 3),
(103, 'Walnut', '#6b3a1f', 0.00, 1),
(103, 'Espresso', '#3c1e0d', 0.00, 2),
(103, 'White Wash', '#f8f4ed', 50.00, 3),
(104, 'Walnut', '#6b3a1f', 0.00, 1),
(104, 'White', '#f8f8f8', 0.00, 2),
(104, 'Dark Oak', '#5c3a21', 0.00, 3),
(105, 'Charcoal Grey', '#4a4a4a', 0.00, 1),
(105, 'Beige', '#e8d5b7', 0.00, 2),
(105, 'Navy Blue', '#1a3a5c', 20.00, 3),
(106, 'Clear Glass', '#e0f0f8', 0.00, 1),
(106, 'Frosted Glass', '#d8d8d8', 15.00, 2),
(107, 'Black Mesh', '#1a1a1a', 0.00, 1),
(107, 'Grey Fabric', '#9e9e9e', 0.00, 2),
(107, 'Blue Fabric', '#3a5a8c', 0.00, 3),
(108, 'Pine Natural', '#d4a853', 0.00, 1),
(108, 'Dark Oak', '#5c3a21', 30.00, 2),
(108, 'White', '#f8f8f8', 20.00, 3);

-- =====================================================
-- TABLE: FurnitureSize (Per-product size options)
-- =====================================================
DROP TABLE IF EXISTS `FurnitureSize`;
CREATE TABLE `FurnitureSize` (
    `SizeID` INT NOT NULL AUTO_INCREMENT,
    `FurnitureID` INT NOT NULL,
    `SizeName` VARCHAR(50) NOT NULL,
    `Dimensions` VARCHAR(100) DEFAULT NULL,
    `AdditionalPrice` DECIMAL(10,2) DEFAULT 0.00,
    `SortOrder` INT DEFAULT 0,
    PRIMARY KEY (`SizeID`),
    FOREIGN KEY (`FurnitureID`) REFERENCES `Furniture`(`FurnitureID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB;

INSERT INTO `FurnitureSize` (`FurnitureID`, `SizeName`, `Dimensions`, `AdditionalPrice`, `SortOrder`) VALUES
(101, 'Small', '120×60×75 cm', 0.00, 1),
(101, 'Medium', '150×75×75 cm', 50.00, 2),
(101, 'Large', '180×90×75 cm', 120.00, 3),
(103, '4-Seater', '120×80×75 cm', 0.00, 1),
(103, '6-Seater', '160×90×75 cm', 100.00, 2),
(103, '8-Seater', '200×100×75 cm', 200.00, 3),
(104, '3-Shelf', '60×25×90 cm', 0.00, 1),
(104, '5-Shelf', '60×25×180 cm', 40.00, 2),
(105, '2-Seater', '150×85×80 cm', 0.00, 1),
(105, '3-Seater', '210×85×80 cm', 120.00, 2),
(106, 'Small', '80×50×45 cm', 0.00, 1),
(106, 'Large', '120×70×45 cm', 40.00, 2),
(108, 'Single', '100×200 cm', 0.00, 1),
(108, 'Double', '140×200 cm', 80.00, 2),
(108, 'Queen', '160×200 cm', 150.00, 3);

-- =====================================================
-- TABLE: FurnitureAccessory (Per-product add-ons)
-- =====================================================
DROP TABLE IF EXISTS `FurnitureAccessory`;
CREATE TABLE `FurnitureAccessory` (
    `AccessoryID` INT NOT NULL AUTO_INCREMENT,
    `FurnitureID` INT NOT NULL,
    `AccessoryName` VARCHAR(100) NOT NULL,
    `Description` VARCHAR(255) DEFAULT NULL,
    `AdditionalPrice` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `SortOrder` INT DEFAULT 0,
    PRIMARY KEY (`AccessoryID`),
    FOREIGN KEY (`FurnitureID`) REFERENCES `Furniture`(`FurnitureID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB;

INSERT INTO `FurnitureAccessory` (`FurnitureID`, `AccessoryName`, `Description`, `AdditionalPrice`, `SortOrder`) VALUES
(101, 'Cable Management Tray', 'Under-desk tray for organizing cables and power strips', 35.00, 1),
(101, 'Monitor Stand Riser', 'Elevated stand with storage compartment', 45.00, 2),
(101, 'Drawer Unit', 'Lockable side drawer attachment', 89.00, 3),
(102, 'Lumbar Support Cushion', 'Memory foam additional back support', 25.00, 1),
(102, 'Headrest Pillow', 'Adjustable neck support pillow', 30.00, 2),
(103, 'Table Extension Leaf', 'Adds 40cm to table length for extra seating', 75.00, 1),
(103, 'Table Protector Pad', 'Clear protective cover for tabletop', 25.00, 2),
(104, 'LED Strip Lighting', 'Warm white under-shelf LED lighting kit', 30.00, 1),
(104, 'Wall Anchor Kit', 'Heavy-duty wall mounting hardware set', 15.00, 2),
(105, 'Throw Pillow Set', 'Set of 2 matching decorative pillows', 40.00, 1),
(105, 'Sofa Cover', 'Machine-washable protective cover', 55.00, 2),
(106, 'Coaster Set', 'Set of 6 cork-backed coasters', 12.00, 1),
(107, 'Armrest Pads', 'Memory foam armrest cushion set', 20.00, 1),
(107, 'Floor Mat', 'Hard floor protective mat for chair casters', 45.00, 2),
(108, 'Under-Bed Storage Drawer', 'Roll-out storage drawer with wheels', 65.00, 1),
(108, 'Bedside Pockets', 'Hanging organizer with 3 pockets', 20.00, 2),
(108, 'LED Headboard Light', 'Dimmable reading light attachment', 35.00, 3);

-- =====================================================
-- TABLE: Furniture_Material (Junction table for many-to-many)
-- =====================================================
DROP TABLE IF EXISTS `Furniture_Material`;
CREATE TABLE `Furniture_Material` (
    `FurnitureID` INT NOT NULL,
    `MaterialID` INT NOT NULL,
    `MaterialQuantity` DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (`FurnitureID`, `MaterialID`),
    FOREIGN KEY (`FurnitureID`) REFERENCES `Furniture`(`FurnitureID`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`MaterialID`) REFERENCES `Material`(`MaterialID`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB;

-- Materials required for each furniture product
INSERT INTO `Furniture_Material` (`FurnitureID`, `MaterialID`, `MaterialQuantity`) VALUES
(101, 1, 25.00),   -- Oak Desk needs Oak Wood
(101, 7, 50.00),   -- Oak Desk needs Screws
(101, 8, 2.00),    -- Oak Desk needs Wood Glue

(102, 3, 45.00),   -- Leather Recliner needs Leather
(102, 9, 8.00),    -- Leather Recliner needs Foam
(102, 10, 1.00),   -- Leather Recliner needs Steel Legs

(103, 2, 20.00),   -- Walnut Table needs Walnut Veneer
(103, 6, 4.00),    -- Walnut Table needs Aluminum Frame
(103, 8, 1.50),    -- Walnut Table needs Wood Glue

(104, 2, 12.00),   -- Bookshelf needs Walnut Veneer
(104, 6, 2.50),    -- Bookshelf needs Aluminum Frame
(104, 7, 30.00),   -- Bookshelf needs Screws

(105, 4, 60.00),   -- Fabric Sofa needs Cotton Fabric
(105, 9, 12.00),   -- Fabric Sofa needs Foam
(105, 10, 2.00),   -- Fabric Sofa needs Steel Legs

(106, 5, 12.00),   -- Coffee Table needs Glass
(106, 6, 2.00),    -- Coffee Table needs Aluminum Frame
(106, 7, 20.00),   -- Coffee Table needs Screws

(107, 4, 8.00),    -- Office Chair needs Fabric
(107, 6, 3.00),    -- Office Chair needs Aluminum Frame
(107, 10, 1.00),   -- Office Chair needs Steel Legs

(108, 1, 35.00),   -- Bed Frame needs Oak Wood
(108, 7, 80.00),   -- Bed Frame needs Screws
(108, 8, 3.00);    -- Bed Frame needs Wood Glue

-- =====================================================
-- TABLE: Orders
-- =====================================================
DROP TABLE IF EXISTS `Orders`;
CREATE TABLE `Orders` (
    `OrderID` INT NOT NULL AUTO_INCREMENT,
    `CustomerID` INT NOT NULL,
    `OrderDate` DATE NOT NULL,
    `FurnitureID` INT NOT NULL,
    `OrderQuantity` INT NOT NULL,
    `TotalOrderAmount` DECIMAL(10,2) NOT NULL,
    `DeliveryAddress` TEXT NOT NULL,
    `DeliveryDate` DATE NOT NULL,
    `OrderStatus` ENUM('pending', 'accepted', 'rejected', 'delivered') DEFAULT 'pending',
    `PaymentMethod` ENUM('credit_card', 'bank_transfer', 'cod') DEFAULT NULL,
    `PaymentStatus` ENUM('unpaid', 'paid', 'refunded') DEFAULT 'unpaid',
    `LastModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`OrderID`),
    FOREIGN KEY (`CustomerID`) REFERENCES `Customer`(`CustomerID`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`FurnitureID`) REFERENCES `Furniture`(`FurnitureID`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB;

-- Sample Orders (mix of statuses and payment methods for testing)
INSERT INTO `Orders` (`OrderID`, `CustomerID`, `OrderDate`, `FurnitureID`, `OrderQuantity`, `TotalOrderAmount`, `DeliveryAddress`, `DeliveryDate`, `OrderStatus`, `PaymentMethod`, `PaymentStatus`) VALUES
(5001, 1001, '2025-11-15', 101, 1, 899.00, '15 Canton Road, Tsim Sha Tsui, Hong Kong', '2025-11-25', 'delivered', 'credit_card', 'paid'),
(5002, 1002, '2025-11-20', 102, 2, 1098.00, '28 Nathan Road, Kowloon, Hong Kong', '2025-11-30', 'delivered', 'cod', 'paid'),
(5003, 1003, '2025-12-01', 103, 1, 1299.00, '42 Hollywood Road, Central, Hong Kong', '2025-12-15', 'accepted', 'bank_transfer', 'paid'),
(5004, 1001, '2025-12-05', 105, 1, 799.00, '15 Canton Road, Tsim Sha Tsui, Hong Kong', '2025-12-18', 'pending', 'credit_card', 'paid'),
(5005, 1004, '2025-12-08', 106, 2, 698.00, '7 Hoi Wang Road, Mongkok, Hong Kong', '2025-12-20', 'accepted', 'bank_transfer', 'unpaid'),
(5006, 1005, '2025-12-10', 108, 1, 649.00, '33 Lockhart Road, Wan Chai, Hong Kong', '2025-12-22', 'pending', 'cod', 'unpaid');

-- =====================================================
-- TABLE: Staff
-- =====================================================
DROP TABLE IF EXISTS `Staff`;
CREATE TABLE `Staff` (
    `StaffID` INT NOT NULL AUTO_INCREMENT,
    `StaffName` VARCHAR(100) NOT NULL,
    `Password` VARCHAR(255) NOT NULL,
    `Email` VARCHAR(100) NOT NULL,
    `Role` VARCHAR(50) DEFAULT 'staff',
    `HireDate` DATE NOT NULL,
    PRIMARY KEY (`StaffID`)
) ENGINE = InnoDB;

-- Sample Staff Accounts
INSERT INTO `Staff` (`StaffID`, `StaffName`, `Password`, `Email`, `Role`, `HireDate`) VALUES
(1, 'Admin User', MD5('admin123'), 'admin@premiumliving.com', 'administrator', '2024-01-01'),
(2, 'Staff Manager', MD5('staff456'), 'manager@premiumliving.com', 'manager', '2024-01-15'),
(3, 'Inventory Clerk', MD5('inventory789'), 'inventory@premiumliving.com', 'staff', '2024-02-01');

-- =====================================================
-- TABLE: ContactInquiries
-- =====================================================
DROP TABLE IF EXISTS `ContactInquiries`;
CREATE TABLE `ContactInquiries` (
    `InquiryID` INT NOT NULL AUTO_INCREMENT,
    `CustomerName` VARCHAR(100) NOT NULL,
    `Email` VARCHAR(100) NOT NULL,
    `Subject` VARCHAR(200) DEFAULT NULL,
    `Message` TEXT NOT NULL,
    `CreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`InquiryID`)
) ENGINE = InnoDB;

-- =====================================================
-- TRIGGER: Update stock quantity after order insertion
-- =====================================================
DROP TRIGGER IF EXISTS `update_stock_after_order`;
DELIMITER //
CREATE TRIGGER `update_stock_after_order`
AFTER INSERT ON `Orders`
FOR EACH ROW
BEGIN
    UPDATE `Furniture`
    SET `StockQuantity` = `StockQuantity` - NEW.`OrderQuantity`
    WHERE `FurnitureID` = NEW.`FurnitureID`;
END//
DELIMITER ;

-- =====================================================
-- TRIGGER: Restore stock quantity when order is deleted
-- =====================================================
DROP TRIGGER IF EXISTS `restore_stock_after_order_delete`;
DELIMITER //
CREATE TRIGGER `restore_stock_after_order_delete`
AFTER DELETE ON `Orders`
FOR EACH ROW
BEGIN
    UPDATE `Furniture`
    SET `StockQuantity` = `StockQuantity` + OLD.`OrderQuantity`
    WHERE `FurnitureID` = OLD.`FurnitureID`;
END//
DELIMITER ;

-- =====================================================
-- VIEW: Order Details with Customer and Furniture Info
-- =====================================================
DROP VIEW IF EXISTS `OrderDetailsView`;
CREATE VIEW `OrderDetailsView` AS
SELECT 
    o.OrderID,
    o.OrderDate,
    o.OrderQuantity,
    o.TotalOrderAmount,
    o.DeliveryAddress,
    o.DeliveryDate,
    o.OrderStatus,
    c.CustomerID,
    c.CustomerName,
    c.ContactNumber,
    c.Email,
    f.FurnitureID,
    f.FurnitureName,
    f.FurnitureImage,
    f.Price
FROM `Orders` o
INNER JOIN `Customer` c ON o.CustomerID = c.CustomerID
INNER JOIN `Furniture` f ON o.FurnitureID = f.FurnitureID;

-- =====================================================
-- VIEW: Material Usage Report
-- =====================================================
DROP VIEW IF EXISTS `MaterialUsageView`;
CREATE VIEW `MaterialUsageView` AS
SELECT 
    m.MaterialID,
    m.MaterialName,
    m.Unit,
    m.PhysicalQuantity AS AvailableQuantity,
    COALESCE(SUM(fm.MaterialQuantity * o.OrderQuantity), 0) AS ReservedQuantity,
    (m.PhysicalQuantity - COALESCE(SUM(fm.MaterialQuantity * o.OrderQuantity), 0)) AS RemainingQuantity
FROM `Material` m
LEFT JOIN `Furniture_Material` fm ON m.MaterialID = fm.MaterialID
LEFT JOIN `Furniture` f ON fm.FurnitureID = f.FurnitureID
LEFT JOIN `Orders` o ON f.FurnitureID = o.FurnitureID AND o.OrderStatus IN ('pending', 'accepted')
GROUP BY m.MaterialID, m.MaterialName, m.Unit, m.PhysicalQuantity;

-- =====================================================
-- VIEW: Sales Report by Furniture Item
-- =====================================================
DROP VIEW IF EXISTS `SalesReportView`;
CREATE VIEW `SalesReportView` AS
SELECT 
    o.OrderID,
    f.FurnitureID,
    f.FurnitureName,
    f.FurnitureImage,
    o.OrderQuantity AS TotalNumberForOrderItem,
    (f.Price * o.OrderQuantity) AS TotalSalesAmount
FROM `Orders` o
INNER JOIN `Furniture` f ON o.FurnitureID = f.FurnitureID
WHERE o.OrderStatus = 'delivered'
ORDER BY TotalSalesAmount DESC;

-- =====================================================
-- Display confirmation message
-- =====================================================
SELECT 'Database projectDB created successfully with all tables, triggers, and views!' AS Status;
SELECT 'Customer Table' AS TableName, COUNT(*) AS RecordCount FROM Customer
UNION
SELECT 'Furniture Table', COUNT(*) FROM Furniture
UNION
SELECT 'Material Table', COUNT(*) FROM Material
UNION
SELECT 'Furniture_Material Table', COUNT(*) FROM Furniture_Material
UNION
SELECT 'Orders Table', COUNT(*) FROM Orders
UNION
SELECT 'Staff Table', COUNT(*) FROM Staff;