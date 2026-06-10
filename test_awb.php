<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$srv = new App\Services\ShiprocketService();
$order = App\Models\Order::find(100002);

echo "=== Testing Shiprocket Full Shipment for Order 100002 ===\n\n";
try {
    $res = $srv->createFullShipment($order, ["weight"=>0.5, "length"=>10, "breadth"=>10, "height"=>10]);
    echo "SUCCESS!\n";
    print_r($res->toArray());
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
