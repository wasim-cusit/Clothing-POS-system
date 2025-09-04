<?php
require_once 'includes/auth.php';
require_login();
require_once 'includes/config.php';

$activePage = 'add_order';

// Create tables on first run (safe if already exist)
$pdo->exec("CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(50) NULL,
  customer_id INT NULL,
  order_date DATE NULL,
  delivery_date DATE NULL,
  sub_total DECIMAL(10,2) DEFAULT 0.00,
  discount DECIMAL(10,2) DEFAULT 0.00,
  total_amount DECIMAL(10,2) DEFAULT 0.00,
  paid_amount DECIMAL(10,2) DEFAULT 0.00,
  remaining_amount DECIMAL(10,2) DEFAULT 0.00,
  details TEXT NULL,
  status VARCHAR(20) DEFAULT 'Pending',
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

// Ensure FK from orders.customer_id -> customers.id (safe if exists)
try { $pdo->exec("ALTER TABLE orders ADD INDEX idx_orders_customer_id (customer_id)"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE orders ADD CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL"); } catch (Throwable $e) {}


$pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NULL,
  product_id INT NULL,
  description VARCHAR(255) NULL,
  quantity INT DEFAULT 0,
  unit_price DECIMAL(10,2) DEFAULT 0.00,
  total_price DECIMAL(10,2) DEFAULT 0.00,
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

try {
    $pdo->exec("ALTER TABLE order_items MODIFY COLUMN quantity INT DEFAULT 0");
} catch (Throwable $e) {}

// Ensure unit_prices table exists (shared with add_unit.php)
$pdo->exec(
  "CREATE TABLE IF NOT EXISTS unit_prices (
      id INT AUTO_INCREMENT PRIMARY KEY,
      unit_name VARCHAR(100) NOT NULL UNIQUE,
      unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

// AJAX: return customer balance (sum of remaining from orders)
if (isset($_GET['balance_for'])) {
    header('Content-Type: application/json');
    $cid = (int)$_GET['balance_for'];
    if ($cid <= 0) {
        echo json_encode(['success' => false, 'balance' => 0]);
        exit;
    }
    try {
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(remaining_amount),0) FROM orders WHERE customer_id = ?');
        $stmt->execute([$cid]);
        $balance = (float)$stmt->fetchColumn();
        echo json_encode(['success' => true, 'balance' => number_format($balance, 2, '.', '')]);
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
    $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $order_no = null;
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
        $stmt = $pdo->prepare("INSERT INTO orders (order_no, customer_id, order_date, delivery_date, sub_total, discount, total_amount, paid_amount, remaining_amount, details, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$order_no, $customer_id, $order_date, $delivery_date, $sub_total, $discount, $total_amount, $paid_amount, $remaining_amount, $details, $created_by]);
        $order_id = (int)$pdo->lastInsertId();

        // Items arrays
        $product_ids = $_POST['product_id'] ?? [];
        $descriptions = $_POST['description'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $unit_prices = $_POST['unit_price'] ?? [];
        $total_prices = $_POST['total_price'] ?? [];

        $numRows = max(
            count($product_ids),
            count($descriptions),
            count($quantities),
            count($unit_prices),
            count($total_prices)
        );

        for ($i = 0; $i < $numRows; $i++) {
            $descFromPost = isset($descriptions[$i]) ? trim((string)$descriptions[$i]) : '';
            $pid = (!empty($product_ids[$i] ?? null)) ? (int)$product_ids[$i] : null;
            if ($pid === null && $descFromPost === '' && empty($unit_prices[$i] ?? null)) { continue; }
            $qty = (int)($quantities[$i] ?? 0);
            $uprice = (float)($unit_prices[$i] ?? 0);
            $tprice = (float)($total_prices[$i] ?? ($qty * $uprice));

            // Resolve description from product if provided
            $desc = $descFromPost !== '' ? $descFromPost : null;
            if ($pid) {
                $pstmt = $pdo->prepare('SELECT name FROM products WHERE id = ?');
                $pstmt->execute([$pid]);
                $desc = $pstmt->fetchColumn();
            }

            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, description, quantity, unit_price, total_price) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$order_id, $pid, $desc, $qty, $uprice, $tprice]);
        }

        $pdo->commit();
        header('Location: add_order.php?success=created&order_id=' . $order_id);
        exit;
    } catch (Throwable $e) {
        $pdo->rollBack();
        die('Failed to create order: ' . htmlspecialchars($e->getMessage()));
    }
}

// Fetch dropdown data
$customers = $pdo->query("SELECT id, name FROM customer ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$products = $pdo->query("SELECT id, product_name as name, product_unit as unit FROM products WHERE status = 1 ORDER BY product_name")->fetchAll(PDO::FETCH_ASSOC);
try {
    $units = $pdo->query("SELECT unit_name, unit_price FROM unit_prices ORDER BY unit_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $units = [];
}

// Fetch all orders
$orders = $pdo->query("SELECT o.*, c.name AS customer_name, u.username AS created_by_name FROM orders o LEFT JOIN customer c ON o.customer_id = c.id LEFT JOIN system_users u ON o.created_by = u.id ORDER BY o.id DESC")->fetchAll(PDO::FETCH_ASSOC);


include 'includes/header.php';
?>
<style>
  /* Mobile tweaks for the order page */
  .order-item-actions { display: flex; gap: .5rem; }
  @media (max-width: 768px) {
    .order-item-actions .btn { flex: 1 1 0; }
  }
  @media (max-width: 576px) {
    .order-item-row .form-label { font-size: .85rem; }
  }
  .column-headers { 
    font-size: .9rem; 
    text-transform: none;
    color: #6c757d;
    border-bottom: 1px solid #e9ecef; 
    padding-bottom: .5rem;
    margin-bottom: .25rem;
  }
</style>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
      <h2 class="mb-4">Create Order</h2>

      <?php if (isset($_GET['success']) && $_GET['success']==='created'): ?>
        <div class="alert alert-success">Order created successfully!</div>
      <?php endif; ?>

      <div class="card mb-4">
        <div class="card-header">Order Info</div>
        <div class="card-body">
          <form method="post" id="orderForm">
            <div class="row g-3 align-items-end">
              
              <div class="col-12 col-md-3">
                <label class="form-label">Customer Name</label>
                <div class="input-group">
                  <select name="customer_id" id="customerSelect" class="form-control">
                    <option value="">Select Customer</option>
                    <?php foreach ($customers as $c): ?>
                      <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#addCustomerModal"><i class="bi bi-person-plus"></i></button>
                </div>
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label">Balance</label>
                <input type="text" class="form-control" id="balance" value="0" readonly>
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label">Delivery Date</label>
                <input type="date" name="delivery_date" class="form-control">
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label">Order Date</label>
                <input type="date" name="order_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
              </div>
            </div>

            <hr class="my-4"/>
            
            <div id="orderItems">
              <div class="row column-headers gx-2 d-none d-md-flex">
                <div class="col-md-4">Unit</div>
                <div class="col-md-2">Price</div>
                <div class="col-md-2">Quantity</div>
                <div class="col-md-2">Total Amount</div>
                <div class="col-md-2">Action</div>
              </div>
              <div class="row g-2 align-items-center mb-2 order-item-row">
                <div class="col-12 col-md-4">
                  <label class="form-label d-md-none">Unit</label>
                  <div class="input-group input-group-sm">
                    <select name="description[]" class="form-control form-control-sm unit-select" aria-label="Unit">
                      <option value="">Select Unit</option>
                      <?php foreach ($units as $u): ?>
                        <option value="<?= htmlspecialchars($u['unit_name']) ?>" data-price="<?= number_format((float)$u['unit_price'], 2, '.', '') ?>"><?= htmlspecialchars($u['unit_name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <a href="add_unit.php" target="_blank" class="btn btn-outline-secondary" title="Add Unit"><i class="bi bi-plus"></i></a>
                  </div>
                </div>
                <div class="col-6 col-md-2">
                  <label class="form-label d-md-none">Price</label>
                  <input type="number" step="0.01" name="unit_price[]" class="form-control form-control-sm unit-price" placeholder="Price" aria-label="Price">
                </div>
                <div class="col-6 col-md-2">
                  <label class="form-label d-md-none">Quantity</label>
                  <input type="number" step="1" min="1" name="quantity[]" class="form-control form-control-sm quantity" placeholder="Quantity" aria-label="Quantity">
                </div>
                <div class="col-6 col-md-2">
                  <label class="form-label d-md-none">Total Amount</label>
                  <input type="number" step="0.01" name="total_price[]" class="form-control form-control-sm total-price" placeholder="Total" readonly aria-label="Total Amount">
                </div>
                <div class="col-12 col-md-2">
                  <div class="order-item-actions">
                    <button type="button" class="btn btn-success btn-sm add-row" title="Add row"><i class="bi bi-plus-lg"></i></button>
                    <button type="button" class="btn btn-danger btn-sm remove-row" title="Remove row"><i class="bi bi-dash-lg"></i></button>
                  </div>
                </div>
              </div>
            </div>

            <div class="row g-3 mt-4">
              <div class="col-6 col-md-2">
                <label class="form-label">Sub Total</label>
                <input type="number" step="0.01" name="sub_total" id="subTotal" class="form-control" readonly>
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label">Discount</label>
                <input type="number" step="0.01" name="discount" id="discount" class="form-control" value="0">
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label">Total</label>
                <input type="number" step="0.01" name="total_amount" id="grandTotal" class="form-control" readonly>
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label">Paid</label>
                <input type="number" step="0.01" name="paid" id="paid" class="form-control" value="0">
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label">Remaining</label>
                <input type="number" step="0.01" name="remaining" id="remaining" class="form-control" readonly>
              </div>
              <div class="col-12 col-md-9">
                <label class="form-label">Order Details</label>
                <textarea name="details" class="form-control" rows="2" placeholder="Order Details (e.g., measurements, notes)"></textarea>
              </div>
            </div>

            <div class="mt-4">
              <button type="submit" class="btn btn-primary" name="create_order">Save Order</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Add Customer Modal -->
      <div class="modal fade" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <form id="addCustomerForm">
              <div class="modal-header">
                <h5 class="modal-title" id="addCustomerModalLabel">Add Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label">Name</label>
                  <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Contact</label>
                  <input type="text" name="contact" class="form-control">
                </div>
                <div class="mb-3">
                  <label class="form-label">Address</label>
                  <input type="text" name="address" class="form-control">
                </div>
                <div class="mb-3">
                  <label class="form-label">Email</label>
                  <input type="email" name="email" class="form-control">
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Customer</button>
              </div>
            </form>
          </div>
        </div>
      </div>

<script>
// Clone and remove rows
document.addEventListener('click', function(e) {
  if (e.target.closest('.add-row')) {
    const firstRow = document.querySelector('#orderItems .order-item-row');
    const clone = firstRow.cloneNode(true);
    clone.querySelectorAll('input').forEach(i => i.value = '');
    clone.querySelector('select').selectedIndex = 0;
    document.getElementById('orderItems').appendChild(clone);
  }
  if (e.target.closest('.remove-row')) {
    const rows = document.querySelectorAll('#orderItems .order-item-row');
    if (rows.length > 1) {
      e.target.closest('.order-item-row').remove();
      recalcTotals();
    }
  }
});

// Auto-fill price from product
document.addEventListener('change', function(e) {
  if (e.target.classList.contains('unit-select')) {
    const option = e.target.options[e.target.selectedIndex];
    const row = e.target.closest('.order-item-row');
    const priceInput = row.querySelector('.unit-price');
    if (option && option.dataset.price) {
      priceInput.value = option.dataset.price;
      const qty = parseFloat(row.querySelector('.quantity').value || 0);
      const price = parseFloat(priceInput.value || 0);
      row.querySelector('.total-price').value = (qty * price).toFixed(2);
      recalcTotals();
    }
  }
  if (e.target.id === 'discount' || e.target.id === 'paid') {
    recalcTotals();
  }
  if (e.target.id === 'customerSelect') {
    updateCustomerBalance(e.target.value);
  }
});

// Calculate row totals and grand totals
document.addEventListener('input', function(e) {
  if (e.target.classList.contains('quantity') || e.target.classList.contains('unit-price')) {
    const row = e.target.closest('.order-item-row');
    const qty = parseFloat(row.querySelector('.quantity').value || 0);
    const price = parseFloat(row.querySelector('.unit-price').value || 0);
    row.querySelector('.total-price').value = (qty * price).toFixed(2);
    recalcTotals();
  }
});

function recalcTotals() {
  let sub = 0;
  document.querySelectorAll('.total-price').forEach(el => {
    const v = parseFloat(el.value || 0);
    sub += v;
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
document.addEventListener('DOMContentLoaded', recalcTotals);

async function updateCustomerBalance(customerId) {
  const balanceInput = document.getElementById('balance');
  if (!customerId) { balanceInput.value = '0.00'; return; }
  try {
    const res = await fetch('add_order.php?balance_for=' + encodeURIComponent(customerId));
    const data = await res.json();
    if (data && data.success) {
      balanceInput.value = parseFloat(data.balance).toFixed(2);
    } else {
      balanceInput.value = '0.00';
    }
  } catch (_) {
    balanceInput.value = '0.00';
  }
}

// Add customer via AJAX and select it
document.getElementById('addCustomerForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  fetch('add_customer_ajax.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        const select = document.getElementById('customerSelect');
        const option = document.createElement('option');
        option.value = data.customer.id;
        option.textContent = data.customer.name;
        option.selected = true;
        select.appendChild(option);
        updateCustomerBalance(option.value);
        const modal = bootstrap.Modal.getInstance(document.getElementById('addCustomerModal'));
        modal.hide();
        this.reset();
      } else {
        alert(data.error || 'Failed to add customer.');
      }
    })
    .catch(() => alert('Failed to add customer.'));
});
</script>

<?php include 'includes/footer.php'; ?>
