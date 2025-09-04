<?php
require_once 'includes/auth.php';
require_login();
require_once 'includes/config.php';

// Set JSON content type
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    // Validate required fields
    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $opening_balance = (float)($_POST['opening_balance'] ?? 0);
    
    if (empty($name) || empty($mobile) || empty($address)) {
        echo json_encode(['success' => false, 'error' => 'Name, mobile, and address are required']);
        exit;
    }
    
    // Check if mobile already exists
    $stmt = $pdo->prepare('SELECT id FROM customer WHERE mobile = ?');
    $stmt->execute([$mobile]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'A customer with this mobile number already exists']);
        exit;
    }
    
    // Insert new customer
    $stmt = $pdo->prepare('INSERT INTO customer (name, mobile, email, address, opening_balance, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$name, $mobile, $email, $address, $opening_balance]);
    
    $customerId = (int)$pdo->lastInsertId();
    
    // Return success with customer data
    echo json_encode([
        'success' => true,
        'customer' => [
            'id' => $customerId,
            'name' => $name,
            'mobile' => $mobile,
            'email' => $email,
            'address' => $address,
            'opening_balance' => $opening_balance
        ]
    ]);
    
} catch (Throwable $e) {
    error_log('Error adding customer: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to add customer. Please try again.']);
}
?>
