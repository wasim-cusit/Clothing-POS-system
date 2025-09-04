<?php
require_once 'includes/auth.php';
require_login();
require_once 'includes/config.php';
require_once 'includes/notifications.php';

$activePage = 'notifications';

// Mark as read
if (isset($_GET['read'])) {
    $id = intval($_GET['read']);
    if (mark_notification_read($id)) {
        header("Location: notifications.php?success=read");
    } else {
        header("Location: notifications.php?error=read");
    }
    exit;
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $user_id = $_SESSION['user_id'];
    if (mark_all_notifications_read($user_id)) {
        header("Location: notifications.php?success=all_read");
    } else {
        header("Location: notifications.php?error=all_read");
    }
    exit;
}

// Delete notification
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if (delete_notification($id)) {
        header("Location: notifications.php?success=deleted");
    } else {
        header("Location: notifications.php?error=deleted");
    }
    exit;
}

// Get current user ID
$user_id = $_SESSION['user_id'];

// Fetch notifications for current user
$notifications = get_user_notifications($user_id, 100, 0, false);

// Get notification statistics
$stats = get_notification_stats();

// Helper function for notification type colors
function get_notification_type_color($type) {
    $colors = [
        'info' => 'info',
        'success' => 'success',
        'warning' => 'warning',
        'error' => 'danger'
    ];
    
    return $colors[$type] ?? 'secondary';
}

include 'includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>  
        <main class="col-md-10 ms-sm-auto px-4 py-5" style="margin-top: 25px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-bell me-2"></i>Notifications</h2>
                <div>
                    <a href="?mark_all_read" class="btn btn-success me-2" onclick="return confirm('Mark all notifications as read?')">
                        <i class="bi bi-check-all me-1"></i>Mark All as Read
                    </a>
                    <a href="notifications.php" class="btn btn-info">
                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                    </a>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php
                    if ($_GET['success'] === 'read') echo "Notification marked as read successfully!";
                    if ($_GET['success'] === 'all_read') echo "All notifications marked as read successfully!";
                    if ($_GET['success'] === 'deleted') echo "Notification deleted successfully!";
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php
                    if ($_GET['error'] === 'read') echo "Error marking notification as read!";
                    if ($_GET['error'] === 'all_read') echo "Error marking all notifications as read!";
                    if ($_GET['error'] === 'deleted') echo "Error deleting notification!";
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Notification Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h4><?= $stats['total'] ?? 0 ?></h4>
                            <p class="mb-0">Total Notifications</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body text-center">
                            <h4><?= $stats['unread'] ?? 0 ?></h4>
                            <p class="mb-0">Unread</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h4><?= count(array_filter($notifications, function($n) { return $n['is_read'] == 1; })) ?></h4>
                            <p class="mb-0">Read</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h4><?= count($notifications) ?></h4>
                            <p class="mb-0">Showing</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>All Notifications</span>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="filterNotifications('all')">All</button>
                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="filterNotifications('unread')">Unread</button>
                        <button type="button" class="btn btn-outline-success btn-sm" onclick="filterNotifications('read')">Read</button>
                    </div>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Title</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notifications as $n): ?>
                                <tr class="notification-row <?= $n['is_read'] ? 'table-secondary' : '' ?>" data-status="<?= $n['is_read'] ? 'read' : 'unread' ?>">
                                    <td>
                                        <span class="badge bg-<?= get_notification_type_color($n['type']) ?>">
                                            <?= ucfirst(htmlspecialchars($n['type'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($n['title'] ?: 'No Title') ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($n['message']) ?></td>
                                    <td>
                                        <?php if ($n['is_read']): ?>
                                            <span class="badge bg-success">Read</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Unread</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <?php if (!$n['is_read']): ?>
                                                <a href="notifications.php?read=<?= $n['id'] ?>" class="btn btn-sm btn-primary" title="Mark as Read">
                                                    <i class="bi bi-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="notifications.php?delete=<?= $n['id'] ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this notification?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($notifications)): ?>
                                <tr><td colspan="6" class="text-center">No notifications found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Filter notifications by status
function filterNotifications(status) {
    const rows = document.querySelectorAll('.notification-row');
    
    rows.forEach(row => {
        const rowStatus = row.getAttribute('data-status');
        
        if (status === 'all' || rowStatus === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    
    // Update active filter button
    document.querySelectorAll('.btn-group .btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
}

// Auto-refresh notifications every 30 seconds
setInterval(() => {
    // Only refresh if user is on the notifications page
    if (document.location.pathname.includes('notifications.php')) {
        location.reload();
    }
}, 30000);

// Initialize with all notifications shown
document.addEventListener('DOMContentLoaded', function() {
    // Show all notifications by default
    filterNotifications('all');
    
    // Set first filter button as active
    const firstFilterBtn = document.querySelector('.btn-group .btn');
    if (firstFilterBtn) {
        firstFilterBtn.classList.add('active');
    }
});
</script>

<?php include 'includes/footer.php'; ?>