<?php
require_once 'includes/auth.php';
require_login();
require_once 'includes/config.php';

$activePage = 'units';

// Get unit ID from URL
$unitId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($unitId <= 0) {
    header('Location: unit.php');
    exit;
}

// Ensure table exists and load unit with all fields
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

// Add new columns if they don't exist
try {
    $pdo->exec("ALTER TABLE unit_prices ADD COLUMN IF NOT EXISTS karegar_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER unit_price");
    $pdo->exec("ALTER TABLE unit_prices ADD COLUMN IF NOT EXISTS material_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER karegar_price");
    $pdo->exec("ALTER TABLE unit_prices ADD COLUMN IF NOT EXISTS zakat_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER material_price");
} catch (Exception $e) {
    // Columns might already exist, ignore error
}

// Update existing records to have default values for new fields
try {
    $pdo->exec("UPDATE unit_prices SET karegar_price = 0.00 WHERE karegar_price IS NULL");
    $pdo->exec("UPDATE unit_prices SET material_price = 0.00 WHERE material_price IS NULL");
    $pdo->exec("UPDATE unit_prices SET zakat_percentage = 0.00 WHERE zakat_percentage IS NULL");
} catch (Exception $e) {
    // Ignore errors
}

// Fetch specific unit with all pricing information
$stmt = $pdo->prepare("SELECT id, unit_name, unit_price, karegar_price, material_price, zakat_percentage FROM unit_prices WHERE id = ?");
$stmt->execute([$unitId]);
$unit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$unit) {
    header('Location: unit.php');
    exit;
}

