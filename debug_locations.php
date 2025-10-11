<?php
require_once 'config.php';

echo "<h1>Location Data Debug</h1>";

// Get distinct provinces
$stmt = $pdo->query("SELECT DISTINCT province FROM locations ORDER BY province");
$provinces = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "<h2>Distinct Provinces:</h2>";
echo "<pre>" . print_r($provinces, true) . "</pre>";

// Get sample districts for each province
echo "<h2>Districts by Province:</h2>";
foreach ($provinces as $province) {
    $stmt = $pdo->prepare("SELECT DISTINCT district FROM locations WHERE province = ? ORDER BY district LIMIT 3");
    $stmt->execute([$province]);
    $districts = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "<h3>$province:</h3>";
    echo "<pre>" . print_r($districts, true) . "</pre>";
}
?>