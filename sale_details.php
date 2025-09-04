<?php
require_once 'includes/auth.php';
require_login();
require_once 'includes/config.php';

$activePage = 'sales';

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
    SELECT 
        si.id,
        si.sale_id,
        si.product_id,
        si.warehouse_id,
        si.product_code,
        si.price,
        si.stock_qty,
        si.quantity,
        si.total_price,
        si.category_name,
        si.notes,
        p.product_name,
        p.product_unit,
        COALESCE(cat.category, si.category_name) AS category_name_final
    FROM sale_items si
    LEFT JOIN products p ON si.product_id = p.id
    LEFT JOIN categories cat ON p.category_id = cat.id
    WHERE si.sale_id = ?
    ORDER BY si.id
");

try {
    $stmt->execute([$sale_id]);
    $sale_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching sale items: " . $e->getMessage());
    $sale_items = [];
}

// Use the stored subtotal from the sale table for consistency
$grand_total = $sale['subtotal'] ?? 0;

// Helper function to extract color from notes
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

include 'includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-10 ms-sm-auto px-4 py-5" style="margin-top: 25px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0"><i class="bi bi-receipt text-primary"></i> Sale Details</h2>
                <div class="btn-group" role="group">
                    <a href="sales.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Sales
                    </a>
                    <a href="print_invoice.php?id=<?= $sale_id ?>" target="_blank" class="btn btn-success">
                        <i class="bi bi-printer"></i> Print Invoice
                    </a>
                </div>
            </div>
            
            <!-- Sale Summary Information -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-receipt"></i> Sale Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6 class="text-primary border-bottom pb-2">Invoice Information</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Invoice No:</strong></td>
                                    <td><span class="badge bg-primary fs-6"><?= htmlspecialchars($sale['sale_no']) ?></span></td>
                                </tr>
                                <tr>
                                    <td><strong>Sale Date:</strong></td>
                                    <td><i class="bi bi-calendar-event"></i> <?= date('d M Y', strtotime($sale['sale_date'])) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Delivery Date:</strong></td>
                                    <td>
                                        <?php if ($sale['delivery_date']): ?>
                                            <i class="bi bi-calendar-check text-success"></i> <?= date('d M Y', strtotime($sale['delivery_date'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Created By:</strong></td>
                                    <td><i class="bi bi-person-badge"></i> <?= htmlspecialchars($sale['created_by_name']) ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-primary border-bottom pb-2">Customer Information</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Customer:</strong></td>
                                    <td><i class="bi bi-person-circle"></i> <?= htmlspecialchars($sale['customer_name']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Contact:</strong></td>
                                    <td>
                                        <?php if ($sale['customer_contact']): ?>
                                            <i class="bi bi-telephone"></i> <?= htmlspecialchars($sale['customer_contact']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Address:</strong></td>
                                    <td>
                                        <?php if ($sale['customer_address']): ?>
                                            <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($sale['customer_address']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td>
                                        <?php if ($sale['customer_email']): ?>
                                            <i class="bi bi-envelope"></i> <?= htmlspecialchars($sale['customer_email']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Reference Persons:</strong></td>
                                    <td>
                                        <?php if ($sale['reference_persons']): ?>
                                            <i class="bi bi-people"></i> <?= htmlspecialchars($sale['reference_persons']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

                         <?php
             // Calculate grand total from sale items
             $grand_total = 0;
             foreach ($sale_items as $item) {
                 $grand_total += $item['total_price'];
             }
             ?>
             
             <!-- Financial Information -->
             <div class="card mb-4">
                 <div class="card-header bg-success text-white">
                     <h5 class="mb-0"><i class="bi bi-calculator"></i> Financial Details</h5>
                 </div>
                 <div class="card-body">
                     <div class="row">
                         <div class="col-md-6 mb-3">
                             <h6 class="text-success border-bottom pb-2">Pricing Summary</h6>
                             <table class="table table-borderless">
                                 
                                 <tr>
                                     <td><strong>Total Amount:</strong></td>
                                     <td><span class="badge bg-info">PKR <?= number_format($grand_total, 2) ?></span></td>
                                 </tr>
                                 <tr>
                                     <td><strong>Discount:</strong></td>
                                     <td>
                                         <?php if ($sale['discount'] > 0): ?>
                                             <span class="badge bg-warning text-dark">PKR <?= number_format($sale['discount'], 2) ?></span>
                                         <?php else: ?>
                                             <span class="text-muted">-</span>
                                         <?php endif; ?>
                                     </td>
                                 </tr>
                                 <tr>
                                     <td><strong>After Discount:</strong></td>
                                     <td><span class="badge bg-secondary">PKR <?= number_format($grand_total - $sale['discount'], 2) ?></span></td>
                                 </tr>
                                 <tr>
                                     <td><strong>Final Total:</strong></td>
                                     <td><span class="badge bg-success fs-6"><strong>PKR <?= number_format($grand_total - $sale['discount'], 2) ?></strong></span></td>
                                 </tr>
                             </table>
                         </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-success border-bottom pb-2">Payment Information</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Paid Amount:</strong></td>
                                    <td><span class="badge bg-success">PKR <?= number_format($sale['paid_amount'], 2) ?></span></td>
                                </tr>
                                                                 <tr>
                                     <td><strong>Due Amount:</strong></td>
                                     <td>
                                         <?php 
                                         $final_total = $grand_total - $sale['discount'];
                                         $due_amount = $final_total - $sale['paid_amount'];
                                         if ($due_amount > 0): ?>
                                             <span class="badge bg-danger">PKR <?= number_format($due_amount, 2) ?></span>
                                         <?php else: ?>
                                             <span class="badge bg-success">Paid</span>
                                         <?php endif; ?>
                                     </td>
                                 </tr>
                                <tr>
                                    <td><strong>Payment Method:</strong></td>
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
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Sale Items -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-box-seam"></i> Sale Items</h5>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th><i class="bi bi-hash"></i> #</th>
                                <th><i class="bi bi-box"></i> Product Name</th>
                                <th><i class="bi bi-tags"></i> Category</th>
                                <th><i class="bi bi-palette"></i> Color</th>
                                <th><i class="bi bi-rulers"></i> Unit</th>
                                <th><i class="bi bi-123"></i> Quantity</th>
                                <th><i class="bi bi-currency-dollar"></i> Unit Price</th>
                                <th><i class="bi bi-calculator"></i> Total</th>
                                <th><i class="bi bi-check-circle"></i> Paid</th>
                                <th><i class="bi bi-exclamation-circle"></i> Remaining</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1; 
                            $final_total = $grand_total - $sale['discount'];
                            foreach ($sale_items as $item): 
                                // Calculate proportional paid and remaining amounts for each item
                                $item_proportion = $item['total_price'] / $grand_total;
                                $item_paid = $item_proportion * $sale['paid_amount'];
                                $item_remaining = $item['total_price'] - $item_paid;
                            ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?= $counter++ ?></span></td>
                                    <td><strong><?= htmlspecialchars($item['product_name']) ?></strong></td>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($item['category_name_final']) ?></span></td>
                                    <td>
                                        <?php 
                                        $color = extractColorFromNotes($item['notes'] ?? '');
                                        if ($color !== 'N/A') {
                                            echo '<span class="badge bg-light text-dark">' . htmlspecialchars($color) . '</span>';
                                        } else {
                                            echo '<span class="text-muted">-</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($item['product_unit']) ?></td>
                                    <td><span class="badge bg-warning text-dark"><?= number_format($item['quantity'], 2) ?></span></td>
                                    <td><span class="badge bg-primary">PKR <?= number_format($item['price'], 2) ?></span></td>
                                    <td><span class="badge bg-success"><strong>PKR <?= number_format($item['total_price'], 2) ?></strong></span></td>
                                    <td><span class="badge bg-success">PKR <?= number_format($item_paid, 2) ?></span></td>
                                    <td>
                                        <?php if ($item_remaining > 0): ?>
                                            <span class="badge bg-danger">PKR <?= number_format($item_remaining, 2) ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Paid</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="table-warning">
                                <td colspan="8" class="text-end"><strong><i class="bi bi-calculator"></i> Grand Total:</strong></td>
                                <td><strong><span class="badge bg-success fs-6">PKR <?= number_format($grand_total, 2) ?></span></strong></td>
                            </tr>
                        </tbody>
                    </table>
                    <?php if (empty($sale_items)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-cart-x fs-1"></i>
                            <h5 class="mt-3">No items found for this sale.</h5>
                            <p>This sale doesn't have any items associated with it.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
