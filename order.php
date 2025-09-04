<?php
require_once 'includes/auth.php';
require_login();
require_once 'includes/config.php';

$activePage = 'order';

// Ensure orders table has cost breakdown columns
try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS karegar_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_amount");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS material_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER karegar_price");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS zakat_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER material_price");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS final_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER zakat_amount");
} catch (Exception $e) {
    // Columns might already exist, ignore error
}

// Handle delete order
if (isset($_GET['delete'])) {
  $deleteId = (int)$_GET['delete'];
  if ($deleteId > 0) {
    try {
      // First delete order items
      $stmt = $pdo->prepare('DELETE FROM order_items WHERE order_id = ?');
      $stmt->execute([$deleteId]);
      // Then delete the order
      $stmt = $pdo->prepare('DELETE FROM orders WHERE id = ?');
      $stmt->execute([$deleteId]);
      header('Location: order.php?success=deleted');
      exit;
    } catch (Throwable $e) {
      header('Location: order.php?error=delete_failed');
      exit;
    }
  }
}

// Handle Status Update
if (isset($_GET['update_status'])) {
  $id = intval($_GET['update_status']);
  $new_status = $_GET['status'];
  $valid_statuses = ['Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled'];
  if (in_array($new_status, $valid_statuses)) {
    try {
      $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
      $stmt->execute([$new_status, $id]);
      header("Location: order.php?success=status_updated");
      exit;
    } catch (Exception $e) {
      error_log("Error updating order status: " . $e->getMessage());
      header("Location: order.php?error=Failed to update status.");
      exit;
    }
  }
}

