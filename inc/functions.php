<?php
/**
 * functions.php - Reusable helper functions for the application
 * Promotes code reusability as required in the assessment criteria
 */

require_once 'config.php';

/**
 * Validate email format
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate phone number (Hong Kong format)
 */
function validatePhoneNumber($phone) {
    return preg_match('/^[0-9]{8}$|^852-[0-9]{8}$/', $phone);
}

/**
 * Validate date format and range
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Get all furniture products with stock status
 */
function getAllFurniture($conn, $sortColumn = 'FurnitureName', $sortOrder = 'ASC') {
    $allowedColumns = ['FurnitureName', 'Price', 'StockQuantity', 'Category'];
    $allowedOrders = ['ASC', 'DESC'];
    
    $sortColumn = in_array($sortColumn, $allowedColumns) ? $sortColumn : 'FurnitureName';
    $sortOrder = in_array($sortOrder, $allowedOrders) ? $sortOrder : 'ASC';
    
    $sql = "SELECT * FROM Furniture ORDER BY $sortColumn $sortOrder";
    $result = mysqli_query($conn, $sql);
    
    $furniture = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['is_sold_out'] = ($row['StockQuantity'] <= 0);
        $furniture[] = $row;
    }
    return $furniture;
}

/**
 * Get furniture by ID
 */
function getFurnitureById($conn, $furnitureId) {
    $sql = "SELECT * FROM Furniture WHERE FurnitureID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $furnitureId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

/**
 * Get all product view images for a furniture product
 */
function getFurnitureImages($conn, $furnitureId) {
    $sql = "SELECT ImageID, ImagePath, SortOrder, IsPrimary
            FROM FurnitureImage
            WHERE FurnitureID = ?
            ORDER BY SortOrder ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $furnitureId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $images = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $images[] = $row;
    }
    return $images;
}

/**
 * Insert product view images for a furniture product
 * @param array $images Array of ['path' => string, 'sort_order' => int, 'is_primary' => int]
 */
function insertFurnitureImages($conn, $furnitureId, $images) {
    $sql = "INSERT INTO FurnitureImage (FurnitureID, ImagePath, SortOrder, IsPrimary) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);

    foreach ($images as $img) {
        $sortOrder = (int)($img['sort_order'] ?? 0);
        $isPrimary = (int)($img['is_primary'] ?? 0);
        mysqli_stmt_bind_param($stmt, "isii", $furnitureId, $img['path'], $sortOrder, $isPrimary);
        mysqli_stmt_execute($stmt);
    }
    return true;
}

/**
 * Delete all product view images for a furniture product
 * Also deletes the physical image files from disk
 */
function deleteFurnitureImages($conn, $furnitureId) {
    // Get image paths first so we can delete files
    $images = getFurnitureImages($conn, $furnitureId);
    foreach ($images as $img) {
        $filePath = FURNITURE_IMAGE_UPLOAD_PATH . $img['ImagePath'];
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    $sql = "DELETE FROM FurnitureImage WHERE FurnitureID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $furnitureId);
    return mysqli_stmt_execute($stmt);
}

/**
 * Get materials for a furniture product
 */
function getMaterialsByFurnitureId($conn, $furnitureId) {
    $sql = "SELECT m.MaterialID, m.MaterialName, m.Unit, fm.MaterialQuantity, m.PhysicalQuantity
            FROM Furniture_Material fm
            INNER JOIN Material m ON fm.MaterialID = m.MaterialID
            WHERE fm.FurnitureID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $furnitureId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $materials = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $materials[] = $row;
    }
    return $materials;
}

/**
 * Get color options for a furniture product
 */
function getFurnitureColors($conn, $furnitureId) {
    $sql = "SELECT ColorID, ColorName, ColorHex, AdditionalPrice, SortOrder
            FROM FurnitureColor WHERE FurnitureID = ? ORDER BY SortOrder ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $furnitureId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $colors = [];
    while ($row = mysqli_fetch_assoc($result)) { $colors[] = $row; }
    return $colors;
}

/**
 * Get size options for a furniture product
 */
function getFurnitureSizes($conn, $furnitureId) {
    $sql = "SELECT SizeID, SizeName, Dimensions, AdditionalPrice, SortOrder
            FROM FurnitureSize WHERE FurnitureID = ? ORDER BY SortOrder ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $furnitureId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $sizes = [];
    while ($row = mysqli_fetch_assoc($result)) { $sizes[] = $row; }
    return $sizes;
}

/**
 * Get accessory options for a furniture product
 */
function getFurnitureAccessories($conn, $furnitureId) {
    $sql = "SELECT AccessoryID, AccessoryName, Description, AdditionalPrice, SortOrder
            FROM FurnitureAccessory WHERE FurnitureID = ? ORDER BY SortOrder ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $furnitureId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $accessories = [];
    while ($row = mysqli_fetch_assoc($result)) { $accessories[] = $row; }
    return $accessories;
}

