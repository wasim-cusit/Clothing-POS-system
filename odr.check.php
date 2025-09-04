<?php
require_once 'includes/auth.php';
require_login();
require_once 'includes/config.php';

$activePage = 'add_order';

// Tables already exist in database - using orders, order_items, and unit_prices

// AJAX: return customer balance (sum of remaining from orders)
if (isset($_GET['balance_for'])) {
    header('Content-Type: application/json');
    $cid = (int)$_GET['balance_for'];
    if ($cid <= 0) {
        echo json_encode(['success' => false, 'balance' => 0]);
        exit;
    }
    try {
        // Get customer's opening balance
        $stmt = $pdo->prepare('SELECT opening_balance FROM customer WHERE id = ?');
        $stmt->execute([$cid]);
        $opening_balance = (float)$stmt->fetchColumn();
        
        // Get sum of remaining amounts from orders
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(remaining_amount),0) FROM orders WHERE customer_id = ?');
        $stmt->execute([$cid]);
        $orders_balance = (float)$stmt->fetchColumn();
        
        // Total balance = opening balance + orders balance
        $total_balance = $opening_balance + $orders_balance;
        
        echo json_encode(['success' => true, 'balance' => number_format($total_balance, 2, '.', '')]);
        exit;
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'balance' => 0]);
        exit;
    }
}

// Handle delete order


if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    if ($deleteId > 0) {
        try {
            $stmt = $pdo->prepare('DELETE FROM orders WHERE id = ?');
            $stmt->execute([$deleteId]);
            header('Location: add_order.php?success=deleted');
            exit;
        } catch (Throwable $e) {
            header('Location: add_order.php?error=delete_failed');
            exit;
        }
    }
}
     // Handle form submit
     if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
     
     $customer_id = null;
     $walk_in_customer_name = null;
    
    // Handle walk-in customer vs regular customer
    if ($_POST['customer_id'] === 'walk_in') {
        $walk_in_customer_name = trim($_POST['walk_in_cust_name'] ?? '');
        if (empty($walk_in_customer_name)) {
            header('Location: add_order.php?error=walk_in_name_required');
            exit;
        }
    } else {
        $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        if (!$customer_id) {
            header('Location: add_order.php?error=customer_required');
            exit;
        }
    }
    
    // Generate order number
    $order_no = 'ORD-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $order_date = $_POST['order_date'] ?? date('Y-m-d');
    $delivery_date = $_POST['delivery_date'] ?? null;
    $sub_total = (float)($_POST['sub_total'] ?? 0);
    $discount = (float)($_POST['discount'] ?? 0);
    $total_amount = (float)($_POST['total_amount'] ?? 0);
    $paid_amount = (float)($_POST['paid'] ?? 0);
    $remaining_amount = (float)($_POST['remaining'] ?? max($total_amount - $paid_amount, 0));
    $details = $_POST['details'] ?? null;
    $created_by = $_SESSION['user_id'] ?? null;

                   $pdo->beginTransaction();
      try {
         
         // Validate required fields
         if (empty($order_no) || empty($order_date) || empty($total_amount)) {
             throw new Exception('Required fields are missing: order_no, order_date, or total_amount');
         }
         
                 // For walk-in customers, we'll store the name in details field and set customer_id to NULL
        if ($_POST['customer_id'] === 'walk_in') {
            $walk_in_details = "Walk-in Customer: " . $walk_in_customer_name;
            if ($details) {
                $details = $walk_in_details . "\n" . $details;
            } else {
                $details = $walk_in_details;
            }
        }
        
        
        
        $stmt = $pdo->prepare("INSERT INTO orders (order_no, customer_id, order_date, delivery_date, sub_total, discount, total_amount, paid_amount, remaining_amount, details, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $result = $stmt->execute([$order_no, $customer_id, $order_date, $delivery_date, $sub_total, $discount, $total_amount, $paid_amount, $remaining_amount, $details, $created_by]);
        
        if (!$result) {
            error_log('Failed to execute order insert statement');
            error_log('PDO error info: ' . print_r($stmt->errorInfo(), true));
            throw new Exception('Failed to insert order into database');
        }
         $order_id = (int)$pdo->lastInsertId();

                           // Items arrays - use description as the main field since we don't have product_id
          $descriptions = $_POST['description'] ?? [];
          $quantities = $_POST['quantity'] ?? [];
          $unit_prices = $_POST['unit_price'] ?? [];
          $total_prices = $_POST['total_price'] ?? [];
          
          // Validate that we have at least one item
          if (empty($descriptions) || empty($quantities) || empty($unit_prices)) {
              throw new Exception('Order items data is missing or incomplete');
          }
          
          // Ensure all arrays have the same length and process each item
          $numRows = count($descriptions);
          $itemsInserted = 0;
          
          for ($i = 0; $i < $numRows; $i++) {
              $descFromPost = isset($descriptions[$i]) ? trim((string)$descriptions[$i]) : '';
              $qty = isset($quantities[$i]) ? (int)$quantities[$i] : 0;
              $uprice = isset($unit_prices[$i]) ? (float)$unit_prices[$i] : 0;
              $tprice = isset($total_prices[$i]) ? (float)$total_prices[$i] : ($qty * $uprice);
  
              // Skip empty rows
              if ($descFromPost === '' || $qty <= 0 || $uprice <= 0) { 
                  continue; 
              }
  
              // Insert order item with description (no product_id for now)
              $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, description, quantity, unit_price, total_price) VALUES (?,?,?,?,?,?)");
              $result = $stmt->execute([$order_id, null, $descFromPost, $qty, $uprice, $tprice]);
              
              if (!$result) {
                  throw new Exception('Failed to insert order item ' . $i . ' into database');
              }
              
              $itemsInserted++;
          }
          
          // Ensure at least one item was inserted
          if ($itemsInserted === 0) {
              throw new Exception('No valid order items were inserted');
          }

                                   $pdo->commit();
          header('Location: add_order.php?success=created&order_id=' . $order_id);
          exit;
      } catch (Throwable $e) {
          $pdo->rollBack();
          die('Failed to create order: ' . htmlspecialchars($e->getMessage()));
      }
}

