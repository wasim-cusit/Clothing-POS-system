<?php
require_once 'includes/auth.php';
require_login();
require_once 'includes/config.php';

$activePage = 'products';

// Handle Delete Product
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: products.php?success=deleted");
    exit;
}

// Fetch all products
$products = $pdo->query("
    SELECT p.*, c.category AS category_name
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY p.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-10 ms-sm-auto px-4 py-5" style="margin-top: 25px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0"><i class="bi bi-box-seam text-primary"></i> Product Details</h2>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php
                    if ($_GET['success'] === 'added') echo "Product added successfully!";
                    if ($_GET['success'] === 'updated') echo "Product updated successfully!";
                    if ($_GET['success'] === 'deleted') echo "Product deleted successfully!";
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>



            <!-- Product List Table -->
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0"><i class="bi bi-list-ul"></i> Product List</h6>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Unit</th>
                                <th>Product Code</th>
                                <th>Color</th>
                                <th>Brand</th>
                                <th>Description</th>
                                <th>Low Stock Alert</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['product_name']) ?></td>
                                    <td><?= htmlspecialchars($product['category_name']) ?></td>
                                    <td><?= htmlspecialchars($product['product_unit']) ?></td>
                                    <td><?= htmlspecialchars($product['product_code'] ?? '') ?></td>
                                    <td>
                                        <?php if (!empty($product['color'])): ?>
                                            <div class="d-flex align-items-center">
                                                <div class="color-swatch me-2" style="background-color: <?= htmlspecialchars($product['color']) ?>; width: 16px; height: 16px; border-radius: 50%; border: 1px solid #dee2e6;"></div>
                                                <span class="color-name"><?= htmlspecialchars($product['color']) ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($product['brand']) ?></td>
                                    <td><?= htmlspecialchars(substr($product['description'] ?? '', 0, 50)) ?><?= (strlen($product['description'] ?? '') > 50 ? '...' : '') ?></td>
                                    <td><?= htmlspecialchars($product['alert_quantity']) ?></td>
                                    <td>
                                        <small class="text-muted">
                                            <?php if ($product['created_at']): ?>
                                                <?= date('d M Y', strtotime($product['created_at'])) ?><br>
                                                <span class="text-secondary"><?= date('H:i', strtotime($product['created_at'])) ?></span>
                                            <?php else: ?>
                                                <span class="text-secondary">N/A</span>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <a href="add_product.php?edit=<?= $product['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                        <a href="products.php?delete=<?= $product['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this product?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="10" class="text-center">No products found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include 'includes/footer.php'; ?>

<style>
/* Color swatch styling for products table */
.color-swatch {
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: transform 0.2s ease;
}

.color-swatch:hover {
    transform: scale(1.1);
}

.color-name {
    font-weight: 500;
    color: #495057;
    font-size: 0.9rem;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .color-swatch {
        width: 14px !important;
        height: 14px !important;
    }
    
    .color-name {
        font-size: 0.8rem;
    }
}
</style>