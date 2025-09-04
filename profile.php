<?php
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_once 'includes/config.php';

require_login();
$activePage = 'profile';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newpass = trim($_POST['new_password'] ?? '');
    $confirmpass = trim($_POST['confirm_password'] ?? '');
    
    // Enhanced validation
    if (empty($newpass) || empty($confirmpass)) {
        set_flash('danger', 'Both password fields are required.');
    } elseif (strlen($newpass) < 6) {
        set_flash('danger', 'Password must be at least 6 characters long.');
    } elseif ($newpass !== $confirmpass) {
        set_flash('danger', 'Passwords do not match.');
    } else {
        try {
            $hash = password_hash($newpass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE system_users SET password=? WHERE id=?');
            if ($stmt->execute([$hash, $_SESSION['user_id']])) {
                set_flash('success', 'Password updated successfully! You can now use your new password.');
                // Clear the form
                $_POST = array();
            } else {
                set_flash('danger', 'Failed to update password. Please try again.');
            }
        } catch (Exception $e) {
            set_flash('danger', 'Database error occurred while updating password. Please try again.');
        }
    }
}

include 'includes/header.php';
?>

<div class="container-fluid">
  <div class="row">
    <?php include 'includes/sidebar.php'; ?>
    <main class="col-md-10 ms-sm-auto px-4 py-5" style="margin-top: 25px;">
      <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
        <h1 class="h2">
          <i class="bi bi-person-circle me-2"></i>Profile & Settings
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
          <div class="btn-group me-2">
            <a href="<?= $base_url ?>dashboard.php" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
            </a>
          </div>
        </div>
      </div>
      
      <?= get_flash() ?>
      
      <div class="row">
        <div class="col-lg-6 col-md-12 mb-4">
          <div class="card shadow-sm h-100">
            <div class="card-header bg-primary text-white">
              <h5 class="card-title mb-0">
                <i class="bi bi-info-circle me-2"></i>User Information
              </h5>
            </div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label fw-bold text-muted">Username</label>
                <input type="text" class="form-control bg-light" value="<?= htmlspecialchars(current_user()) ?>" disabled>
              </div>
              <div class="mb-3">
                <label class="form-label fw-bold text-muted">User ID</label>
                <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($_SESSION['user_id']) ?>" disabled>
              </div>
              <div class="mb-3">
                <label class="form-label fw-bold text-muted">Role</label>
                <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($_SESSION['role_id'] == 1 ? 'Administrator' : 'User') ?>" disabled>
              </div>
              <div class="mb-3">
                <label class="form-label fw-bold text-muted">Login Status</label>
                <div class="d-flex align-items-center">
                  <span class="badge bg-success me-2">Active</span>
                  <small class="text-muted">Last login: <?= date('M d, Y H:i') ?></small>
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-bold text-muted">Session Info</label>
                <div class="d-flex align-items-center">
                  <span class="badge bg-info me-2"><?= session_name() ?></span>
                  <small class="text-muted">Started: <?= date('M d, Y H:i', time()) ?></small>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="col-lg-6 col-md-12 mb-4">
          <div class="card shadow-sm h-100">
            <div class="card-header bg-warning text-dark">
              <h5 class="card-title mb-0">
                <i class="bi bi-key me-2"></i>Change Password
              </h5>
            </div>
            <div class="card-body">
              <form method="post" id="passwordForm">
                <div class="mb-3">
                  <label for="new_password" class="form-label fw-bold">New Password</label>
                  <div class="input-group">
                    <input type="password" class="form-control" id="new_password" name="new_password" 
                           required minlength="6" pattern="^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$!%*#?&]{6,}$"
                           placeholder="Enter new password (min 6 chars)">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                      <i class="bi bi-eye"></i>
                    </button>
                  </div>
                  <div class="form-text">Password must be at least 6 characters with letters and numbers.</div>
                </div>
                <div class="mb-3">
                  <label for="confirm_password" class="form-label fw-bold">Confirm Password</label>
                  <div class="input-group">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                           required minlength="6" placeholder="Confirm your new password">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                      <i class="bi bi-eye"></i>
                    </button>
                  </div>
                </div>
                <div class="mb-3">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="showPassword" onchange="toggleAllPasswords()">
                    <label class="form-check-label" for="showPassword">
                      Show passwords
                    </label>
                  </div>
                </div>
                <button type="submit" class="btn btn-warning btn-lg w-100">
                  <i class="bi bi-key me-2"></i>Update Password
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<script>
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.nextElementSibling;
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

function toggleAllPasswords() {
    const showPassword = document.getElementById('showPassword');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    if (showPassword.checked) {
        newPassword.type = 'text';
        confirmPassword.type = 'text';
    } else {
        newPassword.type = 'password';
        confirmPassword.type = 'password';
    }
}

// Form validation
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }
    
    if (newPassword.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long!');
        return false;
    }
    
    return true;
});

// Auto-focus on first password field
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('new_password').focus();
});
</script>

<?php include 'includes/footer.php'; ?>