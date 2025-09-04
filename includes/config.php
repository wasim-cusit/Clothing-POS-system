<?php
// Auto-detect base URL
function getBaseUrl() {
    // Check if running from command line
    if (php_sapi_name() === 'cli') {
        return 'http://localhost/tailor/';
    }
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script_name = $_SERVER['SCRIPT_NAME'];
    $path_info = pathinfo($script_name);
    $base_path = $path_info['dirname'];
    
    // If we're in the root directory, return just the protocol and host
    if ($base_path === '/') {
        return $protocol . '://' . $host . '/';
    }
    
    // Otherwise, return the full path
    return $protocol . '://' . $host . $base_path . '/';
}

// Base URL configuration - you can override this manually if needed
$base_url = getBaseUrl();

// Alternative: Manual override (uncomment and modify if needed)
// $base_url = '/tailor/';
// $base_url = 'http://localhost/tailor/';
// $base_url = 'https://yoursite.com/tailor/';

// Database configuration
$host = 'localhost';
$db   = 'tailor_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    exit('Database connection failed: ' . $e->getMessage());
}

// Helper function to get full URL
function getFullUrl($path = '') {
    global $base_url;
    return rtrim($base_url, '/') . '/' . ltrim($path, '/');
}

// Helper function to get asset URL
function getAssetUrl($path = '') {
    global $base_url;
    return rtrim($base_url, '/') . '/assets/' . ltrim($path, '/');
}