// Ensure unit_prices table exists
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS unit_prices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unit_name VARCHAR(100) NOT NULL UNIQUE,
        unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        karegar_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        material_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        zakat_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

// Fetch dropdown data
$customers = $pdo->query("SELECT id, name FROM customer ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$products = $pdo->query("SELECT id, product_name, product_unit FROM products ORDER BY product_name")->fetchAll(PDO::FETCH_ASSOC);
try {
    $units = $pdo->query("SELECT unit_name, unit_price FROM unit_prices ORDER BY unit_name")->fetchAll(PDO::FETCH_ASSOC);
    // If no units exist, create some default ones
    if (empty($units)) {
        $pdo->exec("INSERT INTO unit_prices (unit_name, unit_price) VALUES 
            ('Meter', 100.00),
            ('Yard', 150.00),
            ('Piece', 200.00),
            ('Set', 500.00)
        ON DUPLICATE KEY UPDATE unit_price = VALUES(unit_price)");
        $units = $pdo->query("SELECT unit_name, unit_price FROM unit_prices ORDER BY unit_name")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $units = [];
}

// Fetch all orders
$orders = $pdo->query("SELECT o.*, c.name AS customer_name, u.username AS created_by_name FROM orders o LEFT JOIN customer c ON o.customer_id = c.id LEFT JOIN system_users u ON o.created_by = u.id ORDER BY o.id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Process orders to show walk-in customer names
foreach ($orders as &$order) {
    if (!$order['customer_name'] && $order['details']) {
        // Check if this is a walk-in customer by looking at details
        if (strpos($order['details'], 'Walk-in Customer:') === 0) {
            $lines = explode("\n", $order['details']);
            $walkInName = str_replace('Walk-in Customer:', '', $lines[0]);
            $order['customer_name'] = '🚶 ' . trim($walkInName);
        }
    }
}


include 'includes/header.php';
?>
<style>
  /* Enhanced styling for the order form */
  .form-label.required::after {
    content: " *";
    color: #dc3545;
  }
  
  .balance-display {
    font-weight: 600;
    font-size: 1.1rem;
  }
  
  .balance-negative {
    color: #dc3545 !important;
  }
  
  .balance-positive {
    color: #fd7e14 !important;
  }
  
  .balance-zero {
    color: #6c757d !important;
  }
  
  /* Modal improvements */
  .modal-lg .modal-body {
    padding: 1.5rem;
  }
  
  .form-text {
    font-size: 0.875rem;
    margin-top: 0.25rem;
  }
  
  /* Button improvements */
  .btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
  }
  
  /* Alert improvements */
  .alert {
    border-radius: 0.5rem;
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
  }
  
  /* Order form specific styles */
  .order-item-row {
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 1rem;
    margin-bottom: 1rem;
  }
  
  .order-item-row:last-child {
    border-bottom: none;
  }
  
  /* Input group improvements */
  .input-group-text {
    background-color: #f8f9fa;
    border-color: #ced4da;
  }
  
  /* Section headers */
  .text-primary.border-bottom {
    border-color: #0d6efd !important;
  }
  
  /* Form validation */
  .form-control:invalid,
  .form-select:invalid {
    border-color: #dc3545;
  }
  
  .form-control:valid,
  .form-select:valid {
    border-color: #198754;
  }
  

  
  /* Customer dropdown styling */
  .customer-dropdown-container {
    position: relative;
    width: 100%;
  }
  
  .customer-dropdown-btn {
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
  
  .customer-dropdown-btn:hover {
    border-color: #86b7fe;
  }
  
  .customer-dropdown-btn:focus {
    border-color: #86b7fe;
    outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
  }
  
  .dropdown-arrow {
    transition: transform 0.2s ease;
  }
  
  .customer-dropdown-btn.active .dropdown-arrow {
    transform: rotate(180deg);
  }
  
  .customer-dropdown-list {
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
  
  .customer-dropdown-list.show {
    display: block;
  }
  
  .customer-search-box {
    padding: 0.75rem;
    border-bottom: 1px solid #dee2e6;
  }
  
  .customer-search-box input {
    width: 100%;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
  }
  
  .customer-dropdown-separator {
    height: 1px;
    background-color: #dee2e6;
    margin: 0;
  }
  
  .customer-option {
    padding: 0.75rem 1rem;
    cursor: pointer;
    transition: background-color 0.15s ease-in-out;
    border-bottom: 1px solid #f8f9fa;
  }
  
  .customer-option:hover {
    background-color: #f8f9fa;
  }
  
  .customer-option.selected {
    background-color: #0d6efd;
    color: #fff;
  }
  
  .customer-option.hidden {
    display: none;
  }
</style>
<?php include 'includes/sidebar.php'; ?>
        <main class="col-md-10 ms-sm-auto px-4 py-5" style="margin-top: 25px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0"><i class="bi bi-clipboard-plus text-primary"></i> Create New Order</h2>
            </div>

                         
             
             <?php if (isset($_GET['success'])): ?>
                 <?php if ($_GET['success'] === 'created'): ?>
                     <div class="alert alert-success">
                         <i class="bi bi-check-circle"></i> Order created successfully! 
                         <a href='order_details.php?id=<?= $_GET['order_id'] ?? '' ?>' target='_blank'>View Order Details</a>
                     </div>
                 <?php elseif ($_GET['success'] === 'deleted'): ?>
                     <div class="alert alert-success">
                         <i class="bi bi-check-circle"></i> Order deleted successfully!
                     </div>
                 <?php endif; ?>
             <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> 
                    <?php 
                    $error = $_GET['error'];
                    switch($error) {
                        case 'walk_in_name_required':
                            echo 'Walk-in customer name is required.';
                            break;
                        case 'customer_required':
                            echo 'Please select a customer.';
                            break;
                        case 'delete_failed':
                            echo 'Failed to delete order. Please try again.';
                            break;
                        default:
                            echo htmlspecialchars(urldecode($error));
                    }
                    ?>
                </div>
            <?php endif; ?>

            <!-- Create Order Form -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-clipboard-plus"></i> Create New Order</h5>
                </div>
                <div class="card-body">
                                         <form method="post" id="orderForm" onsubmit="return validateForm();">
                        <!-- Customer Information Section -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                    <i class="bi bi-person-circle"></i> Customer Information
                                </h6>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold">Customer <span class="text-danger">*</span></label>
                                <div class="customer-dropdown-container">
                                    <button type="button" class="customer-dropdown-btn" id="customerDropdownBtn">
                                        <span class="customer-selected-text">Select Customer</span>
                                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                                    </button>
                                    <div class="customer-dropdown-list" id="customerDropdownList">
                                        <div class="customer-search-box">
                                            <input type="text" id="customerSearchInput" class="form-control form-control-sm" placeholder="🔍 Search customers...">
                                        </div>
                                        <div class="customer-dropdown-separator"></div>
                                        <div class="customer-option" data-value="walk_in">
                                            🚶 Walk-in Customer
                                        </div>
                                        <?php foreach ($customers as $c): ?>
                                            <div class="customer-option" data-value="<?= $c['id'] ?>">
                                                👤 <?= htmlspecialchars($c['name']) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="customer_id" id="customerSelect" required>
                                </div>
                            </div>

                            <div class="col-md-3 mb-3" id="walkInCustomerField" style="display: none;">
                                <label class="form-label fw-bold">Walk-in Customer Name <span class="text-danger">*</span></label>
                                <input type="text" name="walk_in_cust_name" class="form-control" placeholder="Enter customer name" required>
                                <!-- <small class="text-muted">This field appears when "Walk-in Customer" is selected</small> -->
                            </div>

<script>
    // Custom dropdown functionality (no jQuery required)

    // Always initialize the basic functionality (works with or without jQuery)
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize customer dropdown functionality
        const dropdownBtn = document.getElementById('customerDropdownBtn');
        const dropdownList = document.getElementById('customerDropdownList');
        const customerSelect = document.getElementById('customerSelect');
        const customerSearchInput = document.getElementById('customerSearchInput');
        const selectedText = document.querySelector('.customer-selected-text');
        
        // Toggle dropdown on click
        dropdownBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdownList.classList.toggle('show');
            dropdownBtn.classList.toggle('active');
            
            if (dropdownList.classList.contains('show')) {
                customerSearchInput.focus();
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!dropdownBtn.contains(e.target) && !dropdownList.contains(e.target)) {
                dropdownList.classList.remove('show');
                dropdownBtn.classList.remove('active');
            }
        });
        
        // Handle customer option selection
        dropdownList.addEventListener('click', function(e) {
            const customerOption = e.target.closest('.customer-option');
            if (customerOption) {
                const value = customerOption.dataset.value;
                const text = customerOption.textContent;
                
                                 // Update hidden input and display text
                 customerSelect.value = value;
                 selectedText.textContent = text;
                 
                 // Update visual selection
                 dropdownList.querySelectorAll('.customer-option').forEach(item => {
                     item.classList.remove('selected');
                 });
                 customerOption.classList.add('selected');
                 
                 // Close dropdown
                 dropdownList.classList.remove('show');
                 dropdownBtn.classList.remove('active');
                 
                 // Handle customer selection
                 handleCustomerSelection(value);
            }
        });
        
        // Handle search functionality
        customerSearchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const customerOptions = dropdownList.querySelectorAll('.customer-option');
            
            customerOptions.forEach(option => {
                const optionText = option.textContent.toLowerCase();
                if (optionText.includes(searchTerm) || option.dataset.value === 'walk_in') {
                    option.classList.remove('hidden');
                } else {
                    option.classList.add('hidden');
                }
            });
        });
        
        // Clear search when dropdown opens
        dropdownBtn.addEventListener('click', function() {
            customerSearchInput.value = '';
            dropdownList.querySelectorAll('.customer-option').forEach(option => {
                option.classList.remove('hidden');
            });
        });
    });

    // Function to handle customer selection changes
    function handleCustomerSelection(customerId) {
        const walkInField = document.getElementById('walkInCustomerField');
        const walkInInput = walkInField.querySelector('input[name="walk_in_cust_name"]');
        const balanceInput = document.getElementById('balance');
        
        // Check if 'Walk-in Customer' is selected
        if (customerId === 'walk_in') {
            walkInField.style.display = 'block';  // Show the walk-in input field
            walkInInput.required = true; // Make it required
            walkInInput.focus(); // Focus on the input field
            
            // Reset balance display for walk-in customer
            balanceInput.value = '0.00';
            balanceInput.classList.remove('text-danger', 'text-warning', 'fw-bold');
            balanceInput.title = 'Walk-in customer - no balance tracking';
        } else {
            walkInField.style.display = 'none';  // Hide the walk-in input field
            walkInInput.required = false; // Remove required attribute
            walkInInput.value = ''; // Clear the input
            
            // Update customer balance if a real customer is selected
            if (customerId && customerId !== '') {
                updateCustomerBalance(customerId);
            } else {
                // Reset balance display for no selection
                balanceInput.value = '0.00';
                balanceInput.classList.remove('text-danger', 'text-warning', 'fw-bold');
                balanceInput.title = '';
            }
        }
    }
