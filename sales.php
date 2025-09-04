<?php
require_once 'includes/auth.php';
require_login();
require_once 'includes/config.php';

$activePage = 'sales';

// Get the next sale invoice number
function get_next_sale_invoice_no($pdo) {
    $stmt = $pdo->query("SELECT MAX(id) AS max_id FROM sale");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $next = ($row && $row['max_id']) ? $row['max_id'] + 1 : 1;
    return 'SALE-' . str_pad($next, 3, '0', STR_PAD_LEFT);
}

// Generate WhatsApp message for sale with complete details
function generateWhatsAppMessage($sale, $pdo) {
    try {
        // Get sale items for detailed message
        $stmt = $pdo->prepare("SELECT si.*, p.product_name, c.category FROM sale_items si 
                               LEFT JOIN products p ON si.product_id = p.id 
                               LEFT JOIN categories c ON p.category_id = c.id 
                               WHERE si.sale_id = ?");
        $stmt->execute([$sale['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $message = "🛍️ *SALE INVOICE - TAILOR SHOP*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        // Header Information
        $message .= "📋 *Invoice No:* " . html_entity_decode($sale['sale_no']) . "\n";
        $message .= "👤 *Customer:* " . html_entity_decode($sale['customer_name']) . "\n";
        $message .= "📅 *Date:* " . date('d M Y', strtotime($sale['sale_date'])) . "\n";
        $message .= "🕐 *Time:* " . date('h:i A', strtotime($sale['sale_date'])) . "\n\n";
        
        // Items Details
        $message .= "🛒 *ITEMS PURCHASED:*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        
        foreach ($items as $index => $item) {
            $itemNo = $index + 1;
            $message .= $itemNo . ". *" . html_entity_decode($item['product_name']) . "*\n";
            if (!empty($item['category_name'])) {
                $message .= "   📂 Category: " . html_entity_decode($item['category_name']) . "\n";
            }
            if (!empty($item['product_code'])) {
                $message .= "   🏷️ Code: " . html_entity_decode($item['product_code']) . "\n";
            }
            $message .= "   📏 Qty: " . $item['quantity'] . " × PKR " . number_format($item['price'], 2) . "\n";
            $message .= "   💰 Total: PKR " . number_format($item['total_price'], 2) . "\n\n";
        }
        
        // Summary Section
        $message .= "📊 *BILL SUMMARY:*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "💰 *Subtotal:* PKR " . number_format($sale['subtotal'], 2) . "\n";
        
        if ($sale['discount'] > 0) {
            $message .= "🎯 *Discount:* PKR " . number_format($sale['discount'], 2) . "\n";
            $message .= "💵 *After Discount:* PKR " . number_format($sale['after_discount'], 2) . "\n";
        }
        
        $message .= "💳 *Total Amount:* PKR " . number_format($sale['total_amount'], 2) . "\n";
        $message .= "💸 *Paid Amount:* PKR " . number_format($sale['paid_amount'], 2) . "\n";
        
        if ($sale['due_amount'] > 0) {
            $message .= "⚠️ *Due Amount:* PKR " . number_format($sale['due_amount'], 2) . "\n";
        }
        
        // Footer
        $message .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "🏪 *WASEM WEARS*\n";
        $message .= "📞 Contact: +92 323 9507813\n";
        $message .= "📍 Address: Address shop #1 hameed plaza main university road\n";
        $message .= "🌐 Website: www.wasemwears.com\n\n";
        $message .= "Thank you for choosing us! 🙏\n";
        $message .= "Please visit again! ✨";
        
        return urlencode($message);
    } catch (Exception $e) {
        // Fallback to simple message if there's an error
        $message = "🛍️ *SALE INVOICE*\n\n";
        $message .= "📋 Invoice: " . html_entity_decode($sale['sale_no']) . "\n";
        $message .= "👤 Customer: " . html_entity_decode($sale['customer_name']) . "\n";
        $message .= "💰 Total: PKR " . number_format($sale['total_amount'], 2) . "\n";
        $message .= "📅 Date: " . date('d M Y', strtotime($sale['sale_date'])) . "\n\n";
        $message .= "Thank you! 🙏";
        
        return urlencode($message);
    }
}

// Handle Add Sale
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sale'])) {
    $customer_id = $_POST['customer_id'];
    $walk_in_cust_name = $_POST['walk_in_cust_name'];
    $invoice_no = get_next_sale_invoice_no($pdo);
    $sale_date = $_POST['sale_date'];
    // Calculate subtotal from sale items
    $subtotal = 0;
    if (isset($_POST['total_price']) && is_array($_POST['total_price'])) {
        foreach ($_POST['total_price'] as $total_price) {
            if (!empty($total_price) && is_numeric($total_price)) {
                $subtotal += floatval($total_price);
            }
        }
    }
    
    $discount = floatval($_POST['discount'] ?? 0);
    $total_amount = floatval($_POST['total_amount']);
    $paid_amount = floatval($_POST['paid_amount'] ?? 0);
    $due_amount = floatval($_POST['due_amount'] ?? 0);
    $payment_method_id = $_POST['payment_method_id'] ?? null;
    $notes = $_POST['notes'] ?? '';
    $created_by = $_SESSION['user_id'];

    // If walk-in customer is selected, use walk_in_cust_name
    if ($customer_id === 'walk_in') {
        if (empty(trim($walk_in_cust_name))) {
            $error = "Walk-in customer name is required when selecting walk-in customer.";
        } else {
            $customer_id = null; // Use null for walk-in customers (database now supports this)
        }
    }

    // If no error, proceed with the sale
    if (!isset($error)) {
        $after_discount = $subtotal - $discount;
        $stmt = $pdo->prepare("INSERT INTO sale (customer_id, walk_in_cust_name, sale_no, sale_date, subtotal, discount, after_discount, total_amount, paid_amount, due_amount, payment_method_id, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$customer_id, $walk_in_cust_name, $invoice_no, $sale_date, $subtotal, $discount, $after_discount, $total_amount, $paid_amount, $due_amount, $payment_method_id, $notes, $created_by]);
        $sale_id = $pdo->lastInsertId();

        // Handle sale items
        $product_ids = $_POST['product_id'];
        $quantities = $_POST['quantity'];
        $purchase_prices = $_POST['purchase_price'];
        $unit_prices = $_POST['unit_price'];
        $total_prices = $_POST['total_price'];

        for ($i = 0; $i < count($product_ids); $i++) {
            if (!empty($product_ids[$i])) {
                try {
                    // Get product details and stock item details
                    $stmt = $pdo->prepare("SELECT p.product_name, si.id as stock_item_id, si.product_code, si.purchase_price, si.sale_price 
                                          FROM products p 
                                          JOIN stock_items si ON p.id = si.product_id 
                                          WHERE p.id = ? AND si.status = 'available' AND si.quantity >= ? 
                                          ORDER BY si.id ASC LIMIT 1");
                    $stmt->execute([$product_ids[$i], $quantities[$i]]);
                    $stock_item = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($stock_item) {
                        $product_code = $stock_item['product_code'] ?: '';
                        $stock_item_id = $stock_item['stock_item_id'];
                        
                        // Get category name for the product
                        $stmt = $pdo->prepare("SELECT c.category FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
                        $stmt->execute([$product_ids[$i]]);
                        $category = $stmt->fetch(PDO::FETCH_ASSOC);
                        $category_name = $category ? $category['category'] : '';
                        
                        $stmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, warehouse_id, product_code, price, stock_qty, quantity, total_price, category_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$sale_id, $product_ids[$i], 0, $product_code, $unit_prices[$i], $quantities[$i], $quantities[$i], $total_prices[$i], $category_name]);

                        // Update stock - remove from stock_items using specific stock item ID
                        $stmt = $pdo->prepare("UPDATE stock_items SET quantity = quantity - ? WHERE id = ?");
                        $stmt->execute([$quantities[$i], $stock_item_id]);

                        // Check for low stock and create notification if needed
                        $stmt = $pdo->prepare("SELECT p.product_name, p.alert_quantity, COALESCE(SUM(si.quantity), 0) as current_stock FROM products p LEFT JOIN stock_items si ON p.id = si.product_id AND si.status = 'available' WHERE p.id = ? GROUP BY p.id");
                        $stmt->execute([$product_ids[$i]]);
                        $product = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($product && $product['current_stock'] <= $product['alert_quantity']) {
                            $msg = 'Low stock alert: ' . $product['product_name'] . ' stock is ' . $product['current_stock'] . ' (threshold: ' . $product['alert_quantity'] . ')';
                            // Prevent duplicate unread notifications for this product and user
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'Low Stock' AND message = ? AND is_read = 0");
                            $stmt->execute([$created_by, $msg]);
                            $exists = $stmt->fetchColumn();
                            if (!$exists) {
                                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'Low Stock', ?)");
                                $stmt->execute([$created_by, $msg]);
                            }
                        }
                    } else {
                        // Log error if no stock available
                        error_log("No available stock found for product ID: " . $product_ids[$i] . " with quantity: " . $quantities[$i]);
                    }
                } catch (Exception $e) {
                    // Log any database errors
                    error_log("Error processing sale item: " . $e->getMessage());
                }
            }
        }

        header("Location: sales.php?success=added&sale_id=" . $sale_id);
        exit;
    } else {
        // If there was an error, redirect back to the form with error message
        header("Location: sales.php?error=" . urlencode($error));
        exit;
    }
}

// Handle Delete Sale
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Get sale items to reverse stock
    $stmt = $pdo->prepare("SELECT product_id, quantity FROM sale_items WHERE sale_id = ?");
    $stmt->execute([$id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($items as $item) {
        // Restore stock to stock_items table
        $stmt = $pdo->prepare("UPDATE stock_items SET quantity = quantity + ? WHERE product_id = ? AND status = 'available' LIMIT 1");
        $stmt->execute([$item['quantity'], $item['product_id']]);
    }
    
    $stmt = $pdo->prepare("DELETE FROM sale_items WHERE sale_id = ?");
    $stmt->execute([$id]);
    $stmt = $pdo->prepare("DELETE FROM sale WHERE id = ?");
    $stmt->execute([$id]);
    
    header("Location: sales.php?success=deleted");
    exit;
}

// Fetch customers and products for dropdowns
$customers = $pdo->query("SELECT * FROM customer ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$products = $pdo->query("SELECT p.*, COALESCE(SUM(si.quantity), 0) as stock_quantity, ROUND(COALESCE(SUM(si.quantity * si.sale_price) / SUM(si.quantity), 0), 2) as sale_price, ROUND(COALESCE(SUM(si.quantity * si.purchase_price) / SUM(si.quantity), 0), 2) as purchase_price FROM products p LEFT JOIN stock_items si ON p.id = si.product_id AND si.status = 'available' GROUP BY p.id HAVING stock_quantity > 0 ORDER BY p.product_name")->fetchAll(PDO::FETCH_ASSOC);
$payment_methods = $pdo->query("SELECT * FROM payment_method WHERE status = 1 ORDER BY method")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all sales
$sales = $pdo->query("SELECT s.*, COALESCE(c.name, s.walk_in_cust_name) AS customer_name, c.mobile AS customer_mobile, u.username AS created_by_name FROM sale s LEFT JOIN customer c ON s.customer_id = c.id LEFT JOIN system_users u ON s.created_by = u.id ORDER BY s.id DESC")->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-10 ms-sm-auto px-4 py-5" style="margin-top: 25px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0"><i class="bi bi-list-ul text-primary"></i> Sales History</h2>
            </div>

            <!-- Search Box Section -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-lg-8 col-md-7 col-sm-12 mb-3 mb-md-0">
                            <div class="input-group">
                                <span class="input-group-text bg-primary text-white">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" id="salesSearchInput" class="form-control form-control-lg" placeholder="Search sales by customer name, invoice number, reference persons, or date..." aria-label="Search sales">
                                <button class="btn btn-outline-secondary" type="button" id="clearSearchBtn" style="display: none;">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-5 col-sm-12">
                            <div class="d-flex gap-2 flex-wrap">
                                <select id="filterStatus" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="paid">Paid</option>
                                    <option value="due">Due</option>
                                </select>
                                <select id="filterDate" class="form-select">
                                    <option value="">All Dates</option>
                                    <option value="today">Today</option>
                                    <option value="week">This Week</option>
                                    <option value="month">This Month</option>
                                    <option value="year">This Year</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="d-flex flex-wrap gap-2" id="searchTags" style="display: none;">
                            <!-- Search tags will be displayed here -->
                        </div>
                    </div>
                </div>
            </div>
            


            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <?php
                    if ($_GET['success'] === 'added') echo "Sale added successfully! <a href='print_invoice.php?id=" . $_GET['sale_id'] . "' target='_blank'>Print Invoice</a>";
                    if ($_GET['success'] === 'deleted') echo "Sale deleted successfully!";
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($_GET['error']) ?>
                </div>
            <?php endif; ?>




            

            <!-- Sale List Table -->
            <div class="card border-0 shadow-lg">
                <div class="card-header bg-gradient text-white" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">
                            <i class="bi bi-list-ul me-2"></i> 
                            Sales History Dashboard
                        </h5>
                        <div class="header-stats">
                            <span class="badge bg-light text-dark me-2">
                                <i class="bi bi-cart-check me-1"></i>
                                Total Sales: <?= count($sales) ?>
                            </span>
                            <span class="badge bg-light text-dark">
                                <i class="bi bi-whatsapp me-1"></i>
                                WhatsApp Ready: <?= count(array_filter($sales, function($sale) { return !empty($sale['customer_mobile']); })) ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th><i class="bi bi-receipt"></i> Invoice No</th>
                                <th><i class="bi bi-person"></i> Customer</th>
                                <th><i class="bi bi-people"></i> Reference</th>
                                <th><i class="bi bi-calendar-event"></i> Sale Date</th>
                                <th><i class="bi bi-percent"></i> Discount</th>
                                <th><i class="bi bi-calculator"></i> After Discount</th>
                                <th><i class="bi bi-currency-dollar"></i> Total Amount</th>
                                <th><i class="bi bi-cash"></i> Paid Amount</th>
                                <th><i class="bi bi-exclamation-triangle"></i> Due Amount</th>
                                <th><i class="bi bi-credit-card"></i> Payment Method</th>
                                <th><i class="bi bi-person-badge"></i> Created By</th>
                                <th><i class="bi bi-gear"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $sale): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-primary fs-6">
                                            <i class="bi bi-receipt"></i> <?= htmlspecialchars($sale['sale_no']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="customer-info">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="bi bi-person-circle text-primary me-2"></i> 
                                                <strong><?= htmlspecialchars($sale['customer_name']) ?></strong>
                                            </div>
                                            <?php if (!empty($sale['customer_mobile'])): ?>
                                                <div class="customer-mobile">
                                                    <i class="bi bi-phone me-1"></i> <?= htmlspecialchars($sale['customer_mobile']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($sale['reference_persons'])): ?>
                                            <span class="badge bg-info">
                                                <i class="bi bi-people"></i> <?= htmlspecialchars($sale['reference_persons']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <i class="bi bi-calendar-event"></i> 
                                        <?= date('d M Y', strtotime($sale['sale_date'])) ?>
                                    </td>
                                    <td>
                                        <?php if ($sale['discount'] > 0): ?>
                                            <span class="badge bg-warning text-dark">
                                                PKR <?= number_format($sale['discount'], 2) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            PKR <?= number_format($sale['after_discount'], 2) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success fs-6">
                                            <strong>PKR <?= number_format($sale['total_amount'], 2) ?></strong>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">
                                            PKR <?= number_format($sale['paid_amount'], 2) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($sale['due_amount'] > 0): ?>
                                            <span class="badge bg-danger">
                                                PKR <?= number_format($sale['due_amount'], 2) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Paid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($sale['payment_method_id']) {
                                            $stmt = $pdo->prepare("SELECT method FROM payment_method WHERE id = ?");
                                            $stmt->execute([$sale['payment_method_id']]);
                                            $method = $stmt->fetch(PDO::FETCH_ASSOC);
                                            echo '<span class="badge bg-primary"><i class="bi bi-credit-card"></i> ' . htmlspecialchars($method['method'] ?? '') . '</span>';
                                        } else {
                                            echo '<span class="text-muted">-</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <i class="bi bi-person-badge"></i> 
                                        <?= htmlspecialchars($sale['created_by_name']) ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <div class="btn-group-vertical" role="group">
                                                <a href="sale_details.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-info mb-1" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="print_invoice.php?id=<?= $sale['id'] ?>" target="_blank" class="btn btn-sm btn-success mb-1" title="Print Invoice">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                                                                            <?php if (!empty($sale['customer_mobile'])): ?>
                                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $sale['customer_mobile']) ?>?text=<?= generateWhatsAppMessage($sale, $pdo) ?>" target="_blank" class="btn btn-sm btn-whatsapp mb-1" title="Send Bill via WhatsApp" onclick="return confirm('Send bill to <?= htmlspecialchars($sale['customer_name']) ?> via WhatsApp?')">
                                                    <i class="bi bi-whatsapp"></i>
                                                </a>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-whatsapp mb-1" title="Send to another number" onclick="sendToAnotherNumber(<?= $sale['id'] ?>, '<?= htmlspecialchars($sale['customer_name']) ?>')">
                                                    <i class="bi bi-whatsapp"></i>
                                                </button>
                                            <?php endif; ?>
                                                <a href="sales.php?delete=<?= $sale['id'] ?>" class="btn btn-sm btn-danger" title="Delete Sale" onclick="return confirm('Are you sure you want to delete this sale? This action cannot be undone.')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($sales)): ?>
                                <tr>
                                    <td colspan="12" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="bi bi-cart-x fs-1"></i>
                                            <h5 class="mt-3">No sales found</h5>
                                            <p>Start creating your first sale using the form above.</p>
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

<!-- WhatsApp Number Modal -->
<div class="modal fade" id="whatsappNumberModal" tabindex="-1" aria-labelledby="whatsappNumberModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="whatsappNumberModalLabel">
                    <i class="bi bi-whatsapp me-2"></i>Send Bill via WhatsApp
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="customerName" class="form-label">Customer Name:</label>
                    <input type="text" class="form-control" id="customerName" readonly>
                </div>
                <div class="mb-3">
                    <label for="phoneNumber" class="form-label">Phone Number:</label>
                    <div class="input-group">
                        <span class="input-group-text">+92</span>
                        <input type="tel" class="form-control" id="phoneNumber" placeholder="3XX XXXXXXX" maxlength="10" pattern="[0-9]{10}">
                    </div>
                    <div class="form-text">Enter the 10-digit phone number without country code</div>
                </div>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <small>This will open WhatsApp with the bill message. Make sure the number is correct.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="sendWhatsAppMessage()">
                    <i class="bi bi-whatsapp me-2"></i>Send via WhatsApp
                </button>
            </div>
        </div>
    </div>
</div>



<?php include 'includes/footer.php'; ?>

<style>
/* Table improvements */
.table-hover tbody tr:hover {
    background-color: #f8f9fa;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.table-dark th {
    background: linear-gradient(135deg, #343a40 0%, #495057 100%);
    border-color: #454d55;
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

/* Success message styling */
.alert-success {
    border-left: 4px solid #28a745;
}

/* Enhanced button styles */
.btn-lg {
    padding: 12px 24px;
    font-size: 1.1rem;
    font-weight: 500;
}

/* Success/error message enhancements */
.alert {
    border-radius: 8px;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
}

/* WhatsApp Info Card Styling */
.whatsapp-info-card .card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid #dee2e6;
}

.whatsapp-icon-wrapper {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
    box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
}

.feature-item {
    padding: 8px 0;
    font-size: 0.9rem;
    color: #495057;
}

.whatsapp-preview {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border: 2px dashed #25D366;
}

.preview-header {
    background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
    color: white;
    padding: 10px 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-weight: 600;
}

.preview-content {
    padding: 10px;
    background: #f8f9fa;
    border-radius: 8px;
}

/* Enhanced Action Buttons */
.action-buttons .btn-group-vertical {
    gap: 5px;
}

.action-buttons .btn {
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.action-buttons .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.action-buttons .btn-whatsapp {
    background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
    border: none;
    color: white;
    font-weight: 600;
}

.action-buttons .btn-whatsapp:hover {
    background: linear-gradient(135deg, #128C7E 0%, #075E54 100%);
    transform: translateY(-2px) scale(1.05);
}

.action-buttons .btn-whatsapp:disabled {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%) !important;
    border: none;
    color: #adb5bd !important;
    cursor: not-allowed;
    opacity: 0.7;
}

.action-buttons .btn-whatsapp:disabled:hover {
    transform: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.action-buttons .btn-info {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    border: none;
}

.action-buttons .btn-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
}

.action-buttons .btn-danger {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    border: none;
}

/* Enhanced Customer Display */
.customer-info {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 10px;
    border-radius: 8px;
    border-left: 4px solid #007bff;
}

.customer-mobile {
    background: #e3f2fd;
    color: #1976d2;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 500;
}

/* Enhanced Table Styling */
.table {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.table thead th {
    position: relative;
}

.table thead th::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, #25D366, #128C7E, #25D366);
}

/* Header Stats Styling */
.header-stats .badge {
    font-size: 0.8rem;
    padding: 8px 12px;
    border-radius: 20px;
    font-weight: 500;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.header-stats .badge:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
}

/* Enhanced Card Styling */
.card {
    border-radius: 16px;
    overflow: hidden;
}

.card-header {
    border-bottom: none;
    padding: 1.5rem;
}

/* Enhanced Badge Styling */
.badge {
    font-weight: 500;
    letter-spacing: 0.3px;
}

.badge.bg-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important;
}

.badge.bg-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
}

.badge.bg-warning {
    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%) !important;
}

.badge.bg-danger {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
}

.badge.bg-secondary {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%) !important;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .whatsapp-info-card .row {
        flex-direction: column;
    }
    
    .whatsapp-preview {
        margin-top: 20px;
    }
    
    .action-buttons .btn-group-vertical {
        flex-direction: row;
        gap: 5px;
    }
    
    .header-stats {
        flex-direction: column;
        gap: 10px;
    }
    
    .header-stats .badge {
        margin: 0 !important;
    }
}

/* Enhanced Mobile Responsiveness */
@media (max-width: 1199.98px) {
    .header-stats {
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .header-stats .badge {
        font-size: 0.75rem;
        padding: 0.5rem 0.75rem;
    }
}

@media (max-width: 991.98px) {
    .container-fluid {
        padding-left: 15px;
        padding-right: 15px;
    }
    
    .main-content {
        padding: 15px;
    }
    
    /* Search box tablet layout */
    .search-box-section .row {
        gap: 1rem;
    }
    
    .search-box-section .col-lg-8,
    .search-box-section .col-lg-4 {
        margin-bottom: 1rem;
    }
}

@media (max-width: 767.98px) {
    .container-fluid {
        padding-left: 10px;
        padding-right: 10px;
    }
    
    .main-content {
        padding: 10px;
        margin-top: 20px;
    }
    
    /* Mobile-optimized header */
    .d-flex.justify-content-between.align-items-center {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .d-flex.justify-content-between.align-items-center h2 {
        font-size: 1.5rem;
        margin-bottom: 0;
    }
    
    /* Mobile search box */
    .search-box-section {
        margin-bottom: 1rem;
    }
    
    .search-box-section .card-body {
        padding: 1rem;
    }
    
    .search-box-section .row {
        flex-direction: column;
        gap: 1rem;
    }
    
    .search-box-section .col-lg-8,
    .search-box-section .col-lg-4 {
        width: 100%;
        margin-bottom: 0;
    }
    
    /* Mobile search input */
    .search-box-section .input-group {
        flex-direction: column;
    }
    
    .search-box-section .input-group-text {
        border-radius: 8px 8px 0 0;
        justify-content: center;
        padding: 0.75rem;
    }
    
    .search-box-section .form-control {
        border-radius: 0 0 8px 8px;
        border-top: none;
        padding: 0.75rem;
        font-size: 16px; /* Prevents zoom on iOS */
    }
    
    .search-box-section .btn {
        border-radius: 8px;
        margin-top: 0.5rem;
        width: 100%;
    }
    
    /* Mobile filters */
    .search-box-section .d-flex {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .search-box-section .form-select {
        width: 100%;
        padding: 0.75rem;
        font-size: 16px;
        border-radius: 8px;
    }
    
    /* Mobile search tags */
    .search-box-section #searchTags {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .search-box-section .badge {
        width: 100%;
        justify-content: space-between;
        padding: 0.75rem;
        font-size: 0.9rem;
    }
    
    /* Mobile table improvements */
    .table-responsive {
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 1rem;
    }
    
    .table {
        font-size: 0.85rem;
    }
    
    .table thead th {
        padding: 0.75rem 0.5rem;
        font-size: 0.8rem;
        white-space: nowrap;
    }
    
    .table tbody td {
        padding: 0.75rem 0.5rem;
        vertical-align: top;
    }
    
    /* Mobile action buttons */
    .action-buttons .btn-group-vertical {
        flex-direction: row;
        gap: 0.25rem;
        flex-wrap: wrap;
    }
    
    .action-buttons .btn {
        padding: 0.5rem;
        font-size: 0.8rem;
        min-width: 40px;
    }
    
    /* Mobile customer info */
    .customer-info {
        padding: 0.5rem;
        font-size: 0.85rem;
    }
    
    .customer-mobile {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
    
    /* Mobile badges */
    .badge {
        font-size: 0.75rem;
        padding: 0.5rem 0.75rem;
    }
    
    /* Mobile header stats */
    .header-stats {
        flex-direction: column;
        gap: 0.5rem;
        margin-top: 1rem;
    }
    
    .header-stats .badge {
        width: 100%;
        text-align: center;
        margin: 0 !important;
    }
    
    /* Mobile card improvements */
    .card {
        margin-bottom: 1rem;
        border-radius: 12px;
    }
    
    .card-header {
        padding: 1rem;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    /* Mobile modal improvements */
    .modal-dialog {
        margin: 1rem;
        max-width: calc(100% - 2rem);
    }
    
    .modal-body {
        padding: 1rem;
    }
}

@media (max-width: 575.98px) {
    .container-fluid {
        padding-left: 5px;
        padding-right: 5px;
    }
    
    .main-content {
        padding: 5px;
    }
    
    /* Ultra-small mobile optimization */
    .d-flex.justify-content-between.align-items-center h2 {
        font-size: 1.25rem;
    }
    
    /* Mobile search box */
    .search-box-section .card-body {
        padding: 0.75rem;
    }
    
    .search-box-section .form-control,
    .search-box-section .form-select {
        padding: 0.6rem;
        font-size: 0.9rem;
    }
    
    .search-box-section .input-group-text {
        padding: 0.6rem;
    }
    
    /* Mobile table */
    .table {
        font-size: 0.8rem;
    }
    
    .table thead th {
        padding: 0.5rem 0.25rem;
        font-size: 0.75rem;
    }
    
    .table tbody td {
        padding: 0.5rem 0.25rem;
    }
    
    /* Mobile action buttons */
    .action-buttons .btn {
        padding: 0.4rem;
        font-size: 0.75rem;
        min-width: 35px;
    }
    
    /* Mobile badges */
    .badge {
        font-size: 0.7rem;
        padding: 0.4rem 0.6rem;
    }
    
    /* Mobile search tags */
    .search-box-section .badge {
        padding: 0.6rem;
        font-size: 0.8rem;
    }
}

/* Touch-friendly interactions */
@media (hover: none) and (pointer: coarse) {
    .btn:hover {
        transform: none;
    }
    
    .btn:active {
        transform: scale(0.95);
    }
    
    .badge:hover {
        transform: none;
    }
    
    .badge:active {
        transform: scale(0.95);
    }
    
    .form-control:focus,
    .form-select:focus {
        transform: scale(1.02);
    }
}

/* Landscape mobile optimization */
@media (max-width: 767.98px) and (orientation: landscape) {
    .main-content {
        padding: 0.5rem;
    }
    
    .search-box-section .row {
        flex-direction: row;
        gap: 0.5rem;
    }
    
    .search-box-section .col-lg-8 {
        width: 60%;
    }
    
    .search-box-section .col-lg-4 {
        width: 40%;
    }
    
    .search-box-section .form-control,
    .search-box-section .form-select {
        padding: 0.5rem;
    }
}

/* Mobile-first responsive utilities */
.d-mobile-none {
    display: none !important;
}

.d-mobile-block {
    display: block !important;
}

@media (min-width: 768px) {
    .d-mobile-none {
        display: initial !important;
    }
    
    .d-mobile-block {
        display: initial !important;
    }
}

/* Mobile form validation improvements */
@media (max-width: 767.98px) {
    .form-control.is-invalid,
    .form-select.is-invalid {
        border-width: 2px;
    }
    
    .invalid-feedback,
    .valid-feedback {
        font-size: 0.8rem;
        margin-top: 0.25rem;
    }
}

/* Mobile accessibility improvements */
@media (max-width: 767.98px) {
    /* Better focus indicators for mobile */
    .form-control:focus-visible,
    .form-select:focus-visible,
    .btn:focus-visible {
        outline: 3px solid #007bff;
        outline-offset: 2px;
    }
    
    /* Mobile form validation improvements */
    .form-control.is-invalid,
    .form-select.is-invalid {
        border-color: #dc3545;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
    }
    
    .form-control.is-valid,
    .form-select.is-valid {
        border-color: #28a745;
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
    }
    
    /* Mobile error message styling */
    .invalid-feedback {
        font-size: 0.8rem;
        color: #dc3545;
        margin-top: 0.25rem;
    }
    
    .valid-feedback {
        font-size: 0.8rem;
        color: #28a745;
        margin-top: 0.25rem;
    }
}

/* Performance optimizations for mobile */
@media (max-width: 767.98px) {
    .table {
        will-change: transform;
        backface-visibility: hidden;
    }
    
    .form-control, .form-select {
        will-change: transform;
        backface-visibility: hidden;
    }
    
    .btn {
        will-change: transform;
        backface-visibility: hidden;
    }
    
    .card {
        will-change: transform;
        backface-visibility: hidden;
    }
    
    /* Search box performance optimizations */
    .search-box-section {
        will-change: transform;
        backface-visibility: hidden;
    }
    
    .search-box-section .input-group {
        will-change: transform;
        backface-visibility: hidden;
    }
}

/* Enhanced search box styling */
.search-box-section .card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid #dee2e6;
    transition: all 0.3s ease;
}

.search-box-section .card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transform: translateY(-1px);
}

.search-box-section .input-group-text {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border: none;
    color: white;
    font-weight: 600;
}

.search-box-section .form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.search-box-section .form-select:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.search-box-section .btn-outline-secondary {
    border-color: #6c757d;
    color: #6c757d;
}

.search-box-section .btn-outline-secondary:hover {
    background-color: #6c757d;
    border-color: #6c757d;
    color: white;
}

/* Search tags styling */
.search-box-section .badge {
    transition: all 0.2s ease;
    cursor: pointer;
}

.search-box-section .badge:hover {
    transform: scale(1.05);
}

.search-box-section .badge .btn-close {
    font-size: 0.7rem;
    margin-left: 0.5rem;
}

/* No results message styling */
.no-results-message .text-muted {
    color: #6c757d !important;
}

.no-results-message .btn-outline-primary {
    border-color: #007bff;
    color: #007bff;
    transition: all 0.2s ease;
}

.no-results-message .btn-outline-primary:hover {
    background-color: #007bff;
    border-color: #007bff;
    color: white;
    transform: translateY(-1px);
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

/* WhatsApp input group styling */
.input-group-text {
    background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
    color: white;
    border: none;
    font-weight: 600;
}
</style>

<script>
let currentSaleId = null;
let currentSaleData = null;

// Function to open modal for sending to another number
function sendToAnotherNumber(saleId, customerName) {
    currentSaleId = saleId;
    document.getElementById('customerName').value = customerName;
    document.getElementById('phoneNumber').value = '';
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('whatsappNumberModal'));
    modal.show();
}

// Function to send WhatsApp message
function sendWhatsAppMessage() {
    const phoneNumber = document.getElementById('phoneNumber').value.trim();
    
    if (!phoneNumber) {
        alert('Please enter a phone number');
        return;
    }
    
    if (!/^\d{10}$/.test(phoneNumber)) {
        alert('Please enter a valid 10-digit phone number');
        return;
    }
    
    // Get the sale data and generate message
    fetch(`get_sale_data.php?id=${currentSaleId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const message = generateWhatsAppMessageFromData(data.sale);
                const whatsappUrl = `https://wa.me/92${phoneNumber}?text=${encodeURIComponent(message)}`;
                window.open(whatsappUrl, '_blank');
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('whatsappNumberModal'));
                modal.hide();
            } else {
                alert('Error: Could not load sale data');
            }
        })
        .catch(error => {
            // Handle error silently
        });
}

// Function to generate WhatsApp message from sale data
function generateWhatsAppMessageFromData(sale) {
    let message = "🛍️ *SALE INVOICE - TAILOR SHOP*\n";
    message += "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Header Information
    message += "📋 *Invoice No:* " + sale.sale_no + "\n";
    message += "👤 *Customer:* " + sale.customer_name + "\n";
    message += "📅 *Date:* " + sale.sale_date + "\n";
    message += "🕐 *Time:* " + sale.sale_time + "\n\n";
    
    // Items Details (if available)
    if (sale.items && sale.items.length > 0) {
        message += "🛒 *ITEMS PURCHASED:*\n";
        message += "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        
        sale.items.forEach((item, index) => {
            const itemNo = index + 1;
            message += itemNo + ". *" + item.product_name + "*\n";
            if (item.category_name) {
                message += "   📂 Category: " + item.category_name + "\n";
            }
            if (item.product_code) {
                message += "   🏷️ Code: " + item.product_code + "\n";
            }
            message += "   📏 Qty: " + item.quantity + " × PKR " + parseFloat(item.price).toFixed(2) + "\n";
            message += "   💰 Total: PKR " + parseFloat(item.total_price).toFixed(2) + "\n\n";
        });
    }
    
    // Summary Section
    message += "📊 *BILL SUMMARY:*\n";
    message += "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    message += "💰 *Subtotal:* PKR " + parseFloat(sale.subtotal).toFixed(2) + "\n";
    
    if (parseFloat(sale.discount) > 0) {
        message += "🎯 *Discount:* PKR " + parseFloat(sale.discount).toFixed(2) + "\n";
        message += "💵 *After Discount:* PKR " + parseFloat(sale.after_discount).toFixed(2) + "\n";
    }
    
    message += "💳 *Total Amount:* PKR " + parseFloat(sale.total_amount).toFixed(2) + "\n";
    message += "💸 *Paid Amount:* PKR " + parseFloat(sale.paid_amount).toFixed(2) + "\n";
    
    if (parseFloat(sale.due_amount) > 0) {
        message += "⚠️ *Due Amount:* PKR " + parseFloat(sale.due_amount).toFixed(2) + "\n";
    }
    
    // Footer
    message += "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    message += "🏪 *Tailor Shop*\n";
    message += "📞 Contact: +92 XXX XXXXXXX\n";
    message += "📍 Address: Your Shop Address\n";
    message += "🌐 Website: www.yourshop.com\n\n";
    message += "Thank you for choosing us! 🙏\n";
    message += "Please visit again! ✨";
    
    return message;
}

// Phone number input validation
document.addEventListener('DOMContentLoaded', function() {
    const phoneInput = document.getElementById('phoneNumber');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            // Remove non-numeric characters
            this.value = this.value.replace(/\D/g, '');
            
            // Limit to 10 digits
            if (this.value.length > 10) {
                this.value = this.value.slice(0, 10);
            }
        });
    }

    // Sales Search Functionality
    initializeSalesSearch();
});

