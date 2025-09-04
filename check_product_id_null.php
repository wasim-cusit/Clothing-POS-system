<?php
require_once 'includes/config.php';

echo "=== PRODUCT_ID FIELD CHECK ===\n\n";

try {
    // Check if product_id field allows NULL
    $stmt = $pdo->query("DESCRIBE order_items");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        if ($col['Field'] === 'product_id') {
            echo "Field: {$col['Field']}\n";
            echo "Type: {$col['Type']}\n";
            echo "Null: {$col['Null']}\n";
            echo "Key: {$col['Key']}\n";
            echo "Default: {$col['Default']}\n\n";
            
            if ($col['Null'] === 'NO') {
                echo "❌ PROBLEM: product_id field does NOT allow NULL values!\n";
                echo "   Since you removed the product selection, this will cause insertion failures.\n\n";
                echo "SOLUTION: Modify the table to allow NULL:\n";
                echo "ALTER TABLE order_items MODIFY COLUMN product_id INT NULL;\n\n";
            } else {
                echo "✅ product_id field allows NULL values\n";
            }
            break;
        }
    }
    
    // Check foreign key constraint details
    echo "=== FOREIGN KEY DETAILS ===\n";
    $stmt = $pdo->query("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME,
            UPDATE_RULE,
            DELETE_RULE
        FROM information_schema.REFERENTIAL_CONSTRAINTS rc
        JOIN information_schema.KEY_COLUMN_USAGE kcu 
        ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
        WHERE kcu.TABLE_SCHEMA = 'tailor_db' 
        AND kcu.TABLE_NAME = 'order_items'
        AND kcu.COLUMN_NAME = 'product_id'
    ");
    
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($constraints)) {
        foreach ($constraints as $constraint) {
            echo "Constraint: {$constraint['CONSTRAINT_NAME']}\n";
            echo "Column: {$constraint['COLUMN_NAME']}\n";
            echo "References: {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}\n";
            echo "Update Rule: {$constraint['UPDATE_RULE']}\n";
            echo "Delete Rule: {$constraint['DELETE_RULE']}\n\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