// Fetch all orders with cost breakdown
try {
    $orders = $pdo->query("
        SELECT 
            o.*, 
            c.name AS customer_name, 
            c.mobile AS customer_mobile
        FROM orders o 
        LEFT JOIN customer c ON o.customer_id = c.id 
        ORDER BY o.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    error_log("Orders query returned " . count($orders) . " orders");
} catch (Exception $e) {
    error_log("Error fetching orders: " . $e->getMessage());
    $orders = [];
}

// Generate WhatsApp message for order with complete details
function generateOrderWhatsAppMessage($order, $pdo) {
    try {
        // Get order items for detailed message
        $stmt = $pdo->prepare("SELECT oi.*, p.product_name, c.category FROM order_items oi 
                               LEFT JOIN products p ON oi.product_id = p.id 
                               LEFT JOIN categories c ON p.category_id = c.id 
                               WHERE oi.order_id = ?");
        $stmt->execute([$order['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $message = "📋 *ORDER DETAILS - TAILOR SHOP*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        // Header Information
        $message .= "📋 *Order No:* " . html_entity_decode($order['order_no'] ?? 'ORD-' . date('Y') . '-' . str_pad($order['id'], 4, '0', STR_PAD_LEFT)) . "\n";
        $message .= "👤 *Customer:* " . html_entity_decode($order['customer_name'] ?? 'Walk-in Customer') . "\n";
        $message .= "📅 *Order Date:* " . date('d M Y', strtotime($order['order_date'])) . "\n";
        $message .= "🕐 *Order Time:* " . date('h:i A', strtotime($order['order_date'])) . "\n";
        if (!empty($order['delivery_date'])) {
            $message .= "🚚 *Delivery Date:* " . date('d M Y', strtotime($order['delivery_date'])) . "\n";
        }
        $message .= "\n";
        
        // Items Details
        if (!empty($items)) {
            $message .= "🛒 *ORDER ITEMS:*\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            
            foreach ($items as $index => $item) {
                $itemNo = $index + 1;
                $message .= $itemNo . ". *" . html_entity_decode($item['product_name']) . "*\n";
                if (!empty($item['category'])) {
                    $message .= "   📂 Category: " . html_entity_decode($item['category']) . "\n";
                }
                if (!empty($item['product_code'])) {
                    $message .= "   🏷️ Code: " . html_entity_decode($item['product_code']) . "\n";
                }
                $message .= "   📏 Qty: " . $item['quantity'] . " × PKR " . number_format($item['price'], 2) . "\n";
                $message .= "   💰 Total: PKR " . number_format($item['total_price'], 2) . "\n\n";
            }
        }
        
        // Summary Section
        $message .= "📊 *ORDER SUMMARY:*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "💰 *Total Amount:* PKR " . number_format($order['total_amount'], 2) . "\n";
        $message .= "💸 *Paid Amount:* PKR " . number_format($order['paid_amount'], 2) . "\n";
        
        if ($order['remaining_amount'] > 0) {
            $message .= "⚠️ *Remaining Amount:* PKR " . number_format($order['remaining_amount'], 2) . "\n";
        }
        
        $message .= "📋 *Status:* " . ($order['status'] ?? 'Pending') . "\n";
        
        // Footer
        $message .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "🏪 *WASEM WEARS*\n";
        $message .= "📞 Contact: +92 323 9507813\n";
        $message .= "📍 Address: Address shop #1 hameed plaza main university road\n";
        $message .= "🌐 Website: www.wasemwears.com\n\n";
        $message .= "Thank you for choosing us! 🙏\n";
        $message .= "Please visit again! ✨";
        
        return urlencode($message);
    } catch (Exception $e) {
        // Fallback to simple message if there's an error
        $message = "📋 *ORDER DETAILS*\n\n";
        $message .= "📋 Order: " . html_entity_decode($order['order_no'] ?? 'ORD-' . date('Y') . '-' . str_pad($order['id'], 4, '0', STR_PAD_LEFT)) . "\n";
        $message .= "👤 Customer: " . html_entity_decode($order['customer_name'] ?? 'Walk-in Customer') . "\n";
        $message .= "💰 Total: PKR " . number_format($order['total_amount'], 2) . "\n";
        $message .= "📅 Date: " . date('d M Y', strtotime($order['order_date'])) . "\n";
        $message .= "📋 Status: " . ($order['status'] ?? 'Pending') . "\n\n";
        $message .= "Thank you! 🙏";
        
        return urlencode($message);
    }
}

include 'includes/header.php';
?>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
  <div class="d-flex  mb-3">
    <h2 class="mb-0">Orders</h2>
    <!-- <a href="add_order.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New Order</a> -->
  </div>

  <?php if (isset($_GET['success']) && $_GET['success']==='deleted'): ?>
    <div class="alert alert-success">Order deleted successfully.</div>
  <?php endif; ?>
  <?php if (isset($_GET['success']) && $_GET['success']==='status_updated'): ?>
    <div class="alert alert-success">Order status updated successfully.</div>
  <?php endif; ?>
  <?php if (isset($_GET['error']) && $_GET['error']==='delete_failed'): ?>
    <div class="alert alert-danger">Failed to delete order.</div>
  <?php endif; ?>
  <?php if (isset($_GET['error']) && $_GET['error']==='Failed to update status.'): ?>
    <div class="alert alert-danger">Failed to update order status.</div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">
      <div class="d-flex left-right">
        <h5 class="mb-0">Order List</h5>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" class="form-control" id="orderSearchInput" placeholder="Search orders..." autocomplete="off">
          <button class="btn btn-outline-secondary" type="button" id="clearSearchBtn" title="Clear search">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
      </div>
    </div>
    
    <!-- Cost Summary Table -->
    <div class="card-body border-bottom">
      <div class="row mb-3">
        <div class="col-md-6">
          <h6 class="mb-2"><i class="bi bi-list-ul me-2"></i>Order Cost Summary</h6>
        </div>
        <div class="col-md-6 text-end">
          <div class="btn-group" role="group">
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportToExcel('daily')">
              <i class="bi bi-calendar-day me-1"></i>Daily
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportToExcel('weekly')">
              <i class="bi bi-calendar-week me-1"></i>Weekly
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportToExcel('monthly')">
              <i class="bi bi-calendar-month me-1"></i>Monthly
            </button>
            <button type="button" class="btn btn-sm btn-outline-success" onclick="exportToExcel('custom')">
              <i class="bi bi-calendar-range me-1"></i>Custom Range
            </button>
          </div>
        </div>
      </div>
      
      <!-- Date Range Filter -->
      <div class="row mb-3">
        <div class="col-md-12">
          <div class="card bg-light">
            <div class="card-body py-2">
              <div class="row align-items-center">
                <div class="col-md-2">
                  <label class="form-label mb-1"><i class="bi bi-calendar-event me-1"></i>Filter by Date Range:</label>
                </div>
                <div class="col-md-3">
                  <input type="date" id="fromDate" class="form-control form-control-sm" placeholder="From Date">
                </div>
                <div class="col-md-3">
                  <input type="date" id="toDate" class="form-control form-control-sm" placeholder="To Date">
                </div>
                <div class="col-md-2">
                  <button type="button" class="btn btn-sm btn-primary" onclick="filterByDateRange()">
                    <i class="bi bi-funnel me-1"></i>Filter
                  </button>
                </div>
                <div class="col-md-2">
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearDateFilter()">
                    <i class="bi bi-x-circle me-1"></i>Clear
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0 cost-summary-table">
          <thead class="table-light">
            <tr>
              <th class="text-nowrap">Order No</th>
              <th class="text-nowrap">Customer</th>
              <th class="text-nowrap">Order Date</th>
              <th class="text-nowrap">Total Amount</th>
              <th class="text-nowrap">Karegar Price</th>
              <th class="text-nowrap">Material Price</th>
              <th class="text-nowrap">Zakat</th>
              <th class="text-nowrap">Final Amount</th>
            </tr>
          </thead>
          <tbody id="costSummaryBody">
            <?php if (empty($orders)): ?>
              <tr>
                <td colspan="8" class="text-center py-3 text-muted">
                  <i class="bi bi-info-circle me-2"></i>No orders found
                </td>
              </tr>
            <?php else: ?>
              <?php 
              $grand_total_karegar = 0;
              $grand_total_material = 0;
              $grand_total_zakat = 0;
              $grand_total_cost = 0;
              ?>
              <?php foreach ($orders as $order): ?>
                <?php 
                $karegar_price = floatval($order['karegar_price'] ?? 0);
                $material_price = floatval($order['material_price'] ?? 0);
                $zakat = floatval($order['zakat_amount'] ?? 0);
                $final_amount = floatval($order['final_amount'] ?? 0);
                $total_amount = floatval($order['total_amount'] ?? 0);
                
                $grand_total_karegar += $karegar_price;
                $grand_total_material += $material_price;
                $grand_total_zakat += $zakat;
                $grand_total_cost += $final_amount;
                ?>
                <tr class="order-summary-row" data-order-date="<?= htmlspecialchars($order['order_date']) ?>">
                  <td class="text-nowrap">
                    <strong><?= htmlspecialchars($order['order_no'] ?? 'ORD-' . date('Y') . '-' . str_pad($order['id'], 4, '0', STR_PAD_LEFT)) ?></strong>
                  </td>
                  <td class="text-nowrap">
                    <?= htmlspecialchars($order['customer_name'] ?? 'Walk-in Customer') ?>
                  </td>
                  <td class="text-nowrap"><?= htmlspecialchars($order['order_date']) ?></td>
                  <td class="text-nowrap text-end">Rs. <?= number_format($total_amount, 2) ?></td>
                  <td class="text-nowrap text-end">Rs. <?= number_format($karegar_price, 2) ?></td>
                  <td class="text-nowrap text-end">Rs. <?= number_format($material_price, 2) ?></td>
                  <td class="text-nowrap text-end">Rs. <?= number_format($zakat, 2) ?></td>
                  <td class="text-nowrap text-end fw-bold">Rs. <?= number_format($final_amount, 2) ?></td>
                </tr>
              <?php endforeach; ?>
              <!-- Grand Total Row -->
              <tr class="table-success" id="grandTotalRow">
                <td colspan="3" class="text-end fw-bold">GRAND TOTAL:</td>
                <td class="text-end fw-bold">Rs. <?= number_format(array_sum(array_column($orders, 'total_amount')), 2) ?></td>
                <td class="text-end fw-bold">Rs. <?= number_format($grand_total_karegar, 2) ?></td>
                <td class="text-end fw-bold">Rs. <?= number_format($grand_total_material, 2) ?></td>
                <td class="text-end fw-bold">Rs. <?= number_format($grand_total_zakat, 2) ?></td>
                <td class="text-end fw-bold">Rs. <?= number_format($grand_total_cost, 2) ?></td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-striped mb-0">
          <thead class="table-dark">
            <tr>
              <th class="text-nowrap">Order No</th>
              <th class="text-nowrap">Customer</th>
              <th class="text-nowrap d-none d-md-table-cell">Order Date</th>
              <th class="text-nowrap d-none d-lg-table-cell">Delivery Date</th>
              <th class="text-nowrap d-none d-md-table-cell">Total Amount</th>
              <th class="text-nowrap d-none d-lg-table-cell">Paid Amount</th>
              <th class="text-nowrap d-none d-lg-table-cell">Remaining</th>
              <th class="text-nowrap">Status</th>
              <th class="text-nowrap">Actions</th>
          </tr>
        </thead>
        <tbody id="ordersTableBody">
          <?php if (empty($orders)): ?>
            <tr id="noOrdersRow">
              <td colspan="9" class="text-center py-4">
                <div class="alert alert-info mb-0">
                  <i class="bi bi-info-circle me-2"></i>
                  No orders found. <a href="add_order.php">Create your first order</a>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($orders as $order): ?>
            <tr class="order-row" 
                data-order-no="<?= htmlspecialchars($order['order_no'] ?? 'ORD-' . date('Y') . '-' . str_pad($order['id'], 4, '0', STR_PAD_LEFT)) ?>"
                data-customer="<?= htmlspecialchars($order['customer_name'] ?? 'Walk-in Customer') ?>"
                data-status="<?= htmlspecialchars($order['status'] ?? 'Pending') ?>"
                data-total="<?= $order['total_amount'] ?>"
                data-paid="<?= $order['paid_amount'] ?>"
                data-remaining="<?= $order['remaining_amount'] ?>"
                data-order-date="<?= htmlspecialchars($order['order_date']) ?>"
                data-delivery-date="<?= htmlspecialchars($order['delivery_date'] ?? 'Not set') ?>">
              <td class="text-nowrap" data-label="Order No">
                <strong><?= htmlspecialchars($order['order_no'] ?? 'ORD-' . date('Y') . '-' . str_pad($order['id'], 4, '0', STR_PAD_LEFT)) ?></strong>
              </td>
              <td class="text-nowrap" data-label="Customer">
                <?= htmlspecialchars($order['customer_name'] ?? 'Walk-in Customer') ?>
              </td>
              <td class="d-none d-md-table-cell text-nowrap" data-label="Order Date">
                <?= htmlspecialchars($order['order_date']) ?>
              </td>
              <td class="d-none d-lg-table-cell text-nowrap" data-label="Delivery Date">
                <?= htmlspecialchars($order['delivery_date'] ?? 'Not set') ?>
              </td>
              <td class="d-none d-md-table-cell text-nowrap" data-label="Total Amount">
                <strong>PKR <?= number_format($order['total_amount'], 2) ?></strong>
              </td>
              <td class="d-none d-lg-table-cell text-nowrap" data-label="Paid Amount">
                PKR <?= number_format($order['paid_amount'], 2) ?>
              </td>
              <td class="d-none d-lg-table-cell text-nowrap" data-label="Remaining">
                PKR <?= number_format($order['remaining_amount'], 2) ?>
              </td>
              <td class="text-nowrap" data-label="Status">
                <?php
                $status_colors = [
                  'Pending' => 'bg-warning',
                  'Confirmed' => 'bg-info',
                  'In Progress' => 'bg-primary',
                  'Completed' => 'bg-success',
                  'Cancelled' => 'bg-danger'
                ];
                $status_icons = [
                  'Pending' => '⏳',
                  'Confirmed' => '✅',
                  'In Progress' => '🔄',
                  'Completed' => '🎉',
                  'Cancelled' => '❌'
                ];
                $color = $status_colors[$order['status']] ?? 'bg-secondary';
                $icon = $status_icons[$order['status']] ?? '❓';
                ?>
                
                <!-- Status Badge -->
                <div class="d-flex align-items-center justify-content-between">
                  <span class="badge <?= $color ?>">
                    <?= $icon ?> <?= htmlspecialchars($order['status'] ?? 'Pending') ?>
                  </span>
                  
                  <!-- Mobile status dropdown -->
                  <div class="dropdown d-lg-none">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Change Status">
                      <i class="bi bi-gear"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                      <?php if (($order['status'] ?? 'Pending') !== 'Pending'): ?>
                        <li><a class="dropdown-item" href="?update_status=<?= $order['id'] ?>&status=Pending">⏳ Pending</a></li>
                      <?php endif; ?>
                      <?php if (($order['status'] ?? 'Pending') !== 'Confirmed'): ?>
                        <li><a class="dropdown-item" href="?update_status=<?= $order['id'] ?>&status=Confirmed">✅ Confirmed</a></li>
                      <?php endif; ?>
                      <?php if (($order['status'] ?? 'Pending') !== 'In Progress'): ?>
                        <li><a class="dropdown-item" href="?update_status=<?= $order['id'] ?>&status=In Progress">🔄 In Progress</a></li>
                      <?php endif; ?>
                      <?php if (($order['status'] ?? 'Pending') !== 'Completed'): ?>
                        <li><a class="dropdown-item" href="?update_status=<?= $order['id'] ?>&status=Completed">🎉 Completed</a></li>
                      <?php endif; ?>
                      <?php if (($order['status'] ?? 'Pending') !== 'Cancelled'): ?>
                        <li><a class="dropdown-item" href="?update_status=<?= $order['id'] ?>&status=Cancelled">❌ Cancelled</a></li>
                      <?php endif; ?>
                    </ul>
                  </div>
                </div>
                
                <!-- Desktop status buttons -->
                <div class="btn-group-vertical btn-group-sm mt-1 d-none d-lg-block">
                  <?php if (($order['status'] ?? 'Pending') !== 'Pending'): ?>
                    <a href="?update_status=<?= $order['id'] ?>&status=Pending" class="btn btn-sm btn-warning">⏳ Pending</a>
                  <?php endif; ?>
                  <?php if (($order['status'] ?? 'Pending') !== 'Confirmed'): ?>
                    <a href="?update_status=<?= $order['id'] ?>&status=Confirmed" class="btn btn-sm btn-info">✅ Confirmed</a>
                  <?php endif; ?>
                  <?php if (($order['status'] ?? 'Pending') !== 'In Progress'): ?>
                    <a href="?update_status=<?= $order['id'] ?>&status=In Progress" class="btn btn-sm btn-primary">🔄 In Progress</a>
                  <?php endif; ?>
                  <?php if (($order['status'] ?? 'Pending') !== 'Completed'): ?>
                    <a href="?update_status=<?= $order['id'] ?>&status=Completed" class="btn btn-sm btn-success">🎉 Completed</a>
                  <?php endif; ?>
                  <?php if (($order['status'] ?? 'Pending') !== 'Cancelled'): ?>
                    <a href="?update_status=<?= $order['id'] ?>&status=Cancelled" class="btn btn-sm btn-danger">❌ Cancelled</a>
                  <?php endif; ?>
                </div>
              </td>
              <td class="text-nowrap" data-label="Actions">
                <div class="d-flex flex-column flex-lg-row gap-1">
                  <a href="order_details.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-info">
                    <i class="bi bi-eye"></i> <span class="d-none d-lg-inline">View</span>
                  </a>
                  <a href="print_order.php?id=<?= $order['id'] ?>" target="_blank" class="btn btn-sm btn-success">
                    <i class="bi bi-printer"></i> <span class="d-none d-lg-inline">Print</span>
                  </a>
                  <?php if (!empty($order['customer_mobile'])): ?>
                    <a href="#" onclick="sendOrderWhatsApp(<?= $order['id'] ?>, '<?= htmlspecialchars($order['customer_name']) ?>', '<?= preg_replace('/[^0-9]/', '', $order['customer_mobile']) ?>')" class="btn btn-sm btn-whatsapp" title="Send Order via WhatsApp">
                      <i class="bi bi-whatsapp"></i>
                    </a>
                  <?php else: ?>
                    <button type="button" class="btn btn-sm btn-whatsapp" title="Send to another number" onclick="sendToAnotherNumber(<?= $order['id'] ?>, '<?= htmlspecialchars($order['customer_name']) ?>')">
                      <i class="bi bi-whatsapp"></i>
                    </button>
                  <?php endif; ?>
                  <a href="?delete=<?= $order['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this order?')">
                    <i class="bi bi-trash"></i> <span class="d-none d-lg-inline">Delete</span>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>
</div>

<!-- WhatsApp Number Modal -->
<div class="modal fade" id="whatsappNumberModal" tabindex="-1" aria-labelledby="whatsappNumberModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="whatsappNumberModalLabel">
                    <i class="bi bi-whatsapp me-2"></i>Send Order via WhatsApp
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="customerName" class="form-label fw-bold">Customer Name:</label>
                            <input type="text" class="form-control" id="customerName" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="phoneNumber" class="form-label fw-bold">Phone Number:</label>
                            <div class="input-group">
                                <span class="input-group-text">+92</span>
                                <input type="tel" class="form-control" id="phoneNumber" placeholder="3XX XXXXXXX" maxlength="10" pattern="[0-9]{10}">
                            </div>
                            <div class="form-text">Enter the 10-digit phone number without country code</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Quick Options:</label>
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setQuickNumber('shop')">
                                        <i class="bi bi-shop me-2"></i>Shop WhatsApp
                                    </button>
                                    <button type="button" class="btn btn-outline-info btn-sm" onclick="setQuickNumber('manager')">
                                        <i class="bi bi-person-badge me-2"></i>Manager WhatsApp
                                    </button>
                                    <button type="button" class="btn btn-outline-success btn-sm" onclick="setQuickNumber('custom')">
                                        <i class="bi bi-pencil me-2"></i>Custom Number
                                    </button>
                                </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Recent Numbers:</label>
                            <div id="recentNumbers" class="d-grid gap-1">
                                <!-- Recent numbers will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <small>This will open WhatsApp with the order message. You can send to any number including shop staff, managers, or other contacts.</small>
                </div>
                
                <!-- Order Preview -->
                <div class="mt-3">
                    <h6 class="fw-bold">Order Preview:</h6>
                    <div id="orderPreview" class="border rounded p-3 bg-light">
                        <div class="text-center text-muted">
                            <i class="bi bi-hourglass-split"></i> Loading order details...
                        </div>
                    </div>
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

<script>
let currentOrderId = null;
let currentOrderData = null;

// Ultra-optimized Search functionality - No forced reflows
(function() {
    'use strict';
    
    let searchInput, clearSearchBtn, orderRows, tableBody;
    let searchTimeout, noResultsRow;
    let isInitialized = false;
    let rowData = [];
    
    // Cache DOM elements and optimize search
    function initializeSearch() {
        if (isInitialized) return;
        
        searchInput = document.getElementById('orderSearchInput');
        clearSearchBtn = document.getElementById('clearSearchBtn');
        orderRows = document.querySelectorAll('.order-row');
        tableBody = document.getElementById('ordersTableBody');
        
        if (!searchInput || !orderRows.length) return;
        
        // Pre-cache row text content to avoid repeated DOM queries
        rowData = Array.from(orderRows).map(row => ({
            element: row,
            text: row.textContent.toLowerCase()
        }));
        
        // Ultra-optimized search function - zero forced reflows
        function performSearch() {
            if (!searchInput) return;
            
            const searchTerm = searchInput.value.toLowerCase().trim();
            let visibleCount = 0;
            
            // Use DocumentFragment for batch DOM operations
            const fragment = document.createDocumentFragment();
            const hiddenElements = [];
            
            // Process all rows in a single pass
            rowData.forEach(({ element, text }) => {
                const matchesSearch = !searchTerm || text.includes(searchTerm);
                
                if (matchesSearch) {
                    element.style.display = '';
                    visibleCount++;
                } else {
                    element.style.display = 'none';
                    hiddenElements.push(element);
                }
            });
            
            // Handle no results message
            handleNoResults(visibleCount);
            
            // Update clear button visibility
            if (clearSearchBtn) {
                clearSearchBtn.style.display = searchTerm ? 'block' : 'none';
            }
        }
        
        // Optimized no results handling
        function handleNoResults(visibleCount) {
            const hasSearchTerm = searchInput.value.trim();
            
            if (visibleCount === 0 && hasSearchTerm) {
                if (!noResultsRow) {
                    noResultsRow = document.createElement('tr');
                    noResultsRow.id = 'noResultsRow';
                    noResultsRow.innerHTML = `
                        <td colspan="9" class="text-center py-4">
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-search me-2"></i>
                                No orders found matching your search criteria.
                                <button class="btn btn-sm btn-outline-primary ms-2" onclick="clearSearch()">
                                    <i class="bi bi-x-circle me-1"></i>Clear Search
                                </button>
                            </div>
                        </td>
                    `;
                    tableBody.appendChild(noResultsRow);
                }
            } else if (noResultsRow) {
                noResultsRow.remove();
                noResultsRow = null;
            }
        }
        
        // Clear search function
        window.clearSearch = function() {
            if (searchInput) {
                searchInput.value = '';
                performSearch();
            }
        };
        
        // Optimized event listeners with passive listeners
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(performSearch, 100); // Further reduced debounce
        }, { passive: true });
        
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', clearSearch, { passive: true });
        }
        
        // Lazy initialization to avoid blocking
        if (window.requestIdleCallback) {
            requestIdleCallback(performSearch);
        } else {
            setTimeout(performSearch, 0);
        }
        isInitialized = true;
    }
    
    // Optimized DOM ready detection
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeSearch, { once: true });
    } else {
        // DOM already loaded - use microtask
        Promise.resolve().then(initializeSearch);
    }
})();

