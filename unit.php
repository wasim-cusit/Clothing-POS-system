<?php
require_once 'includes/auth.php';
require_login();
require_once 'includes/config.php';

$activePage = 'units';

// Ensure table exists and load units with new fields
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

// Fetch units with all pricing information
$units = $pdo->query("SELECT id, unit_name, unit_price, karegar_price, material_price, zakat_percentage FROM unit_prices ORDER BY unit_name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch products for each unit to show usage
$unitProducts = [];
foreach ($units as $unit) {
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
    $unitProducts[$unit['unit_name']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get category names for products
$categoryNames = [];
if (!empty($unitProducts)) {
    $categoryIds = [];
    foreach ($unitProducts as $products) {
        foreach ($products as $product) {
            if ($product['category_id']) {
                $categoryIds[] = $product['category_id'];
            }
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
    <h2 class="mb-0"><i class="bi bi-rulers text-primary"></i> Unit Management</h2>
    <a href="add_unit.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Unit</a>
  </div>

  <!-- Unit Summary Cards -->
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card bg-primary text-white">
        <div class="card-body text-center">
          <h4 class="card-title"><?= count($units) ?></h4>
          <p class="card-text">Total Units</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-success text-white">
        <div class="card-body text-center">
          <h4 class="card-title">
            <?php
            $totalProducts = 0;
            foreach ($unitProducts as $products) {
                $totalProducts += count($products);
            }
            echo $totalProducts;
            ?>
          </h4>
          <p class="card-text">Total Products</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-info text-white">
        <div class="card-body text-center">
          <h4 class="card-title">
            <?php
            $totalValue = 0;
            foreach ($unitProducts as $products) {
                foreach ($products as $product) {
                    $totalValue += ($product['sale_price'] * $product['stock_quantity']);
                }
            }
            echo 'PKR ' . number_format($totalValue, 2);
            ?>
          </h4>
          <p class="card-text">Total Stock Value</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-warning text-dark">
        <div class="card-body text-center">
          <h4 class="card-title">
            <?php
            $activeUnits = 0;
            foreach ($unitProducts as $products) {
                if (count($products) > 0) {
                    $activeUnits++;
                }
            }
            echo $activeUnits;
            ?>
          </h4>
          <p class="card-text">Active Units</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Units Table -->
  <div class="card mb-4">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0"><i class="bi bi-rulers me-2"></i>Units Overview</h5>
    </div>
    <div class="card-body table-responsive">
      <table class="table table-bordered table-striped table-hover">
        <thead class="table-dark">
          <tr>
            <th><i class="bi bi-hash"></i> #</th>
            <th><i class="bi bi-rulers"></i> Unit Name</th>
            <th><i class="bi bi-currency-dollar"></i> Unit Price</th>
            <th><i class="bi bi-person-badge"></i> Karegar Price</th>
                         <th><i class="bi bi-box-seam"></i> Material Price</th>
             <th><i class="bi bi-percent"></i> Zakat</th>
             <th><i class="bi bi-calculator"></i> Final Cost</th>
             <th><i class="bi bi-box"></i> Products Count</th>
             <th><i class="bi bi-calculator"></i> Total Value</th>
             <th><i class="bi bi-gear"></i> Actions <small class="text-light">(View/Edit/Delete)</small></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($units as $index => $row): ?>
            <tr>
              <td><span class="badge bg-secondary"><?= $index + 1 ?></span></td>
              <td>
                <strong class="text-primary"><?= htmlspecialchars($row['unit_name']) ?></strong>
              </td>
              <td>
                <span class="badge bg-success">PKR <?= htmlspecialchars(number_format((float)$row['unit_price'], 2)) ?></span>
              </td>
              <td>
                <span class="badge bg-info">PKR <?= htmlspecialchars(number_format((float)$row['karegar_price'], 2)) ?></span>
              </td>
                             <td>
                 <span class="badge bg-warning text-dark">PKR <?= htmlspecialchars(number_format((float)$row['material_price'], 2)) ?></span>
               </td>
               <td>
                 <span class="badge bg-danger"><?= htmlspecialchars(number_format((float)$row['zakat_percentage'], 2)) ?>%</span>
               </td>
               <td>
                 <?php
                 $totalCost = (float)$row['unit_price'] + (float)$row['karegar_price'] + (float)$row['material_price'];
                 $zakatAmount = $totalCost * ((float)$row['zakat_percentage'] / 100);
                 $finalCost = $totalCost - $zakatAmount;
                 ?>
                 <span class="badge bg-success">PKR <?= number_format($finalCost, 2) ?></span>
                 <br><small class="text-muted">After Zakat: PKR <?= number_format($zakatAmount, 2) ?></small>
               </td>
               <td>
                 <span class="badge bg-primary"><?= count($unitProducts[$row['unit_name']] ?? []) ?></span>
               </td>
              <td>
                <?php
                $unitValue = 0;
                $products = $unitProducts[$row['unit_name']] ?? [];
                foreach ($products as $product) {
                    $unitValue += ($product['sale_price'] * $product['stock_quantity']);
                }
                ?>
                <span class="badge bg-success">PKR <?= number_format($unitValue, 2) ?></span>
              </td>
                             <td>
                 <div class="btn-group" role="group">
                   <a href="unit_details.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-info" title="View Unit Profile">
                     <i class="bi bi-eye"></i>
                   </a>
                   <a href="add_unit.php?edit=<?= (int)$row['id'] ?>" class="btn btn-sm btn-primary" title="Edit Unit">
                     <i class="bi bi-pencil"></i>
                   </a>
                   <a href="add_unit.php?delete=<?= (int)$row['id'] ?>" class="btn btn-sm btn-danger" title="Delete Unit" 
                      onclick="return confirm('Delete this unit? This will remove it from allowed units.');">
                     <i class="bi bi-trash"></i>
                   </a>
                 </div>
               </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($units)): ?>
            <tr>
                             <td colspan="10" class="text-center py-5">
                <div class="text-muted">
                  <i class="bi bi-rulers fs-1"></i>
                  <h5 class="mt-3">No units found</h5>
                  <p>Start by adding your first unit using the form above.</p>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
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

 .btn-group .btn {
     border-radius: 6px;
     margin: 0 2px;
 }

 .table-hover tbody tr:hover {
     background-color: #f8f9fa;
     transform: translateY(-1px);
     transition: all 0.2s ease;
 }
 </style>

<?php include 'includes/footer.php'; ?>