</script>

                            <div class="col-md-2 mb-3">
                                <label class="form-label fw-bold">Current Balance</label>
                                <div class="input-group">
                                    <span class="input-group-text">PKR</span>
                                    <input type="text" class="form-control" id="balance" value="0.00" readonly>
                                </div>
                                <!-- <small class="text-muted">Opening balance + pending orders</small> -->
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label fw-bold">Order Date <span class="text-danger">*</span></label>
                                <input type="date" name="order_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label fw-bold">Delivery Date</label>
                                <input type="date" name="delivery_date" class="form-control" min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-3 mb-3" style="margin-top: 30px;">
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                                    <i class="bi bi-person-plus"></i> Add New Customer
                                </button>
                            </div>
                        </div>

                        <!-- Order Items Section -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                    <i class="bi bi-box-seam"></i> Order Items
                                </h6>
                                <div id="orderItems">
                                    <div class="row mb-3 align-items-end order-item-row">
                                        <div class="col-md-3">
                                            <label class="form-label small fw-bold">Unit/Service <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <select name="description[]" class="form-select unit-select" required>
                                                    <option value="">Select Unit</option>
                                                    <?php foreach ($units as $u): ?>
                                                        <option value="<?= htmlspecialchars($u['unit_name']) ?>" 
                                                                data-price="<?= number_format((float)$u['unit_price'], 2, '.', '') ?>">
                                                            📦 <?= htmlspecialchars($u['unit_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <a href="add_unit.php" target="_blank" class="btn btn-outline-secondary" title="Add Unit">
                                                    <i class="bi bi-plus"></i>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-bold">Unit Price <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text">PKR</span>
                                                <input type="number" step="0.01" name="unit_price[]" class="form-control unit-price" placeholder="0.00" required min="0.01">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-bold">Quantity <span class="text-danger">*</span></label>
                                            <input type="number" step="1" min="1" name="quantity[]" class="form-control quantity" placeholder="Qty" required min="1">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-bold">Total Amount</label>
                                            <div class="input-group">
                                                <span class="input-group-text">PKR</span>
                                                <input type="number" step="0.01" name="total_price[]" class="form-control total-price" placeholder="0.00" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <button type="button" class="btn btn-danger btn-sm remove-item" title="Remove Item">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                            <button type="button" class="btn btn-success btn-sm" id="addItem" title="Add Another Item">
                                                <i class="bi bi-plus-circle-fill"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                                    <!-- Pricing Summary Section -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                    <i class="bi bi-calculator"></i> Pricing Summary
                                </h6>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label fw-bold">Sub Total</label>
                                <div class="input-group">
                                    <span class="input-group-text">PKR</span>
                                    <input type="number" step="0.01" name="sub_total" id="subTotal" class="form-control" readonly>
                                </div>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label fw-bold">Discount</label>
                                <div class="input-group">
                                    <span class="input-group-text">PKR</span>
                                    <input type="number" step="0.01" name="discount" id="discount" class="form-control" value="0.00" min="0">
                                </div>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label fw-bold text-success">Final Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">PKR</span>
                                    <input type="number" step="0.01" name="total_amount" id="grandTotal" class="form-control fw-bold" required readonly>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Information Section -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                    <i class="bi bi-credit-card"></i> Payment Information
                                </h6>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label fw-bold">Paid Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">PKR</span>
                                    <input type="number" step="0.01" name="paid" id="paid" class="form-control" value="0.00" min="0">
                                </div>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label fw-bold text-warning">Remaining Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">PKR</span>
                                    <input type="number" step="0.01" name="remaining" id="remaining" class="form-control" readonly>
                                </div>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label fw-bold">Order Details & Notes</label>
                                <textarea name="details" class="form-control" rows="3" placeholder="Enter order details, measurements, special instructions, or other important notes..."></textarea>
                            </div>
                        </div>
                        
                                                 <!-- Submit Section -->
                         <div class="row">
                             <div class="col-12 text-center">
                                 <button type="submit" class="btn btn-primary btn-lg" name="create_order" id="submitOrderBtn">
                                     <i class="bi bi-check-circle"></i> Create Order
                                 </button>
                                 <button type="reset" class="btn btn-secondary btn-lg ms-2">
                                     <i class="bi bi-arrow-clockwise"></i> Reset Form
                                 </button>
                                 <button type="button" class="btn btn-warning btn-lg ms-2" onclick="testFormSubmission()">
                                     <i class="bi bi-bug"></i> Test Submit
                                 </button>
                             </div>
                         </div>
                    </form>
                </div>
            </div>

            <!-- Add Customer Modal -->
            <div class="modal fade" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-lg">
                <div class="modal-content">
                  <form id="addCustomerForm">
                    <div class="modal-header bg-primary text-white">
                      <h5 class="modal-title" id="addCustomerModalLabel">
                        <i class="bi bi-person-plus"></i> Add New Customer
                      </h5>
                      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      <div class="row">
                        <div class="col-md-6 mb-3">
                          <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                          <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="name" class="form-control" placeholder="Enter customer full name" required>
                          </div>
                        </div>
                        <div class="col-md-6 mb-3">
                          <label class="form-label fw-bold">Mobile Number <span class="text-danger">*</span></label>
                          <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                            <input type="text" name="mobile" class="form-control" placeholder="Enter mobile number" required>
                          </div>
                        </div>
                      </div>
                      <div class="row">
                        <div class="col-md-6 mb-3">
                          <label class="form-label fw-bold">Email Address</label>
                          <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="Enter email address">
                          </div>
                        </div>
                        <div class="col-md-6 mb-3">
                          <label class="form-label fw-bold">Address <span class="text-danger">*</span></label>
                          <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                            <input type="text" name="address" class="form-control" placeholder="Enter address" required>
                          </div>
                        </div>
                      </div>
                      <div class="row">
                        <div class="col-md-6 mb-3">
                          <label class="form-label fw-bold">Opening Balance</label>
                          <div class="input-group">
                            <span class="input-group-text">₨</span>
                            <input type="number" step="0.01" name="opening_balance" class="form-control" placeholder="0.00" value="0.00" min="0">
                          </div>
                          <small class="text-muted">Enter any existing balance the customer owes or credit they have</small>
                        </div>
                      </div>
                      <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Note:</strong> Only the customer name and mobile are required. Other fields are optional and can be filled later.
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                      </button>
                      <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Add Customer
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <!-- Existing Orders Section -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-list-ul"></i> Existing Orders</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($orders)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox display-4"></i>
                            <p class="mt-3">No orders found. Create your first order above!</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Order Date</th>
                                        <th>Delivery Date</th>
                                        <th>Total Amount</th>
                                        <th>Paid</th>
                                        <th>Remaining</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($order['order_no'] ?? 'N/A') ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($order['customer_name']): ?>
                                                    <?= htmlspecialchars($order['customer_name']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Walk-in Customer</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
                                            <td>
                                                <?php if ($order['delivery_date']): ?>
                                                    <?= date('M d, Y', strtotime($order['delivery_date'])) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not set</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">
                                                    PKR <?= number_format((float)$order['total_amount'], 2) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    PKR <?= number_format((float)$order['paid_amount'], 2) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                $remaining = (float)$order['remaining_amount'];
                                                $badgeClass = $remaining > 0 ? 'bg-warning' : 'bg-success';
                                                ?>
                                                <span class="badge <?= $badgeClass ?>">
                                                    PKR <?= number_format($remaining, 2) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($remaining > 0) {
                                                    echo '<span class="badge bg-warning">Pending</span>';
                                                } else {
                                                    echo '<span class="badge bg-success">Completed</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="order_details.php?id=<?= (int)$order['id'] ?>" 
                                                       class="btn btn-sm btn-info" title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="add_order.php?delete=<?= (int)$order['id'] ?>" 
                                                       class="btn btn-sm btn-danger" title="Delete Order"
                                                       onclick="return confirm('Are you sure you want to delete this order? This action cannot be undone.')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
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
    const container = document.getElementById('orderItems');
    const newRow = container.children[0].cloneNode(true);
    
    // Clear all input values in the new row
    newRow.querySelectorAll('input, select').forEach(input => input.value = '');
    
    // Ensure the remove button has the correct class and event handling
    const removeBtn = newRow.querySelector('.remove-item');
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            if (document.querySelectorAll('.order-item-row').length > 1) {
                this.closest('.order-item-row').remove();
                recalcTotals(); // Update totals after removing item
            }
        });
    }
    
    container.appendChild(newRow);
});