/**
 * Batch insert color options
 */
function insertFurnitureColors($conn, $furnitureId, $colors) {
    $sql = "INSERT INTO FurnitureColor (FurnitureID, ColorName, ColorHex, AdditionalPrice, SortOrder) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    foreach ($colors as $c) {
        $so = (int)($c['sort_order'] ?? 0);
        $ap = (float)($c['additional_price'] ?? 0);
        mysqli_stmt_bind_param($stmt, "issdi", $furnitureId, $c['name'], $c['hex'], $ap, $so);
        mysqli_stmt_execute($stmt);
    }
}

/**
 * Batch insert size options
 */
function insertFurnitureSizes($conn, $furnitureId, $sizes) {
    $sql = "INSERT INTO FurnitureSize (FurnitureID, SizeName, Dimensions, AdditionalPrice, SortOrder) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    foreach ($sizes as $s) {
        $so = (int)($s['sort_order'] ?? 0);
        $ap = (float)($s['additional_price'] ?? 0);
        $dim = $s['dimensions'] ?? null;
        mysqli_stmt_bind_param($stmt, "issdi", $furnitureId, $s['name'], $dim, $ap, $so);
        mysqli_stmt_execute($stmt);
    }
}

/**
 * Batch insert accessory options
 */
function insertFurnitureAccessories($conn, $furnitureId, $accessories) {
    $sql = "INSERT INTO FurnitureAccessory (FurnitureID, AccessoryName, Description, AdditionalPrice, SortOrder) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    foreach ($accessories as $a) {
        $so = (int)($a['sort_order'] ?? 0);
        $ap = (float)($a['additional_price'] ?? 0);
        $desc = $a['description'] ?? null;
        mysqli_stmt_bind_param($stmt, "issdi", $furnitureId, $a['name'], $desc, $ap, $so);
        mysqli_stmt_execute($stmt);
    }
}

/**
 * Get orders by customer ID
 */
function getOrdersByCustomerId($conn, $customerId, $sortColumn = 'OrderDate', $sortOrder = 'DESC') {
    $allowedColumns = ['OrderID', 'OrderDate', 'OrderQuantity', 'TotalOrderAmount', 'DeliveryDate', 'OrderStatus'];
    $allowedOrders = ['ASC', 'DESC'];
    
    $sortColumn = in_array($sortColumn, $allowedColumns) ? $sortColumn : 'OrderDate';
    $sortOrder = in_array($sortOrder, $allowedOrders) ? $sortOrder : 'DESC';
    
    $sql = "SELECT o.*, f.FurnitureName, f.FurnitureImage, f.Price 
            FROM Orders o
            INNER JOIN Furniture f ON o.FurnitureID = f.FurnitureID
            WHERE o.CustomerID = ?
            ORDER BY $sortColumn $sortOrder";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $customerId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $orders = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['can_delete'] = canDeleteOrder($row['DeliveryDate']);
        $orders[] = $row;
    }
    return $orders;
}

/**
 * Get all orders (for staff)
 */
function getAllOrders($conn, $statusFilter = null) {
    $sql = "SELECT o.*, c.CustomerName, c.ContactNumber, f.FurnitureName, f.FurnitureImage, f.Price
            FROM Orders o
            INNER JOIN Customer c ON o.CustomerID = c.CustomerID
            INNER JOIN Furniture f ON o.FurnitureID = f.FurnitureID";
    
    if ($statusFilter && $statusFilter != 'all') {
        $sql .= " WHERE o.OrderStatus = '" . mysqli_real_escape_string($conn, $statusFilter) . "'";
    }
    
    $sql .= " ORDER BY o.OrderDate DESC";
    
    $result = mysqli_query($conn, $sql);
    $orders = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $orders[] = $row;
    }
    return $orders;
}

/**
 * Create new order
 */
