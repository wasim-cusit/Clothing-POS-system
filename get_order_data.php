<?php
// Prevent any output before JSON
ob_start();

// Include required files
require_once 'includes/auth.php';
require_login();
require_once 'includes/config.php';

// Clear any output buffer
ob_clean();

// Set proper headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Ensure no errors are displayed
error_reporting(0);
ini_set('display_errors', 0);

try {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
        exit;
    }

    $orderId = intval($_GET['id']);

    // Get order details
    $stmt = $pdo->prepare("SELECT o.*, c.name AS customer_name, c.mobile AS customer_mobile 
                           FROM orders o 
                           LEFT JOIN customer c ON o.customer_id = c.id 
                           WHERE o.id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }
    
    // Get order items with better error handling
    try {
        $stmt = $pdo->prepare("SELECT oi.*, p.product_name, p.product_code, cat.category_name 
                               FROM order_items oi 
                               LEFT JOIN products p ON oi.product_id = p.id 
                               LEFT JOIN categories cat ON p.category_id = cat.id 
                               WHERE oi.order_id = ?");
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // If the join fails, try to get just the order items
        error_log("Join query failed, trying simple query: " . $e->getMessage());
        $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Clean up items data and handle missing values
    foreach ($items as &$item) {
        $item['product_name'] = $item['product_name'] ?: 'Product ID: ' . $item['product_id'];
        $item['product_code'] = $item['product_code'] ?: '';
        $item['category'] = $item['category_name'] ?: 'General';
        $item['quantity'] = $item['quantity'] ?: 0;
        $item['price'] = $item['price'] ?: 0;
        $item['total_price'] = $item['total_price'] ?: 0;
    }
    unset($item);
    
    // Format dates
    if (!empty($order['order_date'])) {
        $order['order_date'] = date('d M Y', strtotime($order['order_date']));
        $order['order_time'] = date('h:i A', strtotime($order['order_date']));
    } else {
        $order['order_date'] = 'Not set';
        $order['order_time'] = 'Not set';
    }
    
    if (!empty($order['delivery_date'])) {
        $order['delivery_date'] = date('d M Y', strtotime($order['delivery_date']));
    } else {
        $order['delivery_date'] = 'Not set';
    }
    
    // Add items to order data
    $order['items'] = $items;
    
    echo json_encode(['success' => true, 'order' => $order]);
    
} catch (Exception $e) {
    error_log("Error fetching order data: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to fetch order data: ' . $e->getMessage()]);
} catch (Error $e) {
    error_log("Fatal error fetching order data: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'System error occurred']);
}

// Ensure no additional output
exit;
?>