// Fetch products for this unit to show usage
$stmt = $pdo->prepare("
    SELECT p.id, p.product_name, p.category_id, 
           COALESCE(AVG(si.sale_price), 0) as sale_price,
           COALESCE(SUM(si.quantity), 0) as stock_quantity
    FROM products p 
    LEFT JOIN stock_items si ON p.id = si.product_id AND si.status = 'available'
    WHERE p.product_unit = ? 
    GROUP BY p.id 
    ORDER BY p.product_name
");
$stmt->execute([$unit['unit_name']]);
$unitProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get category names for products
$categoryNames = [];
if (!empty($unitProducts)) {
    $categoryIds = [];
    foreach ($unitProducts as $product) {
        if ($product['category_id']) {
            $categoryIds[] = $product['category_id'];
        }
    }
    if (!empty($categoryIds)) {
        $placeholders = str_repeat('?,', count($categoryIds) - 1) . '?';
        $stmt = $pdo->prepare("SELECT id, category FROM categories WHERE id IN ($placeholders)");
        $stmt->execute($categoryIds);
        $categories = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $categoryNames = $categories;
    }
}

include 'includes/header.php';
?>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h2 class="mb-0"><i class="bi bi-rulers text-primary"></i> Unit Profile</h2>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="unit.php"><i class="bi bi-house"></i> Units</a></li>
          <li class="breadcrumb-item active"><?= htmlspecialchars($unit['unit_name']) ?></li>
        </ol>
      </nav>
    </div>
    <div>
      <a href="unit.php" class="btn btn-secondary me-2"><i class="bi bi-arrow-left me-1"></i>Back to Units</a>
      <a href="add_unit.php?edit=<?= (int)$unit['id'] ?>" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Edit Unit</a>
    </div>
  </div>

  <!-- Unit Header Card -->
  <div class="card mb-4 bg-gradient-primary text-white">
    <div class="card-body text-center py-4">
      <h1 class="display-4 mb-2"><?= htmlspecialchars($unit['unit_name']) ?></h1>
      <p class="lead mb-0">Complete Unit Profile & Analysis</p>
    </div>
  </div>

  <!-- Unit Pricing Information -->
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card bg-light">
        <div class="card-body text-center">
          <h6 class="card-title text-primary">Unit Price</h6>
          <h4 class="text-success">PKR <?= number_format((float)$unit['unit_price'], 2) ?></h4>
          <small class="text-muted">Base price per unit</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-light">
        <div class="card-body text-center">
          <h6 class="card-title text-info">Karegar Price</h6>
          <h4 class="text-info">PKR <?= number_format((float)$unit['karegar_price'], 2) ?></h4>
          <small class="text-muted">Tailoring cost per unit</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-light">
        <div class="card-body text-center">
          <h6 class="card-title text-warning">Material Price</h6>
          <h4 class="text-warning">PKR <?= number_format((float)$unit['material_price'], 2) ?></h4>
          <small class="text-muted">Material cost per unit</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-light">
        <div class="card-body text-center">
          <h6 class="card-title text-danger">Zakat</h6>
          <h4 class="text-danger"><?= number_format((float)$unit['zakat_percentage'], 2) ?>%</h4>
          <small class="text-muted">Deduction percentage</small>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Cost Summary -->
  <div class="row mb-4">
    <div class="col-md-6">
      <div class="card bg-primary text-white">
        <div class="card-body text-center">
          <h6 class="card-title">Total Cost (Before Zakat)</h6>
          <h4>PKR <?= number_format((float)$unit['unit_price'] + (float)$unit['karegar_price'] + (float)$unit['material_price'], 2) ?></h4>
          <small>Unit + Karegar + Material</small>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card bg-success text-white">
        <div class="card-body text-center">
          <h6 class="card-title">Final Cost (After Zakat)</h6>
          <?php
          $totalCost = (float)$unit['unit_price'] + (float)$unit['karegar_price'] + (float)$unit['material_price'];
          $zakatAmount = $totalCost * ((float)$unit['zakat_percentage'] / 100);
          $finalCost = $totalCost - $zakatAmount;
          ?>
          <h4>PKR <?= number_format($finalCost, 2) ?></h4>
          <small>Zakat Deducted: PKR <?= number_format($zakatAmount, 2) ?></small>
        </div>
      </div>
    </div>
  </div>

  <!-- Products in this Unit -->
  <div class="card">
    <div class="card-header bg-info text-white">
      <h5 class="mb-0">
        <i class="bi bi-box me-2"></i>
        Products using <?= htmlspecialchars($unit['unit_name']) ?> (<?= count($unitProducts) ?>)
      </h5>
    </div>
    <div class="card-body">
      <?php if (!empty($unitProducts)): ?>
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-hover">
            <thead class="table-secondary">
              <tr>
                <th>Product Name</th>
                <th>Category</th>
                <th>Sale Price</th>
                <th>Stock Qty</th>
                <th>Stock Value</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($unitProducts as $product): ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($product['product_name']) ?></strong>
                  </td>
                  <td>
                    <span class="badge bg-secondary">
                      <?= htmlspecialchars($categoryNames[$product['category_id']] ?? 'N/A') ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge bg-success">PKR <?= number_format($product['sale_price'], 2) ?></span>
                  </td>
                  <td>
                    <span class="badge bg-primary"><?= number_format($product['stock_quantity'], 2) ?></span>
                  </td>
                  <td>
                    <span class="badge bg-info">PKR <?= number_format($product['sale_price'] * $product['stock_quantity'], 2) ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="text-center text-muted py-5">
          <i class="bi bi-box-x fs-1"></i>
          <h5 class="mt-3">No products found</h5>
          <p class="mb-0">No products are currently using this unit.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<style>
.card {
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border: none;
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
    border-bottom: none;
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
}

.breadcrumb {
    background: transparent;
    padding: 0;
}

.breadcrumb-item a {
    color: rgba(255,255,255,0.8);
    text-decoration: none;
}

.breadcrumb-item a:hover {
    color: white;
}

.breadcrumb-item.active {
    color: rgba(255,255,255,0.6);
}

.table th {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.badge {
    font-weight: 500;
    padding: 6px 10px;
}
</style>

<?php include 'includes/footer.php'; ?>
