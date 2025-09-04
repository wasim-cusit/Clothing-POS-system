<?php
require_once 'includes/auth.php';
require_login();
require_once 'includes/config.php';
require_once 'includes/settings.php';

$purchase_id = intval($_GET['id'] ?? 0);
if (!$purchase_id) {
    header("Location: purchases.php");
    exit;
}

// Fetch purchase details
$stmt = $pdo->prepare("
    SELECT p.*, s.supplier_name, s.supplier_contact, s.supplier_address, s.supplier_email,
           u.username AS created_by_name
    FROM purchase p 
    LEFT JOIN supplier s ON p.supplier_id = s.id 
    LEFT JOIN system_users u ON p.created_by = u.id 
    WHERE p.id = ?
");
$stmt->execute([$purchase_id]);
$purchase = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$purchase) {
    header("Location: purchases.php");
    exit;
}

// Fetch purchase items with product details
$stmt = $pdo->prepare("
    SELECT pi.*, p.product_name, p.product_unit, c.category AS category_name
    FROM purchase_items pi
    LEFT JOIN products p ON pi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE pi.purchase_id = ?
    ORDER BY pi.id
");
$stmt->execute([$purchase_id]);
$purchase_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        return format_date($date);
    } catch (Exception $e) {
        return date('d/m/Y', strtotime($date));
    }
}