// Sales Search Functionality
function initializeSalesSearch() {
    const searchInput = document.getElementById('salesSearchInput');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const filterStatus = document.getElementById('filterStatus');
    const filterDate = document.getElementById('filterDate');
    const searchTags = document.getElementById('searchTags');
    const tableRows = document.querySelectorAll('tbody tr');
    
    let currentSearchTerm = '';
    let currentStatusFilter = '';
    let currentDateFilter = '';

    // Search input event listener
    searchInput.addEventListener('input', function() {
        currentSearchTerm = this.value.toLowerCase().trim();
        updateClearButton();
        filterSales();
    });

    // Clear search button
    clearSearchBtn.addEventListener('click', function() {
        searchInput.value = '';
        currentSearchTerm = '';
        updateClearButton();
        filterSales();
    });

    // Status filter
    filterStatus.addEventListener('change', function() {
        currentStatusFilter = this.value;
        filterSales();
    });

    // Date filter
    filterDate.addEventListener('change', function() {
        currentDateFilter = this.value;
        filterSales();
    });

    // Update clear button visibility
    function updateClearButton() {
        if (currentSearchTerm || currentStatusFilter || currentDateFilter) {
            clearSearchBtn.style.display = 'block';
        } else {
            clearSearchBtn.style.display = 'none';
        }
    }

    // Filter sales function
    function filterSales() {
        let visibleCount = 0;
        let hiddenCount = 0;

        tableRows.forEach(row => {
            if (row.cells.length < 12) return; // Skip empty rows

            const invoiceNo = row.cells[0]?.textContent?.toLowerCase() || '';
            const customerName = row.cells[1]?.textContent?.toLowerCase() || '';
            const reference = row.cells[2]?.textContent?.toLowerCase() || '';
            const saleDate = row.cells[3]?.textContent?.toLowerCase() || '';
            const dueAmount = row.cells[8]?.textContent?.toLowerCase() || '';
            
            let shouldShow = true;

            // Text search filter
            if (currentSearchTerm) {
                const searchMatch = invoiceNo.includes(currentSearchTerm) ||
                                  customerName.includes(currentSearchTerm) ||
                                  reference.includes(currentSearchTerm) ||
                                  saleDate.includes(currentSearchTerm);
                shouldShow = shouldShow && searchMatch;
            }

            // Status filter
            if (currentStatusFilter) {
                if (currentStatusFilter === 'paid') {
                    shouldShow = shouldShow && dueAmount.includes('paid');
                } else if (currentStatusFilter === 'due') {
                    shouldShow = shouldShow && !dueAmount.includes('paid') && dueAmount.includes('pkr');
                }
            }

            // Date filter
            if (currentDateFilter && shouldShow) {
                const today = new Date();
                const saleDateObj = parseSaleDate(row.cells[3]?.textContent || '');
                
                if (saleDateObj) {
                    switch (currentDateFilter) {
                        case 'today':
                            shouldShow = isSameDay(saleDateObj, today);
                            break;
                        case 'week':
                            shouldShow = isSameWeek(saleDateObj, today);
                            break;
                        case 'month':
                            shouldShow = isSameMonth(saleDateObj, today);
                            break;
                        case 'year':
                            shouldShow = isSameYear(saleDateObj, today);
                            break;
                    }
                }
            }

            // Show/hide row
            if (shouldShow) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
                hiddenCount++;
            }
        });

        // Update search tags
        updateSearchTags();
        
        // Show no results message if needed
        showNoResultsMessage(visibleCount, hiddenCount);
    }

    // Parse sale date from table cell
    function parseSaleDate(dateText) {
        try {
            // Extract date from text like "📅 15 Dec 2024"
            const dateMatch = dateText.match(/(\d{1,2})\s+(\w{3})\s+(\d{4})/);
            if (dateMatch) {
                const day = parseInt(dateMatch[1]);
                const month = dateMatch[2];
                const year = parseInt(dateMatch[3]);
                
                const monthMap = {
                    'Jan': 0, 'Feb': 1, 'Mar': 2, 'Apr': 3, 'May': 4, 'Jun': 5,
                    'Jul': 6, 'Aug': 7, 'Sep': 8, 'Oct': 9, 'Nov': 10, 'Dec': 11
                };
                
                return new Date(year, monthMap[month], day);
            }
        } catch (e) {
            console.error('Error parsing date:', e);
        }
        return null;
    }

    // Date comparison functions
    function isSameDay(date1, date2) {
        return date1.getDate() === date2.getDate() &&
               date1.getMonth() === date2.getMonth() &&
               date1.getFullYear() === date2.getFullYear();
    }

    function isSameWeek(date1, date2) {
        const oneDay = 24 * 60 * 60 * 1000;
        const diffTime = Math.abs(date2 - date1);
        const diffDays = Math.ceil(diffTime / oneDay);
        return diffDays <= 7;
    }

    function isSameMonth(date1, date2) {
        return date1.getMonth() === date2.getMonth() &&
               date1.getFullYear() === date2.getFullYear();
    }

    function isSameYear(date1, date2) {
        return date1.getFullYear() === date2.getFullYear();
    }

    // Update search tags display
    function updateSearchTags() {
        searchTags.innerHTML = '';
        const hasFilters = currentSearchTerm || currentStatusFilter || currentDateFilter;
        
        if (hasFilters) {
            searchTags.style.display = 'flex';
            
            if (currentSearchTerm) {
                addSearchTag('Search: ' + currentSearchTerm, 'primary');
            }
            if (currentStatusFilter) {
                addSearchTag('Status: ' + currentStatusFilter, 'success');
            }
            if (currentDateFilter) {
                addSearchTag('Date: ' + currentDateFilter, 'info');
            }
        } else {
            searchTags.style.display = 'none';
        }
    }

    // Add search tag
    function addSearchTag(text, color) {
        const tag = document.createElement('span');
        tag.className = `badge bg-${color} d-flex align-items-center gap-1`;
        tag.innerHTML = `
            ${text}
            <button type="button" class="btn-close btn-close-white btn-sm" 
                    onclick="removeSearchTag(this)" aria-label="Remove filter">
            </button>
        `;
        searchTags.appendChild(tag);
    }

    // Show no results message
    function showNoResultsMessage(visibleCount, hiddenCount) {
        const existingMessage = document.querySelector('.no-results-message');
        if (existingMessage) {
            existingMessage.remove();
        }

        if (visibleCount === 0 && (currentSearchTerm || currentStatusFilter || currentDateFilter)) {
            const tbody = document.querySelector('tbody');
            const noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-results-message';
            noResultsRow.innerHTML = `
                <td colspan="12" class="text-center py-5">
                    <div class="text-muted">
                        <i class="bi bi-search-x fs-1"></i>
                        <h5 class="mt-3">No sales found</h5>
                        <p>Try adjusting your search criteria or filters.</p>
                        <button class="btn btn-outline-primary btn-sm" onclick="clearAllFilters()">
                            <i class="bi bi-arrow-clockwise"></i> Clear All Filters
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(noResultsRow);
        }
    }
}

// Remove search tag function
function removeSearchTag(button) {
    const tag = button.closest('.badge');
    const tagText = tag.textContent.trim();
    
    if (tagText.includes('Search:')) {
        document.getElementById('salesSearchInput').value = '';
    } else if (tagText.includes('Status:')) {
        document.getElementById('filterStatus').value = '';
    } else if (tagText.includes('Date:')) {
        document.getElementById('filterDate').value = '';
    }
    
    tag.remove();
    
    // Trigger search update
    const event = new Event('input');
    document.getElementById('salesSearchInput').dispatchEvent(event);
    
    const changeEvent = new Event('change');
    document.getElementById('filterStatus').dispatchEvent(changeEvent);
    document.getElementById('filterDate').dispatchEvent(changeEvent);
}

// Clear all filters function
function clearAllFilters() {
    document.getElementById('salesSearchInput').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterDate').value = '';
    
    // Trigger search update
    const event = new Event('input');
    document.getElementById('salesSearchInput').dispatchEvent(event);
    
    const changeEvent = new Event('change');
    document.getElementById('filterStatus').dispatchEvent(changeEvent);
    document.getElementById('filterDate').dispatchEvent(changeEvent);
}
</script>