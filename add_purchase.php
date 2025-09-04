<?php
require_once 'includes/auth.php';
require_login();
require_once 'includes/config.php';

$activePage = 'add_purchase';

// Get the next invoice number
function get_next_invoice_no($pdo) {
    $stmt = $pdo->query("SELECT MAX(id) AS max_id FROM purchase");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $next = ($row && $row['max_id']) ? $row['max_id'] + 1 : 1;
    return 'INV-' . str_pad($next, 3, '0', STR_PAD_LEFT);
}

// Handle Add Purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_purchase'])) {
    $supplier_id = $_POST['supplier_id'];
    $purchase_no = $_POST['purchase_no'];
    $purchase_date = $_POST['purchase_date'];
    $subtotal = floatval($_POST['subtotal']);
    $discount = floatval($_POST['discount'] ?? 0);
    $total_amount = floatval($_POST['total_amount']);
    $paid_amount = floatval($_POST['paid_amount'] ?? 0);
    $due_amount = floatval($_POST['due_amount'] ?? 0);
    $payment_method_id = $_POST['payment_method_id'] ?? null;
    $notes = $_POST['notes'] ?? '';
    $created_by = $_SESSION['user_id'];

    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO purchase (supplier_id, purchase_no, purchase_date, subtotal, discount, total_amount, paid_amount, due_amount, payment_method_id, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$supplier_id, $purchase_no, $purchase_date, $subtotal, $discount, $total_amount, $paid_amount, $due_amount, $payment_method_id, $notes, $created_by]);
        $purchase_id = $pdo->lastInsertId();

        // Handle purchase items
        $product_ids = $_POST['product_id'];
        $colors = $_POST['color'] ?? [];
        $custom_colors = $_POST['custom_color'] ?? [];
        $quantities = $_POST['quantity'];
        $unit_prices = $_POST['unit_price'];
        $total_prices = $_POST['total_price'];
        
        for ($i = 0; $i < count($product_ids); $i++) {
            if (!empty($product_ids[$i])) {
                // Get product details for product_code
                $stmt = $pdo->prepare("SELECT product_name, product_code FROM products WHERE id = ?");
                $stmt->execute([$product_ids[$i]]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                $product_code = $product ? $product['product_code'] : '';
                
                // Determine the final color value
                $color = '';
                if (isset($colors[$i]) && $colors[$i] === 'custom') {
                    // If custom is selected, use the custom color input
                    $color = $custom_colors[$i] ?? '';
                } elseif (isset($colors[$i]) && $colors[$i] !== '') {
                    // If a predefined color is selected
                    $color = $colors[$i];
                }
                
                $stmt = $pdo->prepare("INSERT INTO purchase_items (purchase_id, product_id, product_code, color, purchase_price, sale_price, quantity, purchase_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$purchase_id, $product_ids[$i], $product_code, $color, $unit_prices[$i], $unit_prices[$i], $quantities[$i], $total_prices[$i]]);

                // Add to stock_items table for inventory management
                try {
        
                    $stmt = $pdo->prepare("INSERT INTO stock_items (product_id, purchase_item_id, product_code, color, quantity, purchase_price, sale_price, stock_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 'available')");
                    $stmt->execute([$product_ids[$i], $purchase_id, $product_code, $color, $quantities[$i], $unit_prices[$i], $unit_prices[$i]]);
                } catch (Exception $e) {
                    // Continue with purchase even if stock update fails
                }

                // Check for low stock and create notification if needed
                try {
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
                } catch (Exception $e) {
                    // If any of these operations fail, skip notification
                }
            }
        }

        $pdo->commit();
        header("Location: add_purchase.php?success=added&purchase_id=" . $purchase_id);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: add_purchase.php?error=purchase_failed");
        exit;
    }
}