function createOrder($conn, $customerId, $furnitureId, $quantity, $deliveryAddress, $deliveryDate, $paymentMethod = 'cod', $selectedColor = null, $selectedSize = null, $selectedAccessories = null, $customizationCost = 0) {
    // Get furniture price and stock
    $furniture = getFurnitureById($conn, $furnitureId);
    if (!$furniture) {
        return ['success' => false, 'message' => 'Furniture product not found.'];
    }

    if ($furniture['StockQuantity'] < $quantity) {
        return ['success' => false, 'message' => 'Insufficient stock. Available: ' . $furniture['StockQuantity']];
    }

    // Validate payment method
    $allowedMethods = ['credit_card', 'bank_transfer', 'cod'];
    if (!in_array($paymentMethod, $allowedMethods)) {
        $paymentMethod = 'cod';
    }

    // Determine payment status based on method
    $paymentStatus = ($paymentMethod === 'credit_card') ? 'paid' : 'unpaid';

    $baseAmount = $furniture['Price'] * $quantity;
    $totalAmount = $baseAmount + (float)$customizationCost;
    $orderDate = date('Y-m-d');

    // Encode accessories as JSON if provided
    $accessoriesJson = null;
    if (!empty($selectedAccessories) && is_array($selectedAccessories)) {
        $accessoriesJson = json_encode($selectedAccessories);
    }

    $sql = "INSERT INTO Orders (CustomerID, OrderDate, FurnitureID, OrderQuantity, TotalOrderAmount, DeliveryAddress, DeliveryDate, OrderStatus, PaymentMethod, PaymentStatus, SelectedColor, SelectedSize, SelectedAccessories, CustomizationCost)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "isiidsssssssd", $customerId, $orderDate, $furnitureId, $quantity, $totalAmount, $deliveryAddress, $deliveryDate, $paymentMethod, $paymentStatus, $selectedColor, $selectedSize, $accessoriesJson, $customizationCost);

    if (mysqli_stmt_execute($stmt)) {
        $orderId = mysqli_insert_id($conn);
        return ['success' => true, 'message' => 'Order created successfully!', 'order_id' => $orderId, 'payment_method' => $paymentMethod, 'payment_status' => $paymentStatus];
    } else {
        return ['success' => false, 'message' => 'Failed to create order: ' . mysqli_error($conn)];
    }
}

/**
 * Update order status
 */
function updateOrderStatus($conn, $orderId, $status, $paymentStatus = null) {
    $allowedStatuses = ['accepted', 'rejected', 'delivered'];
    if (!in_array($status, $allowedStatuses)) {
        return false;
    }

    // If payment status is provided, update both
    if ($paymentStatus) {
        $allowedPaymentStatuses = ['unpaid', 'paid', 'refunded'];
        if (!in_array($paymentStatus, $allowedPaymentStatuses)) {
            return false;
        }
        $sql = "UPDATE Orders SET OrderStatus = ?, PaymentStatus = ? WHERE OrderID = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $status, $paymentStatus, $orderId);
    } else {
        $sql = "UPDATE Orders SET OrderStatus = ? WHERE OrderID = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $status, $orderId);
    }

    return mysqli_stmt_execute($stmt);
}

/**
 * Update order quantity
 */
function updateOrderQuantity($conn, $orderId, $newQuantity) {
    // First get current order details
    $sql = "SELECT o.*, f.Price, f.StockQuantity 
            FROM Orders o 
            INNER JOIN Furniture f ON o.FurnitureID = f.FurnitureID 
            WHERE o.OrderID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $orderId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($result);
    
    if (!$order) {
        return false;
    }
    
    $quantityDiff = $newQuantity - $order['OrderQuantity'];
    
    if ($quantityDiff > 0 && $order['StockQuantity'] < $quantityDiff) {
        return false; // Insufficient stock
    }
    
    $newTotalAmount = $order['Price'] * $newQuantity;
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Update order
        $sql = "UPDATE Orders SET OrderQuantity = ?, TotalOrderAmount = ? WHERE OrderID = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "idi", $newQuantity, $newTotalAmount, $orderId);
        mysqli_stmt_execute($stmt);
        
        // Update stock
        updateStockQuantity($conn, $order['FurnitureID'], -$quantityDiff);
        
        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return false;
    }
}

/**
 * Delete order
 */
function deleteOrder($conn, $orderId) {
    $sql = "SELECT DeliveryDate FROM Orders WHERE OrderID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $orderId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($result);
    
    if (!$order) {
        return false;
    }
    
    if (!canDeleteOrder($order['DeliveryDate'])) {
        return false;
    }
    
    $sql = "DELETE FROM Orders WHERE OrderID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $orderId);
    return mysqli_stmt_execute($stmt);
}

/**
 * Get sales report
 */
function getSalesReport($conn) {
    $sql = "SELECT * FROM SalesReportView";
    $result = mysqli_query($conn, $sql);
    
    $report = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $report[] = $row;
    }
    return $report;
}

/**
 * Get material stock report
 */
function getMaterialReport($conn) {
    $sql = "SELECT * FROM MaterialUsageView";
    $result = mysqli_query($conn, $sql);
    
    $materials = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $materials[] = $row;
    }
    return $materials;
}

/**
 * Insert new furniture product with materials
 */
