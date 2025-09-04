<?php
require_once 'includes/auth.php';
require_login();
require_once 'includes/config.php';
$activePage = 'dashboard';

// Get real-time data for dashboard
try {
    // Today's sales
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as today_sales FROM sale WHERE DATE(sale_date) = CURDATE()");
    $stmt->execute();
    $today_sales = $stmt->fetchColumn();

    // This month's sales
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as month_sales FROM sale WHERE DATE_FORMAT(sale_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
    $stmt->execute();
    $month_sales = $stmt->fetchColumn();

    // Total sales
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as total_sales FROM sale");
    $total_sales = $stmt->fetchColumn();

    // Total stock value (using stock_items table)
    $stmt = $pdo->query("SELECT COALESCE(SUM(si.quantity * si.purchase_price), 0) as stock_value FROM stock_items si WHERE si.status = 'available'");
    $stock_value = $stmt->fetchColumn();

    // Upcoming deliveries (next 7 days)
    $stmt = $pdo->prepare("SELECT COUNT(*) as upcoming_deliveries FROM sale WHERE delivery_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND delivery_date IS NOT NULL");
    $stmt->execute();
    $upcoming_deliveries = $stmt->fetchColumn();

    // Low stock alerts (using products table with alert_quantity)
    $stmt = $pdo->query("SELECT COUNT(*) as low_stock_count FROM products p JOIN stock_items si ON p.id = si.product_id WHERE si.quantity <= p.alert_quantity AND si.status = 'available'");
    $low_stock_count = $stmt->fetchColumn();

    // Today's expenses
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as today_expenses FROM expenses WHERE DATE(exp_date) = CURDATE()");
    $stmt->execute();
    $today_expenses = $stmt->fetchColumn();

    // Today's purchases
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as today_purchases FROM purchase WHERE DATE(purchase_date) = CURDATE() AND status = 'completed'");
    $stmt->execute();
    $today_purchases = $stmt->fetchColumn();

    // Total expenses
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total_expenses FROM expenses");
    $total_expenses = $stmt->fetchColumn();

    // Total purchases
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as total_purchases FROM purchase");
    $total_purchases = $stmt->fetchColumn();

    // Monthly sales trend (last 6 months)
    $stmt = $pdo->query("SELECT DATE_FORMAT(sale_date, '%Y-%m') as month, SUM(total_amount) as total FROM sale WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(sale_date, '%Y-%m') ORDER BY month");
    $monthly_sales = $stmt->fetchAll();

    // Recent sales with customer names
    $stmt = $pdo->query("SELECT s.*, COALESCE(c.name, s.walk_in_cust_name) as customer_name FROM sale s LEFT JOIN customer c ON s.customer_id = c.id ORDER BY s.created_at DESC LIMIT 5");
    $recent_sales = $stmt->fetchAll();

    // Low stock products
    $stmt = $pdo->query("SELECT p.product_name, p.alert_quantity, COALESCE(SUM(si.quantity), 0) as current_stock FROM products p LEFT JOIN stock_items si ON p.id = si.product_id AND si.status = 'available' GROUP BY p.id HAVING current_stock <= p.alert_quantity ORDER BY current_stock ASC LIMIT 5");
    $low_stock_products = $stmt->fetchAll();

    // Total customers
    $stmt = $pdo->query("SELECT COUNT(*) as total_customers FROM customer");
    $total_customers = $stmt->fetchColumn();

    // Total products
    $stmt = $pdo->query("SELECT COUNT(*) as total_products FROM products");
    $total_products = $stmt->fetchColumn();

} catch (Exception $e) {
    // Handle errors gracefully
    $today_sales = 0;
    $month_sales = 0;
    $total_sales = 0;
    $stock_value = 0;
    $upcoming_deliveries = 0;
    $low_stock_count = 0;
    $today_expenses = 0;
    $today_purchases = 0;
    $total_expenses = 0;
    $total_purchases = 0;
    $monthly_sales = [];
    $recent_sales = [];
    $low_stock_products = [];
    $total_customers = 0;
    $total_products = 0;
}

include 'includes/header.php';
?>

<style>
/* Dashboard-specific mobile responsive styles */
.dashboard-stats-card {
    transition: all 0.3s ease;
    border-radius: 12px;
    overflow: hidden;
    will-change: transform;
}

.dashboard-stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.dashboard-stats-card .card-body {
    padding: 1.5rem;
}

.dashboard-stats-card .stats-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-size: 1.5rem;
}

.dashboard-stats-card .stats-content h6 {
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.dashboard-stats-card .stats-content h4 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0;
}