// Handle remove button clicks for existing and new rows
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-item') || e.target.closest('.remove-item')) {
        const removeBtn = e.target.classList.contains('remove-item') ? e.target : e.target.closest('.remove-item');
        const itemRow = removeBtn.closest('.order-item-row');
        
        if (document.querySelectorAll('.order-item-row').length > 1) {
            itemRow.remove();
            recalcTotals(); // Update totals after removing item
        }
    }
});

document.addEventListener('change', function(e) {
    if (e.target.classList.contains('unit-select')) {
        const row = e.target.closest('.order-item-row');
        const option = e.target.options[e.target.selectedIndex];
        const unitPrice = row.querySelector('.unit-price');
        const quantity = row.querySelector('.quantity');
        
        // Get price from data attribute
        const price = parseFloat(option.dataset.price) || 0;
        
        // Auto-fill unit price
        unitPrice.value = price > 0 ? price.toFixed(2) : '';
        
        // Clear total price since quantity might be empty
        const totalPrice = row.querySelector('.total-price');
        totalPrice.value = '';
        
        // Update totals
        recalcTotals();
    }
    if (e.target.id === 'discount' || e.target.id === 'paid') {
        recalcTotals();
    }
    if (e.target.id === 'customerSelect') {
        updateCustomerBalance(e.target.value);
    }
    
    // Also handle the customer dropdown selection
    if (e.target.closest('.customer-option')) {
        const customerOption = e.target.closest('.customer-option');
        const value = customerOption.dataset.value;
        if (value && value !== 'walk_in') {
            updateCustomerBalance(value);
        }
    }
});