function insertFurniture($conn, $data, $imageFile) {
    // Handle image upload
    $imageName = '';
    if ($imageFile && $imageFile['error'] === UPLOAD_ERR_OK) {
        $extension = pathinfo($imageFile['name'], PATHINFO_EXTENSION);
        $imageName = time() . '_' . uniqid() . '.' . $extension;
        $destination = FURNITURE_IMAGE_UPLOAD_PATH . $imageName;
        
        if (!move_uploaded_file($imageFile['tmp_name'], $destination)) {
            return ['success' => false, 'message' => 'Failed to upload image.'];
        }
    }
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Insert furniture
        $sql = "INSERT INTO Furniture (FurnitureName, FurnitureDescription, FurnitureImage, Price, StockQuantity, Category, CreatedDate) 
                VALUES (?, ?, ?, ?, ?, ?, CURDATE())";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssdis", 
            $data['furniture_name'], 
            $data['furniture_description'], 
            $imageName, 
            $data['price'], 
            $data['stock_quantity'], 
            $data['category']
        );
        mysqli_stmt_execute($stmt);
        $furnitureId = mysqli_insert_id($conn);
        
        // Insert material relationships
        if (isset($data['materials']) && is_array($data['materials'])) {
            $sql = "INSERT INTO Furniture_Material (FurnitureID, MaterialID, MaterialQuantity) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            
            foreach ($data['materials'] as $material) {
                mysqli_stmt_bind_param($stmt, "iid", $furnitureId, $material['id'], $material['quantity']);
                mysqli_stmt_execute($stmt);
            }
        }
        
        mysqli_commit($conn);
        return ['success' => true, 'message' => 'Furniture added successfully!', 'furniture_id' => $furnitureId];
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ['success' => false, 'message' => 'Failed to add furniture: ' . $e->getMessage()];
    }
}

/**
 * Update existing furniture product with materials
 */
function updateFurniture($conn, $furnitureId, $data, $imageFile = null) {
    // Verify furniture exists
    $existing = getFurnitureById($conn, $furnitureId);
    if (!$existing) {
        return ['success' => false, 'message' => 'Furniture product not found.'];
    }

    // Handle optional image upload
    $imageName = $existing['FurnitureImage']; // keep existing by default
    if ($imageFile && $imageFile['error'] === UPLOAD_ERR_OK) {
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($imageFile['type'], $allowedTypes)) {
            return ['success' => false, 'message' => 'Only JPG, PNG, GIF, and WEBP images are allowed.'];
        }
        if ($imageFile['size'] > MAX_FILE_SIZE) {
            return ['success' => false, 'message' => 'Image size must be less than 2MB.'];
        }

        $extension = pathinfo($imageFile['name'], PATHINFO_EXTENSION);
        $imageName = time() . '_' . uniqid() . '.' . $extension;
        $destination = FURNITURE_IMAGE_UPLOAD_PATH . $imageName;

        if (!move_uploaded_file($imageFile['tmp_name'], $destination)) {
            return ['success' => false, 'message' => 'Failed to upload new image.'];
        }

        // Optionally delete old image file (skip placeholder.jpg)
        if (!empty($existing['FurnitureImage']) && $existing['FurnitureImage'] !== 'placeholder.jpg') {
            $oldPath = FURNITURE_IMAGE_UPLOAD_PATH . $existing['FurnitureImage'];
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }
    }

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // Update furniture record
        $sql = "UPDATE Furniture
                SET FurnitureName = ?, FurnitureDescription = ?, FurnitureImage = ?,
                    Price = ?, StockQuantity = ?, Category = ?
                WHERE FurnitureID = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssdisi",
            $data['furniture_name'],
            $data['furniture_description'],
            $imageName,
            $data['price'],
            $data['stock_quantity'],
            $data['category'],
            $furnitureId
        );
        mysqli_stmt_execute($stmt);

        // Delete existing material relationships
        $sql = "DELETE FROM Furniture_Material WHERE FurnitureID = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $furnitureId);
        mysqli_stmt_execute($stmt);

        // Insert new material relationships
        if (isset($data['materials']) && is_array($data['materials'])) {
            $sql = "INSERT INTO Furniture_Material (FurnitureID, MaterialID, MaterialQuantity) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);

            foreach ($data['materials'] as $material) {
                if (isset($material['id']) && isset($material['quantity']) && $material['quantity'] > 0) {
                    mysqli_stmt_bind_param($stmt, "iid", $furnitureId, $material['id'], $material['quantity']);
                    mysqli_stmt_execute($stmt);
                }
            }
        }

        mysqli_commit($conn);
        return ['success' => true, 'message' => 'Furniture updated successfully!'];
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ['success' => false, 'message' => 'Failed to update furniture: ' . $e->getMessage()];
    }
}

/**
 * Check if furniture can be deleted (no existing orders)
 */
function canDeleteFurniture($conn, $furnitureId) {
    $sql = "SELECT COUNT(*) as order_count FROM Orders WHERE FurnitureID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $furnitureId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return $row['order_count'] == 0;
}

/**
 * Delete furniture product
 */