// Fetch suppliers and products for dropdowns
$suppliers = $pdo->query("SELECT * FROM supplier ORDER BY supplier_name")->fetchAll(PDO::FETCH_ASSOC);
$products = $pdo->query("SELECT * FROM products ORDER BY product_name")->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>
<style>
.purchase-item-row {
    background-color: #f8f9fa;
    border-radius: 5px;
    padding: 10px;
    margin-bottom: 10px;
}

/* Supplier dropdown styling */
.supplier-dropdown-container {
    position: relative;
    width: 100%;
}

.supplier-dropdown-btn {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
    font-weight: 400;
    line-height: 1.5;
    color: #212529;
    background-color: #fff;
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
    cursor: pointer;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    text-align: left;
}

.supplier-dropdown-btn:hover {
    border-color: #86b7fe;
}

.supplier-dropdown-btn:focus {
    border-color: #86b7fe;
    outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.dropdown-arrow {
    transition: transform 0.2s ease;
}

.supplier-dropdown-btn.active .dropdown-arrow {
    transform: rotate(180deg);
}

.supplier-dropdown-list {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1000;
    display: none;
    background-color: #fff;
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    max-height: 300px;
    overflow-y: auto;
    margin-top: 2px;
}

.supplier-dropdown-list.show {
    display: block;
}

.supplier-search-box {
    padding: 0.75rem;
    border-bottom: 1px solid #dee2e6;
}

.supplier-search-box input {
    width: 100%;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}

.supplier-dropdown-separator {
    height: 1px;
    background-color: #dee2e6;
    margin: 0;
}

.supplier-option {
    padding: 0.75rem 1rem;
    cursor: pointer;
    transition: background-color 0.15s ease-in-out;
    border-bottom: 1px solid #f8f9fa;
}

.supplier-option:hover {
    background-color: #f8f9fa;
}

.supplier-option.selected {
    background-color: #0d6efd;
    color: #fff;
}

.supplier-option.hidden {
    display: none;
}
</style>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-10 ms-sm-auto px-4 py-5" style="margin-top: 25px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0"><i class="bi bi-cart-plus text-primary"></i> Add New Purchase</h2>
                <!-- <a href="purchases.php" class="btn btn-secondary">
                    <i class="bi bi-list-ul"></i> View Purchase History
                </a> -->
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <?php
                    if ($_GET['success'] === 'added') echo "Purchase added successfully! <a href='purchase_details.php?id=" . $_GET['purchase_id'] . "' target='_blank'>View Purchase Details</a>";
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    Error: Purchase failed to add. Please try again.
                </div>
            <?php endif; ?>

            <!-- Add Purchase Form -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-cart-plus"></i> Create New Purchase</h5>
                </div>
                <div class="card-body">
                    <form method="post" id="purchaseForm">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Supplier</label>
                                <div class="supplier-dropdown-container">
                                    <button type="button" class="supplier-dropdown-btn" id="supplierDropdownBtn">
                                        <span class="supplier-selected-text">Select Supplier</span>
                                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                                    </button>
                                    <div class="supplier-dropdown-list" id="supplierDropdownList">
                                        <div class="supplier-search-box">
                                            <input type="text" id="supplierSearchInput" class="form-control form-control-sm" placeholder="🔍 Search suppliers...">
                                        </div>
                                        <div class="supplier-dropdown-separator"></div>
                                        <?php foreach ($suppliers as $supplier): ?>
                                            <div class="supplier-option" data-value="<?= $supplier['id'] ?>">
                                                🏢 <?= htmlspecialchars($supplier['supplier_name']) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="supplier_id" id="supplierSelect" required>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3 d-none">
                                <label class="form-label">Purchase No</label>
                                <input type="text" name="purchase_no" class="form-control" value="<?= get_next_invoice_no($pdo) ?>" readonly>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Purchase Date</label>
                                <input type="date" name="purchase_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn btn-outline-primary w-100" onclick="openAddSupplierModal()">
                                    <i class="bi bi-plus-circle"></i> Add New Supplier
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Purchase Items</label>
                            <div id="purchaseItems">
                                <div class="row mb-2 align-items-end">
                                    <div class="col-md-2">
                                        <label class="form-label small">Product</label>
                                        <select name="product_id[]" class="form-control product-select" required>
                                            <option value="">Select Product</option>
                                            <?php foreach ($products as $product): ?>
                                                <option value="<?= $product['id'] ?>" data-unit="<?= $product['product_unit'] ?>"><?= htmlspecialchars($product['product_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Color/Names</label>
                                        <select name="color[]" class="form-control color-select" style="height: 38px;">
                                            <option value="">Select Color</option>
                                            <option value="Red">Red</option>
                                            <option value="Blue">Blue</option>
                                            <option value="Green">Green</option>
                                            <option value="Yellow">Yellow</option>
                                            <option value="Black">Black</option>
                                            <option value="White">White</option>
                                            <option value="Purple">Purple</option>
                                            <option value="custom">+ Add Custom Color</option>
                                        </select>
                                        <input type="text" name="custom_color[]" class="form-control custom-color-input mt-1" placeholder="Enter custom color names" style="height: 32px; display: none;">
                                        <!-- <small class="text-muted">Select from list or add custom names</small> -->
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Quantity</label>
                                        <input type="number" step="0.01" name="quantity[]" class="form-control quantity" placeholder="Qty" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Unit Price</label>
                                        <input type="number" step="0.01" name="unit_price[]" class="form-control unit-price" placeholder="Unit Price" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Total</label>
                                        <input type="number" step="0.01" name="total_price[]" class="form-control total-price" placeholder="Total" readonly>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-danger btn-sm remove-item">Remove</button>
                                        <button type="button" class="btn btn-secondary btn-sm" id="addItem">Add Item</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Details Section -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">💰 Payment Details</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-2 mb-3">
                                        <label class="form-label">Subtotal</label>
                                        <input type="number" step="0.01" name="subtotal" id="subtotal" class="form-control" readonly>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <label class="form-label">Discount</label>
                                        <input type="number" step="0.01" name="discount" id="discount" class="form-control" value="0.00" step="0.01">
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <label class="form-label">Total Amount</label>
                                        <input type="number" step="0.01" name="total_amount" id="grandTotal" class="form-control" required readonly>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <label class="form-label">Paid Amount</label>
                                        <input type="number" step="0.01" name="paid_amount" id="paid_amount" class="form-control" value="0.00" step="0.01">
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <label class="form-label">Remaining Amount</label>
                                        <input type="number" step="0.01" name="due_amount" id="due_amount" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Payment Method</label>
                                        <select name="payment_method_id" class="form-control">
                                            <option value="">Select Method</option>
                                            <?php 
                                            $payment_methods = $pdo->query("SELECT * FROM payment_method WHERE status = 1")->fetchAll(PDO::FETCH_ASSOC);
                                            foreach ($payment_methods as $method): ?>
                                                <option value="<?= $method['id'] ?>"><?= htmlspecialchars($method['method']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-5 mb-3">
                                        <label class="form-label">Details/Notes</label>
                                        <textarea name="notes" class="form-control" rows="3" placeholder="Enter payment details, notes, or other information"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" name="add_purchase">Add Purchase</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" aria-labelledby="addSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSupplierModalLabel">
                    <i class="bi bi-plus-circle"></i> Add New Supplier
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addSupplierForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="supplierName" class="form-label">Supplier Name *</label>
                            <input type="text" class="form-control" id="supplierName" name="supplier_name" required placeholder="Enter supplier name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="supplierContact" class="form-label">Contact Number</label>
                            <input type="text" class="form-control" id="supplierContact" name="supplier_contact" placeholder="Enter contact number">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="supplierEmail" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="supplierEmail" name="supplier_email" placeholder="Enter email address">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="supplierOpeningBalance" class="form-label">Opening Balance (Rs.)</label>
                            <input type="number" class="form-control" id="supplierOpeningBalance" name="opening_balance" step="0.01" min="0" placeholder="0.00">
                            <small class="text-muted">Enter the opening balance for this supplier</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="supplierAddress" class="form-label">Address</label>
                        <textarea class="form-control" id="supplierAddress" name="supplier_address" rows="3" placeholder="Enter supplier address"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="saveSupplier()">
                    <i class="bi bi-check-circle"></i> Add Supplier
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Notification function to replace alerts
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
    
    // Allow manual close
    notification.querySelector('.btn-close').addEventListener('click', () => {
        notification.remove();
    });
}

document.getElementById('addItem').addEventListener('click', function() {
    const container = document.getElementById('purchaseItems');
    const newRow = container.children[0].cloneNode(true);
    
    // Reset all form fields
    newRow.querySelectorAll('input, select').forEach(input => {
        input.value = '';
    });
    
    // Hide custom color input in new row
    const customColorInput = newRow.querySelector('.custom-color-input');
    if (customColorInput) {
        customColorInput.style.display = 'none';
        customColorInput.required = false;
    }
    
    container.appendChild(newRow);
    
    // Recalculate grand total after adding new row
    calculateGrandTotal();
});

// Payment calculations
document.getElementById('discount').addEventListener('input', calculateFinalAmount);
document.getElementById('paid_amount').addEventListener('input', calculateRemainingAmount);

function calculateFinalAmount() {
    const subtotal = parseFloat(document.getElementById('subtotal').value) || 0;
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    
    const finalAmount = subtotal - discount;
    document.getElementById('grandTotal').value = finalAmount.toFixed(2);
    
    // Recalculate remaining amount
    calculateRemainingAmount();
}

function calculateRemainingAmount() {
    const finalAmount = parseFloat(document.getElementById('grandTotal').value) || 0;
    const paidAmount = parseFloat(document.getElementById('paid_amount').value) || 0;
    
    const remaining = finalAmount - paidAmount;
    document.getElementById('due_amount').value = remaining.toFixed(2);
}

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-item')) {
        if (document.querySelectorAll('.remove-item').length > 1) {
            e.target.closest('.row').remove();
            // Recalculate grand total after removing row
            calculateGrandTotal();
        }
    }
});

