<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config.php';
require_login();

// Highlighting for Add Unit page
$activePage = 'add_unit';

// Helper: fetch current unit values from products.product_unit
function get_current_unit_values(PDO $pdo): array {
    $stmt = $pdo->prepare("SELECT DISTINCT product_unit FROM products WHERE product_unit IS NOT NULL AND product_unit != '' ORDER BY product_unit");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$error = null;
$success = null;

// Ensure unit_prices table exists to store default prices per unit
function ensure_unit_prices_table(PDO $pdo): void {
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
}

$error = null;
$success = null;

// Handle add unit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_unit'])) {
    $newUnit = trim($_POST['unit_name'] ?? '');
    if ($newUnit === '') {
        $error = 'Unit name is required.';
    } else {
        // Basic validation: allow letters, numbers, spaces, hyphen, underscore
        if (!preg_match('/^[A-Za-z0-9 _-]{1,50}$/', $newUnit)) {
            $error = 'Invalid unit name. Use letters, numbers, spaces, hyphen, underscore (max 50 chars).';
        } else {
            // Validate prices
            $priceRaw = trim($_POST['unit_price'] ?? '');
            $karegarPriceRaw = trim($_POST['karegar_price'] ?? '');
            $materialPriceRaw = trim($_POST['material_price'] ?? '');
            $zakatPercentageRaw = trim($_POST['zakat_percentage'] ?? '');
            
            if ($priceRaw === '' || !is_numeric($priceRaw) || (float)$priceRaw < 0) {
                $error = 'Please enter a valid non-negative Unit Price.';
            } elseif ($karegarPriceRaw === '' || !is_numeric($karegarPriceRaw) || (float)$karegarPriceRaw < 0) {
                $error = 'Please enter a valid non-negative Karegar Price.';
            } elseif ($materialPriceRaw === '' || !is_numeric($materialPriceRaw) || (float)$materialPriceRaw < 0) {
                $error = 'Please enter a valid non-negative Material Price.';
            } elseif ($zakatPercentageRaw === '' || !is_numeric($zakatPercentageRaw) || (float)$zakatPercentageRaw < 0 || (float)$zakatPercentageRaw > 100) {
                $error = 'Please enter a valid Zakat percentage between 0 and 100.';
            }
        }

        if ($error === null) {
            $currentUnits = get_current_unit_values($pdo);
            // Prevent duplicates (case-insensitive)
            $lowerSet = array_map('mb_strtolower', $currentUnits);
            if (in_array(mb_strtolower($newUnit), $lowerSet, true)) {
                $error = 'This unit already exists.';
            } else {
                // Store prices
                ensure_unit_prices_table($pdo);
                $stmt = $pdo->prepare("INSERT INTO unit_prices (unit_name, unit_price, karegar_price, material_price, zakat_percentage) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $newUnit, 
                    number_format((float)$priceRaw, 2, '.', ''),
                    number_format((float)$karegarPriceRaw, 2, '.', ''),
                    number_format((float)$materialPriceRaw, 2, '.', ''),
                    number_format((float)$zakatPercentageRaw, 2, '.', '')
                ]);
                header('Location: add_unit.php?success=added');
                exit;
            }
        }
    }
}

// Handle update price
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_price'])) {
    try {
        ensure_unit_prices_table($pdo);
        $id = (int)($_POST['id'] ?? 0);
        $priceRaw = trim($_POST['unit_price'] ?? '');
        $karegarPriceRaw = trim($_POST['karegar_price'] ?? '');
        $materialPriceRaw = trim($_POST['material_price'] ?? '');
        $zakatPercentageRaw = trim($_POST['zakat_percentage'] ?? '');
        
        if ($id <= 0 || $priceRaw === '' || !is_numeric($priceRaw) || (float)$priceRaw < 0 ||
            $karegarPriceRaw === '' || !is_numeric($karegarPriceRaw) || (float)$karegarPriceRaw < 0 ||
            $materialPriceRaw === '' || !is_numeric($materialPriceRaw) || (float)$materialPriceRaw < 0 ||
            $zakatPercentageRaw === '' || !is_numeric($zakatPercentageRaw) || (float)$zakatPercentageRaw < 0 || (float)$zakatPercentageRaw > 100) {
            $error = 'Invalid data provided for update.';
        } else {
            $stmt = $pdo->prepare('UPDATE unit_prices SET unit_price = ?, karegar_price = ?, material_price = ?, zakat_percentage = ? WHERE id = ?');
            $stmt->execute([
                number_format((float)$priceRaw, 2, '.', ''),
                number_format((float)$karegarPriceRaw, 2, '.', ''),
                number_format((float)$materialPriceRaw, 2, '.', ''),
                number_format((float)$zakatPercentageRaw, 2, '.', ''),
                $id
            ]);
            header('Location: add_unit.php?success=updated');
            exit;
        }
    } catch (Throwable $t) {
        $error = 'Failed to update price: ' . $t->getMessage();
    }
}