// Ultra-optimized function to open modal - no forced reflows
function sendToAnotherNumber(orderId, customerName) {
    currentOrderId = orderId;
    
    // Direct DOM manipulation without requestAnimationFrame
    const customerNameEl = document.getElementById('customerName');
    const phoneNumberEl = document.getElementById('phoneNumber');
    
    if (customerNameEl) customerNameEl.value = customerName;
    if (phoneNumberEl) phoneNumberEl.value = '';

    // Show the modal
    const modalEl = document.getElementById('whatsappNumberModal');
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
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
    
    // Get the order data and generate message
    fetch(`get_order_data.php?id=${currentOrderId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text(); // Get response as text first
        })
        .then(text => {
            // Try to parse as JSON
            try {
                const data = JSON.parse(text);
                return data;
            } catch (parseError) {
                console.error('JSON Parse Error:', parseError);
                console.error('Response Text:', text);
                throw new Error('Invalid response format from server');
            }
        })
        .then(data => {
            // Remove loading alert
            loadingAlert.remove();
            
            if (data.success) {
                console.log('Order data received:', data.order); // Debug log
                const message = generateOrderWhatsAppMessageFromData(data.order);
                
                if (phoneNumber && phoneNumber !== 'null') {
                    // Customer has mobile number - send directly
                    const whatsappUrl = `https://wa.me/92${phoneNumber}?text=${encodeURIComponent(message)}`;
                    
                    if (confirm(`Send order to ${customerName} via WhatsApp?`)) {
                        window.open(whatsappUrl, '_blank');
                    }
                } else {
                    // No mobile number - show modal for custom number
                    sendToAnotherNumber(orderId, customerName);
                }
            } else {
                showErrorAlert('Error: ' + (data.error || 'Could not load order data'));
            }
        })
        .catch(error => {
            // Remove loading alert
            loadingAlert.remove();
            
            console.error('Error:', error);
            showErrorAlert('Error: ' + error.message);
        });
}

