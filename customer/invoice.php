<?php
/**
 * invoice.php - Printable Invoice View
 * Part V: Clean invoice for printing/sharing
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';

checkCustomerRole();

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sql = "SELECT o.*, f.FurnitureName, f.Price, c.CustomerName, c.Email, c.ContactNumber
        FROM Orders o
        INNER JOIN Furniture f ON o.FurnitureID = f.FurnitureID
        INNER JOIN Customer c ON o.CustomerID = c.CustomerID
        WHERE o.OrderID = ? AND o.CustomerID = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $orderId, $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($result);

if (!$order) {
    $_SESSION['error'] = 'Invoice not found.';
    header('Location: view_orders.php');
    exit();
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?php echo $orderId; ?> - Premium Living</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #1f2937; padding: 40px; max-width: 800px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: start; border-bottom: 3px solid #1a3c2a; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { font-family: Georgia, serif; color: #1a3c2a; font-size: 28px; }
        .header .sub { color: #c8963e; font-size: 12px; text-transform: uppercase; letter-spacing: 2px; }
        .invoice-title { font-size: 32px; color: #c8963e; font-weight: 700; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
        .info-box h3 { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; margin-bottom: 8px; }
        .info-box p { font-size: 14px; line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; margin: 30px 0; }
        th { background: #1a3c2a; color: #fff; padding: 12px 16px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 12px 16px; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
        .totals { margin-left: auto; width: 300px; }
        .totals div { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; }
        .totals .grand { border-top: 2px solid #1a3c2a; padding-top: 12px; font-size: 18px; font-weight: 700; color: #1a3c2a; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 12px; color: #9ca3af; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-paid { background: #d1fae5; color: #059669; }
        .badge-unpaid { background: #fef3c7; color: #d97706; }
        @media print {
            body { padding: 20px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 24px; background: #1a3c2a; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 14px;">🖨️ Print Invoice</button>
        <a href="view_orders.php" style="margin-left: 10px; color: #6b7280; font-size: 14px;">← Back to Orders</a>
    </div>

    <div class="header">
        <div>
            <h1>Premium Living Furniture</h1>
            <div class="sub">Est. 1998 · Hong Kong</div>
            <p style="font-size: 13px; color: #6b7280; margin-top: 8px;">15 Canton Road, Tsim Sha Tsui<br>852-9123-4567 · info@premiumliving.com</p>
        </div>
        <div style="text-align: right;">
            <div class="invoice-title">INVOICE</div>
            <p style="font-size: 14px; color: #6b7280;">#<?php echo $orderId; ?></p>
            <p style="font-size: 12px; color: #9ca3af;">Date: <?php echo date('d/m/Y', strtotime($order['OrderDate'])); ?></p>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <h3>Bill To</h3>
            <p><strong><?php echo htmlspecialchars($order['CustomerName']); ?></strong><br>
            <?php echo htmlspecialchars($order['DeliveryAddress']); ?><br>
            <?php echo htmlspecialchars($order['Email']); ?><br>
            <?php echo htmlspecialchars($order['ContactNumber']); ?></p>
        </div>
        <div class="info-box">
            <h3>Order Details</h3>
            <p>Delivery Date: <strong><?php echo date('d/m/Y', strtotime($order['DeliveryDate'])); ?></strong><br>
            Status: <?php echo ucfirst($order['OrderStatus']); ?><br>
            Payment: <?php echo ucfirst(str_replace('_', ' ', $order['PaymentMethod'])); ?>
            <span class="badge <?php echo $order['PaymentStatus'] === 'paid' ? 'badge-paid' : 'badge-unpaid'; ?>"><?php echo ucfirst($order['PaymentStatus']); ?></span></p>
        </div>
    </div>

    <table>
        <thead><tr><th>Item</th><th>Unit Price</th><th>Qty</th><th>Total</th></tr></thead>
        <tbody>
            <tr>
                <td><strong><?php echo htmlspecialchars($order['FurnitureName']); ?></strong></td>
                <td><?php echo formatCurrency($order['Price']); ?></td>
                <td><?php echo $order['OrderQuantity']; ?></td>
                <td><strong><?php echo formatCurrency($order['TotalOrderAmount']); ?></strong></td>
            </tr>
        </tbody>
    </table>

    <div class="totals">
        <div><span>Subtotal</span><span><?php echo formatCurrency($order['TotalOrderAmount']); ?></span></div>
        <div><span>Delivery</span><span>Free</span></div>
        <div class="grand"><span>Total</span><span><?php echo formatCurrency($order['TotalOrderAmount']); ?></span></div>
    </div>

    <div class="footer">
        <p>Thank you for your business! · Premium Living Furniture Co. Ltd.</p>
        <p>Invoice #<?php echo $orderId; ?> · Generated <?php echo date('d/m/Y H:i'); ?></p>
    </div>
</body>
</html>