// Calculate row totals and grand totals
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('quantity') || e.target.classList.contains('unit-price')) {
        const row = e.target.closest('.order-item-row');
        const qty = parseFloat(row.querySelector('.quantity').value || 0);
        const price = parseFloat(row.querySelector('.unit-price').value || 0);
        const totalPrice = row.querySelector('.total-price');
        
        if (qty > 0 && price > 0) {
            totalPrice.value = (qty * price).toFixed(2);
        } else {
            totalPrice.value = '';
        }
        
        recalcTotals();
    }
});

function recalcTotals() {
    let sub = 0;
    document.querySelectorAll('.total-price').forEach(el => {
        const v = parseFloat(el.value || 0);
        if (!isNaN(v)) {
            sub += v;
        }
    });
    
    const discount = parseFloat(document.getElementById('discount').value || 0);
    const paid = parseFloat(document.getElementById('paid').value || 0);

    const total = Math.max(sub - discount, 0);
    const remaining = Math.max(total - paid, 0);

    document.getElementById('subTotal').value = sub.toFixed(2);
    document.getElementById('grandTotal').value = total.toFixed(2);
    document.getElementById('remaining').value = remaining.toFixed(2);
}

// Initialize totals on load
document.addEventListener('DOMContentLoaded', function() {
    recalcTotals();
});

