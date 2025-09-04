<?php
require_once 'includes/config.php';

echo "=== FOREIGN KEY CONSTRAINT CHECK ===\n\n";

try {
    // Check if order_items has foreign key to orders
    $stmt = $pdo->query("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'tailor_db' 
        AND TABLE_NAME = 'order_items'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($constraints)) {
        echo "❌ CRITICAL ISSUE: No foreign key constraints found!\n";
        echo "   This means order_items table is not properly linked to orders table.\n";
        echo "   Data insertion will fail due to referential integrity issues.\n\n";
        
        echo "SOLUTION: You need to add foreign key constraints:\n";
        echo "ALTER TABLE order_items ADD CONSTRAINT fk_order_items_order \n";
        echo "FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE;\n\n";
        
        echo "ALTER TABLE order_items ADD CONSTRAINT fk_order_items_product \n";
        echo "FOREIGN KEY (product_id) REFERENCES products(id);\n\n";
        
    } else {
        echo "✅ Foreign key constraints found:\n";
        foreach ($constraints as $constraint) {
            echo "   {$constraint['CONSTRAINT_NAME']}: {$constraint['COLUMN_NAME']} → {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}\n";
        }
    }
    
    // Check table structure mismatch
    echo "\n=== TABLE STRUCTURE CHECK ===\n";
    
    // Check if orders table has auto_increment
    $stmt = $pdo->query("SHOW CREATE TABLE orders");
    $create_table = $stmt->fetchColumn(1);
    
    if (strpos($create_table, 'AUTO_INCREMENT') === false) {
        echo "❌ ISSUE: orders table missing AUTO_INCREMENT on id field\n";
    } else {
        echo "✅ orders table has AUTO_INCREMENT\n";
    }
    
    // Check if order_items table has proper structure
    $stmt = $pdo->query("DESCRIBE order_items");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $has_order_id = false;
    $has_product_id = false;
    
    foreach ($columns as $col) {
        if ($col['Field'] === 'order_id') $has_order_id = true;
        if ($col['Field'] === 'product_id') $has_product_id = true;
    }
    
    if (!$has_order_id) {
        echo "❌ ISSUE: order_items table missing 'order_id' field\n";
    } else {
        echo "✅ order_items table has 'order_id' field\n";
    }
    
    if (!$has_product_id) {
        echo "❌ ISSUE: order_items table missing 'product_id' field\n";
    } else {
        echo "✅ order_items table has 'product_id' field\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