/* Mobile-first responsive grid */
@media (max-width: 767.98px) {
    .dashboard-stats-card .card-body {
        padding: 1rem;
    }
    
    .dashboard-stats-card .stats-icon {
        width: 50px;
        height: 50px;
        font-size: 1.25rem;
    }
    
    .dashboard-stats-card .stats-content h6 {
        font-size: 0.8rem;
    }
    
    .dashboard-stats-card .stats-content h4 {
        font-size: 1.25rem;
    }
    
    /* Stack cards vertically on mobile */
    .row.g-4 > [class*="col-"] {
        margin-bottom: 1rem;
    }
    
    /* Daily books summary mobile layout */
    .daily-books-summary .col-md-3 {
        margin-bottom: 1rem;
    }
    
    .daily-books-summary .d-flex {
        flex-direction: column;
        text-align: center;
        padding: 1rem;
    }
    
    .daily-books-summary .stats-icon {
        margin: 0 auto 0.5rem auto;
    }
    
    /* Chart container mobile optimization */
    .chart-container {
        height: 300px;
        margin-bottom: 1rem;
    }
    
    /* Quick actions mobile layout */
    .quick-actions .btn {
        margin-bottom: 0.5rem;
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
    }
    
    /* Tables mobile optimization */
    .table-responsive {
        border-radius: 8px;
        overflow: hidden;
    }
    
    .table th,
    .table td {
        padding: 0.75rem 0.5rem;
        font-size: 0.85rem;
        vertical-align: middle;
    }
    
    /* Mobile card spacing */
    .card {
        margin-bottom: 1rem;
        border-radius: 12px;
    }
    
    .card-header {
        padding: 1rem;
        border-bottom: 1px solid rgba(0,0,0,0.125);
    }
    
    .card-body {
        padding: 1rem;
    }
}

@media (max-width: 575.98px) {
    .dashboard-stats-card .card-body {
        padding: 0.75rem;
    }
    
    .dashboard-stats-card .stats-icon {
        width: 45px;
        height: 45px;
        font-size: 1.1rem;
    }
    
    .dashboard-stats-card .stats-content h6 {
        font-size: 0.75rem;
    }
    
    .dashboard-stats-card .stats-content h4 {
        font-size: 1.1rem;
    }
    
    /* Ultra-small mobile optimization */
    .container-fluid {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }
    
    .main-content {
        padding: 0.5rem;
    }
    
    /* Mobile table improvements */
    .table th,
    .table td {
        padding: 0.5rem 0.25rem;
        font-size: 0.8rem;
    }
    
    /* Mobile button improvements */
    .btn {
        padding: 0.6rem 0.8rem;
        font-size: 0.85rem;
        border-radius: 8px;
    }
}

/* Touch-friendly interactions */
@media (hover: none) and (pointer: coarse) {
    .dashboard-stats-card:hover {
        transform: none;
    }
    
    .dashboard-stats-card:active {
        transform: scale(0.98);
    }
    
    .btn:active {
        transform: scale(0.95);
    }
}

/* Landscape mobile optimization */
@media (max-width: 767.98px) and (orientation: landscape) {
    .dashboard-stats-card .card-body {
        padding: 0.75rem;
    }
    
    .chart-container {
        height: 250px;
    }
}

/* Enhanced mobile navigation for dashboard */
@media (max-width: 1199.98px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .dashboard-header h1 {
        font-size: 1.5rem;
        margin-bottom: 0;
    }
}

@media (max-width: 767.98px) {
    .dashboard-header {
        margin-bottom: 1.5rem;
    }
    
    .dashboard-header h1 {
        font-size: 1.25rem;
    }
}

/* Mobile chart responsiveness */
.chart-container {
    position: relative;
    height: 400px;
    width: 100%;
    contain: layout style paint;
}

@media (max-width: 767.98px) {
    .chart-container {
        height: 300px;
    }
}

@media (max-width: 575.98px) {
    .chart-container {
        height: 250px;
    }
}

/* Mobile-friendly data tables */
.mobile-table {
    font-size: 0.9rem;
}

.mobile-table .table th,
.mobile-table .table td {
    padding: 0.75rem 0.5rem;
}

@media (max-width: 575.98px) {
    .mobile-table .table th,
    .mobile-table .table td {
        padding: 0.5rem 0.25rem;
        font-size: 0.8rem;
    }
}

/* Mobile quick actions grid */
.quick-actions-grid {
    display: grid;
    gap: 0.75rem;
}

@media (max-width: 767.98px) {
    .quick-actions-grid {
        grid-template-columns: 1fr;
    }
}

@media (min-width: 768px) {
    .quick-actions-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1200px) {
    .quick-actions-grid {
        grid-template-columns: 1fr;
    }
}

/* Performance optimizations */
.dashboard-stats-card,
.btn,
.card {
    backface-visibility: hidden;
    transform: translateZ(0);
}

