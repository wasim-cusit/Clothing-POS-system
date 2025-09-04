<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Get unread notification count
$unread_count = 0;
if (is_logged_in()) {
  $user_id = $_SESSION['user_id'];
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
  $stmt->execute([$user_id]);
  $unread_count = $stmt->fetchColumn();
}
?>
<nav class="sidebar" id="sidebar">
  <div class="p-3">
    <!-- Mobile header for sidebar -->
    <div class="d-lg-none mb-3 pb-3 border-bottom border-secondary">
      <div class="d-flex align-items-center justify-content-between">
        <h6 class="text-warning mb-0">Menu</h6>
        <button type="button" class="btn-close btn-close-white" id="closeSidebar" aria-label="Close sidebar"></button>
      </div>
    </div>
    
    <ul class="nav flex-column mb-4">
      <li class="nav-item mb-2">
        <a class="nav-link<?= $activePage === 'dashboard' ? ' active' : '' ?>" href="<?= $base_url ?>dashboard.php">
          <i class="bi bi-speedometer2 me-2"></i>
          <span class="nav-text">Dashboard</span>
        </a>
      </li>
      
      <li class="nav-item mb-2">
        <a class="nav-link<?= $activePage === 'sales' || $activePage === 'add_sale' ? ' active' : '' ?>" href="#" data-bs-toggle="collapse" data-bs-target="#salesSubmenu" aria-expanded="<?= $activePage === 'sales' || $activePage === 'add_sale' ? 'true' : 'false' ?>" aria-controls="salesSubmenu">
          <i class="bi bi-cash-coin me-2"></i>
          <span class="nav-text">Sales</span>
          <i class="bi bi-chevron-right ms-auto nav-chevron"></i>
        </a>
        <div class="collapse<?= $activePage === 'sales' || $activePage === 'add_sale' ? ' show' : '' ?>" id="salesSubmenu">
          <ul class="nav flex-column ms-3">
            <li class="nav-item">
              <a class="nav-link<?= $activePage === 'add_sale' ? ' active' : '' ?>" href="<?= $base_url ?>add_sale.php">
                <i class="bi bi-cart-plus me-2"></i>
                <span class="nav-text">Add Sale</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link<?= $activePage === 'sales' ? ' active' : '' ?>" href="<?= $base_url ?>sales.php">
                <i class="bi bi-list-ul me-2"></i>
                <span class="nav-text">Sales Details</span>
              </a>
            </li>
          </ul>
        </div>
      </li>
      
      <li class="nav-item mb-2">
        <a class="nav-link<?= $activePage === 'purchases' || $activePage === 'purchase_details' || $activePage === 'add_purchase' ? ' active' : '' ?>" href="#" data-bs-toggle="collapse" data-bs-target="#purchasesSubmenu" aria-expanded="<?= $activePage === 'purchases' || $activePage === 'purchase_details' || $activePage === 'add_purchase' ? 'true' : 'false' ?>" aria-controls="purchasesSubmenu">
          <i class="bi bi-cart-plus me-2"></i>
          <span class="nav-text">Purchases</span>
          <i class="bi bi-chevron-right ms-auto nav-chevron"></i>
        </a>
        <div class="collapse<?= $activePage === 'purchases' || $activePage === 'purchase_details' || $activePage === 'add_purchase' ? ' show' : '' ?>" id="purchasesSubmenu">
          <ul class="nav flex-column ms-3">
            <li class="nav-item">
              <a class="nav-link<?= $activePage === 'add_purchase' ? ' active' : '' ?>" href="<?= $base_url ?>add_purchase.php">
                <i class="bi bi-plus-circle me-2"></i>
                <span class="nav-text">Add Purchase</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link<?= $activePage === 'purchases' ? ' active' : '' ?>" href="<?= $base_url ?>purchases.php">
                <i class="bi bi-list-ul me-2"></i>
                <span class="nav-text">Purchase Details</span>
              </a>
            </li>
          </ul>
        </div>
      </li>
     
      <li class="nav-item mb-2">
        <a class="nav-link<?= $activePage === 'products' || $activePage === 'add_product' || $activePage === 'product_details' ? ' active' : '' ?>" href="#" data-bs-toggle="collapse" data-bs-target="#productsSubmenu" aria-expanded="<?= $activePage === 'products' || $activePage === 'add_product' || $activePage === 'product_details' ? 'true' : 'false' ?>" aria-controls="productsSubmenu">
          <i class="bi bi-box-seam me-2"></i>
          <span class="nav-text">Products</span>
          <i class="bi bi-chevron-right ms-auto nav-chevron"></i>
        </a>
        <div class="collapse<?= $activePage === 'products' || $activePage === 'add_product' || $activePage === 'product_details' ? ' show' : '' ?>" id="productsSubmenu">
          <ul class="nav flex-column ms-3">
            <li class="nav-item">
              <a class="nav-link<?= $activePage === 'add_product' ? ' active' : '' ?>" href="<?= $base_url ?>add_product.php">
                <i class="bi bi-plus-circle me-2"></i>
                <span class="nav-text">Add Products</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link<?= $activePage === 'products' ? ' active' : '' ?>" href="<?= $base_url ?>products.php">
                <i class="bi bi-list-ul me-2"></i>
                <span class="nav-text">Products Details</span>
              </a>
            </li>
          </ul>
        </div>
      </li>

      <li class="nav-item mb-2">
        <a class="nav-link<?= $activePage === 'categories' ? ' active' : '' ?>" href="<?= $base_url ?>categories.php">
          <i class="bi bi-tags me-2"></i>
          <span class="nav-text">Categories</span>
        </a>
      </li>

      <li class="nav-item mb-2">
        <a class="nav-link<?= $activePage === 'customers' || $activePage === 'customer_payment' || $activePage === 'customer_payment_list' || $activePage === 'customer_payment_details' || $activePage === 'customer_ledger' ? ' active' : '' ?>" href="#" data-bs-toggle="collapse" data-bs-target="#customersSubmenu" aria-expanded="<?= $activePage === 'customers' || $activePage === 'customer_payment' || $activePage === 'customer_payment_list' || $activePage === 'customer_payment_details' || $activePage === 'customer_ledger' ? 'true' : 'false' ?>" aria-controls="customersSubmenu">
          <i class="bi bi-people me-2"></i>
          <span class="nav-text">Customers</span>
          <i class="bi bi-chevron-right ms-auto nav-chevron"></i>
        </a>
        <div class="collapse<?= $activePage === 'customers' || $activePage === 'customer_payment' || $activePage === 'customer_payment_list' || $activePage === 'customer_payment_details' || $activePage === 'customer_ledger' ? ' show' : '' ?>" id="customersSubmenu">
          <ul class="nav flex-column ms-3">
            <li class="nav-item">
              <a class="nav-link<?= $activePage === 'customers' ? ' active' : '' ?>" href="<?= $base_url ?>customers.php">
                <i class="bi bi-people me-2"></i>
                <span class="nav-text">Customer</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link<?= $activePage === 'customer_payment' ? ' active' : '' ?>" href="<?= $base_url ?>customer_payment.php">
                <i class="bi bi-credit-card me-2"></i>
                <span class="nav-text">Customer Payment</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link<?= $activePage === 'customer_payment_list' ? ' active' : '' ?>" href="<?= $base_url ?>customer_payment_list.php">
                <i class="bi bi-list-ul me-2"></i>
                <span class="nav-text">Payment List</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link<?= $activePage === 'customer_payment_details' ? ' active' : '' ?>" href="<?= $base_url ?>customer_payment_details.php">
                <i class="bi bi-file-text me-2"></i>
                <span class="nav-text">Payment Details</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link<?= $activePage === 'customer_ledger' ? ' active' : '' ?>" href="<?= $base_url ?>customer_ledger.php">
                <i class="bi bi-journal-text me-2"></i>
                <span class="nav-text">Customer Ledger</span>
              </a>
            </li>
          </ul>
        </div>
      </li>
      
      <li class="nav-item mb-2">
        <a class="nav-link<?= $activePage === 'suppliers' || $activePage === 'supplier_payment' || $activePage === 'supplier_payment_list' || $activePage === 'supplier_payment_details' || $activePage === 'supplier_ledger' ? ' active' : '' ?>" href="#" data-bs-toggle="collapse" data-bs-target="#suppliersSubmenu" aria-expanded="<?= $activePage === 'suppliers' || $activePage === 'supplier_payment' || $activePage === 'supplier_payment_list' || $activePage === 'supplier_payment_details' || $activePage === 'supplier_ledger' ? 'true' : 'false' ?>" aria-controls="suppliersSubmenu">
          <i class="bi bi-truck me-2"></i>
          <span class="nav-text">Suppliers</span>
          <i class="bi bi-chevron-right ms-auto nav-chevron"></i>
        </a>
        <div class="collapse<?= $activePage === 'suppliers' || $activePage === 'supplier_payment' || $activePage === 'supplier_payment_list' || $activePage === 'supplier_payment_details' || $activePage === 'supplier_ledger' ? ' show' : '' ?>" id="suppliersSubmenu">
          <ul class="nav flex-column ms-3">
            <li class="nav-item">
              <a class="nav-link<?= $activePage === 'suppliers' ? ' active' : '' ?>" href="<?= $base_url ?>suppliers.php">
                <i class="bi bi-people me-2"></i>
                <span class="nav-text">Supplier</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link<?= $activePage === 'supplier_payment' ? ' active' : '' ?>" href="<?= $base_url ?>supplier_payment.php">
                <i class="bi bi-credit-card me-2"></i>
                <span class="nav-text">Payment</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link<?= $activePage === 'supplier_payment_list' ? ' active' : '' ?>" href="<?= $base_url ?>supplier_payment_list.php">
                <i class="bi bi-list-ul me-2"></i>
                <span class="nav-text">Payment List</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link<?= $activePage === 'supplier_payment_details' ? ' active' : '' ?>" href="<?= $base_url ?>supplier_payment_details.php">
                <i class="bi bi-file-text me-2"></i>
                <span class="nav-text">Payment Details</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link<?= $activePage === 'supplier_ledger' ? ' active' : '' ?>" href="<?= $base_url ?>supplier_ledger.php">
                <i class="bi bi-journal-text me-2"></i>
                <span class="nav-text">Supplier Ledger</span>
              </a>
            </li>
          </ul>
        </div>
      </li>
      
      <li class="nav-item mb-2">
        <a class="nav-link<?= $activePage === 'stock' ? ' active' : '' ?>" href="<?= $base_url ?>stock.php">
          <i class="bi bi-boxes me-2"></i>
          <span class="nav-text">Stock Details</span>
        </a>
      </li>
    
      <li class="nav-item mb-2">
        <a class="nav-link<?= $activePage === 'expenses' ? ' active' : '' ?>" href="<?= $base_url ?>expenses.php">
          <i class="bi bi-receipt me-2"></i>
          <span class="nav-text">Expenses</span>
        </a>
      </li>
      
      <li class="nav-item mb-2">
        <a class="nav-link<?= $activePage === 'order' || $activePage === 'add_order' ? ' active' : '' ?>" href="#" data-bs-toggle="collapse" data-bs-target="#ordersSubmenu" aria-expanded="<?= $activePage === 'order' || $activePage === 'add_order' ? 'true' : 'false' ?>" aria-controls="ordersSubmenu">
          <i class="bi bi-clipboard-data me-2"></i>
          <span class="nav-text">Staching</span>
          <i class="bi bi-chevron-right ms-auto nav-chevron"></i>
        </a>
        <div class="collapse<?= $activePage === 'order' || $activePage === 'add_order' ? ' show' : '' ?>" id="ordersSubmenu">
          <ul class="nav flex-column ms-3">
            <li class="nav-item">
              <a class="nav-link<?= $activePage === 'add_order' ? ' active' : '' ?>" href="<?= $base_url ?>add_order.php">
                <i class="bi bi-plus-circle me-2"></i>
                <span class="nav-text">Add Order</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link<?= $activePage === 'order' ? ' active' : '' ?>" href="<?= $base_url ?>order.php">
                <i class="bi bi-list-ul me-2"></i>
                <span class="nav-text">Order Details</span>
              </a>
            </li>
          </ul>
        </div>
      </li>
      
      <li class="nav-item mb-2">
        <a class="nav-link<?= $activePage === 'return_percale' ? ' active' : '' ?>" href="<?= $base_url ?>return_percale.php">
          <i class="bi bi-arrow-return-left me-2"></i>
          <span class="nav-text">Return Percale</span>
        </a>
      </li>
      
      <li class="nav-item mb-2">
        <a class="nav-link<?= $activePage === 'unit' || $activePage === 'add_unit' || $activePage === 'units' ? ' active' : '' ?>" href="#" data-bs-toggle="collapse" data-bs-target="#unitsSubmenu" aria-expanded="<?= $activePage === 'unit' || $activePage === 'add_unit' || $activePage === 'units' ? 'true' : 'false' ?>" aria-controls="unitsSubmenu">
          <i class="bi bi-rulers me-2"></i>
          <span class="nav-text">Unit Prices</span>
          <i class="bi bi-chevron-right ms-auto nav-chevron"></i>
        </a>
        <div class="collapse<?= $activePage === 'unit' || $activePage === 'add_unit' || $activePage === 'units' ? ' show' : '' ?>" id="unitsSubmenu">
          <ul class="nav flex-column ms-3">
            <li class="nav-item">
              <a class="nav-link<?= $activePage === 'add_unit' ? ' active' : '' ?>" href="<?= $base_url ?>add_unit.php">
                <i class="bi bi-plus-circle me-2"></i>
                <span class="nav-text">Add Unit Price</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link<?= $activePage === 'unit' || $activePage === 'units' ? ' active' : '' ?>" href="<?= $base_url ?>unit.php">
                <i class="bi bi-list-ul me-2"></i>
                <span class="nav-text">View Unit Prices</span>
              </a>
            </li>
          </ul>
        </div>
      </li>
    </ul>
    
    <!-- Bottom section with user-related links -->
    <ul class="nav flex-column mb-4">
      <li class="nav-item mb-2">
        <a class="nav-link position-relative<?= $activePage === 'notifications' ? ' active' : '' ?>" href="<?= $base_url ?>notifications.php">
          <i class="bi bi-bell me-2"></i>
          <span class="nav-text">Notifications</span>
          <?php if ($unread_count > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.7em; margin-left: 5px;">
              <?= $unread_count ?>
            </span>
          <?php endif; ?>
        </a>
      </li>
      
      <li class="nav-item mb-2">
        <a class="nav-link<?= $activePage === 'profile' ? ' active' : '' ?>" href="<?= $base_url ?>profile.php">
          <i class="bi bi-person-circle me-2"></i>
          <span class="nav-text">Profile & Settings</span>
        </a>
      </li>
      
      <li class="nav-item mb-2">
        <a class="nav-link<?= $activePage === 'daily_books' ? ' active' : '' ?>" href="<?= $base_url ?>daily_books.php">
          <i class="bi bi-journal-text me-2"></i>
          <span class="nav-text">Daily Books</span>
        </a>
      </li>
      
      <li class="nav-item mb-2">
        <a class="nav-link<?= $activePage === 'reports' ? ' active' : '' ?>" href="<?= $base_url ?>reports.php">
          <i class="bi bi-graph-up-arrow me-2"></i>
          <span class="nav-text">Reports</span>
        </a>
      </li>
      
      <li class="nav-item mb-2">
        <a class="nav-link<?= $activePage === 'backup' ? ' active' : '' ?>" href="<?= $base_url ?>backup.php">
          <i class="bi bi-cloud-arrow-up me-2"></i>
          <span class="nav-text">System Backup</span>
        </a>
      </li>
      
      <?php if (function_exists('has_role') && has_role('Admin')): ?>
        <li class="nav-item mb-2">
          <a class="nav-link<?= $activePage === 'users' ? ' active' : '' ?>" href="<?= $base_url ?>users.php">
            <i class="bi bi-person-gear me-2"></i>
            <span class="nav-text">User Management</span>
          </a>
        </li>
        <li class="nav-item mb-2">
          <a class="nav-link<?= $activePage === 'settings' ? ' active' : '' ?>" href="<?= $base_url ?>settings.php">
            <i class="bi bi-gear me-2"></i>
            <span class="nav-text">Settings</span>
          </a>
        </li>
      <?php endif; ?>
      
      <li class="nav-item mt-3">
        <a class="nav-link text-danger<?= $activePage === 'logout' ? ' active' : '' ?>" href="<?= $base_url ?>logout.php">
          <i class="bi bi-box-arrow-right me-2"></i>
          <span class="nav-text">Logout</span>
        </a>
      </li>
    </ul>
    
    <!-- Mobile footer info -->
    <div class="d-lg-none mt-4 pt-3 border-top border-secondary">
      <div class="text-center text-muted small">
        <div class="mb-1">Clothing POS System</div>
        <div class="text-secondary">v1.0.0</div>
      </div>
    </div>
  </div>
</nav>

<style>
/* Enhanced sidebar styling for mobile */
@media (max-width: 1199.98px) {
  .sidebar {
    padding-top: 0;
  }
  
  .sidebar .nav-link {
    padding: 0.75rem 1rem;
    margin: 0.125rem 0.5rem;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
  }
  
  .sidebar .nav-link:hover {
    background: rgba(255, 193, 7, 0.1);
    transform: translateX(3px);
  }
  
  .sidebar .collapse .nav-link {
    padding: 0.5rem 1rem 0.5rem 2.5rem;
    margin: 0.125rem 0.5rem 0.125rem 1.5rem;
    font-size: 0.9rem;
  }
  
  .sidebar .collapse .nav-link:hover {
    background: rgba(255, 193, 7, 0.05);
  }
  
  .nav-chevron {
    transition: transform 0.2s ease;
    font-size: 0.75rem;
  }
  
  .nav-link[aria-expanded="true"] .nav-chevron {
    transform: rotate(90deg);
  }
  
  /* Mobile-specific improvements */
  .sidebar .nav-link {
    min-height: 48px;
    display: flex;
    align-items: center;
  }
  
  .sidebar .nav-link .nav-text {
    flex: 1;
  }
  
  .sidebar .nav-link i {
    width: 20px;
    text-align: center;
  }
  
  /* Mobile header styling */
  .sidebar .border-bottom {
    border-color: rgba(255, 193, 7, 0.3) !important;
  }
  
  .sidebar .btn-close {
    filter: invert(1) brightness(200%);
    opacity: 0.8;
  }
  
  .sidebar .btn-close:hover {
    opacity: 1;
  }
  
  /* Mobile footer styling */
  .sidebar .border-top {
    border-color: rgba(255, 193, 7, 0.3) !important;
  }
}

/* Enhanced hover effects for larger screens */
@media (min-width: 1200px) {
  .sidebar .nav-link:hover {
    background: #343a40;
    color: #ffc107;
    border-left-color: #ffc107;
    transform: translateX(5px);
  }
  
  .sidebar .collapse .nav-link:hover {
    background: rgba(255, 193, 7, 0.1);
    border-left-color: #ffc107;
  }
}

/* Improved active states */
.sidebar .nav-link.active {
  background: #343a40;
  color: #ffc107;
  border-left-color: #ffc107;
  font-weight: 600;
}

.sidebar .collapse .nav-link.active {
  background: rgba(255, 193, 7, 0.2);
  border-left-color: #ffc107;
}

/* Enhanced submenu styling */
.sidebar .collapse {
  background: rgba(0, 0, 0, 0.1);
  margin: 0.25rem 0.5rem;
  border-radius: 0.375rem;
  overflow: hidden;
}

.sidebar .collapse .nav {
  padding: 0.5rem 0;
}

/* Improved spacing and typography */
.sidebar .nav-item {
  margin-bottom: 0.25rem;
}

.sidebar .nav-link {
  font-weight: 500;
  letter-spacing: 0.025em;
}

.sidebar .collapse .nav-link {
  font-weight: 400;
  opacity: 0.9;
}

/* Enhanced mobile touch targets */
@media (max-width: 1199.98px) {
  .sidebar .nav-link,
  .sidebar .btn-close {
    -webkit-tap-highlight-color: rgba(255, 193, 7, 0.3);
  }
  
  .sidebar .nav-link:active {
    transform: scale(0.98);
  }
}

/* Smooth transitions */
.sidebar * {
  transition: all 0.2s ease;
}

/* Enhanced mobile animations */
@media (max-width: 1199.98px) {
  .sidebar .nav-link {
    will-change: transform, background-color;
  }
  
  .sidebar .collapse {
    will-change: height;
  }
}

/* Print styles */
@media print {
  .sidebar {
    display: none !important;
  }
}
</style>

<script>
// Initialize mobile sidebar close functionality
document.addEventListener('DOMContentLoaded', function() {
  const closeSidebar = document.getElementById('closeSidebar');
  const sidebar = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');
  
  if (closeSidebar) {
    closeSidebar.addEventListener('click', function() {
      sidebar.classList.remove('show');
      if (sidebarOverlay) {
        sidebarOverlay.classList.remove('show');
      }
      document.body.style.overflow = '';
    });
  }
  
  // Initialize chevron rotation for already expanded sections
  const expandedToggles = document.querySelectorAll('[aria-expanded="true"]');
  expandedToggles.forEach(toggle => {
    const chevron = toggle.querySelector('.nav-chevron');
    if (chevron) {
      chevron.style.transform = 'rotate(90deg)';
    }
  });
});
</script>