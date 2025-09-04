<?php
require_once 'includes/config.php';

echo "<h2>Database Table Check</h2>";

try {
    // Check if orders table exists and show structure
    echo "<h3>Orders Table Structure:</h3>";
    $stmt = $pdo->query("DESCRIBE orders");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Count orders
    echo "<h3>Orders Count:</h3>";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total orders: " . $count['count'] . "<br>";

    // Show recent orders
    echo "<h3>Recent Orders (Last 10):</h3>";
    $stmt = $pdo->query("SELECT id, order_no, customer_id, order_date, total_amount, status, created_at FROM orders ORDER BY id DESC LIMIT 10");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($orders)) {
        echo "No orders found in the database.";
    } else {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Order No</th><th>Customer ID</th><th>Date</th><th>Total</th><th>Status</th><th>Created</th></tr>";
        foreach ($orders as $order) {
            echo "<tr>";
            echo "<td>{$order['id']}</td>";
            echo "<td>{$order['order_no']}</td>";
            echo "<td>{$order['customer_id']}</td>";
            echo "<td>{$order['order_date']}</td>";
            echo "<td>{$order['total_amount']}</td>";
            echo "<td>{$order['status']}</td>";
            echo "<td>{$order['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Check order_items table
    echo "<h3>Order Items Table Structure:</h3>";
    $stmt = $pdo->query("DESCRIBE order_items");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Count order items
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM order_items");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total order items: " . $count['count'] . "<br>";

    // Show all tables in database
    echo "<h3>All Tables in Database:</h3>";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "- " . $table . "<br>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
