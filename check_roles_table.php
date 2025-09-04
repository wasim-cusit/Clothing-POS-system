<?php
require_once 'includes/config.php';

echo "<h2>Database Tables Check</h2>";

try {
    // Check if roles table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'roles'");
    $rolesExists = $stmt->rowCount() > 0;
    
    echo "<p><strong>Roles table exists:</strong> " . ($rolesExists ? "YES" : "NO") . "</p>";
    
    if ($rolesExists) {
        echo "<h3>Roles table structure:</h3>";
        $stmt = $pdo->query("DESCRIBE roles");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>" . print_r($columns, true) . "</pre>";
        
        echo "<h3>Roles table data:</h3>";
        $stmt = $pdo->query("SELECT * FROM roles");
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>" . print_r($roles, true) . "</pre>";
    }
    
    // Check system_users table structure
    echo "<h3>System_users table structure:</h3>";
    $stmt = $pdo->query("DESCRIBE system_users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($columns, true) . "</pre>";
    
    // Check if role_id column has any values
    echo "<h3>Role IDs in system_users:</h3>";
    $stmt = $pdo->query("SELECT DISTINCT role_id FROM system_users");
    $roleIds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($roleIds, true) . "</pre>";
    
} catch (Exception $e) {
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
?>
