<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$srv = new App\Services\ShiprocketService();

echo "=== Testing Shiprocket AWB Generation ===\n\n";
try {
    $res = $srv->generateAWB("1323333751");
    print_r($res);
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