// Form validation function
function validateForm() {
    const customerSelect = document.getElementById('customerSelect');
    const walkInNameField = document.querySelector('input[name="walk_in_cust_name"]');
    const orderItems = document.querySelectorAll('.order-item-row');
    let hasValidItems = false;
    
    // Check if customer is selected
    if (!customerSelect.value || customerSelect.value === '') {
        showNotification('Please select a customer before creating an order.', 'warning');
        const dropdownBtn = document.getElementById('customerDropdownBtn');
        if (dropdownBtn) dropdownBtn.focus();
        return false;
    }
    
    // Check walk-in customer name if walk-in is selected
    if (customerSelect.value === 'walk_in') {
        if (!walkInNameField.value.trim()) {
            showNotification('Please enter the walk-in customer name.', 'warning');
            walkInNameField.focus();
            return false;
        }
        
        // Validate walk-in customer name length
        if (walkInNameField.value.trim().length < 2) {
            showNotification('Walk-in customer name must be at least 2 characters long.', 'warning');
            walkInNameField.focus();
            return false;
        }
    }
    
    // Check if at least one order item has data
    orderItems.forEach((item, index) => {
        const description = item.querySelector('select[name="description[]"]').value;
        const quantity = item.querySelector('input[name="quantity[]"]').value;
        const unitPrice = item.querySelector('input[name="unit_price[]"]').value;
        
        // Check if this row has any data
        if (description || quantity || unitPrice) {
            // If any field has data, all fields must be filled
            if (!description || !quantity || !unitPrice) {
                showNotification(`Please complete all fields in order item ${index + 1}`, 'warning');
                return false;
            }
            
            // Validate quantity and unit price
            if (parseFloat(quantity) <= 0) {
                showNotification(`Quantity must be greater than 0 in order item ${index + 1}`, 'warning');
                return false;
            }
            
            if (parseFloat(unitPrice) <= 0) {
                showNotification(`Unit price must be greater than 0 in order item ${index + 1}`, 'warning');
                return false;
            }
            
            hasValidItems = true;
        }
    });
    
    if (!hasValidItems) {
        showNotification('Please add at least one order item with description, quantity, and unit price.', 'warning');
        return false;
    }
    
    // Show loading state
    const submitBtn = document.getElementById('submitOrderBtn');
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Saving...';
    submitBtn.disabled = true;
    
    // Clean up empty rows before submission
    cleanupEmptyRows();
    
    // Final validation after cleanup
    const finalOrderItems = document.querySelectorAll('.order-item-row');
    let finalValidItems = 0;
    
    finalOrderItems.forEach((item, index) => {
        const description = item.querySelector('select[name="description[]"]').value;
        const quantity = item.querySelector('input[name="quantity[]"]').value;
        const unitPrice = item.querySelector('input[name="unit_price[]"]').value;
        
        if (description && quantity && unitPrice) {
            finalValidItems++;
        }
    });
    
    if (finalValidItems === 0) {
        showNotification('Please add at least one complete order item.', 'warning');
        submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Create Order';
        submitBtn.disabled = false;
        return false;
    }
    
    return true; // Allow form submission
}