// Handle delete unit (only if not used by any product)
if (isset($_GET['delete'])) {
    try {
        ensure_unit_prices_table($pdo);
        $id = (int)$_GET['delete'];
        $stmt = $pdo->prepare('SELECT unit_name FROM unit_prices WHERE id = ?');
        $stmt->execute([$id]);
        $unitName = $stmt->fetchColumn();
        if ($unitName) {
            // Check usage in products
            $cstmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE product_unit = ?');
            $cstmt->execute([$unitName]);
            $inUse = (int)$cstmt->fetchColumn();
            if ($inUse > 0) {
                $error = "Cannot delete. Unit is used by {$inUse} product(s).";
            } else {
                // Delete row from unit_prices
                $dstmt = $pdo->prepare('DELETE FROM unit_prices WHERE id = ?');
                $dstmt->execute([$id]);
                header('Location: add_unit.php?success=deleted');
                exit;
            }
        }
    } catch (Throwable $t) {
        $error = 'Failed to process deletion: ' . $t->getMessage();
    }
}

// Read current units for display
$units = get_current_unit_values($pdo);
// Map prices
try {
    ensure_unit_prices_table($pdo);
    
    // Update existing records to have default values for new fields
    $pdo->exec("UPDATE unit_prices SET karegar_price = 0.00 WHERE karegar_price IS NULL");
    $pdo->exec("UPDATE unit_prices SET material_price = 0.00 WHERE material_price IS NULL");
    $pdo->exec("UPDATE unit_prices SET zakat_percentage = 0.00 WHERE zakat_percentage IS NULL");
    
    $priceRows = $pdo->query("SELECT unit_name, unit_price FROM unit_prices")->fetchAll(PDO::FETCH_KEY_PAIR);
    $unitRows = $pdo->query("SELECT id, unit_name, unit_price, karegar_price, material_price, zakat_percentage FROM unit_prices ORDER BY unit_name")->fetchAll(PDO::FETCH_ASSOC);
    $usageRows = $pdo->query("SELECT unit, COUNT(*) AS cnt FROM products GROUP BY unit")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $t) {
    $priceRows = [];
    $unitRows = [];
    $usageRows = [];
}

// If editing, fetch unit for edit form
$edit_unit = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    try {
        $stmt = $pdo->prepare('SELECT id, unit_name, unit_price, karegar_price, material_price, zakat_percentage FROM unit_prices WHERE id = ?');
        $stmt->execute([$id]);
        $edit_unit = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $t) {
        $edit_unit = null;
    }
}

include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="main-content">
    

    <?php if (isset($_GET['success']) && $_GET['success'] === 'added'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Unit added successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card mb-4">
                <div class="card-header"><?= $edit_unit ? 'Edit Unit Price' : 'Add New Unit' ?></div>
                <div class="card-body">
                    <form method="post">
                        <?php if ($edit_unit): ?>
                            <input type="hidden" name="id" value="<?= (int)$edit_unit['id'] ?>">
                        <?php endif; ?>
                        <div class="mb-2">
                            <label class="form-label">Unit Name</label>
                            <input type="text" name="unit_name" class="form-control" placeholder="Enter Unit Name" value="<?= $edit_unit ? htmlspecialchars($edit_unit['unit_name']) : '' ?>" <?= $edit_unit ? 'readonly' : 'required' ?>>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Unit Price</label>
                            <input type="number" step="0.01" min="0" name="unit_price" class="form-control" placeholder="Enter Unit Price" value="<?= $edit_unit ? htmlspecialchars($edit_unit['unit_price']) : '' ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Karegar (Tailor)</label>
                            <input type="number" step="0.01" min="0" name="karegar_price" class="form-control" placeholder="Enter Karegar Price" value="<?= $edit_unit ? htmlspecialchars($edit_unit['karegar_price'] ?? '') : '' ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Material</label>
                            <input type="number" step="0.01" min="0" name="material_price" class="form-control" placeholder="Enter Material Price" value="<?= $edit_unit ? htmlspecialchars($edit_unit['material_price'] ?? '') : '' ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Zakat (%)</label>
                            <input type="number" step="0.01" min="0" max="100" name="zakat_percentage" class="form-control" placeholder="Enter Zakat Percentage" value="<?= $edit_unit ? htmlspecialchars($edit_unit['zakat_percentage'] ?? '') : '' ?>" required>
                            <small class="form-text text-muted">Percentage to be deducted from total unit cost</small>
                        </div>
                        <?php if ($edit_unit): ?>
                            <button type="submit" name="update_price" class="btn btn-primary">Update</button>
                            <a href="add_unit.php" class="btn btn-secondary">Cancel</a>
                        <?php else: ?>
                            <button type="submit" name="add_unit" class="btn btn-info text-white px-4">Add</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>