// Function to generate WhatsApp message from order data
function generateOrderWhatsAppMessageFromData(order) {
    let message = "📋 *ORDER DETAILS - TAILOR SHOP*\n";
    message += "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Header Information
    message += "📋 *Order No:* " + (order.order_no || 'ORD-' + new Date().getFullYear() + '-' + String(order.id).padStart(4, '0')) + "\n";
    message += "👤 *Customer:* " + (order.customer_name || 'Walk-in Customer') + "\n";
    message += "📅 *Order Date:* " + (order.order_date || 'Not set') + "\n";
    message += "🕐 *Order Time:* " + (order.order_time || 'Not set') + "\n";
    if (order.delivery_date && order.delivery_date !== 'Not set') {
        message += "🚚 *Delivery Date:* " + order.delivery_date + "\n";
    }
    message += "\n";
    
    // Items Details (if available)
    if (order.items && order.items.length > 0) {
        message += "🛒 *ORDER ITEMS:*\n";
        message += "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        
        order.items.forEach((item, index) => {
            const itemNo = index + 1;
            const productName = item.product_name || 'Product ID: ' + (item.product_id || 'Unknown');
            message += itemNo + ". *" + productName + "*\n";
            
            if (item.category && item.category !== '' && item.category !== 'General') {
                message += "   📂 Category: " + item.category + "\n";
            }
            if (item.product_code && item.product_code !== '') {
                message += "   🏷️ Code: " + item.product_code + "\n";
            }
            
            const quantity = item.quantity || 0;
            const price = parseFloat(item.price || 0);
            const totalPrice = parseFloat(item.total_price || 0);
            
            message += "   📏 Qty: " + quantity + " × PKR " + price.toFixed(2) + "\n";
            message += "   💰 Total: PKR " + totalPrice.toFixed(2) + "\n\n";
        });
    } else {
        message += "🛒 *ORDER ITEMS:*\n";
        message += "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        message += "No items found for this order.\n\n";
    }
    
    // Summary Section
    message += "📊 *ORDER SUMMARY:*\n";
    message += "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    const totalAmount = parseFloat(order.total_amount || 0);
    const paidAmount = parseFloat(order.paid_amount || 0);
    const remainingAmount = parseFloat(order.remaining_amount || 0);
    
    message += "💰 *Total Amount:* PKR " + totalAmount.toFixed(2) + "\n";
    message += "💸 *Paid Amount:* PKR " + paidAmount.toFixed(2) + "\n";
    
    if (remainingAmount > 0) {
        message += "⚠️ *Remaining Amount:* PKR " + remainingAmount.toFixed(2) + "\n";
    } else if (paidAmount >= totalAmount) {
        message += "✅ *Status:* Fully Paid\n";
    }
    
    message += "📋 *Status:* " + (order.status || 'Pending') + "\n";
    
    // Footer
    message += "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    message += "🏪 *WASEM WEARS*\n";
    message += "📞 Contact: +92 323 9507813\n";
    message += "📍 Address: Address shop #1 hameed plaza main university road\n";
    message += "🌐 Website: www.wasemwears.com\n\n";
    message += "Thank you for choosing us! 🙏\n";
    message += "Please visit again! ✨";
    
    return message;
}

