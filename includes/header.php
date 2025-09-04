<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clothing POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { 
            background: #f8f9fa; 
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Fixed sidebar styling */
        .sidebar {
            position: fixed;
            top: 56px; /* Height of the navbar */
            left: 0;
            width: 250px;
            height: calc(100vh - 56px);
            background: #212529;
            color: #fff;
            overflow-y: auto;
            z-index: 1000;
            transition: all 0.3s ease;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: #fff;
            padding: 12px 20px;
            border-radius: 0;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            font-size: 0.95rem;
        }
        
        .sidebar .nav-link:hover {
            background: #343a40;
            color: #ffc107;
            border-left-color: #ffc107;
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            background: #343a40;
            color: #ffc107;
            border-left-color: #ffc107;
            font-weight: 600;
        }
        
        /* Main content area */
        .main-content {
            margin-left: 250px;
            margin-top: 56px;
            min-height: calc(100vh - 56px);
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        /* Navbar styling */
        .navbar-brand {
            color: #ffc107 !important;
            font-weight: 700;
            font-size: 1.4rem;
        }
        
        .profile-dropdown {
            min-width: 220px;
            z-index: 1050;
            margin-top: 0.5rem;
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border-radius: 0.5rem;
            padding: 0.5rem 0;
        }
        
        .profile-dropdown .dropdown-item {
            padding: 0.75rem 1rem;
            transition: all 0.2s ease;
            border-radius: 0.25rem;
            margin: 0.125rem 0.5rem;
            font-weight: 500;
        }
        
        .profile-dropdown .dropdown-item:hover {
            background-color: #f8f9fa;
            color: #212529;
            transform: translateX(2px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .profile-dropdown .dropdown-item.text-danger:hover {
            background-color: #dc3545;
            color: white;
            transform: translateX(2px);
        }
        
        .profile-dropdown .dropdown-divider {
            margin: 0.5rem 0.75rem;
        }
        
        .navbar-nav .dropdown-toggle::after {
            display: none;
        }
        
        .navbar-nav .nav-link {
            transition: all 0.2s ease;
        }
        
        .navbar-nav .nav-link:hover {
            color: #ffc107 !important;
        }
        
        .navbar-nav .dropdown-toggle:hover {
            color: #ffc107 !important;
        }
        
        .navbar-nav .dropdown-toggle:focus {
            color: #ffc107 !important;
            box-shadow: none;
        }
        
        /* Profile dropdown specific styling */
        #profileDropdown {
            cursor: pointer;
            user-select: none;
        }
        
        #profileDropdown:hover {
            background-color: rgba(255, 193, 7, 0.1);
            border-radius: 0.375rem;
        }
        
        #profileDropdown:active {
            transform: scale(0.98);
        }
        
        /* Fallback dropdown styling if Bootstrap fails */
        .dropdown-menu.show {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        .dropdown-menu:not(.show) {
            display: none !important;
        }
        
        /* Ensure dropdown is always visible when needed */
        .profile-dropdown.show {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
            transform: none !important;
        }
        
        /* Additional fallback styling */
        .dropdown-toggle[aria-expanded="true"] + .dropdown-menu {
            display: block !important;
        }
        
        /* Ensure profile links are always clickable */
        .dropdown-item[data-profile-link="true"] {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .dropdown-item[data-profile-link="true"]:hover {
            background-color: #f8f9fa;
            color: #212529;
            transform: translateX(2px);
        }
        
        /* Ensure logout link is always clickable */
        .dropdown-item[data-logout-link="true"] {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .dropdown-item[data-logout-link="true"]:hover {
            background-color: #dc3545;
            color: white;
            transform: translateX(2px);
        }
        
        /* Enhanced clickable elements */
        .nav-link, .btn, .dropdown-item, .navbar-brand {
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        
        .nav-link:hover, .btn:hover, .dropdown-item:hover {
            transform: translateY(-1px);
        }
        
        .nav-link:active, .btn:active, .dropdown-item:active {
            transform: translateY(0);
        }
        
        /* Notification badge enhancement */
        .badge {
            transition: all 0.2s ease;
        }
        
        .badge:hover {
            transform: scale(1.1);
        }
        
        /* Sidebar toggle button enhancement */
        .navbar-toggler {
            transition: all 0.2s ease;
            border: 1px solid rgba(255, 193, 7, 0.3);
            padding: 0.375rem 0.75rem;
        }
        
        .navbar-toggler:hover {
            border-color: #ffc107;
            background-color: rgba(255, 193, 7, 0.1);
        }
        
        .navbar-toggler:focus {
            box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
        }
        
        /* Global clickable elements enhancement */
        a, button, input[type="submit"], input[type="button"], .btn, .nav-link, .dropdown-item {
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        
        a:hover, button:hover, input[type="submit"]:hover, input[type="button"]:hover, .btn:hover, .nav-link:hover, .dropdown-item:hover {
            transform: translateY(-1px);
            text-decoration: none;
        }
        
        a:active, button:active, input[type="submit"]:active, input[type="button"]:active, .btn:active, .nav-link:active, .dropdown-item:active {
            transform: translateY(0);
        }
        
        /* Table row hover effects */
        .table tbody tr {
            transition: all 0.2s ease;
        }
        
        .table tbody tr:hover {
            background-color: rgba(255, 193, 7, 0.1);
            transform: scale(1.01);
        }
        
        /* Card hover effects */
        .card {
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        /* Form control focus states */
        .form-control:focus, .form-select:focus {
            border-color: #ffc107 !important;
            box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25) !important;
        }
        
        /* Button group enhancements */
        .btn-group .btn {
            transition: all 0.2s ease;
        }
        
        .btn-group .btn:hover {
            z-index: 2;
        }
        
        /* Pagination enhancements */
        .pagination .page-link {
            transition: all 0.2s ease;
        }
        
        .pagination .page-link:hover {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
        }
        
        /* Alert enhancements */
        .alert {
            transition: all 0.3s ease;
        }
        
        .alert:hover {
            transform: translateX(2px);
        }
        
        /* Modal enhancements */
        .modal-content {
            transition: all 0.3s ease;
        }
        
        .modal.show .modal-content {
            transform: scale(1.02);
        }
        
        /* WhatsApp button styling */
        .btn-whatsapp {
            background-color: #25D366 !important;
            border-color: #25D366 !important;
            color: white !important;
        }
        
        .btn-whatsapp:hover {
            background-color: #128C7E !important;
            border-color: #128C7E !important;
            color: white !important;
        }
        
        .btn-whatsapp:focus {
            background-color: #25D366 !important;
            border-color: #25D366 !important;
            color: white !important;
            box-shadow: 0 0 0 0.2rem rgba(37, 211, 102, 0.25);
        }
        
        .btn-whatsapp.disabled {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
            color: #adb5bd !important;
            cursor: not-allowed;
        }
        
        /* Scrollbar styling for sidebar */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: #343a40;
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: #ffc107;
            border-radius: 3px;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #e0a800;
        }
        
        /* Chevron icon styling and transitions */
        .sidebar .bi-chevron-right {
            transition: transform 0.3s ease;
            font-size: 0.8em;
        }
        
        /* Smooth collapse animation */
        .collapse {
            transition: all 0.3s ease;
        }
        
        /* Submenu styling */
        .sidebar .collapse .nav-link {
            padding: 8px 20px 8px 40px;
            font-size: 0.9em;
            border-left: 2px solid transparent;
        }
        
        .sidebar .collapse .nav-link:hover {
            border-left-color: #ffc107;
            background: rgba(255, 193, 7, 0.1);
        }
        
        .sidebar .collapse .nav-link.active {
            border-left-color: #ffc107;
            background: rgba(255, 193, 7, 0.2);
        }
        
        /* Ensure submenu items are properly indented */
        .sidebar .collapse .nav {
            margin-left: 0;
        }
        
        /* Better spacing for submenu items */
        .sidebar .collapse .nav-item {
            margin-bottom: 2px;
        }
        
        /* Active state for parent menu when submenu is active */
        .sidebar .nav-link[aria-expanded="true"] {
            background: #343a40;
            color: #ffc107;
            border-left-color: #ffc107;
        }
        
        /* Enhanced Mobile Responsiveness */
        @media (max-width: 1199.98px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .navbar-brand {
                font-size: 1.2rem;
            }
        }
        
        @media (max-width: 991.98px) {
            .main-content {
                padding: 10px;
            }
            
            .container-fluid {
                padding-left: 10px;
                padding-right: 10px;
            }
        }
        
        @media (max-width: 767.98px) {
            .navbar {
                padding: 0.5rem 1rem;
            }
            
            .navbar-brand {
                font-size: 1.1rem;
            }
            
            .main-content {
                padding: 10px 5px;
                margin-top: 56px;
            }
            
            .sidebar {
                width: 100%;
                max-width: 320px;
            }
            
            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
                display: none;
                backdrop-filter: blur(2px);
            }
            
            .sidebar-overlay.show {
                display: block;
            }
            
            /* Mobile-specific navbar adjustments */
            .navbar-nav .nav-link {
                padding: 0.5rem 0.75rem;
            }
            
            .profile-dropdown {
                min-width: 200px;
                right: 0;
                left: auto;
            }
        }
        
        @media (max-width: 575.98px) {
            .navbar {
                padding: 0.5rem;
            }
            
            .navbar-brand {
                font-size: 1rem;
            }
            
            .main-content {
                padding: 8px 3px;
            }
            
            .container-fluid {
                padding-left: 5px;
                padding-right: 5px;
            }
            
            /* Ensure mobile sidebar doesn't overlap */
            .sidebar {
                max-width: 280px;
            }
        }
        
        /* Mobile-first responsive utilities */
        .d-mobile-none {
            display: none !important;
        }
        
        .d-mobile-block {
            display: block !important;
        }
        
        @media (min-width: 576px) {
            .d-mobile-none {
                display: initial !important;
            }
            
            .d-mobile-block {
                display: initial !important;
            }
        }
        
        /* Enhanced mobile navigation */
        .mobile-nav-toggle {
            display: none;
            background: none;
            border: none;
            color: #ffc107;
            font-size: 1.5rem;
            padding: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        @media (max-width: 1199.98px) {
            .mobile-nav-toggle {
                display: block;
            }
        }
        
        .mobile-nav-toggle:hover {
            color: #fff;
            transform: scale(1.1);
        }
        
        /* Enhanced mobile card layouts */
        @media (max-width: 767.98px) {
            .card {
                margin-bottom: 1rem;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            .table-responsive {
                font-size: 0.9rem;
            }
            
            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
            
            .form-control, .form-select {
                font-size: 1rem;
                padding: 0.75rem;
            }
        }
        
        /* Mobile form improvements */
        @media (max-width: 575.98px) {
            .form-row {
                margin-left: -5px;
                margin-right: -5px;
            }
            
            .form-row > .col,
            .form-row > [class*="col-"] {
                padding-left: 5px;
                padding-right: 5px;
            }
            
            .btn-group {
                flex-direction: column;
                width: 100%;
            }
            
            .btn-group .btn {
                border-radius: 0.375rem !important;
                margin-bottom: 0.25rem;
            }
        }
        
        /* Mobile table improvements */
        @media (max-width: 767.98px) {
            .table th,
            .table td {
                padding: 0.5rem 0.25rem;
                font-size: 0.85rem;
            }
            
            .table-responsive {
                border: none;
            }
        }
        
        /* Mobile modal improvements */
        @media (max-width: 575.98px) {
            .modal-dialog {
                margin: 0.5rem;
                max-width: calc(100% - 1rem);
            }
            
            .modal-body {
                padding: 1rem;
            }
            
            .modal-footer {
                padding: 0.75rem 1rem;
            }
        }
        
        /* Mobile pagination improvements */
        @media (max-width: 575.98px) {
            .pagination {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .pagination .page-link {
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
            }
        }
        
        /* Mobile alert improvements */
        @media (max-width: 575.98px) {
            .alert {
                padding: 0.75rem;
                margin-bottom: 1rem;
            }
            
            .alert-dismissible .btn-close {
                padding: 0.75rem;
            }
        }
        
        /* Mobile dropdown improvements */
        @media (max-width: 575.98px) {
            .dropdown-menu {
                position: fixed !important;
                top: 50% !important;
                left: 50% !important;
                transform: translate(-50%, -50%) !important;
                width: 90% !important;
                max-width: 300px;
                margin: 0;
                border-radius: 0.5rem;
                box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.3);
            }
        }
        
        /* Mobile sidebar animation improvements */
        @media (max-width: 1199.98px) {
            .sidebar {
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .sidebar:not(.show) {
                transform: translateX(-100%);
            }
        }
        
        /* Mobile touch improvements */
        @media (max-width: 1199.98px) {
            .nav-link, .btn, .dropdown-item {
                min-height: 44px;
                display: flex;
                align-items: center;
            }
            
            .sidebar .nav-link {
                min-height: 48px;
            }
        }
        
        /* Mobile landscape orientation */
        @media (max-width: 1199.98px) and (orientation: landscape) {
            .sidebar {
                height: 100vh;
                top: 0;
            }
            
            .main-content {
                margin-top: 56px;
            }
        }
        
        /* Print styles */
        @media print {
            .sidebar,
            .navbar,
            .sidebar-overlay {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                margin-top: 0 !important;
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
$unread_count = 0;
if (is_logged_in()) {
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetchColumn();
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top shadow-sm">
  <div class="container-fluid">
    <button class="navbar-toggler mobile-nav-toggle d-lg-none" type="button" id="sidebarToggle" aria-label="Toggle sidebar">
      <i class="bi bi-list"></i>
    </button>
    <a class="navbar-brand fw-bold" href="<?= $base_url ?>dashboard.php">Clothing POS</a>
    
    <ul class="navbar-nav ms-auto align-items-center">
      <li class="nav-item me-3 d-none d-md-block">
        <a class="nav-link position-relative" href="<?= $base_url ?>notifications.php" title="Notifications">
          <i class="bi bi-bell fs-5"></i>
          <?php if ($unread_count > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.7em;">
              <?= $unread_count ?>
            </span>
          <?php endif; ?>
        </a>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-haspopup="true" title="Click to open profile menu">
          <i class="bi bi-person-circle fs-5 me-2"></i>
          <span class="fw-bold d-none d-sm-inline"><?= htmlspecialchars(current_user() ?? 'User') ?></span>
          <i class="bi bi-chevron-down ms-1 fs-6 d-none d-sm-inline"></i>
        </a>
        <ul class="dropdown-menu dropdown-menu-end profile-dropdown" aria-labelledby="profileDropdown">
          <li><a class="dropdown-item" href="<?= $base_url ?>profile.php" tabindex="0" data-profile-link="true"><i class="bi bi-person me-2"></i>Profile & Settings</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="<?= $base_url ?>logout.php" tabindex="0" data-logout-link="true"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
        </ul>
      </li>
    </ul>
  </div>
</nav>

<!-- Mobile overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>