/* Prevent layout thrashing */
.chart-container canvas {
    display: block;
    max-width: 100%;
    height: auto;
}
</style>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-10 ms-sm-auto px-4 py-5" style="margin-top: 25px;">
            <div class="dashboard-header d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2">
                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                </h1>
            </div>

            <!-- Stats Cards Row 1 -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
                    <div class="card dashboard-stats-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="stats-icon bg-primary bg-opacity-10">
                                        <i class="bi bi-cash-coin text-primary"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3 stats-content">
                                    <h6 class="card-title text-muted mb-1">Today's Sales</h6>
                                    <h4 class="mb-0 text-primary">PKR <?= number_format($today_sales, 2) ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
                    <div class="card dashboard-stats-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="stats-icon bg-success bg-opacity-10">
                                        <i class="bi bi-graph-up text-success"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3 stats-content">
                                    <h6 class="card-title text-muted mb-1">This Month</h6>
                                    <h4 class="mb-0 text-success">PKR <?= number_format($month_sales, 2) ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
                    <div class="card dashboard-stats-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="stats-icon bg-info bg-opacity-10">
                                        <i class="bi bi-box-seam text-info"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3 stats-content">
                                    <h6 class="card-title text-muted mb-1">Stock Value</h6>
                                    <h4 class="mb-0 text-info">PKR <?= number_format($stock_value, 2) ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
                    <div class="card dashboard-stats-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="stats-icon bg-warning bg-opacity-10">
                                        <i class="bi bi-people text-warning"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3 stats-content">
                                    <h6 class="card-title text-muted mb-1">Total Customers</h6>
                                    <h4 class="mb-0 text-warning"><?= number_format($total_customers) ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards Row 2 -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
                    <div class="card dashboard-stats-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="stats-icon bg-danger bg-opacity-10">
                                        <i class="bi bi-exclamation-triangle text-danger"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3 stats-content">
                                    <h6 class="card-title text-muted mb-1">Low Stock Alerts</h6>
                                    <h4 class="mb-0 text-danger"><?= $low_stock_count ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
                    <div class="card dashboard-stats-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="stats-icon bg-secondary bg-opacity-10">
                                        <i class="bi bi-calendar-check text-secondary"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3 stats-content">
                                    <h6 class="card-title text-muted mb-1">Upcoming Deliveries</h6>
                                    <h4 class="mb-0 text-secondary"><?= $upcoming_deliveries ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
                    <div class="card dashboard-stats-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="stats-icon bg-dark bg-opacity-10">
                                        <i class="bi bi-box text-dark"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3 stats-content">
                                    <h6 class="card-title text-muted mb-1">Total Products</h6>
                                    <h4 class="mb-0 text-dark"><?= number_format($total_products) ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
                    <div class="card dashboard-stats-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="stats-icon bg-success bg-opacity-10">
                                        <i class="bi bi-calculator text-success"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3 stats-content">
                                    <h6 class="card-title text-muted mb-1">Net Profit</h6>
                                    <h4 class="mb-0 text-success">PKR <?= number_format($total_sales - $total_purchases - $total_expenses, 2) ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily Books Summary -->
            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-journal-text text-info me-2"></i>
                                Today's Daily Books Summary
                            </h5>
                            <a href="daily_books.php" class="btn btn-sm btn-outline-info d-none d-md-inline-block">
                                <i class="bi bi-arrow-right me-1"></i>View Full Report
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 daily-books-summary">
                                <div class="col-lg-3 col-md-6 col-sm-6">
                                    <div class="d-flex align-items-center p-3 bg-primary bg-opacity-10 rounded">
                                        <div class="flex-shrink-0">
                                            <i class="bi bi-cash-coin text-primary fs-4"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-1 text-muted">Today's Sales</h6>
                                            <h5 class="mb-0 text-primary">PKR <?= number_format($today_sales, 2) ?></h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 col-sm-6">
                                    <div class="d-flex align-items-center p-3 bg-success bg-opacity-10 rounded">
                                        <div class="flex-shrink-0">
                                            <i class="bi bi-cart-plus text-success fs-4"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-1 text-muted">Today's Purchases</h6>
                                            <h5 class="mb-0 text-success">PKR <?= number_format($today_purchases ?? 0, 2) ?></h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 col-sm-6">
                                    <div class="d-flex align-items-center p-3 bg-warning bg-opacity-10 rounded">
                                        <div class="flex-shrink-0">
                                            <i class="bi bi-receipt text-warning fs-4"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-1 text-muted">Today's Expenses</h6>
                                            <h5 class="mb-0 text-warning">PKR <?= number_format($today_expenses, 2) ?></h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 col-sm-6">
                                    <div class="d-flex align-items-center p-3 bg-info bg-opacity-10 rounded">
                                        <div class="flex-shrink-0">
                                            <i class="bi bi-graph-up-arrow text-info fs-4"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-1 text-muted">Today's Profit</h6>
                                            <h5 class="mb-0 text-info">PKR <?= number_format(($today_sales - ($today_purchases ?? 0) - $today_expenses), 2) ?></h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Sales Chart -->
                <div class="col-xl-8 col-lg-7 col-md-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-graph-up text-primary me-2"></i>
                                Sales Trend (Last 6 Months)
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="salesChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="col-xl-4 col-lg-5 col-md-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-lightning text-warning me-2"></i>
                                Quick Actions
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="quick-actions-grid">
                                <a href="add_sale.php" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-2"></i>New Sale
                                </a>
                                <a href="add_purchase.php" class="btn btn-success">
                                    <i class="bi bi-cart-plus me-2"></i>New Purchase
                                </a>
                                <a href="add_customer_ajax.php" class="btn btn-info">
                                    <i class="bi bi-person-plus me-2"></i>Add Customer
                                </a>
                                <a href="add_product.php" class="btn btn-warning">
                                    <i class="bi bi-box-seam me-2"></i>Add Product
                                </a>
                                <a href="expense_entry.php" class="btn btn-secondary">
                                    <i class="bi bi-receipt me-2"></i>Add Expense
                                </a>
                                <a href="daily_books.php" class="btn btn-info">
                                    <i class="bi bi-journal-text me-2"></i>Daily Books
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-2">
                <!-- Recent Sales -->
                <div class="col-xl-6 col-lg-6 col-md-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-clock-history text-info me-2"></i>
                                Recent Sales
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recent_sales)): ?>
                                <div class="table-responsive mobile-table">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Invoice</th>
                                                <th>Customer</th>
                                                <th>Amount</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_sales as $sale): ?>
                                                <tr>
                                                    <td>
                                                        <a href="sale_details.php?id=<?= $sale['id'] ?>" class="text-decoration-none">
                                                            <?= htmlspecialchars($sale['sale_no'] ?? 'SALE-' . $sale['id']) ?>
                                                        </a>
                                                    </td>
                                                    <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                                                    <td class="text-success">PKR <?= number_format($sale['total_amount'], 2) ?></td>
                                                    <td><?= date('M j', strtotime($sale['sale_date'])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center py-3">No recent sales</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Low Stock Alerts -->
                <div class="col-xl-6 col-lg-6 col-md-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-exclamation-triangle text-danger me-2"></i>
                                Low Stock Alerts
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($low_stock_products)): ?>
                                <div class="table-responsive mobile-table">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Current Stock</th>
                                                <th>Alert Level</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($low_stock_products as $product): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($product['product_name']) ?></td>
                                                    <td>
                                                        <span class="badge bg-danger"><?= $product['current_stock'] ?></span>
                                                    </td>
                                                    <td><?= $product['alert_quantity'] ?></td>
                                                    <td>
                                                        <a href="add_purchase.php" class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-cart-plus"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center py-3">All products are well stocked</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Chart.js for sales chart -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Performance optimization: Debounce resize events
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Optimized chart initialization
let salesChart = null;

