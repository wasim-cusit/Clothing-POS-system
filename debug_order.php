<?php
require_once 'includes/auth.php';
require_login();
require_once 'includes/config.php';

echo "<h2>Debug Order Items Table</h2>";

try {
    // Check table structure
    echo "<h3>Table Structure:</h3>";
    $stmt = $pdo->query("DESCRIBE order_items");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    // Check sample data
    echo "<h3>Sample Order Items:</h3>";
    $stmt = $pdo->query("SELECT * FROM order_items LIMIT 5");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($items);
    echo "</pre>";
    
    // Check products table
    echo "<h3>Sample Products:</h3>";
    $stmt = $pdo->query("SELECT id, product_name, product_code FROM products LIMIT 5");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($products);
    echo "</pre>";
    
    // Check categories table
    echo "<h3>Sample Categories:</h3>";
    $stmt = $pdo->query("SELECT id, category_name FROM categories LIMIT 5");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($categories);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