function safe_get_setting($key, $default = '') {
    try {
        return get_setting($key, $default);
    } catch (Exception $e) {
        return $default;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Invoice - <?= htmlspecialchars($purchase['purchase_no']) ?></title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px 40px;
            background: white;
            line-height: 1.4;
        }
        .invoice-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .company-name {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
        }
        .company-info {
            font-size: 14px;
            color: #666;
            line-height: 1.6;
        }
        .invoice-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 20px;
        }
        .supplier-info, .invoice-info {
            flex: 1;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .invoice-info {
            text-align: right;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            font-size: 14px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        .total-row {
            font-weight: bold;
            /* background: linear-gradient(135deg, #2c3e50, #34495e); */
            color: black;
            font-size: 14px;
        }
        .total-row td {
            border-top: 3px solid #e74c3c;
            /* border-bottom: 3px solid #e74c3c; */
        }
        .paid-column {
            background-color: #d4edda;
            color: #155724;
            font-weight: bold;
        }
        .remaining-column {
            background-color: #fff3cd;
            color: #856404;
            font-weight: bold;
        }
        .total-column {
            background-color: #f8d7da;
            color: #721c24;
            font-weight: bold;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        .print-button:hover {
            background: #0056b3;
        }
        .no-data {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 20px;
        }
        @media print {
            .print-button {
                display: none;
            }
            body {
                padding-left: 20mm;
                padding-right: 20mm;
                padding-top: 0;
                padding-bottom: 0;
                margin: 0;
            }
            .invoice-header {
                margin-bottom: 20px;
            }
            .invoice-details {
                margin-bottom: 20px;
            }
            table {
                margin-bottom: 20px;
            }
            .footer {
                margin-top: 20px;
            }
        }
        @media screen and (max-width: 768px) {
            .invoice-details {
                flex-direction: column;
            }
            .invoice-info {
                text-align: left;
            }
            table {
                font-size: 12px;
            }
            th, td {
                padding: 8px 4px;
            }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">🖨️ Print Invoice</button>
    
    <div class="invoice-header">
        <div class="company-name"><?= htmlspecialchars(safe_get_setting('company_name', 'WASIM')) ?></div>
        <div class="company-info">
            <strong>PURCHASE INVOICE</strong><br>
            <?= htmlspecialchars(safe_get_setting('company_tagline', 'Professional Tailoring Services')) ?><br>
            📞 Contact: <?= htmlspecialchars(safe_get_setting('company_phone', '+92-300-1234567')) ?><br>
            📧 Email: <?= htmlspecialchars(safe_get_setting('company_email', 'info@tailorshop.com')) ?><br>
            📍 Address: <?= htmlspecialchars(safe_get_setting('company_address', 'Shop #123, Main Street, Lahore, Pakistan')) ?>
        </div>
    </div>

    <div class="invoice-details">
        <div class="supplier-info">
            <div class="section-title">📋 Supplier Information</div>
            <strong><?= htmlspecialchars($purchase['supplier_name'] ?? 'N/A') ?></strong><br>
            📞 Contact: <?= htmlspecialchars($purchase['supplier_contact'] ?? 'N/A') ?><br>
            📍 Address: <?= htmlspecialchars($purchase['supplier_address'] ?? 'N/A') ?><br>
            📧 Email: <?= htmlspecialchars($purchase['supplier_email'] ?? 'N/A') ?>
        </div>
        <div class="invoice-info">
            <div class="section-title">📄 Invoice Details</div>
            <strong>Purchase No:</strong> <?= htmlspecialchars($purchase['purchase_no'] ?? 'N/A') ?><br>
            <strong>Date:</strong> <?= safe_format_date($purchase['purchase_date']) ?><br>
            <strong>Created By:</strong> <?= htmlspecialchars($purchase['created_by_name'] ?? 'N/A') ?>
        </div>
    </div>

    <?php if (!empty($purchase_items)): ?>
    <table>
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="20%">Product Name</th>
                <th width="15%">Category</th>
                <th width="10%">Color</th>
                <th width="8%">Unit</th>
                <th width="10%">Quantity</th>
                <th width="12%">Unit Price</th>
                <th width="10%">Total</th>
                <th width="10%">Paid</th>
                <th width="10%">Remaining</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $counter = 1;
            $grand_total = 0;
            foreach ($purchase_items as $item): 
                $grand_total += $item['purchase_total'];
            ?>
                <tr>
                    <td><?= $counter++ ?></td>
                    <td><strong><?= htmlspecialchars($item['product_name'] ?? 'N/A') ?></strong></td>
                    <td><span class="badge" style="background-color: #17a2b8; color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px;"><?= htmlspecialchars($item['category_name'] ?? 'N/A') ?></span></td>
                    <td>
                        <?php if (!empty($item['color'])): ?>
                            <span style="background-color: #e9ecef; border: 1px solid #ced4da; padding: 3px 8px; border-radius: 15px; font-size: 11px; color: #495057; font-weight: 500;">
                                <?= htmlspecialchars($item['color']) ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #6c757d; font-size: 11px; font-style: italic;">No color specified</span>
                        <?php endif; ?>
                    </td>
                    <td><code style="background-color: #f8f9fa; padding: 2px 6px; border-radius: 4px; font-size: 11px;"><?= htmlspecialchars($item['product_unit'] ?? 'N/A') ?></code></td>
                    <td><span style="background-color: #007bff; color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold;"><?= number_format($item['quantity'] ?? 0, 2) ?></span></td>
                    <td><?= safe_format_currency($item['purchase_price'] ?? 0) ?></td>
                    <td class="total-column"><?= safe_format_currency($item['purchase_total'] ?? 0) ?></td>
                    <td class="paid-column"><?= safe_format_currency($purchase['paid_amount'] ?? 0) ?></td>
                    <td class="remaining-column"><?= safe_format_currency(($purchase['total_amount'] ?? 0) - ($purchase['paid_amount'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="7" style="text-align: right;"><strong>Grand Total:</strong></td>
                <td><strong><?= safe_format_currency($grand_total) ?></strong></td>
                <td><strong><?= safe_format_currency($purchase['paid_amount'] ?? 0) ?></strong></td>
                <td><strong><?= safe_format_currency(($purchase['total_amount'] ?? 0) - ($purchase['paid_amount'] ?? 0)) ?></strong></td>
            </tr>
        </tbody>
    </table>
    <?php else: ?>
    <div class="no-data">
        <h3>No items found for this purchase.</h3>
        <p>This purchase invoice doesn't contain any items.</p>
    </div>
    <?php endif; ?>



    <div class="footer">
        <p><strong><?= htmlspecialchars(safe_get_setting('footer_text', 'Thank you for your business!')) ?></strong></p>
        <p><?= htmlspecialchars(safe_get_setting('print_header', 'This is a computer generated invoice. No signature required.')) ?></p>
        <p>Generated on: <?= date(safe_get_setting('date_format', 'd/m/Y') . ' ' . safe_get_setting('time_format', 'H:i:s')) ?></p>
        <p>Page 1 of 1</p>
    </div>

    <script>
        // Auto-print when page loads (optional - uncomment if needed)
        // window.onload = function() {
        //     setTimeout(function() {
        //         window.print();
        //     }, 1000);
        // }
    </script>
</body>
</html>