document.addEventListener('DOMContentLoaded', function() {
    // Use requestAnimationFrame to prevent forced reflow
    requestAnimationFrame(function() {
        initializeChart();
    });
});

function initializeChart() {
    const ctx = document.getElementById('salesChart');
    if (!ctx) return;
    
    // Prepare data for chart
    const months = <?= json_encode(array_column($monthly_sales, 'month')) ?>;
    const sales = <?= json_encode(array_column($monthly_sales, 'total')) ?>;
    
    // Format months for display
    const formattedMonths = months.map(month => {
        const date = new Date(month + '-01');
        return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
    });
    
    // Get screen size once to avoid repeated DOM queries
    const isMobile = window.innerWidth < 768;
    
    // Create chart with optimized options
    salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: formattedMonths,
            datasets: [{
                label: 'Monthly Sales (PKR)',
                data: sales,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                tension: 0.1,
                fill: true,
                pointRadius: isMobile ? 3 : 4,
                pointHoverRadius: isMobile ? 4 : 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 750,
                easing: 'easeOutQuart'
            },
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'PKR ' + value.toLocaleString();
                        }
                    }
                }
            },
            // Mobile chart optimization
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            },
            elements: {
                point: {
                    radius: isMobile ? 3 : 4,
                    hoverRadius: isMobile ? 4 : 6
                }
            }
        }
    });
}

// Optimized resize handler with debouncing
const debouncedResize = debounce(function() {
    if (salesChart) {
        // Use requestAnimationFrame to prevent forced reflow
        requestAnimationFrame(function() {
            salesChart.resize();
        });
    }
}, 150);

// Add resize listener only once
window.addEventListener('resize', debouncedResize, { passive: true });

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (salesChart) {
        salesChart.destroy();
        salesChart = null;
    }
    window.removeEventListener('resize', debouncedResize);
});
</script>

<?php include 'includes/footer.php'; ?>