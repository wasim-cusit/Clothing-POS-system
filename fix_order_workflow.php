<?php
require_once 'includes/config.php';

echo "<h2>Order Workflow Fix & Test</h2>";

// Step 1: Clean up test orders first
echo "<h3>Step 1: Cleaning Test Orders</h3>";
try {
    // Delete test orders and their items
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_no LIKE 'TEST-%'");
    $stmt->execute();
    $testOrderIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($testOrderIds)) {
        $placeholders = str_repeat('?,', count($testOrderIds) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id IN ($placeholders)");
        $stmt->execute($testOrderIds);
        
        $stmt = $pdo->prepare("DELETE FROM orders WHERE order_no LIKE 'TEST-%'");
        $stmt->execute();
        $deleted = $stmt->rowCount();
        echo "✅ Deleted $deleted test orders<br>";
    } else {
        echo "✅ No test orders to clean<br>";
    }
} catch (Exception $e) {
    echo "❌ Error cleaning: " . $e->getMessage() . "<br>";
}

// Step 2: Test creating a proper order
echo "<h3>Step 2: Creating Test Order</h3>";
try {
    $pdo->beginTransaction();
    
    // Insert order with NULL order_no initially
    $stmt = $pdo->prepare("INSERT INTO orders (order_no, customer_id, order_date, delivery_date, sub_total, discount, total_amount, paid_amount, remaining_amount, details, created_by, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    
    $order_no = null;
    $customer_id = 4; // Use existing customer
    $order_date = date('Y-m-d');
    $delivery_date = date('Y-m-d', strtotime('+7 days'));
    $sub_total = 1500.00;
    $discount = 100.00;
    $total_amount = 1400.00;
    $paid_amount = 700.00;
    $remaining_amount = 700.00;
    $details = 'Debug test order';
    $created_by = 1;
    $status = 'Pending';
    
    $stmt->execute([$order_no, $customer_id, $order_date, $delivery_date, $sub_total, $discount, $total_amount, $paid_amount, $remaining_amount, $details, $created_by, $status]);
    $order_id = (int)$pdo->lastInsertId();
    
    // Generate proper order number
    $order_no = 'ORD-' . date('Y') . '-' . str_pad($order_id, 4, '0', STR_PAD_LEFT);
    $stmt = $pdo->prepare("UPDATE orders SET order_no = ? WHERE id = ?");
    $stmt->execute([$order_no, $order_id]);
    
    // Add order items
    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, description, quantity, unit_price, total_price) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$order_id, null, 'Test Shirt', 1, 800.00, 800.00]);
    $stmt->execute([$order_id, null, 'Test Pants', 1, 700.00, 700.00]);
    
    $pdo->commit();
    echo "✅ Created order ID: $order_id with number: $order_no<br>";
    
} catch (Exception $e) {
    $pdo->rollback();
    echo "❌ Error creating order: " . $e->getMessage() . "<br>";
}

// Step 3: Test the display query
echo "<h3>Step 3: Testing Display Query</h3>";
try {
    $query = "SELECT o.*, c.name AS customer_name FROM orders o LEFT JOIN customer c ON o.customer_id = c.id ORDER BY o.id DESC";
    $orders = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Query returned " . count($orders) . " orders<br>";
    
    if (empty($orders)) {
        echo "❌ NO ORDERS RETURNED!<br>";
        
        // Test simple query
        $simple = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
        echo "Direct count: $simple orders in table<br>";
        
    } else {
        echo "✅ Orders found! Displaying first 5:<br>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Order No</th><th>Customer</th><th>Date</th><th>Total</th><th>Status</th></tr>";
        
        $count = 0;
        foreach ($orders as $order) {
            if ($count >= 5) break;
            echo "<tr>";
            echo "<td>" . $order['id'] . "</td>";
            echo "<td>" . ($order['order_no'] ?? 'NULL') . "</td>";
            echo "<td>" . ($order['customer_name'] ?? 'Walk-in') . "</td>";
            echo "<td>" . $order['order_date'] . "</td>";
            echo "<td>PKR " . number_format($order['total_amount'], 2) . "</td>";
            echo "<td>" . ($order['status'] ?? 'NULL') . "</td>";
            echo "</tr>";
            $count++;
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "❌ Display query error: " . $e->getMessage() . "<br>";
}

// Step 4: Check for common issues
echo "<h3>Step 4: Checking Common Issues</h3>";

// Check database connection
$db_name = $pdo->query("SELECT DATABASE()")->fetchColumn();
echo "Connected to database: <strong>$db_name</strong><br>";

// Check table structures
$orders_structure = $pdo->query("SHOW CREATE TABLE orders")->fetch(PDO::FETCH_ASSOC);
if (strpos($orders_structure['Create Table'], 'customer_id') !== false) {
    echo "✅ Orders table has customer_id column<br>";
} else {
    echo "❌ Orders table missing customer_id column<br>";
}

// Check customer table
try {
    $customer_count = $pdo->query("SELECT COUNT(*) FROM customer")->fetchColumn();
    echo "✅ Customer table exists with $customer_count records<br>";
} catch (Exception $e) {
    echo "❌ Customer table issue: " . $e->getMessage() . "<br>";
}

// Check for PHP errors in order.php
echo "<h3>Step 5: PHP Error Check</h3>";
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Simulate the order.php query execution
    $test_orders = $pdo->query("SELECT o.*, c.name AS customer_name FROM orders o LEFT JOIN customer c ON o.customer_id = c.id ORDER BY o.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Query executed without PHP errors<br>";
    echo "✅ Returned " . count($test_orders) . " orders<br>";
} catch (Exception $e) {
    echo "❌ PHP Error in query: " . $e->getMessage() . "<br>";
}

$errors = ob_get_clean();
if ($errors) {
    echo "PHP Errors found:<br><pre>$errors</pre>";
} else {
    echo "✅ No PHP errors detected<br>";
}

echo "<br><div style='background: #e7f3ff; padding: 15px; border: 1px solid #b3d9ff; margin: 20px 0;'>";
echo "<h4>Next Steps:</h4>";
echo "1. <a href='order.php' target='_blank'>Test order.php page</a><br>";
echo "2. <a href='add_order.php' target='_blank'>Test add_order.php form</a><br>";
echo "3. If orders still don't show, check browser console for JavaScript errors<br>";
echo "4. Verify that order.php includes the correct header and sidebar files<br>";
echo "</div>";
?>