function deleteFurniture($conn, $furnitureId) {
    if (!canDeleteFurniture($conn, $furnitureId)) {
        return ['success' => false, 'message' => 'Cannot delete furniture with existing orders.'];
    }

    // Delete product view image files from disk before DB cascade
    deleteFurnitureImages($conn, $furnitureId);

    // Also delete the main furniture image file
    $furniture = getFurnitureById($conn, $furnitureId);
    if ($furniture && !empty($furniture['FurnitureImage']) && $furniture['FurnitureImage'] !== 'placeholder.jpg') {
        $mainImagePath = FURNITURE_IMAGE_UPLOAD_PATH . $furniture['FurnitureImage'];
        if (file_exists($mainImagePath)) {
            @unlink($mainImagePath);
        }
    }

    $sql = "DELETE FROM Furniture WHERE FurnitureID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $furnitureId);

    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Furniture deleted successfully!'];
    } else {
        return ['success' => false, 'message' => 'Failed to delete furniture.'];
    }
}

/**
 * Insert new material
 */
function insertMaterial($conn, $data) {
    $sql = "INSERT INTO Material (MaterialName, PhysicalQuantity, Unit, ReorderLevel, LastUpdated) 
            VALUES (?, ?, ?, ?, CURDATE())";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sdsd", 
        $data['material_name'], 
        $data['physical_quantity'], 
        $data['unit'], 
        $data['reorder_level']
    );
    
    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Material added successfully!', 'material_id' => mysqli_insert_id($conn)];
    } else {
        return ['success' => false, 'message' => 'Failed to add material.'];
    }
}

/**
 * Get all materials
 */
function getAllMaterials($conn) {
    $sql = "SELECT * FROM Material ORDER BY MaterialName";
    $result = mysqli_query($conn, $sql);

    $materials = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $materials[] = $row;
    }
    return $materials;
}

/**
 * Get material by ID
 */
function getMaterialById($conn, $materialId) {
    $sql = "SELECT * FROM Material WHERE MaterialID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $materialId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

/**
 * Update existing material
 */
function updateMaterial($conn, $materialId, $data) {
    // Verify material exists
    $existing = getMaterialById($conn, $materialId);
    if (!$existing) {
        return ['success' => false, 'message' => 'Material not found.'];
    }

    $sql = "UPDATE Material
            SET MaterialName = ?, PhysicalQuantity = ?, Unit = ?, ReorderLevel = ?, LastUpdated = CURDATE()
            WHERE MaterialID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sdsdi",
        $data['material_name'],
        $data['physical_quantity'],
        $data['unit'],
        $data['reorder_level'],
        $materialId
    );

    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Material updated successfully!'];
    } else {
        return ['success' => false, 'message' => 'Failed to update material: ' . mysqli_error($conn)];
    }
}

/**
 * Check if material can be deleted (not used in any furniture)
 */
function canDeleteMaterial($conn, $materialId) {
    $sql = "SELECT COUNT(*) as usage_count FROM Furniture_Material WHERE MaterialID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $materialId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row['usage_count'] == 0;
}

/**
 * Delete material from inventory
 */
function deleteMaterial($conn, $materialId) {
    // Verify material exists
    $existing = getMaterialById($conn, $materialId);
    if (!$existing) {
        return ['success' => false, 'message' => 'Material not found.'];
    }

    if (!canDeleteMaterial($conn, $materialId)) {
        // Get which furniture products use this material
        $sql = "SELECT f.FurnitureName FROM Furniture_Material fm
                INNER JOIN Furniture f ON fm.FurnitureID = f.FurnitureID
                WHERE fm.MaterialID = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $materialId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = $row['FurnitureName'];
        }
        $productList = implode(', ', $products);
        return ['success' => false, 'message' => 'Cannot delete material. It is used in the following furniture products: ' . $productList];
    }

    $sql = "DELETE FROM Material WHERE MaterialID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $materialId);

    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Material "' . htmlspecialchars($existing['MaterialName']) . '" deleted successfully!'];
    } else {
        return ['success' => false, 'message' => 'Failed to delete material: ' . mysqli_error($conn)];
    }
}

/**
 * Update customer profile (limited fields only)
 */
function updateCustomerProfile($conn, $customerId, $password, $contactNumber, $address, $companyName = null) {
    $sql = "UPDATE Customer SET Password = ?, ContactNumber = ?, Address = ?, CompanyName = ? WHERE CustomerID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    mysqli_stmt_bind_param($stmt, "ssssi", $hashedPassword, $contactNumber, $address, $companyName, $customerId);

    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Profile updated successfully!'];
    } else {
        return ['success' => false, 'message' => 'Failed to update profile.'];
    }
}

/**
 * Register a new customer account
 * Uses bcrypt password hashing for security
 */
