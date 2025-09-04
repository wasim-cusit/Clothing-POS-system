<?php
require_once 'includes/auth.php';
require_login();
require_once 'includes/config.php';

$activePage = 'order';

// Get order ID from URL
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    header('Location: order.php?error=Invalid order ID');
    exit;
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
        header('Location: order.php?error=Order not found');
        exit;
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
    header('Location: order.php?error=Error fetching order');
    exit;
}

include 'includes/header.php';
?>

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Order Details</h2>
        <div>
            <a href="print_order.php?id=<?= $order_id ?>" target="_blank" class="btn btn-success">
                <i class="bi bi-printer"></i> Print Order
            </a>
            <a href="order.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Orders
            </a>
        </div>
    </div>

    <!-- Order Header -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                Order #<?= htmlspecialchars($order['order_no'] ?? 'ORD-' . str_pad($order['id'], 3, '0', STR_PAD_LEFT)) ?>
                <span class="badge bg-<?= 
                    $order['status'] === 'Pending' ? 'warning' : 
                    ($order['status'] === 'Confirmed' ? 'info' : 
                    ($order['status'] === 'In Progress' ? 'primary' : 
                    ($order['status'] === 'Completed' ? 'success' : 
                    ($order['status'] === 'Cancelled' ? 'danger' : 'secondary'))))
                ?> ms-2">
                    <?= htmlspecialchars($order['status'] ?? 'Pending') ?>
                </span>
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Order Date:</strong> <?= htmlspecialchars(date('d/m/Y', strtotime($order['order_date']))) ?></p>
                    <p><strong>Delivery Date:</strong> <?= $order['delivery_date'] ? htmlspecialchars(date('d/m/Y', strtotime($order['delivery_date']))) : 'Not set' ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Total Amount:</strong> <span class="text-primary fw-bold">PKR <?= number_format($order['total_amount'], 2) ?></span></p>
                    <p><strong>Paid Amount:</strong> <span class="text-success">PKR <?= number_format($order['paid_amount'], 2) ?></span></p>
                    <p><strong>Remaining:</strong> <span class="text-warning fw-bold">PKR <?= number_format($order['remaining_amount'], 2) ?></span></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Information -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Customer Information</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Name:</strong> <?= htmlspecialchars($order['customer_name'] ?? 'Walk-in Customer') ?></p>
                    <?php if ($order['mobile']): ?>
                        <p><strong>Mobile:</strong> <?= htmlspecialchars($order['mobile']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <?php if ($order['address']): ?>
                        <p><strong>Address:</strong> <?= htmlspecialchars($order['address']) ?></p>
                    <?php endif; ?>
                    <?php if ($order['email']): ?>
                        <p><strong>Email:</strong> <?= htmlspecialchars($order['email']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Items -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Order Items</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($order_items)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Description</th>
                                <th>Unit</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $index => $item): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($item['description'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($item['product_unit'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($item['quantity']) ?></td>
                                    <td>PKR <?= number_format($item['unit_price'], 2) ?></td>
                                    <td>PKR <?= number_format($item['total_price'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No items found for this order.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pricing Summary -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Pricing Summary</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 offset-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Sub Total:</strong></td>
                            <td class="text-end">PKR <?= number_format($order['sub_total'], 2) ?></td>
                        </tr>
                        <?php if ($order['discount'] > 0): ?>
                            <tr>
                                <td><strong>Discount:</strong></td>
                                <td class="text-end text-danger">-PKR <?= number_format($order['discount'], 2) ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr class="border-top">
                            <td><strong>Total Amount:</strong></td>
                            <td class="text-end"><strong class="text-primary">PKR <?= number_format($order['total_amount'], 2) ?></strong></td>
                        </tr>
                        <tr>
                            <td><strong>Paid Amount:</strong></td>
                            <td class="text-end text-success">PKR <?= number_format($order['paid_amount'], 2) ?></td>
                        </tr>
                        <tr class="border-top">
                            <td><strong>Remaining Amount:</strong></td>
                            <td class="text-end"><strong class="text-warning">PKR <?= number_format($order['remaining_amount'], 2) ?></strong></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Details/Notes -->
    <?php if ($order['details']): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Order Details/Notes</h5>
            </div>
            <div class="card-body">
                <p class="mb-0"><?= nl2br(htmlspecialchars($order['details'])) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <div class="card">
        <div class="card-body text-center">
            <a href="print_order.php?id=<?= $order_id ?>" target="_blank" class="btn btn-success me-2">
                <i class="bi bi-printer"></i> Print Order
            </a>
            <a href="order.php" class="btn btn-secondary me-2">
                <i class="bi bi-arrow-left"></i> Back to Orders
            </a>
            <a href="order.php?delete=<?= $order_id ?>" class="btn btn-danger" 
               onclick="return confirm('Are you sure you want to delete this order? This action cannot be undone.')">
                <i class="bi bi-trash"></i> Delete Order
            </a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
