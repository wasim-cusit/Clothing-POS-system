<?php
require_once 'includes/auth.php';
require_login();
require_once 'includes/config.php';
require_once 'includes/settings.php';

$sale_id = intval($_GET['id'] ?? 0);
if (!$sale_id) {
    header("Location: sales.php");
    exit;
}

// Fetch sale details
$stmt = $pdo->prepare("
    SELECT s.*, COALESCE(c.name, s.walk_in_cust_name) AS customer_name, c.mobile AS customer_contact, c.address AS customer_address, c.email AS customer_email,
           u.username AS created_by_name
    FROM sale s
    LEFT JOIN customer c ON s.customer_id = c.id
    LEFT JOIN system_users u ON s.created_by = u.id
    WHERE s.id = ?
");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    header("Location: sales.php");
    exit;
}

// Fetch sale items with product details
$stmt = $pdo->prepare("
    SELECT si.*, p.product_name, p.product_unit, c.category AS category_name, si.price AS unit_price
    FROM sale_items si
    LEFT JOIN products p ON si.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE si.sale_id = ?
    ORDER BY si.id
");
$stmt->execute([$sale_id]);
$sale_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper functions with fallbacks
function safe_format_currency($amount) {
    try {
        return format_currency($amount);
    } catch (Exception $e) {
        return 'PKR ' . number_format($amount, 2);
    }
}

function safe_format_date($date) {
    try {
        if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') return 'N/A';
        return format_date($date);
    } catch (Exception $e) {
        if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') return 'N/A';
        $timestamp = strtotime($date);
        return ($timestamp && $timestamp > 0) ? date('d/m/Y', $timestamp) : 'N/A';
    }
}

function safe_get_setting($key, $default = '') {
    try {
        return get_setting($key, $default);
    } catch (Exception $e) {
        return $default;
    }
}

function extractColorFromNotes($notes) {
    if (empty($notes)) {
        return 'N/A';
    }
    
    // Check if notes contain color information
    if (strpos($notes, 'Color:') === 0) {
        return trim(substr($notes, 6)); // Remove "Color: " prefix
    }
    
    return 'N/A';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Invoice - <?= htmlspecialchars($sale['sale_no']) ?></title>
    <style>
        * { 
            box-sizing: border-box; 
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Courier New', monospace;
            background: #f5f5f5;
            color: #000;
            line-height: 1.4;
            font-size: 12px;
        }
        
        .invoice-container {
            max-width: 400px;
            margin: 20px auto;
            background: white;
            border: 2px dashed #333;
            overflow: hidden;
        }
        
        /* Header Section */
        .invoice-header {
            background: #000;
            color: white;
            padding: 15px;
            text-align: center;
            border-bottom: 2px dashed #333;
        }
        
        .company-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
            letter-spacing: 1px;
        }
        
        .company-tagline {
            font-size: 10px;
            margin-bottom: 10px;
        }
        
        .invoice-badge {
            background: white;
            color: #000;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
            border: 1px solid #000;
        }
        
        /* Invoice Details Section */
        .invoice-details {
            padding: 15px;
            background: white;
        }
        
        .details-section {
            margin-bottom: 15px;
            border-bottom: 1px dotted #333;
            padding-bottom: 10px;
        }
        
        .section-title {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 8px;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
        }
        
        .detail-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 11px;
        }
        
        .detail-label {
            font-weight: bold;
        }
        
        .detail-value {
            text-align: right;
            max-width: 60%;
            word-wrap: break-word;
        }
        
        /* Items Table */
        .items-section {
            margin-bottom: 15px;
        }
        
        .items-header {
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 8px;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
        }
        
        .item-row {
            margin-bottom: 8px;
            border-bottom: 1px dotted #ccc;
            padding-bottom: 5px;
        }
        
        .item-name {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 2px;
        }
        
        .item-details {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            margin-bottom: 2px;
        }
        
        .item-total {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            font-weight: bold;
        }
        
        /* Summary Section */
        .summary-section {
            border-top: 2px solid #000;
            padding-top: 10px;
            margin-bottom: 15px;
        }
        
        .summary-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 11px;
        }
        
        .summary-line.total {
            border-top: 1px solid #000;
            border-bottom: 2px solid #000;
            padding: 5px 0;
            font-weight: bold;
            font-size: 12px;
        }
        
        .summary-line.due {
            font-weight: bold;
            font-size: 12px;
        }
        
        /* Footer */
        .invoice-footer {
            background: #000;
            color: white;
            padding: 10px;
            text-align: center;
            font-size: 10px;
            border-top: 2px dashed #333;
        }
        
        .footer-text {
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .footer-info {
            line-height: 1.3;
            margin-bottom: 5px;
        }
        
        .footer-note {
            font-size: 9px;
            margin-top: 8px;
            border-top: 1px dotted #666;
            padding-top: 5px;
        }
        
        /* Print Button */
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #000;
            color: white;
            border: 2px solid #333;
            padding: 10px 20px;
            cursor: pointer;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            font-weight: bold;
            z-index: 1000;
        }
        
        .print-button:hover {
            background: #333;
        }
        
        /* Responsive Design */
        @media (max-width: 480px) {
            .invoice-container {
                margin: 10px;
                max-width: 95%;
            }
        }
        
        @media print {
            .print-button { display: none !important; }
            body { 
                background: white !important; 
                margin: 0;
                padding: 0;
                font-size: 11px;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .invoice-container { 
                margin: 0 !important; 
                max-width: none !important;
                width: 100% !important;
                border: none !important;
                padding: 10px !important;
                box-shadow: none !important;
            }
            .invoice-header { 
                background: #000 !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
                border-bottom: 2px solid #000 !important;
            }
            .invoice-footer { 
                background: #000 !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
                border-top: 2px solid #000 !important;
            }
            .details-section {
                border-bottom: 1px solid #333 !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .section-title {
                border-bottom: 1px solid #000 !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .summary-section {
                border-top: 2px solid #000 !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .summary-line.total {
                border-top: 1px solid #000 !important;
                border-bottom: 2px solid #000 !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            * {
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">PRINT</button>
    
    <div class="invoice-container">
        <!-- Header -->
        <div class="invoice-header">
            <div class="company-name"><?= htmlspecialchars(safe_get_setting('company_name', 'WASIM')) ?></div>
            <div class="company-tagline"><?= htmlspecialchars(safe_get_setting('company_tagline', 'Professional Tailoring Services')) ?></div>
            <div class="invoice-badge">SALES INVOICE</div>
        </div>
        
        <!-- Invoice Details -->
        <div class="invoice-details">
            <!-- Company Info -->
            <div class="details-section">
                <div class="section-title">COMPANY INFO</div>
                <div class="detail-line">
                    <span class="detail-label">Phone:</span>
                    <span class="detail-value"><?= htmlspecialchars(safe_get_setting('company_phone', '+92 323 9507813')) ?></span>
                </div>
                <div class="detail-line">
                    <span class="detail-label">Address:</span>
                    <span class="detail-value"><?= htmlspecialchars(safe_get_setting('company_address', 'Shop #1 Hameed Plaza')) ?></span>
                </div>
                <div class="detail-line">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value"><?= htmlspecialchars(safe_get_setting('company_email', 'info@wasemwears.com')) ?></span>
                </div>
            </div>

            <!-- Invoice Info -->
            <div class="details-section">
                <div class="section-title">INVOICE DETAILS</div>
                <div class="detail-line">
                    <span class="detail-label">Invoice No:</span>
                    <span class="detail-value"><?= htmlspecialchars($sale['sale_no'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-line">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value"><?= safe_format_date($sale['created_at'] ?? $sale['sale_date']) ?></span>
                </div>
                <div class="detail-line">
                    <span class="detail-label">Time:</span>
                    <span class="detail-value"><?= date('H:i:s', strtotime($sale['created_at'] ?? $sale['sale_date'])) ?></span>
                </div>
                <div class="detail-line">
                    <span class="detail-label">Delivery:</span>
                    <span class="detail-value"><?= $sale['delivery_date'] ? safe_format_date($sale['delivery_date']) : 'N/A' ?></span>
                </div>
                <div class="detail-line">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value"><?= strtoupper($sale['status'] ?? 'PENDING') ?></span>
                </div>
            </div>

            <!-- Customer Info -->
            <div class="details-section">
                <div class="section-title">CUSTOMER INFO</div>
                <div class="detail-line">
                    <span class="detail-label">Name:</span>
                    <span class="detail-value"><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?></span>
                </div>
                <?php if ($sale['customer_contact']): ?>
                <div class="detail-line">
                    <span class="detail-label">Mobile:</span>
                    <span class="detail-value"><?= htmlspecialchars($sale['customer_contact']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($sale['customer_address']): ?>
                <div class="detail-line">
                    <span class="detail-label">Address:</span>
                    <span class="detail-value"><?= htmlspecialchars($sale['customer_address']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Items -->
            <?php if (!empty($sale_items)): ?>
            <div class="items-section">
                <div class="items-header">ITEMS ORDERED</div>
                <?php 
                $counter = 1;
                foreach ($sale_items as $item): 
                ?>
                <div class="item-row">
                    <div class="item-name"><?= $counter++ ?>. <?= htmlspecialchars($item['product_name'] ?? 'N/A') ?></div>
                    <div class="item-details">
                        <span>Cat: <?= htmlspecialchars($item['category_name'] ?? 'N/A') ?></span>
                        <span>Color: <?= htmlspecialchars(extractColorFromNotes($item['notes'] ?? '')) ?></span>
                    </div>
                    <div class="item-details">
                        <span>Qty: <?= number_format($item['quantity'] ?? 0, 2) ?> <?= htmlspecialchars($item['product_unit'] ?? '') ?></span>
                        <span>Rate: <?= safe_format_currency($item['unit_price'] ?? 0) ?></span>
                    </div>
                    <div class="item-total">
                        <span>Amount:</span>
                        <span><?= safe_format_currency($item['total_price'] ?? 0) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Summary -->
            <div class="summary-section">
                <div class="summary-line">
                    <span>Subtotal:</span>
                    <span><?= safe_format_currency($sale['subtotal'] ?? 0) ?></span>
                </div>
                <div class="summary-line">
                    <span>Discount:</span>
                    <span><?= safe_format_currency($sale['discount'] ?? 0) ?></span>
                </div>
                <div class="summary-line total">
                    <span>TOTAL:</span>
                    <span><?= safe_format_currency($sale['total_amount'] ?? 0) ?></span>
                </div>
                <div class="summary-line">
                    <span>Paid:</span>
                    <span><?= safe_format_currency($sale['paid_amount'] ?? 0) ?></span>
                </div>
                <div class="summary-line due">
                    <span>BALANCE DUE:</span>
                    <span><?= safe_format_currency($sale['due_amount'] ?? 0) ?></span>
                </div>
                
                <!-- Payment Method -->
                <?php if ($sale['payment_method_id']): ?>
                <div class="detail-line" style="margin-top: 8px; border-top: 1px dotted #333; padding-top: 5px;">
                    <span class="detail-label">Payment:</span>
                    <span class="detail-value">
                        <?php
                        $stmt = $pdo->prepare("SELECT method FROM payment_method WHERE id = ?");
                        $stmt->execute([$sale['payment_method_id']]);
                        $method = $stmt->fetch(PDO::FETCH_ASSOC);
                        echo htmlspecialchars($method['method'] ?? 'N/A');
                        ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="invoice-footer">
            <div class="footer-text"><?= htmlspecialchars(safe_get_setting('footer_text', 'Thank you for choosing WASIM!')) ?></div>
            <div class="footer-info">
                Visit us again for quality tailoring services
            </div>
            <div class="footer-note">
                <?= htmlspecialchars(safe_get_setting('print_header', 'This is a computer generated invoice.')) ?><br>
                Generated: <?= date('d/m/Y H:i:s') ?>
            </div>
        </div>
    </div>
</body>
</html>
