<!DOCTYPE html>
<html>
<head>
    <title>Date Format Check</title>
</head>
<body>
    <h2>Date Format Check</h2>
    <?php
    require_once 'includes/config.php';

    // Get a sample sale record to check date format
    $stmt = $pdo->prepare("SELECT id, sale_no, sale_date, created_at, delivery_date FROM sale ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sale) {
        echo "<h3>Sample Sale Record:</h3>";
        echo "<p><strong>ID:</strong> " . $sale['id'] . "</p>";
        echo "<p><strong>Sale No:</strong> " . $sale['sale_no'] . "</p>";
        echo "<p><strong>Sale Date (raw):</strong> " . $sale['sale_date'] . "</p>";
        echo "<p><strong>Created At (raw):</strong> " . $sale['created_at'] . "</p>";
        echo "<p><strong>Delivery Date (raw):</strong> " . ($sale['delivery_date'] ?? 'NULL') . "</p>";
        echo "<br>";
        
        // Test different date formatting
        echo "<h3>Date Formatting Tests:</h3>";
        echo "<p><strong>sale_date with strtotime:</strong> " . date('d/m/Y', strtotime($sale['sale_date'])) . "</p>";
        echo "<p><strong>sale_date with DateTime:</strong> " . (new DateTime($sale['sale_date']))->format('d/m/Y') . "</p>";
        echo "<p><strong>created_at with strtotime:</strong> " . date('d/m/Y H:i:s', strtotime($sale['created_at'])) . "</p>";
        echo "<p><strong>created_at with DateTime:</strong> " . (new DateTime($sale['created_at']))->format('d/m/Y H:i:s') . "</p>";
        
        if ($sale['delivery_date']) {
            echo "<p><strong>delivery_date with strtotime:</strong> " . date('d/m/Y', strtotime($sale['delivery_date'])) . "</p>";
            echo "<p><strong>delivery_date with DateTime:</strong> " . (new DateTime($sale['delivery_date']))->format('d/m/Y') . "</p>";
        }
        
        echo "<br>";
        echo "<h3>Current Time Tests:</h3>";
        echo "<p><strong>Current date():</strong> " . date('d/m/Y H:i:s') . "</p>";
        echo "<p><strong>Current DateTime:</strong> " . (new DateTime())->format('d/m/Y H:i:s') . "</p>";
        
    } else {
        echo "<p>No sale records found in database.</p>";
    }
    ?>
</body>
</html>
