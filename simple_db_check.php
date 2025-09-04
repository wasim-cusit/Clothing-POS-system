<?php
require_once 'includes/config.php';

echo "=== DATABASE STRUCTURE CHECK ===\n\n";

try {
    // Check orders table
    echo "1. ORDERS TABLE:\n";
    $stmt = $pdo->query("DESCRIBE orders");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "   {$col['Field']} - {$col['Type']} - Null: {$col['Null']} - Key: {$col['Key']}\n";
    }
    
    echo "\n2. ORDER_ITEMS TABLE:\n";
    $stmt = $pdo->query("DESCRIBE order_items");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "   {$col['Field']} - {$col['Type']} - Null: {$col['Null']} - Key: {$col['Key']}\n";
    }
    
    echo "\n3. UNIT_PRICES TABLE:\n";
    $stmt = $pdo->query("DESCRIBE unit_prices");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "   {$col['Field']} - {$col['Type']} - Null: {$col['Null']} - Key: {$col['Key']}\n";
    }
    
    echo "\n4. FOREIGN KEY CONSTRAINTS:\n";
    $stmt = $pdo->query("
        SELECT 
            CONSTRAINT_NAME,
            TABLE_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'tailor_db' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
        AND TABLE_NAME = 'order_items'
    ");
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($constraints)) {
        echo "   ❌ No foreign key constraints found!\n";
    } else {
        foreach ($constraints as $constraint) {
            echo "   ✅ {$constraint['CONSTRAINT_NAME']}: {$constraint['TABLE_NAME']}.{$constraint['COLUMN_NAME']} → {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}\n";
        }
    }
    
    echo "\n5. SAMPLE DATA CHECK:\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM customer");
    $customer_count = $stmt->fetchColumn();
    echo "   Customers: $customer_count\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM unit_prices");
    $unit_count = $stmt->fetchColumn();
    echo "   Units: $unit_count\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $order_count = $stmt->fetchColumn();
    echo "   Orders: $order_count\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM order_items");
    $item_count = $stmt->fetchColumn();
    echo "   Order Items: $item_count\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