// Function to remove completely empty order item rows
function cleanupEmptyRows() {
    const orderItems = document.querySelectorAll('.order-item-row');
    orderItems.forEach((item, index) => {
        if (index === 0) return; // Keep the first row
        
        const description = item.querySelector('select[name="description[]"]').value;
        const quantity = item.querySelector('input[name="quantity[]"]').value;
        const unitPrice = item.querySelector('input[name="unit_price[]"]').value;
        
        // If all fields are empty, remove this row
        if (!description && !quantity && !unitPrice) {
            item.remove();
        }
    });
}

async function updateCustomerBalance(customerId) {
  const balanceInput = document.getElementById('balance');
  if (!customerId) { 
    balanceInput.value = '0.00'; 
    return; 
  }
  
  try {
    const res = await fetch('add_order.php?balance_for=' + encodeURIComponent(customerId));
    const data = await res.json();
    if (data && data.success) {
      const balance = parseFloat(data.balance);
      balanceInput.value = balance.toFixed(2);
      
      // Add visual indication for negative balance (customer owes money)
      if (balance < 0) {
        balanceInput.classList.add('text-danger', 'fw-bold');
        balanceInput.title = 'Customer owes money';
      } else if (balance > 0) {
        balanceInput.classList.add('text-warning', 'fw-bold');
        balanceInput.title = 'Customer has credit';
      } else {
        balanceInput.classList.remove('text-danger', 'text-warning', 'fw-bold');
        balanceInput.title = 'No balance';
      }
    } else {
      balanceInput.value = '0.00';
      balanceInput.classList.remove('text-danger', 'text-warning', 'fw-bold');
      balanceInput.title = '';
    }
  } catch (error) {
    balanceInput.value = '0.00';
    balanceInput.classList.remove('text-danger', 'text-warning', 'fw-bold');
    balanceInput.title = '';
  }
}

