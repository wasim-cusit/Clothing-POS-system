<?php
require_once 'includes/config.php';

echo "<h2>Orders Table Status Check</h2>";

try {
    // Check if orders table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'orders'");
    $ordersExists = $stmt->rowCount() > 0;
    
    echo "<p><strong>Orders table exists:</strong> " . ($ordersExists ? "YES" : "NO") . "</p>";
    
    if ($ordersExists) {
        // Count total orders
        $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
        $totalOrders = $stmt->fetchColumn();
        echo "<p><strong>Total orders in table:</strong> $totalOrders</p>";
        
        // Show recent orders
        echo "<h3>Recent Orders:</h3>";
        $stmt = $pdo->query("SELECT o.*, c.name AS customer_name FROM orders o LEFT JOIN customer c ON o.customer_id = c.id ORDER BY o.id DESC LIMIT 5");
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($orders)) {
            echo "<p>No orders found in the database.</p>";
        } else {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>Order No</th><th>Customer</th><th>Date</th><th>Total</th><th>Status</th></tr>";
            foreach ($orders as $order) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($order['id']) . "</td>";
                echo "<td>" . htmlspecialchars($order['order_no']) . "</td>";
                echo "<td>" . htmlspecialchars($order['customer_name'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($order['order_date']) . "</td>";
                echo "<td>" . htmlspecialchars($order['total_amount']) . "</td>";
                echo "<td>" . htmlspecialchars($order['status'] ?? 'pending') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        // Check customer table
        echo "<h3>Customer Table Check:</h3>";
        $stmt = $pdo->query("SELECT COUNT(*) FROM customer");
        $totalCustomers = $stmt->fetchColumn();
        echo "<p><strong>Total customers:</strong> $totalCustomers</p>";
        
        if ($totalCustomers > 0) {
            $stmt = $pdo->query("SELECT id, name FROM customer LIMIT 3");
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<p><strong>Sample customers:</strong></p>";
            foreach ($customers as $customer) {
                echo "<p>ID: {$customer['id']}, Name: {$customer['name']}</p>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='add_order.php'>Go to Add Order</a> | <a href='order.php'>Go to Orders List</a></p>";
?>