// Function to send order via WhatsApp
function sendOrderWhatsApp(orderId, customerName, phoneNumber) {
    // Show loading message
    const loadingMsg = phoneNumber ? 
        `Loading order data for ${customerName}...` : 
        `Loading order data...`;
    
    // Show loading alert
    const loadingAlert = document.createElement('div');
    loadingAlert.className = 'alert alert-info alert-dismissible fade show position-fixed';
    loadingAlert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    loadingAlert.innerHTML = `
        <i class="bi bi-hourglass-split me-2"></i>
        ${loadingMsg}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(loadingAlert);
    
    // First fetch the order data
    fetch(`get_order_data.php?id=${orderId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text(); // Get response as text first
        })
        .then(text => {
            // Try to parse as JSON
            try {
                const data = JSON.parse(text);
                return data;
            } catch (parseError) {
                console.error('JSON Parse Error:', parseError);
                console.error('Response Text:', text);
                throw new Error('Invalid response format from server');
            }
        })
        .then(data => {
            // Remove loading alert
            loadingAlert.remove();
            
            if (data.success) {
                console.log('Order data received:', data.order); // Debug log
                const message = generateOrderWhatsAppMessageFromData(data.order);
                
                if (phoneNumber && phoneNumber !== 'null') {
                    // Customer has mobile number - send directly
                    const whatsappUrl = `https://wa.me/92${phoneNumber}?text=${encodeURIComponent(message)}`;
                    
                    if (confirm(`Send order to ${customerName} via WhatsApp?`)) {
                        window.open(whatsappUrl, '_blank');
                    }
                } else {
                    // No mobile number - show modal for custom number
                    sendToAnotherNumber(orderId, customerName);
                }
            } else {
                showErrorAlert('Error: ' + (data.error || 'Could not load order data'));
            }
        })
        .catch(error => {
            // Remove loading alert
            loadingAlert.remove();
            
            console.error('Error:', error);
            showErrorAlert('Error: ' + error.message);
        });
}