document.addEventListener('input', function(e) {
    if (e.target.classList.contains('quantity') || e.target.classList.contains('unit-price')) {
        const row = e.target.closest('.row');
        const quantity = row.querySelector('.quantity').value;
        const unitPrice = row.querySelector('.unit-price').value;
        const totalPrice = row.querySelector('.total-price');
        
        if (quantity && unitPrice) {
            totalPrice.value = (quantity * unitPrice).toFixed(2);
        } else {
            totalPrice.value = '';
        }
        
        // Calculate grand total
        calculateGrandTotal();
    }
});

function calculateGrandTotal() {
    const totalPrices = document.querySelectorAll('.total-price');
    let grandTotal = 0;
    
    totalPrices.forEach(input => {
        if (input.value && !isNaN(input.value)) {
            grandTotal += parseFloat(input.value);
        }
    });
    
    document.getElementById('subtotal').value = grandTotal.toFixed(2);
    document.getElementById('grandTotal').value = grandTotal.toFixed(2);
    
    // Recalculate final amount with discount
    calculateFinalAmount();
}

// Calculate grand total when page loads
document.addEventListener('DOMContentLoaded', function() {
    calculateGrandTotal();
    
    // Initialize color dropdown functionality
    initializeColorDropdowns();
    
    // Form validation for colors/names
    document.getElementById('purchaseForm').addEventListener('submit', function(e) {
        const colorSelects = document.querySelectorAll('select[name="color[]"]');
        const customColorInputs = document.querySelectorAll('input[name="custom_color[]"]');
        let isValid = true;
        
                colorSelects.forEach((select, index) => {
            if (select.value === 'custom') {
                // Check if custom color input has value
                const customInput = select.closest('.row').querySelector('.custom-color-input');
                
                if (!customInput.value.trim()) {
                    showNotification(`Please enter a custom color name for item ${index + 1}`, 'warning');
                    isValid = false;
                    e.preventDefault();
                    return;
                }
            } else if (select.value === '') {
                showNotification(`Please select a color for item ${index + 1}`, 'warning');
                isValid = false;
                    e.preventDefault();
                    return;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
        }
    });
});

// Initialize supplier dropdown functionality
document.addEventListener('DOMContentLoaded', function() {
    const dropdownBtn = document.getElementById('supplierDropdownBtn');
    const dropdownList = document.getElementById('supplierDropdownList');
    const supplierSelect = document.getElementById('supplierSelect');
    const supplierSearchInput = document.getElementById('supplierSearchInput');
    const selectedText = document.querySelector('.supplier-selected-text');
    
    // Toggle dropdown on click
    dropdownBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdownList.classList.toggle('show');
        dropdownBtn.classList.toggle('active');
        
        if (dropdownList.classList.contains('show')) {
            supplierSearchInput.focus();
        }
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!dropdownBtn.contains(e.target) && !dropdownList.contains(e.target)) {
            dropdownList.classList.remove('show');
            dropdownBtn.classList.remove('active');
        }
    });
    
    // Handle supplier option selection
    dropdownList.addEventListener('click', function(e) {
        const supplierOption = e.target.closest('.supplier-option');
        if (supplierOption) {
            const value = supplierOption.dataset.value;
            const text = supplierOption.textContent;
            
            // Update hidden input and display text
            supplierSelect.value = value;
            selectedText.textContent = text;
            
            // Update visual selection
            dropdownList.querySelectorAll('.supplier-option').forEach(item => {
                item.classList.remove('selected');
            });
            supplierOption.classList.add('selected');
            
            // Close dropdown
            dropdownList.classList.remove('show');
            dropdownBtn.classList.remove('active');
        }
    });
    
    // Handle search functionality
    supplierSearchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const supplierOptions = dropdownList.querySelectorAll('.supplier-option');
        
        supplierOptions.forEach(option => {
            const optionText = option.textContent.toLowerCase();
            if (optionText.includes(searchTerm)) {
                option.classList.remove('hidden');
            } else {
                option.classList.add('hidden');
            }
        });
    });
    
    // Clear search when dropdown opens
    dropdownBtn.addEventListener('click', function() {
        supplierSearchInput.value = '';
        dropdownList.querySelectorAll('.supplier-option').forEach(option => {
            option.classList.remove('hidden');
        });
    });
});

