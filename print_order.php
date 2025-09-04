<?php
require_once 'includes/auth.php';
require_login();
require_once 'includes/config.php';

$activePage = 'order';

// Get order ID from URL
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    die('Invalid order ID');
}

try {
    // Fetch order details
    $stmt = $pdo->prepare("
        SELECT o.*, c.name AS customer_name, c.mobile, c.address, c.email
        FROM orders o 
        LEFT JOIN customer c ON o.customer_id = c.id 
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        die('Order not found');
    }
    
    // Fetch order items
    $stmt = $pdo->prepare("
        SELECT oi.*, p.product_name, p.product_unit
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die('Error fetching order: ' . $e->getMessage());
}

// Set page title
$page_title = "Order #" . ($order['order_no'] ?? 'ORD-' . str_pad($order['id'], 3, '0', STR_PAD_LEFT));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Invoice</title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { 
                margin: 0; 
                padding: 0; 
                font-size: 11px; 
                background: white !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .invoice-container { 
                max-width: 100% !important; 
                border: 2px solid #000 !important;
                box-shadow: none !important;
                margin: 5px !important;
                padding: 15px !important;
            }
            .header {
                border-bottom: 2px dashed #000 !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .footer {
                border-top: 2px dashed #000 !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .order-info {
                border-bottom: 1px dashed #000 !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .customer-info {
                border-bottom: 1px dashed #000 !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .item-header {
                border-bottom: 1px solid #000 !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .item-row {
                border-bottom: 1px dotted #ccc !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .totals {
                border-top: 2px solid #000 !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .total-final {
                border-top: 1px solid #000 !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .status-badge {
                border: 1px solid #000 !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            * {
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
        }
        
        body {
            font-family: 'Courier New', monospace;
            margin: 0;
            padding: 20px;
            background: #fff;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .invoice-container {
            max-width: 400px;
            margin: 0 auto;
            background: white;
            border: 2px solid #000;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        
        .company-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .company-info {
            font-size: 12px;
            margin-bottom: 3px;
        }
        
        .invoice-title {
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            margin: 15px 0;
            text-decoration: underline;
        }
        
        .order-info {
            margin-bottom: 15px;
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
        }
        
        .customer-info {
            margin-bottom: 15px;
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
        }
        
        .items-section {
            margin-bottom: 15px;
        }
        
        .item-header {
            border-bottom: 1px solid #000;
            font-weight: bold;
            padding: 5px 0;
            display: flex;
            justify-content: space-between;
        }
        
        .item-row {
            padding: 3px 0;
            border-bottom: 1px dotted #ccc;
            display: flex;
            justify-content: space-between;
        }
        
        .item-desc {
            flex: 1;
            padding-right: 10px;
        }
        
        .item-qty {
            width: 40px;
            text-align: center;
        }
        
        .item-price {
            width: 80px;
            text-align: right;
        }
        
        .totals {
            border-top: 2px solid #000;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .total-final {
            font-weight: bold;
            font-size: 16px;
            border-top: 1px solid #000;
            padding-top: 5px;
            margin-top: 5px;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            border-top: 2px dashed #000;
            padding-top: 15px;
            font-size: 12px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border: 1px solid #000;
            margin: 5px 0;
        }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .small { font-size: 11px; }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="header">
            <div class="company-name">WASIM</div>
            <div class="company-info">Professional Tailoring Services</div>
            <div class="company-info">Address shop #1 hameed plaza</div>
            <div class="company-info">main university road Pakistan</div>
            <div class="company-info">Phone: +92 323 9507813</div>
            <div class="company-info">Email: info@tailorshop.com</div>
        </div>
        
        <div class="invoice-title">INVOICE</div>
        
        <!-- Order Information -->
        <div class="order-info">
            <div><strong>Invoice No:</strong> <?= 'Invo-' . str_pad($order['id'], 2, '0', STR_PAD_LEFT) ?></div>
            <div><strong>Date:</strong> <?= htmlspecialchars(date('d/m/Y', strtotime($order['order_date']))) ?></div>
            <div><strong>Time:</strong> <?= date('H:i:s', strtotime($order['order_date'])) ?></div>
            <?php if ($order['delivery_date'] && $order['delivery_date'] != '0000-00-00' && $order['delivery_date'] != '0000-00-00 00:00:00'): ?>
            <div><strong>Delivery:</strong> <?= htmlspecialchars(date('d/m/Y', strtotime($order['delivery_date']))) ?></div>
            <?php else: ?>
            <div><strong>Delivery:</strong> N/A</div>
            <?php endif; ?>
            <div class="text-center">
                <span class="status-badge"><?= htmlspecialchars($order['status'] ?? 'Pending') ?></span>
            </div>
        </div>
        
        <!-- Customer Information -->
        <div class="customer-info">
            <div class="bold">CUSTOMER DETAILS:</div>
            <div><strong>Name:</strong> <?= htmlspecialchars($order['customer_name'] ?? 'Walk-in Customer') ?></div>
            <?php if ($order['mobile']): ?>
            <div><strong>Mobile:</strong> <?= htmlspecialchars($order['mobile']) ?></div>
            <?php endif; ?>
            <?php if ($order['address']): ?>
            <div><strong>Address:</strong> <?= htmlspecialchars($order['address']) ?></div>
            <?php endif; ?>
        </div>
        
        <!-- Order Items -->
        <div class="items-section">
            <div class="bold">ORDER ITEMS:</div>
            <div class="item-header">
                <span style="flex: 1;">ITEM</span>
                <span style="width: 40px; text-align: center;">QTY</span>
                <span style="width: 80px; text-align: right;">AMOUNT</span>
            </div>
            <?php if (!empty($order_items)): ?>
                <?php foreach ($order_items as $item): ?>
                <div class="item-row">
                    <div class="item-desc">
                        <?= htmlspecialchars($item['description'] ?? 'Service') ?>
                        <div class="small">@ PKR <?= number_format($item['unit_price'], 2) ?> each</div>
                    </div>
                    <div class="item-qty"><?= $item['quantity'] ?></div>
                    <div class="item-price">PKR <?= number_format($item['total_price'], 2) ?></div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="item-row">
                    <div class="item-desc">No items found</div>
                    <div class="item-qty">-</div>
                    <div class="item-price">PKR 0.00</div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Totals -->
        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>PKR <?= number_format($order['sub_total'], 2) ?></span>
            </div>
            <?php if ($order['discount'] > 0): ?>
            <div class="total-row">
                <span>Discount:</span>
                <span>-PKR <?= number_format($order['discount'], 2) ?></span>
            </div>
            <?php endif; ?>
            <div class="total-row total-final">
                <span>TOTAL:</span>
                <span>PKR <?= number_format($order['total_amount'], 2) ?></span>
            </div>
            <div class="total-row">
                <span>Paid:</span>
                <span>PKR <?= number_format($order['paid_amount'], 2) ?></span>
            </div>
            <div class="total-row bold">
                <span>Balance Due:</span>
                <span>PKR <?= number_format($order['remaining_amount'], 2) ?></span>
            </div>
        </div>
        
        <?php if ($order['details']): ?>
        <!-- Notes -->
        <div style="margin: 15px 0; padding: 10px 0; border-top: 1px dashed #000;">
            <div class="bold">NOTES:</div>
            <div class="small"><?= nl2br(htmlspecialchars($order['details'])) ?></div>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="footer">
            <div class="bold">Thank you for your business!</div>
            <div class="small">Visit us again for quality tailoring services</div>
            <div class="small">Generated: <?= date('d/m/Y H:i') ?></div>
            <div style="margin-top: 10px; border-top: 1px solid #000; padding-top: 5px;">
                <div class="small">** KEEP THIS RECEIPT SAFE **</div>
            </div>
        </div>
    </div>
    
    <!-- Print Button -->
    <div class="no-print text-center mt-4 mb-4">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="bi bi-printer"></i> Print Order
        </button>
        <a href="order.php" class="btn btn-secondary ms-2">
            <i class="bi bi-arrow-left"></i> Back to Orders
        </a>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