// Function to show error alerts
function showErrorAlert(message) {
    const errorAlert = document.createElement('div');
    errorAlert.className = 'alert alert-danger alert-dismissible fade show position-fixed';
    errorAlert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    errorAlert.innerHTML = `
        <i class="bi bi-exclamation-triangle me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(errorAlert);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (errorAlert.parentNode) {
            errorAlert.remove();
        }
    }, 5000);
}

// Phone number input validation - optimized
(function() {
    'use strict';
    
    function initializePhoneValidation() {
        const phoneInput = document.getElementById('phoneNumber');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                // Remove non-numeric characters
                this.value = this.value.replace(/\D/g, '');
                
                // Limit to 10 digits
                if (this.value.length > 10) {
                    this.value = this.value.slice(0, 10);
                }
            }, { passive: true });
        }
    }
    
    // Use same optimization pattern as search
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePhoneValidation, { once: true });
    } else {
        Promise.resolve().then(initializePhoneValidation);
    }
})();

// Date Range Filtering Functions
function filterByDateRange() {
    const fromDate = document.getElementById('fromDate').value;
    const toDate = document.getElementById('toDate').value;
    
    if (!fromDate || !toDate) {
        alert('Please select both From Date and To Date');
        return;
    }
    
    const rows = document.querySelectorAll('.order-summary-row');
    let visibleCount = 0;
    let totalKaregar = 0;
    let totalMaterial = 0;
    let totalZakat = 0;
    let totalFinal = 0;
    let totalAmount = 0;
    
    rows.forEach(row => {
        const orderDate = row.getAttribute('data-order-date');
        const isInRange = orderDate >= fromDate && orderDate <= toDate;
        
        if (isInRange) {
            row.style.display = '';
            visibleCount++;
            
            // Calculate totals for visible rows
            const cells = row.querySelectorAll('td');
            if (cells.length >= 8) {
                totalAmount += parseFloat(cells[3].textContent.replace('Rs. ', '').replace(',', ''));
                totalKaregar += parseFloat(cells[4].textContent.replace('Rs. ', '').replace(',', ''));
                totalMaterial += parseFloat(cells[5].textContent.replace('Rs. ', '').replace(',', ''));
                totalZakat += parseFloat(cells[6].textContent.replace('Rs. ', '').replace(',', ''));
                totalFinal += parseFloat(cells[7].textContent.replace('Rs. ', '').replace(',', ''));
            }
        } else {
            row.style.display = 'none';
        }
    });
    
    // Update grand total row
    updateGrandTotalRow(totalAmount, totalKaregar, totalMaterial, totalZakat, totalFinal);
    
    // Show filter result
    if (visibleCount === 0) {
        alert('No orders found in the selected date range');
    } else {
        alert(`Found ${visibleCount} orders in the selected date range`);
    }
}

function clearDateFilter() {
    document.getElementById('fromDate').value = '';
    document.getElementById('toDate').value = '';
    
    // Show all rows
    const rows = document.querySelectorAll('.order-summary-row');
    rows.forEach(row => {
        row.style.display = '';
    });
    
    // Reset grand total to original values
    location.reload(); // Simple way to reset totals
}

function updateGrandTotalRow(totalAmount, totalKaregar, totalMaterial, totalZakat, totalFinal) {
    const grandTotalRow = document.getElementById('grandTotalRow');
    if (grandTotalRow) {
        const cells = grandTotalRow.querySelectorAll('td');
        if (cells.length >= 8) {
            cells[3].textContent = 'Rs. ' + totalAmount.toFixed(2);
            cells[4].textContent = 'Rs. ' + totalKaregar.toFixed(2);
            cells[5].textContent = 'Rs. ' + totalMaterial.toFixed(2);
            cells[6].textContent = 'Rs. ' + totalZakat.toFixed(2);
            cells[7].textContent = 'Rs. ' + totalFinal.toFixed(2);
        }
    }
}

// Excel Export Functionality
function exportToExcel(period) {
    const table = document.getElementById('costSummaryBody');
    const rows = table.querySelectorAll('tr:not([style*="display: none"])');
    
    if (rows.length === 0) {
        alert('No data to export');
        return;
    }
    
    let csvContent = '';
    
    // Add headers
    csvContent += 'Order No,Customer,Order Date,Total Amount,Karegar Price,Material Price,Zakat,Final Amount\n';
    
    // Add data rows (skip the grand total row for individual exports)
    for (let i = 0; i < rows.length - 1; i++) {
        const row = rows[i];
        const cells = row.querySelectorAll('td');
        
        if (cells.length >= 8) {
            const orderNo = cells[0].textContent.trim();
            const customer = cells[1].textContent.trim();
            const orderDate = cells[2].textContent.trim();
            const totalAmount = cells[3].textContent.replace('Rs. ', '').replace(',', '');
            const karegarPrice = cells[4].textContent.replace('Rs. ', '').replace(',', '');
            const materialPrice = cells[5].textContent.replace('Rs. ', '').replace(',', '');
            const zakat = cells[6].textContent.replace('Rs. ', '').replace(',', '');
            const finalAmount = cells[7].textContent.replace('Rs. ', '').replace(',', '');
            
            csvContent += `"${orderNo}","${customer}","${orderDate}","${totalAmount}","${karegarPrice}","${materialPrice}","${zakat}","${finalAmount}"\n`;
        }
    }
    
    // Add grand total row
    const grandTotalRow = rows[rows.length - 1];
    if (grandTotalRow && grandTotalRow.classList.contains('table-success')) {
        const cells = grandTotalRow.querySelectorAll('td');
        if (cells.length >= 8) {
            const totalAmountTotal = cells[3].textContent.replace('Rs. ', '').replace(',', '');
            const karegarTotal = cells[4].textContent.replace('Rs. ', '').replace(',', '');
            const materialTotal = cells[5].textContent.replace('Rs. ', '').replace(',', '');
            const zakatTotal = cells[6].textContent.replace('Rs. ', '').replace(',', '');
            const finalAmountTotal = cells[7].textContent.replace('Rs. ', '').replace(',', '');
            
            csvContent += `"GRAND TOTAL","","","${totalAmountTotal}","${karegarTotal}","${materialTotal}","${zakatTotal}","${finalAmountTotal}"\n`;
        }
    }
    
    // Create filename based on period
    const now = new Date();
    let filename = '';
    
    switch(period) {
        case 'daily':
            filename = `Order_Cost_Summary_Daily_${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}.csv`;
            break;
        case 'weekly':
            const weekStart = new Date(now.setDate(now.getDate() - now.getDay()));
            filename = `Order_Cost_Summary_Weekly_${weekStart.getFullYear()}-${String(weekStart.getMonth() + 1).padStart(2, '0')}-${String(weekStart.getDate()).padStart(2, '0')}.csv`;
            break;
        case 'monthly':
            filename = `Order_Cost_Summary_Monthly_${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}.csv`;
            break;
        case 'custom':
            const fromDate = document.getElementById('fromDate').value;
            const toDate = document.getElementById('toDate').value;
            if (fromDate && toDate) {
                filename = `Order_Cost_Summary_${fromDate}_to_${toDate}.csv`;
            } else {
                filename = `Order_Cost_Summary_Custom_${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}.csv`;
            }
            break;
        default:
            filename = `Order_Cost_Summary_${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}.csv`;
    }
    
    // Create and download file
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Show success message
    const button = event.target.closest('button');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="bi bi-check-circle me-1"></i>Exported!';
    button.classList.remove('btn-outline-primary', 'btn-outline-success');
    button.classList.add('btn-success');
    
    setTimeout(() => {
        button.innerHTML = originalText;
        button.classList.remove('btn-success');
        if (button.textContent.includes('Custom Range')) {
            button.classList.add('btn-outline-success');
        } else {
            button.classList.add('btn-outline-primary');
        }
    }, 2000);
}
</script>

<?php include 'includes/footer.php'; ?>

<style>
/* WhatsApp button styling */
.btn-whatsapp {
    background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
    border: none;
    color: white;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-whatsapp:hover {
    background: linear-gradient(135deg, #128C7E 0%, #075E54 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(37, 211, 102, 0.3);
    color: white;
}

.btn-whatsapp:active {
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(37, 211, 102, 0.3);
}

.btn-whatsapp:disabled {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%) !important;
    border: none;
    color: #adb5bd !important;
    cursor: not-allowed;
    opacity: 0.7;
}

.btn-whatsapp:disabled:hover {
    transform: none;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Alert styling for WhatsApp functionality */
.alert.position-fixed {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    border: none;
    border-radius: 8px;
    font-weight: 500;
}

.alert-info {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    color: white;
    border-left: 4px solid #17a2b8;
}

.alert-danger {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
    border-left: 4px solid #dc3545;
}

.alert .btn-close {
    filter: brightness(0) invert(1);
}

/* Loading animation */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.bi-hourglass-split {
    animation: spin 1s linear infinite;
}

/* Modal styling */
.modal-content {
    border-radius: 16px;
    border: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
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

/* Enhanced button styles */
.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
}

.btn-sm:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

/* Action buttons container */
.d-flex.flex-wrap.gap-1 {
    gap: 0.25rem !important;
}

/* Mobile responsiveness for action buttons */
@media (max-width: 767.98px) {
    .d-flex.flex-wrap.gap-1 {
        flex-direction: column;
        gap: 0.25rem !important;
    }
    
    .btn-sm {
        width: 100%;
        margin-bottom: 0.25rem;
    }
    
    .modal-dialog {
        margin: 1rem;
        max-width: calc(100% - 2rem);
    }
}

/* WhatsApp button mobile optimization */
@media (max-width: 767.98px) {
    .btn-whatsapp {
        touch-action: manipulation;
        -webkit-tap-highlight-color: transparent;
    }
    
    .btn-whatsapp:active {
        transform: scale(0.95);
    }
}

/* Enhanced table styling */
.table {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.table thead th {
    background: linear-gradient(135deg, #343a40 0%, #495057 100%);
    border-color: #454d55;
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

/* Status badge enhancements */
.badge {
    font-weight: 500;
    letter-spacing: 0.3px;
    transition: all 0.2s ease;
}

.badge:hover {
    transform: scale(1.05);
}

/* Card enhancements */
.card {
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    padding: 1rem 1.5rem;
}

/* Form control enhancements */
.form-control:focus {
    border-color: #25D366;
    box-shadow: 0 0 0 0.2rem rgba(37, 211, 102, 0.25);
}

.form-select:focus {
    border-color: #25D366;
    box-shadow: 0 0 0 0.2rem rgba(37, 211, 102, 0.25);
}

/* Alert enhancements */
.alert {
    border-radius: 8px;
    border: none;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}

.alert-info {
    background-color: #d1ecf1;
    color: #0c5460;
    border-left: 4px solid #17a2b8;
}

/* Performance optimizations */
.btn, .badge, .card, .modal-content {
    will-change: transform;
    backface-visibility: hidden;
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
}

/* Desktop and laptop styling - restore original layout */
@media (min-width: 993px) {
    .card-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-bottom: 1px solid #dee2e6;
        font-weight: 600;
        color: #495057;
        padding: 1.25rem 1.5rem;
    }
    
    .card-header h5 {
        font-size: 1.25rem;
        margin-bottom: 0;
        font-weight: 600;
    }
    
    .card-header .d-flex {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        width: 100%;
    }
    
    .input-group {
        max-width: 350px;
        min-width: 250px;
    }
    
    .input-group .form-control {
        border-radius: 0.375rem 0 0 0.375rem;
    }
    
    .input-group .btn {
        border-radius: 0 0.375rem 0.375rem 0;
    }
    
    /* Ensure perfect alignment for laptop screens */
    .card-header .d-flex > h5 {
        flex-shrink: 0;
        margin-right: 1rem;
    }
    
    .card-header .d-flex > .input-group {
        flex-shrink: 0;
        margin-left: auto;
    }
    
    .table {
        font-size: 0.9rem;
    }
    
    .table th,
    .table td {
        padding: 0.5rem 0.375rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }
    
    .badge {
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
    }
    
    /* Desktop status buttons styling */
    .btn-group-vertical {
        width: 100%;
        display: flex !important;
        flex-direction: column;
    }
    
    .btn-group-vertical .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        margin-bottom: 0.125rem;
        width: 100%;
        border-radius: 0.25rem !important;
        border: 1px solid transparent;
    }
    
    .btn-group-vertical .btn-sm:first-child {
        border-top-left-radius: 0.25rem !important;
        border-top-right-radius: 0.25rem !important;
    }
    
    .btn-group-vertical .btn-sm:last-child {
        border-bottom-left-radius: 0.25rem !important;
        border-bottom-right-radius: 0.25rem !important;
        margin-bottom: 0;
    }
    
    /* Hide mobile dropdown on desktop */
    .dropdown.d-lg-none {
        display: none !important;
    }
    
    /* Ensure desktop status layout */
    .d-flex.align-items-center.justify-content-between {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    /* Status column width for desktop */
    .table td[data-label="Status"] {
        min-width: 140px;
        max-width: 160px;
        vertical-align: top;
    }
}

/* Cost Summary Table Styling */
.cost-summary-table {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.cost-summary-table .table {
    margin-bottom: 0;
}

.cost-summary-table .table th {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
    color: white;
    font-weight: 600;
    border: none;
    padding: 0.75rem 0.5rem;
}

.cost-summary-table .table td {
    padding: 0.5rem;
    border-color: #dee2e6;
    vertical-align: middle;
}

.cost-summary-table .table-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    font-weight: 600;
}

.cost-summary-table .table-success td {
    border-color: #c3e6cb;
}

/* Export buttons styling */
.btn-group .btn {
    border-radius: 0.375rem;
    margin-left: 0.25rem;
}

.btn-group .btn:first-child {
    margin-left: 0;
}

.btn-group .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

/* Search and Filter Styling */

/* Remove color styling from customer names */
.table td[data-label="Customer"] {
    color: #212529 !important;
    font-weight: normal !important;
}

.input-group {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    overflow: hidden;
}

.input-group-text {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
    color: white;
    border: none;
    font-weight: 500;
}

#orderSearchInput {
    border: none;
    border-left: 1px solid #dee2e6;
    font-weight: 500;
    transition: all 0.3s ease;
}

#orderSearchInput:focus {
    border-color: #25D366;
    box-shadow: 0 0 0 0.2rem rgba(37, 211, 102, 0.25);
    background-color: #fff;
}

#clearSearchBtn {
    border: none;
    border-left: 1px solid #dee2e6;
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
    transition: all 0.3s ease;
}

#clearSearchBtn:hover {
    background: linear-gradient(135deg, #c82333 0%, #a71e2a 100%);
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
}


/* Search results styling */
.alert-warning {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    color: #856404;
    border-left: 4px solid #ffc107;
    border-radius: 8px;
}

.alert-warning .btn-outline-primary {
    border-color: #25D366;
    color: #25D366;
}

.alert-warning .btn-outline-primary:hover {
    background-color: #25D366;
    border-color: #25D366;
    color: white;
}

/* Tablet and small laptop responsiveness */
@media (max-width: 992px) and (min-width: 769px) {
    .card-header {
        padding: 1rem;
    }
    
    .card-header h5 {
        font-size: 1.375rem;
        margin-bottom: 0;
    }
    
    .card-header .d-flex {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
    
    .input-group {
        max-width: 350px;
    }
    
    .table {
        font-size: 0.9rem;
    }
    
    .table th,
    .table td {
        padding: 0.5rem 0.375rem;
    }
    
    .btn-sm {
        padding: 0.3rem 0.6rem;
        font-size: 0.8rem;
    }
    
    .badge {
        font-size: 0.8rem;
        padding: 0.3rem 0.6rem;
    }
    
    /* Action buttons horizontal on tablets */
    .d-flex.flex-column.flex-lg-row.gap-1 {
        flex-direction: row !important;
        gap: 0.25rem !important;
    }
    
    .d-flex.flex-column.flex-lg-row.gap-1 .btn {
        width: auto;
        margin-bottom: 0;
    }
    
    /* Tablet status styling */
    .badge {
        font-size: 0.75rem;
        padding: 0.3rem 0.6rem;
    }
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .card-header {
        padding: 1rem;
    }
    
    .card-header h5 {
        font-size: 1.25rem;
        margin-bottom: 0.5rem;
    }
    
    .card-header .d-flex {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }
    
    .input-group {
        width: 100% !important;
        max-width: 100%;
    }
    
    /* Optimize table for mobile */
    .table-responsive {
        border: none;
        -webkit-overflow-scrolling: touch;
    }
    
    .table {
        font-size: 0.875rem;
        margin-bottom: 0;
    }
    
    .table th,
    .table td {
        padding: 0.5rem 0.25rem;
        border-width: 1px;
    }
    
    /* Make table more compact on mobile */
    .table th,
    .table td {
        font-size: 0.8rem;
    }
    
    /* Mobile status dropdown styling */
    .dropdown-toggle {
        padding: 0.25rem 0.5rem;
        font-size: 0.7rem;
        min-height: 32px;
        min-width: 32px;
        border: 1px solid #dee2e6;
    }
    
    .dropdown-menu {
        font-size: 0.8rem;
        min-width: 150px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border: 1px solid #dee2e6;
        position: absolute;
        top: 100%;
        right: 0;
        left: auto;
        z-index: 1050;
        transform: translateX(0);
    }
    
    .dropdown-item {
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
        min-height: 44px;
        display: flex;
        align-items: center;
        border-bottom: 1px solid #f8f9fa;
        white-space: nowrap;
    }
    
    .dropdown-item:last-child {
        border-bottom: none;
    }
    
    .dropdown-item:hover {
        background-color: #f8f9fa;
    }
    
    /* Status badge mobile styling */
    .badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
        white-space: nowrap;
    }
    
    /* Fix dropdown positioning */
    .dropdown {
        position: relative;
    }
    
    /* Ensure proper alignment */
    .d-flex.align-items-center.justify-content-between {
        width: 100%;
    }
}

@media (max-width: 576px) {
    .card-header {
        padding: 0.75rem;
    }
    
    .card-header h5 {
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
    }
    
    .card-header .d-flex {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }
    
    .input-group {
        flex-direction: column;
    }
    
    .input-group-text {
        border-radius: 0;
        border-bottom: 1px solid #dee2e6;
        padding: 0.5rem;
    }
    
    #orderSearchInput {
        border-radius: 0;
        border-left: none;
        border-right: none;
        padding: 0.5rem;
    }
    
    #clearSearchBtn {
        border-radius: 0;
        border-left: none;
        border-top: 1px solid #dee2e6;
        padding: 0.5rem;
    }
    
    /* Ultra-compact table for small screens */
    .table {
        font-size: 0.75rem;
    }
    
    .table th,
    .table td {
        padding: 0.375rem 0.125rem;
    }
    
    /* Make buttons smaller and stack vertically */
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.7rem;
    }
    
    /* Stack action buttons vertically on small screens */
    .d-flex.flex-column.flex-lg-row.gap-1 {
        flex-direction: column !important;
        gap: 0.25rem !important;
    }
    
    .d-flex.flex-column.flex-lg-row.gap-1 .btn {
        width: 100%;
        margin-bottom: 0.125rem;
    }
    
    /* Optimize badges */
    .badge {
        font-size: 0.65rem;
        padding: 0.2rem 0.4rem;
    }
    
    /* Make order numbers more prominent */
    .table td strong {
        font-size: 0.8rem;
    }
    
    /* Mobile status column styling */
    .table td[data-label="Status"] {
        min-width: 80px;
        max-width: 120px;
    }
}

/* Touch-friendly interactions for mobile */
@media (hover: none) and (pointer: coarse) {
    .btn {
        min-height: 44px;
        min-width: 44px;
    }
    
    .btn-sm {
        min-height: 36px;
        min-width: 36px;
    }
    
    .input-group-text,
    #orderSearchInput,
    #clearSearchBtn {
        min-height: 44px;
    }
    
    .btn:hover {
        transform: none;
        box-shadow: none;
    }
    
    .btn:active {
        transform: scale(0.95);
    }
}
</style>