// Color dropdown functionality
function initializeColorDropdowns() {
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('color-select')) {
            const row = e.target.closest('.row');
            const customInput = row.querySelector('.custom-color-input');
            
            if (e.target.value === 'custom') {
                customInput.style.display = 'block';
                customInput.required = true;
                e.target.required = false;
            } else {
                customInput.style.display = 'none';
                customInput.required = false;
                e.target.required = true;
            }
        }
    });
}





// Supplier modal functionality
function openAddSupplierModal() {
    // Clear the form
    document.getElementById('addSupplierForm').reset();
    // Show the modal
    new bootstrap.Modal(document.getElementById('addSupplierModal')).show();
}

function saveSupplier() {
    const form = document.getElementById('addSupplierForm');
    const formData = new FormData(form);
    
    // Validate required fields
    if (!formData.get('supplier_name').trim()) {
        showNotification('Supplier name is required!', 'warning');
        return;
    }
    
    // Send data to PHP endpoint
    fetch('add_supplier_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Add new supplier to the select dropdown
            const supplierSelect = document.getElementById('supplierSelect');
            const newOption = document.createElement('option');
            newOption.value = data.supplier_id;
            newOption.textContent = formData.get('supplier_name');
            supplierSelect.appendChild(newOption);
            
            // Select the new supplier
            supplierSelect.value = data.supplier_id;
            
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('addSupplierModal')).hide();
            
            // Show success message
            showNotification('Supplier added successfully!', 'success');
        } else {
            showNotification('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred while adding supplier', 'error');
    });
}
</script>

<?php include 'includes/footer.php'; ?>