// Test function to bypass validation
function testFormSubmission() {
    // Manually set some test values
    const customerSelect = document.getElementById('customerSelect');
    const descriptionSelect = document.querySelector('select[name="description[]"]');
    const quantityInput = document.querySelector('input[name="quantity[]"]');
    const unitPriceInput = document.querySelector('input[name="unit_price[]"]');
    
    if (!customerSelect.value) {
        customerSelect.value = '1'; // Set to first customer
    }
    
    if (!descriptionSelect.value) {
        descriptionSelect.value = 'Meter';
    }
    
    if (!quantityInput.value) {
        quantityInput.value = '1';
    }
    
    if (!unitPriceInput.value) {
        unitPriceInput.value = '100.00';
    }
    
    // Trigger form submission
    document.getElementById('orderForm').submit();
}

// Add customer via AJAX and select it
const addCustomerForm = document.getElementById('addCustomerForm');
if (addCustomerForm) {
    addCustomerForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Adding...';
        submitBtn.disabled = true;
        
        const formData = new FormData(this);
        fetch('add_customer_ajax.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const successAlert = document.createElement('div');
                    successAlert.className = 'alert alert-success alert-dismissible fade show';
                    successAlert.innerHTML = `
                        <i class="bi bi-check-circle me-2"></i>
                        <strong>Success!</strong> Customer "${data.customer.name}" has been added successfully.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    
                    // Insert alert at the top of the page
                    const mainContent = document.querySelector('.main-content');
                    if (mainContent) {
                        mainContent.insertBefore(successAlert, mainContent.firstChild);
                        
                        // Auto-hide the alert after 5 seconds
                        setTimeout(() => {
                            if (successAlert.parentNode) {
                                const bsAlert = new bootstrap.Alert(successAlert);
                                bsAlert.close();
                            }
                        }, 5000);
                    }
                    
                    // Update customer dropdown display
                    const selectedText = document.querySelector('.customer-selected-text');
                    if (selectedText) {
                        selectedText.textContent = '👤 ' + data.customer.name;
                    }
                    
                    // Update hidden input
                    const customerSelect = document.getElementById('customerSelect');
                    if (customerSelect) {
                        customerSelect.value = data.customer.id;
                    }
                    
                    // Update customer balance
                    updateCustomerBalance(data.customer.id);
                    
                    // Close modal after a short delay
                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('addCustomerModal'));
                        if (modal) {
                            modal.hide();
                        }
                        this.reset();
                    }, 1500);
                    
                } else {
                    // Show error message
                    const errorAlert = document.createElement('div');
                    errorAlert.className = 'alert alert-danger alert-dismissible fade show';
                    errorAlert.innerHTML = `
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Error!</strong> ${data.error || 'Failed to add customer.'}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    
                    // Insert alert at the top of the page
                    const mainContent = document.querySelector('.main-content');
                    if (mainContent) {
                        mainContent.insertBefore(errorAlert, mainContent.firstChild);
                        
                        // Auto-hide the alert after 5 seconds
                        setTimeout(() => {
                            if (errorAlert.parentNode) {
                                const bsAlert = new bootstrap.Alert(errorAlert);
                                bsAlert.close();
                            }
                        }, 5000);
                    }
                }
            })
            .catch((error) => {
                const errorAlert = document.createElement('div');
                errorAlert.className = 'alert alert-danger alert-dismissible fade show';
                errorAlert.innerHTML = `
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Error!</strong> Failed to add customer. Please try again.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                // Insert alert at the top of the page
                const mainContent = document.querySelector('.main-content');
                if (mainContent) {
                    mainContent.insertBefore(errorAlert, mainContent.firstChild);
                    
                    // Auto-hide the alert after 5 seconds
                    setTimeout(() => {
                        if (errorAlert.parentNode) {
                            const bsAlert = new bootstrap.Alert(errorAlert);
                            bsAlert.close();
                        }
                    }, 5000);
                }
            })
            .finally(() => {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
    });
}
</script>

<?php include 'includes/footer.php'; ?>