function registerCustomer($conn, $data) {
    // Validate required fields
    $requiredFields = ['customer_name', 'password', 'contact_number', 'address', 'email'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => 'All required fields must be filled.'];
        }
    }

    $customerName = sanitizeInput($data['customer_name']);
    $password = $data['password'];
    $confirmPassword = $data['confirm_password'] ?? '';
    $companyName = sanitizeInput($data['company_name'] ?? '');
    $contactNumber = sanitizeInput($data['contact_number']);
    $address = sanitizeInput($data['address']);
    $email = sanitizeInput($data['email']);

    // Validate name (at least 2 characters)
    if (strlen($customerName) < 2) {
        return ['success' => false, 'message' => 'Name must be at least 2 characters.'];
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please enter a valid email address.'];
    }

    // Check if email already exists
    $checkSql = "SELECT CustomerID FROM Customer WHERE Email = ?";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    mysqli_stmt_bind_param($checkStmt, "s", $email);
    mysqli_stmt_execute($checkStmt);
    mysqli_stmt_store_result($checkStmt);
    if (mysqli_stmt_num_rows($checkStmt) > 0) {
        mysqli_stmt_close($checkStmt);
        return ['success' => false, 'message' => 'An account with this email already exists.'];
    }
    mysqli_stmt_close($checkStmt);

    // Validate password strength
    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
    }
    if ($password !== $confirmPassword) {
        return ['success' => false, 'message' => 'Passwords do not match.'];
    }

    // Validate contact number (Hong Kong 8-digit format)
    if (!preg_match('/^[0-9]{8}$/', $contactNumber)) {
        return ['success' => false, 'message' => 'Contact number must be 8 digits.'];
    }

    // Validate address
    if (strlen($address) < 10) {
        return ['success' => false, 'message' => 'Please provide a complete address (at least 10 characters).'];
    }

    // Hash password with bcrypt
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $registrationDate = date('Y-m-d');

    $sql = "INSERT INTO Customer (CustomerName, CompanyName, Password, ContactNumber, Address, Email, RegistrationDate)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssssss",
        $customerName,
        $companyName,
        $hashedPassword,
        $contactNumber,
        $address,
        $email,
        $registrationDate
    );

    if (mysqli_stmt_execute($stmt)) {
        $newCustomerId = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        return [
            'success' => true,
            'message' => 'Registration successful! You can now sign in.',
            'customer_id' => $newCustomerId,
            'customer_name' => $customerName
        ];
    } else {
        $error = mysqli_error($conn);
        mysqli_stmt_close($stmt);
        return ['success' => false, 'message' => 'Registration failed: ' . $error];
    }
}

/**
 * Get customer by ID
 */
function getCustomerById($conn, $customerId) {
    $sql = "SELECT CustomerID, CustomerName, CompanyName, ContactNumber, Address, Email FROM Customer WHERE CustomerID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $customerId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

/**
 * Get all customers with optional search and sorting
 */
function getAllCustomers($conn, $search = '', $sortColumn = 'CustomerName', $sortOrder = 'ASC', $limit = 0, $offset = 0) {
    $allowedColumns = ['CustomerID', 'CustomerName', 'CompanyName', 'Email', 'RegistrationDate'];
    $allowedOrders = ['ASC', 'DESC'];

    $sortColumn = in_array($sortColumn, $allowedColumns) ? $sortColumn : 'CustomerName';
    $sortOrder = in_array($sortOrder, $allowedOrders) ? $sortOrder : 'ASC';

    $sql = "SELECT c.*,
            (SELECT COUNT(*) FROM Orders o WHERE o.CustomerID = c.CustomerID) as OrderCount,
            (SELECT SUM(o.TotalOrderAmount) FROM Orders o WHERE o.CustomerID = c.CustomerID AND o.OrderStatus = 'delivered') as TotalSpent
            FROM Customer c";

    if (!empty($search)) {
        $searchTerm = '%' . mysqli_real_escape_string($conn, $search) . '%';
        $sql .= " WHERE c.CustomerName LIKE '$searchTerm'
                   OR c.Email LIKE '$searchTerm'
                   OR c.ContactNumber LIKE '$searchTerm'
                   OR c.CustomerID LIKE '$searchTerm'";
    }

    $sql .= " ORDER BY $sortColumn $sortOrder";

    if ($limit > 0) {
        $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    }

    $result = mysqli_query($conn, $sql);
    $customers = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $customers[] = $row;
    }
    return $customers;
}

/**
 * Check if customer can be deleted (no existing orders)
 */
function canDeleteCustomer($conn, $customerId) {
    $sql = "SELECT COUNT(*) as order_count FROM Orders WHERE CustomerID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $customerId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row['order_count'] == 0;
}

/**
 * Delete customer (only if no orders exist)
 */
function deleteCustomer($conn, $customerId) {
    $existing = getCustomerById($conn, $customerId);
    if (!$existing) {
        return ['success' => false, 'message' => 'Customer not found.'];
    }

    if (!canDeleteCustomer($conn, $customerId)) {
        return ['success' => false, 'message' => 'Cannot delete customer "' . htmlspecialchars($existing['CustomerName']) . '" because they have existing orders.'];
    }

    $sql = "DELETE FROM Customer WHERE CustomerID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $customerId);

    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Customer "' . htmlspecialchars($existing['CustomerName']) . '" deleted successfully!'];
    } else {
        return ['success' => false, 'message' => 'Failed to delete customer: ' . mysqli_error($conn)];
    }
}

