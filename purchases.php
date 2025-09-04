<?php
require_once 'includes/auth.php';
require_login();
require_once 'includes/config.php';

$activePage = 'purchases';

// Generate WhatsApp message for purchase
function generatePurchaseWhatsAppMessage($purchase, $pdo) {
    try {
        // Validate required fields exist
        if (!isset($purchase['id']) || !isset($purchase['purchase_no']) || !isset($purchase['supplier_name']) || 
            !isset($purchase['purchase_date']) || !isset($purchase['total_amount']) || !isset($purchase['paid_amount'])) {
            error_log("Missing required purchase data for purchase ID: " . ($purchase['id'] ?? 'unknown'));
            error_log("Available keys: " . implode(', ', array_keys($purchase)));
            throw new Exception("Missing required purchase data");
        }
        
        // Get purchase items for detailed message
        $stmt = $pdo->prepare("SELECT pi.*, p.product_name, c.category FROM purchase_items pi 
                               LEFT JOIN products p ON pi.product_id = p.id 
                               LEFT JOIN categories c ON p.category_id = c.id 
                               WHERE pi.purchase_id = ?");
        $stmt->execute([$purchase['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $message = "🛒 *PURCHASE ORDER - TAILOR SHOP*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        // Header Information - with safe array access
        $message .= "📋 *Purchase No:* " . (isset($purchase['purchase_no']) ? html_entity_decode($purchase['purchase_no']) : 'N/A') . "\n";
        $message .= "🏪 *Supplier:* " . (isset($purchase['supplier_name']) ? html_entity_decode($purchase['supplier_name']) : 'N/A') . "\n";
        $message .= "📅 *Date:* " . (isset($purchase['purchase_date']) ? date('d M Y', strtotime($purchase['purchase_date'])) : 'N/A') . "\n";
        $message .= "🕐 *Time:* " . (isset($purchase['purchase_date']) ? date('h:i A', strtotime($purchase['purchase_date'])) : 'N/A') . "\n\n";
        
        // Items Details
        if (!empty($items)) {
            $message .= "🛍️ *ITEMS PURCHASED:*\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            
            foreach ($items as $index => $item) {
                $itemNo = $index + 1;
                $productName = isset($item['product_name']) ? html_entity_decode($item['product_name']) : 'Unknown Product';
                $message .= $itemNo . ". *" . $productName . "*\n";
                
                if (isset($item['category']) && !empty($item['category'])) {
                    $message .= "   📂 Category: " . html_entity_decode($item['category']) . "\n";
                }
                if (isset($item['product_code']) && !empty($item['product_code'])) {
                    $message .= "   🏷️ Code: " . html_entity_decode($item['product_code']) . "\n";
                }
                
                $quantity = isset($item['quantity']) ? $item['quantity'] : 0;
                $purchasePrice = isset($item['purchase_price']) ? $item['purchase_price'] : 0;
                $totalPrice = isset($item['total_price']) ? $item['total_price'] : 0;
                
                // Fix the total price calculation if it's 0
                if ($totalPrice == 0 && $quantity > 0 && $purchasePrice > 0) {
                    $totalPrice = $quantity * $purchasePrice;
                }
                
                $message .= "   📏 Qty: " . $quantity . " × PKR " . number_format($purchasePrice, 2) . "\n";
                $message .= "   💰 Total: PKR " . number_format($totalPrice, 2) . "\n\n";
            }
        } else {
            $message .= "🛍️ *ITEMS PURCHASED:*\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "No items found for this purchase.\n\n";
        }
        
        // Summary Section - with safe array access
        $message .= "📊 *PURCHASE SUMMARY:*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "💰 *Total Amount:* PKR " . (isset($purchase['total_amount']) ? number_format($purchase['total_amount'], 2) : '0.00') . "\n";
        $message .= "💸 *Paid Amount:* PKR " . (isset($purchase['paid_amount']) ? number_format($purchase['paid_amount'], 2) : '0.00') . "\n";
        
        $dueAmount = isset($purchase['due_amount']) ? $purchase['due_amount'] : 0;
        if ($dueAmount > 0) {
            $message .= "⚠️ *Due Amount:* PKR " . number_format($dueAmount, 2) . "\n";
        }
        
        // Footer
        $message .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "🏪 *WASEM WEARS*\n";
        $message .= "📞 Contact: +92 323 9507813\n";
        $message .= "📍 Address: Address shop #1 hameed plaza main university road\n";
        $message .= "🌐 Website: www.yourshop.com\n\n";
        $message .= "Thank you for your supply! 🙏\n";
        $message .= "Please deliver as soon as possible! ✨";
        
        return urlencode($message);
    } catch (Exception $e) {
        // Fallback to simple message if there's an error
        error_log("WhatsApp message generation error: " . $e->getMessage());
        
        $message = "🛒 *PURCHASE ORDER*\n\n";
        $message .= "📋 Purchase: " . (isset($purchase['purchase_no']) ? html_entity_decode($purchase['purchase_no']) : 'N/A') . "\n";
        $message .= "🏪 Supplier: " . (isset($purchase['supplier_name']) ? html_entity_decode($purchase['supplier_name']) : 'N/A') . "\n";
        $message .= "💰 Total: PKR " . (isset($purchase['total_amount']) ? number_format($purchase['total_amount'], 2) : '0.00') . "\n";
        $message .= "📅 Date: " . (isset($purchase['purchase_date']) ? date('d M Y', strtotime($purchase['purchase_date'])) : 'N/A') . "\n\n";
        $message .= "Please deliver as soon as possible! 🙏";
        
        return urlencode($message);
    }
}

// Handle Delete Purchase
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Get purchase items to reverse stock
    $stmt = $pdo->prepare("SELECT product_id, quantity FROM purchase_items WHERE purchase_id = ?");
    $stmt->execute([$id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($items as $item) {
        try {
            // Find and remove the exact stock items for this purchase
            $stmt = $pdo->prepare("SELECT id, quantity FROM stock_items WHERE purchase_item_id = ? AND product_id = ? AND status = 'available' ORDER BY id ASC");
            $stmt->execute([$id, $item['product_id']]);
            $stock_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $remaining_qty = $item['quantity'];
            foreach ($stock_items as $stock_item) {
                if ($remaining_qty <= 0) break;
                
                $qty_to_remove = min($stock_item['quantity'], $remaining_qty);
                
                if ($stock_item['quantity'] <= $qty_to_remove) {
                    // Remove entire stock item
                    $stmt = $pdo->prepare("DELETE FROM stock_items WHERE id = ?");
                    $stmt->execute([$stock_item['id']]);
                } else {
                    // Reduce quantity
                    $stmt = $pdo->prepare("UPDATE stock_items SET quantity = quantity - ? WHERE id = ?");
                    $stmt->execute([$qty_to_remove, $stock_item['id']]);
                }
                
                $remaining_qty -= $qty_to_remove;
            }
        } catch (Exception $e) {
            error_log("Stock items removal failed: " . $e->getMessage());
        }
    }
    
    $stmt = $pdo->prepare("DELETE FROM purchase_items WHERE purchase_id = ?");
    $stmt->execute([$id]);
    $stmt = $pdo->prepare("DELETE FROM purchase WHERE id = ?");
    $stmt->execute([$id]);
    
    header("Location: purchases.php?success=deleted");
    exit;
}



// Fetch all purchases
$purchases = $pdo->query("SELECT p.*, s.supplier_name, s.supplier_contact, u.username AS created_by_name FROM purchase p LEFT JOIN supplier s ON p.supplier_id = s.id LEFT JOIN system_users u ON p.created_by = u.id ORDER BY p.id DESC")->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php'; ?>
<style>
    .table th {
        font-weight: 600;
        border-bottom: 2px solid #dee2e6;
        background-color: #f8f9fa;
    }
    .table td {
        vertical-align: middle;
        padding: 12px 8px;
    }
    .btn-group .btn {
        margin: 0 1px;
        border-radius: 4px;
    }
    .badge {
        font-size: 0.85em;
        padding: 6px 10px;
    }
    .table-hover tbody tr:hover {
        background-color: rgba(0,123,255,0.05);
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: all 0.2s ease;
    }
    .card {
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }
    .card-header {
        border-radius: 8px 8px 0 0 !important;
        border-bottom: 1px solid #e9ecef;
    }
    .table {
        margin-bottom: 0;
    }
    .text-end {
        text-align: right !important;
    }
    .text-center {
        text-align: center !important;
    }
    
    /* WhatsApp Button Styling */
    .btn-whatsapp {
        background: linear-gradient(135deg, #25D366 0%, #128C7E 100%) !important;
        border: none !important;
        color: white !important;
        font-weight: 600;
    }
    
    .btn-whatsapp:hover {
        background: linear-gradient(135deg, #128C7E 0%, #075E54 100%) !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        color: white !important;
    }
    
    .btn-whatsapp:focus {
        background: linear-gradient(135deg, #25D366 0%, #128C7E 100%) !important;
        border: none !important;
        color: white !important;
        box-shadow: 0 0 0 0.2rem rgba(37, 211, 102, 0.25);
    }
    
    /* Modal Styling */
    .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    
    .modal-header {
        border-radius: 16px 16px 0 0;
        border-bottom: none;
    }
    
    .modal-footer {
        border-top: none;
        border-radius: 0 0 16px 16px;
    }
    
    .input-group-text {
        background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
        color: white;
        border: none;
        font-weight: 600;
    }
    
    /* Validation styling */
    .is-invalid {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
    }
    
    .invalid-feedback {
        display: block;
        width: 100%;
        margin-top: 0.25rem;
        font-size: 0.875em;
        color: #dc3545;
    }
</style>

<script>
let currentPurchaseId = null;

// Function to open modal for sending to another number
function sendPurchaseToAnotherNumber(purchaseId, supplierName) {
    currentPurchaseId = purchaseId;
    document.getElementById('supplierName').value = supplierName;
    document.getElementById('purchasePhoneNumber').value = '';
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('whatsappPurchaseModal'));
    modal.show();
}

// Function to send WhatsApp message
function sendPurchaseWhatsAppMessage() {
    const phoneNumber = document.getElementById('purchasePhoneNumber').value.trim();
    
    if (!phoneNumber) {
        alert('Please enter a phone number');
        return;
    }
    
    if (!/^[3-9]\d{9}$/.test(phoneNumber)) {
        alert('Please enter a valid 10-digit phone number starting with 3-9');
        return;
    }
    
    // Get the purchase data and generate message
    fetch(`get_purchase_data.php?id=${currentPurchaseId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const message = generatePurchaseWhatsAppMessageFromData(data.purchase);
                // Format phone number properly for WhatsApp
                let formattedPhone = phoneNumber;
                // Remove leading zero if present
                if (formattedPhone.startsWith('0')) {
                    formattedPhone = formattedPhone.substring(1);
                }
                // Add country code 92
                formattedPhone = '92' + formattedPhone;
                
                const whatsappUrl = `https://wa.me/${formattedPhone}?text=${encodeURIComponent(message)}`;
                window.open(whatsappUrl, '_blank');
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('whatsappPurchaseModal'));
                modal.hide();
            } else {
                alert('Error: Could not load purchase data');
            }
        })
        .catch(error => {
            // Handle error silently
        });
}

// Function to generate WhatsApp message from purchase data
function generatePurchaseWhatsAppMessageFromData(purchase) {
    let message = "🛒 *PURCHASE ORDER - TAILOR SHOP*\n";
    message += "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Header Information
    message += "📋 *Purchase No:* " + purchase.purchase_no + "\n";
    message += "🏪 *Supplier:* " + purchase.supplier_name + "\n";
    message += "📅 *Date:* " + purchase.purchase_date + "\n";
    message += "🕐 *Time:* " + purchase.purchase_time + "\n\n";
    
    // Items Details (if available)
    if (purchase.items && purchase.items.length > 0) {
        message += "🛍️ *ITEMS PURCHASED:*\n";
        message += "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        
        purchase.items.forEach((item, index) => {
            const itemNo = index + 1;
            message += itemNo + ". *" + item.product_name + "*\n";
            if (item.category) {
                message += "   📂 Category: " + item.category + "\n";
            }
            if (item.product_code) {
                message += "   🏷️ Code: " + item.product_code + "\n";
            }
            message += "   📏 Qty: " + item.quantity + " × PKR " + parseFloat(item.purchase_price).toFixed(2) + "\n";
            message += "   💰 Total: PKR " + parseFloat(item.total_price).toFixed(2) + "\n\n";
        });
    }
    
    // Summary Section
    message += "📊 *PURCHASE SUMMARY:*\n";
    message += "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    message += "💰 *Total Amount:* PKR " + parseFloat(purchase.total_amount).toFixed(2) + "\n";
    message += "💸 *Paid Amount:* PKR " + parseFloat(purchase.paid_amount).toFixed(2) + "\n";
    
    if (parseFloat(purchase.due_amount) > 0) {
        message += "⚠️ *Due Amount:* PKR " + parseFloat(purchase.due_amount).toFixed(2) + "\n";
    }
    
    // Footer
    message += "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    message += "🏪 *Tailor Shop*\n";
    message += "📞 Contact: +92 XXX XXXXXXX\n";
    message += "📍 Address: Your Shop Address\n";
    message += "🌐 Website: www.yourshop.com\n\n";
    message += "Thank you for your supply! 🙏\n";
    message += "Please deliver as soon as possible! ✨";
    
    return message;
}

// Phone number input validation
document.addEventListener('DOMContentLoaded', function() {
    const phoneInput = document.getElementById('purchasePhoneNumber');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            // Remove non-numeric characters
            this.value = this.value.replace(/\D/g, '');
            
            // Limit to 10 digits
            if (this.value.length > 10) {
                this.value = this.value.slice(0, 10);
            }
        });
        
        // Add focus event to show format hint
        phoneInput.addEventListener('focus', function() {
            this.placeholder = '3XX XXXXXXX';
        });
        
        // Add blur event to validate format
        phoneInput.addEventListener('blur', function() {
            if (this.value && !/^[3-9]\d{9}$/.test(this.value)) {
                this.classList.add('is-invalid');
                // Add validation message
                if (!this.nextElementSibling || !this.nextElementSibling.classList.contains('invalid-feedback')) {
                    const feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    feedback.textContent = 'Please enter a valid 10-digit phone number starting with 3-9';
                    this.parentNode.appendChild(feedback);
                }
            } else {
                this.classList.remove('is-invalid');
                const feedback = this.parentNode.querySelector('.invalid-feedback');
                if (feedback) {
                    feedback.remove();
                }
            }
        });
    }
});
</script>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-10 ms-sm-auto px-4 py-5" 5" style="margin-top: 25px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Purchases</h2>
                <div>
                    <!-- <a href="add_purchase.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add New Purchase
                    </a> -->
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <?php
                    if ($_GET['success'] === 'added') echo "Purchase added successfully!";
                    if ($_GET['success'] === 'deleted') echo "Purchase deleted successfully!";
                    ?>
                </div>
            <?php endif; ?>
            
            <!-- Purchase List Table -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-cart-check me-2"></i>Purchase List</h5>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center"><i class="bi bi-hash me-1"></i>Purchase No</th>
                                <th><i class="bi bi-person-badge me-1"></i>Supplier</th>
                                <th><i class="bi bi-calendar-event me-1"></i>Purchase Date & Time</th>
                                <th class="text-end"><i class="bi bi-currency-dollar me-1"></i>Total Amount</th>
                                <th class="text-end"><i class="bi bi-check-circle me-1"></i>Paid Amount</th>
                                <th class="text-end"><i class="bi bi-exclamation-circle me-1"></i>Remaining</th>
                                <th><i class="bi bi-credit-card me-1"></i>Payment Method</th>
                                <th><i class="bi bi-person me-1"></i>Created By</th>
                                <th class="text-center"><i class="bi bi-gear me-1"></i>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchases as $purchase): ?>
                                <tr>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?= htmlspecialchars($purchase['purchase_no']) ?></span>
                                    </td>
                                    <td>
                                        <i class="bi bi-person-badge me-1"></i>
                                        <a href="purchase_details.php?id=<?= $purchase['id'] ?>" class="text-decoration-none text-primary fw-medium" title="Click to view purchase details">
                                            <?= htmlspecialchars($purchase['supplier_name']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <strong><?= date('d M Y', strtotime($purchase['purchase_date'])) ?></strong>
                                            <small class="text-muted"><?= date('H:i', strtotime($purchase['purchase_date'])) ?></small>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <span class="fw-bold text-primary">PKR <?= number_format($purchase['total_amount'], 2) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <span class="text-success">PKR <?= number_format($purchase['paid_amount'], 2) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($purchase['due_amount'] > 0): ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="bi bi-exclamation-triangle me-1"></i>
                                                PKR <?= number_format($purchase['due_amount'], 2) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle me-1"></i>
                                                PKR <?= number_format($purchase['due_amount'], 2) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($purchase['payment_method_id']) {
                                            $stmt = $pdo->prepare("SELECT method FROM payment_method WHERE id = ?");
                                            $stmt->execute([$purchase['payment_method_id']]);
                                            $method = $stmt->fetch(PDO::FETCH_ASSOC);
                                            echo '<span class="badge bg-info">' . htmlspecialchars($method['method'] ?? 'N/A') . '</span>';
                                        } else {
                                            echo '<span class="text-muted">N/A</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <i class="bi bi-person me-1"></i>
                                        <?= htmlspecialchars($purchase['created_by_name']) ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a href="purchase_details.php?id=<?= $purchase['id'] ?>" class="btn btn-sm btn-outline-info" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="print_purchase.php?id=<?= $purchase['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank" title="Print">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                            <?php if (!empty($purchase['supplier_contact'])): ?>
                                                <?php 
                                                try {
                                                    // Format phone number for WhatsApp - improved logic
                                                    $phone = preg_replace('/[^0-9]/', '', $purchase['supplier_contact']);
                                                    
                                                    // Handle different phone number formats - WhatsApp expects specific format
                                                    if (strlen($phone) == 10) {
                                                        // 10 digits: add 92 prefix (e.g., 3001234567 -> 923001234567)
                                                        $phone = '92' . $phone;
                                                    } elseif (strlen($phone) == 11) {
                                                        if (substr($phone, 0, 1) == '0') {
                                                            // 11 digits starting with 0: remove 0 and add 92 (e.g., 03001234567 -> 923001234567)
                                                            $phone = '92' . substr($phone, 1);
                                                        } else {
                                                            // 11 digits starting with 3-9: add 92 prefix (e.g., 30012345678 -> 9230012345678)
                                                            $phone = '92' . $phone;
                                                        }
                                                    } elseif (strlen($phone) == 12 && substr($phone, 0, 2) == '92') {
                                                        // Already has 92 prefix: use as is (e.g., 923001234567)
                                                        $phone = $phone;
                                                    } elseif (strlen($phone) == 13 && substr($phone, 0, 3) == '920') {
                                                        // 13 digits starting with 920: remove the extra 0 (e.g., 9203001234567 -> 923001234567)
                                                        $phone = '92' . substr($phone, 3);
                                                    } elseif (strlen($phone) == 13 && substr($phone, 0, 2) == '92') {
                                                        // 13 digits starting with 92: use as is (e.g., 9230012345678)
                                                        $phone = $phone;
                                                    } else {
                                                        // For any other format, try to normalize
                                                        if (substr($phone, 0, 1) == '0') {
                                                            $phone = '92' . substr($phone, 1);
                                                        } elseif (strlen($phone) >= 10) {
                                                            // Take the last 10 digits and add 92
                                                            $phone = '92' . substr($phone, -10);
                                                        }
                                                    }
                                                    
                                                    // Final validation - WhatsApp expects exactly 12 or 13 digits for Pakistan
                                                    if (strlen($phone) < 12 || strlen($phone) > 13) {
                                                        throw new Exception("Invalid phone number length after formatting: " . strlen($phone) . " - " . $phone);
                                                    }
                                                    
                                                    // Ensure it starts with 92 (Pakistan country code)
                                                    if (substr($phone, 0, 2) !== '92') {
                                                        throw new Exception("Phone number doesn't start with 92: " . $phone);
                                                    }
                                                    
                                                    // Additional validation: ensure the number after 92 is valid
                                                    $numberAfter92 = substr($phone, 2);
                                                    if (strlen($numberAfter92) < 10 || strlen($numberAfter92) > 11) {
                                                        throw new Exception("Invalid number length after 92: " . strlen($numberAfter92));
                                                    }
                                                    
                                                    // Ensure the number after 92 starts with 3-9 (valid Pakistan mobile prefixes)
                                                    if (!preg_match('/^[3-9]/', $numberAfter92)) {
                                                        throw new Exception("Invalid mobile prefix after 92: " . $numberAfter92);
                                                    }
                                                    
                                                    // Generate WhatsApp message
                                                    $whatsappMessage = generatePurchaseWhatsAppMessage($purchase, $pdo);
                                                    
                                                } catch (Exception $e) {
                                                    error_log("Phone formatting error for purchase " . $purchase['id'] . ": " . $e->getMessage());
                                                    // Fallback to modal
                                                    $phone = null;
                                                }
                                                ?>
                                                
                                                <?php if ($phone && $whatsappMessage): ?>
                                                    <a href="https://wa.me/<?= $phone ?>?text=<?= $whatsappMessage ?>" target="_blank" class="btn btn-sm btn-whatsapp" title="Send Purchase Order via WhatsApp" onclick="return confirm('Send purchase order to <?= htmlspecialchars($purchase['supplier_name']) ?> via WhatsApp?')">
                                                        <i class="bi bi-whatsapp"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-whatsapp" title="Send to another number" onclick="sendPurchaseToAnotherNumber(<?= $purchase['id'] ?>, '<?= htmlspecialchars($purchase['supplier_name']) ?>')">
                                                        <i class="bi bi-whatsapp"></i>
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-whatsapp" title="Send to another number" onclick="sendPurchaseToAnotherNumber(<?= $purchase['id'] ?>, '<?= htmlspecialchars($purchase['supplier_name']) ?>')">
                                                    <i class="bi bi-whatsapp"></i>
                                                </button>
                                            <?php endif; ?>
                                            <a href="purchases.php?delete=<?= $purchase['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this purchase?')" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($purchases)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="bi bi-cart-x fs-1 d-block mb-2"></i>
                                            <h5>No purchases found</h5>
                                            <p>Start by adding your first purchase using the button above.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- WhatsApp Number Modal for Purchases -->
<div class="modal fade" id="whatsappPurchaseModal" tabindex="-1" aria-labelledby="whatsappPurchaseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="whatsappPurchaseModalLabel">
                    <i class="bi bi-whatsapp me-2"></i>Send Purchase Order via WhatsApp
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="supplierName" class="form-label">Supplier Name:</label>
                    <input type="text" class="form-control" id="supplierName" readonly>
                </div>
                <div class="mb-3">
                    <label for="purchasePhoneNumber" class="form-label">Phone Number:</label>
                    <div class="input-group">
                        <span class="input-group-text">+92</span>
                        <input type="tel" class="form-control" id="purchasePhoneNumber" placeholder="3XX XXXXXXX" maxlength="10" pattern="[0-9]{10}">
                    </div>
                    <div class="form-text">Enter the 10-digit phone number without country code</div>
                </div>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <small>This will open WhatsApp with the purchase order message. Make sure the number is correct.</small>
                </div>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <small><strong>Phone Number Format:</strong> Enter 10 digits starting with 3-9 (e.g., 3001234567)</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="sendPurchaseWhatsAppMessage()">
                    <i class="bi bi-whatsapp me-2"></i>Send via WhatsApp
                </button>
            </div>
        </div>
    </div>
</div>



<?php include 'includes/footer.php'; ?>