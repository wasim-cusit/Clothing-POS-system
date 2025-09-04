<?php
// Simple test file to check get_order_data.php
echo "<h2>Testing get_order_data.php</h2>";

// Test with a sample order ID (you may need to change this)
$testOrderId = 1; // Change this to an actual order ID in your system

echo "<p>Testing with Order ID: $testOrderId</p>";

// Make a request to get_order_data.php
$url = "get_order_data.php?id=$testOrderId";
echo "<p>Requesting: $url</p>";

$response = file_get_contents($url);
echo "<h3>Raw Response:</h3>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Try to decode JSON
$data = json_decode($response, true);
if ($data === null) {
    echo "<h3>JSON Error:</h3>";
    echo "<p>JSON decode error: " . json_last_error_msg() . "</p>";
} else {
    echo "<h3>Decoded JSON:</h3>";
    echo "<pre>" . print_r($data, true) . "</pre>";
}

// Check if it's valid JSON
if (json_last_error() === JSON_ERROR_NONE) {
    echo "<p style='color: green;'>✅ Valid JSON response!</p>";
} else {
    echo "<p style='color: red;'>❌ Invalid JSON response!</p>";
}
?>