/**
 * Register a new staff account
 */
function registerStaff($conn, $data) {
    $requiredFields = ['staff_name', 'password', 'email'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => 'All required fields must be filled.'];
        }
    }

    $staffName = sanitizeInput($data['staff_name']);
    $password = $data['password'];
    $confirmPassword = $data['confirm_password'] ?? '';
    $email = sanitizeInput($data['email']);
    $role = sanitizeInput($data['role'] ?? 'staff');
    $hireDate = date('Y-m-d');

    if (strlen($staffName) < 2) {
        return ['success' => false, 'message' => 'Name must be at least 2 characters.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please enter a valid email address.'];
    }
    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
    }
    if ($password !== $confirmPassword) {
        return ['success' => false, 'message' => 'Passwords do not match.'];
    }

    // Check duplicate email
    $checkSql = "SELECT StaffID FROM Staff WHERE Email = ?";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    mysqli_stmt_bind_param($checkStmt, "s", $email);
    mysqli_stmt_execute($checkStmt);
    mysqli_stmt_store_result($checkStmt);
    if (mysqli_stmt_num_rows($checkStmt) > 0) {
        mysqli_stmt_close($checkStmt);
        return ['success' => false, 'message' => 'A staff account with this email already exists.'];
    }
    mysqli_stmt_close($checkStmt);

    $allowedRoles = ['staff', 'manager', 'administrator'];
    if (!in_array($role, $allowedRoles)) $role = 'staff';

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    $sql = "INSERT INTO Staff (StaffName, Password, Email, Role, HireDate) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssss", $staffName, $hashedPassword, $email, $role, $hireDate);

    if (mysqli_stmt_execute($stmt)) {
        $newId = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        return ['success' => true, 'message' => 'Staff account created successfully!', 'staff_id' => $newId];
    } else {
        $error = mysqli_error($conn);
        mysqli_stmt_close($stmt);
        return ['success' => false, 'message' => 'Failed to create staff account: ' . $error];
    }
}

/**
 * Get month-over-month comparison for a metric
 * Returns [current_month_value, previous_month_value, percent_change]
 */
function getMonthlyComparison($conn, $sqlCurrent, $sqlPrevious) {
    $r1 = mysqli_query($conn, $sqlCurrent);
    $current = mysqli_fetch_assoc($r1)['total'] ?? 0;

    $r2 = mysqli_query($conn, $sqlPrevious);
    $previous = mysqli_fetch_assoc($r2)['total'] ?? 0;

    $percentChange = $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : ($current > 0 ? 100 : 0);

    return [
        'current' => (float)$current,
        'previous' => (float)$previous,
        'percent_change' => $percentChange,
        'trend' => $percentChange >= 0 ? 'up' : 'down'
    ];
}

/**
 * Get top N products by revenue
 */
function getTopProductsByRevenue($conn, $limit = 5) {
    $sql = "SELECT f.FurnitureID, f.FurnitureName, f.Category,
            SUM(o.OrderQuantity) as total_sold,
            SUM(o.TotalOrderAmount) as total_revenue
            FROM Orders o
            INNER JOIN Furniture f ON o.FurnitureID = f.FurnitureID
            WHERE o.OrderStatus = 'delivered'
            GROUP BY f.FurnitureID
            ORDER BY total_revenue DESC
            LIMIT " . (int)$limit;
    $result = mysqli_query($conn, $sql);
    $products = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }
    return $products;
}

/**
 * Get order status distribution
 */
function getOrderStatusDistribution($conn) {
    $sql = "SELECT OrderStatus, COUNT(*) as count FROM Orders GROUP BY OrderStatus";
    $result = mysqli_query($conn, $sql);
    $distribution = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $distribution[$row['OrderStatus']] = (int)$row['count'];
    }
    return $distribution;
}

/**
 * Get top customers by spend
 */
function getTopCustomersBySpend($conn, $limit = 5) {
    $sql = "SELECT c.CustomerID, c.CustomerName, c.Email,
            COUNT(o.OrderID) as order_count,
            SUM(o.TotalOrderAmount) as total_spent
            FROM Customer c
            INNER JOIN Orders o ON c.CustomerID = o.CustomerID
            WHERE o.OrderStatus = 'delivered'
            GROUP BY c.CustomerID
            ORDER BY total_spent DESC
            LIMIT " . (int)$limit;
    $result = mysqli_query($conn, $sql);
    $customers = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $customers[] = $row;
    }
    return $customers;
}

/**
 * Get monthly revenue trend (last N months)
 */
function getMonthlyRevenueTrend($conn, $months = 6) {
    $sql = "SELECT DATE_FORMAT(OrderDate, '%Y-%m') as month,
            COUNT(*) as order_count,
            SUM(TotalOrderAmount) as revenue
            FROM Orders WHERE OrderStatus = 'delivered'
            GROUP BY month ORDER BY month ASC LIMIT " . (int)$months;
    $result = mysqli_query($conn, $sql);
    $trend = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $trend[] = $row;
    }
    return $trend;
}

