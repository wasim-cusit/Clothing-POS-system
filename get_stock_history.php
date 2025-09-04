<?php
require_once 'includes/auth.php';
require_login();
require_once 'includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $product_id = intval($_GET['product_id']);
    
    if ($product_id <= 0) {
        throw new Exception('Invalid product ID');
    }
    
    // Get product information
    $stmt = $pdo->prepare("
        SELECT 
            p.product_name,
            p.product_code,
            c.category
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$product_id]);
    $product_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product_info) {
        throw new Exception('Product not found');
    }
    
         // Check if created_at column exists in stock_items table
     $stmt = $pdo->prepare("SHOW COLUMNS FROM stock_items LIKE 'created_at'");
     $stmt->execute();
     $has_created_at = $stmt->fetch();
     
     // Use created_at if it exists, otherwise use stock_date
     $created_at_field = $has_created_at ? 'created_at' : 'stock_date';
     
     // Determine which field to use for created_at
     $created_at_field = 'created_at';
     if (!isset($stock_items[0]['created_at']) || empty($stock_items[0]['created_at'])) {
         $created_at_field = 'updated_at';
     }
     
     // Get stock history data
     $stmt = $pdo->prepare("SELECT 
         id, 
         product_id, 
         quantity, 
         type, 
         reference_id, 
         reference_type, 
         notes, 
         {$created_at_field} as created_at
     FROM stock_items 
     WHERE product_id = ? 
     ORDER BY {$created_at_field} DESC");
     
     $stmt->execute([$product_id]);
     $stock_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
     
     // Process the data
     $processed_history = [];
     foreach ($stock_history as $item) {
         $processed_history[] = [
             'id' => $item['id'],
             'product_id' => $item['product_id'],
             'quantity' => $item['quantity'],
             'type' => $item['type'],
             'reference_id' => $item['reference_id'],
             'reference_type' => $item['reference_type'],
             'notes' => $item['notes'],
             'created_at' => $item['created_at']
         ];
     }
     
     echo json_encode([
         'success' => true,
         'stock_history' => $processed_history
     ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
