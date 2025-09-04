<?php
require_once 'includes/config.php';

echo "<h2>Order Items Table Structure</h2>";

try {
    // Check if order_items table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'order_items'");
    $tableExists = $stmt->rowCount() > 0;
    
    echo "<p><strong>Order_items table exists:</strong> " . ($tableExists ? "YES" : "NO") . "</p>";
    
    if ($tableExists) {
        echo "<h3>Table Structure:</h3>";
        $stmt = $pdo->query("DESCRIBE order_items");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . $column['Default'] . "</td>";
            echo "<td>" . $column['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check sample data
        echo "<h3>Sample Data:</h3>";
        $stmt = $pdo->query("SELECT * FROM order_items LIMIT 5");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($items)) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr>";
            foreach (array_keys($items[0]) as $header) {
                echo "<th>" . $header . "</th>";
            }
            echo "</tr>";
            foreach ($items as $item) {
                echo "<tr>";
                foreach ($item as $value) {
                    echo "<td>" . htmlspecialchars($value) . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No data in order_items table</p>";
        }
    }
    
    // Also check if there are any other similar tables
    echo "<h3>All Tables in Database:</h3>";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>" . $table;
        if (strpos($table, 'order') !== false || strpos($table, 'item') !== false) {
            echo " <strong>(Related to orders/items)</strong>";
        }
        echo "</li>";
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
?>