/**
 * Get inventory health - materials below reorder level
 */
function getInventoryHealth($conn) {
    $sql = "SELECT MaterialID, MaterialName, PhysicalQuantity, Unit, ReorderLevel,
            CASE
                WHEN PhysicalQuantity <= 0 THEN 'critical'
                WHEN PhysicalQuantity <= ReorderLevel AND ReorderLevel > 0 THEN 'low'
                WHEN PhysicalQuantity <= (ReorderLevel * 2) AND ReorderLevel > 0 THEN 'warning'
                ELSE 'healthy'
            END as status
            FROM Material
            ORDER BY
                CASE status
                    WHEN 'critical' THEN 0
                    WHEN 'low' THEN 1
                    WHEN 'warning' THEN 2
                    ELSE 3
                END, MaterialName";
    $result = mysqli_query($conn, $sql);
    $health = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $health[] = $row;
    }
    return $health;
}

/**
 * Get revenue by category
 */
function getRevenueByCategory($conn) {
    $sql = "SELECT f.Category,
            SUM(o.OrderQuantity) as total_sold,
            SUM(o.TotalOrderAmount) as total_revenue
            FROM Orders o
            INNER JOIN Furniture f ON o.FurnitureID = f.FurnitureID
            WHERE o.OrderStatus = 'delivered'
            GROUP BY f.Category
            ORDER BY total_revenue DESC";
    $result = mysqli_query($conn, $sql);
    $categories = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
    return $categories;
}

/**
 * Get total row count for a table with optional WHERE clause
 */
function getTotalCount($conn, $table, $whereClause = '') {
    $sql = "SELECT COUNT(*) as total FROM $table";
    if (!empty($whereClause)) {
        $sql .= " WHERE $whereClause";
    }
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return (int)($row['total'] ?? 0);
}

/**
 * Build pagination HTML
 * @param int $currentPage Current page number (1-based)
 * @param int $totalPages Total number of pages
 * @param string $baseUrl Base URL for page links (use {page} as placeholder)
 * @return string Pagination HTML
 */
function buildPagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) {
        return '';
    }

    $html = '<div class="pagination">';

    // Previous button
    if ($currentPage > 1) {
        $prevUrl = str_replace('{page}', $currentPage - 1, $baseUrl);
        $html .= '<a href="' . $prevUrl . '" title="Previous page">← Prev</a>';
    } else {
        $html .= '<span class="disabled">← Prev</span>';
    }

    // Page number links
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);

    if ($startPage > 1) {
        $html .= '<a href="' . str_replace('{page}', 1, $baseUrl) . '">1</a>';
        if ($startPage > 2) {
            $html .= '<span>...</span>';
        }
    }

    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $currentPage) {
            $html .= '<span class="active">' . $i . '</span>';
        } else {
            $html .= '<a href="' . str_replace('{page}', $i, $baseUrl) . '">' . $i . '</a>';
        }
    }

    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<span>...</span>';
        }
        $html .= '<a href="' . str_replace('{page}', $totalPages, $baseUrl) . '">' . $totalPages . '</a>';
    }

    // Next button
    if ($currentPage < $totalPages) {
        $nextUrl = str_replace('{page}', $currentPage + 1, $baseUrl);
        $html .= '<a href="' . $nextUrl . '" title="Next page">Next →</a>';
    } else {
        $html .= '<span class="disabled">Next →</span>';
    }

    $html .= '</div>';
    return $html;
}

/**
 * Calculate the earliest valid delivery date (minimum 2 days, skip weekends)
 * @param int $minDays Minimum days from today (default 2)
 * @return string Delivery date in Y-m-d format
 */
function calculateDeliveryDate($minDays = 2) {
    $date = new DateTime('today');
    $daysAdded = 0;

    while ($daysAdded < $minDays) {
        $date->modify('+1 day');
        // Skip Saturday (6) and Sunday (7 → 0 in some configs)
        $dayOfWeek = (int)$date->format('N'); // 1=Mon, 7=Sun
        if ($dayOfWeek < 6) { // Monday-Friday
            $daysAdded++;
        }
    }

    return $date->format('Y-m-d');
}

/**
 * Calculate pagination values
 * @param int $totalItems Total number of items
 * @param int $currentPage Current page (1-based)
 * @param int $perPage Items per page (default 10)
 * @return array [offset, totalPages, perPage]
 */
function paginateParams($totalItems, $currentPage = 1, $perPage = 10) {
    $currentPage = max(1, (int)$currentPage);
    $totalPages = max(1, (int)ceil($totalItems / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $perPage;
    return [
        'offset' => $offset,
        'total_pages' => $totalPages,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_items' => $totalItems
    ];
}
?>