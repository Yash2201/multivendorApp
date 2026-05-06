<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$srv = new App\Services\ShiprocketService();
$order = App\Models\Order::find(100003);

// Override shipping address with test data for Rajkot (a common city)
$order->shipping_address_data = json_encode([
    "id" => 1,
    "customer_id" => "304",
    "is_guest" => true,
    "contact_person_name" => "Test User",
    "email" => "test@example.com",
    "address_type" => "permanent",
    "address" => "123 Main Street",
    "address_2" => "Apartment 4B",
    "city" => "Rajkot",
    "zip" => "360001",
    "phone" => "+919876543210",
    "created_at" => null,
    "updated_at" => null,
    "state" => "Gujarat",
    "country" => "India",
    "latitude" => "23.510113826786984",
    "longitude" => "73.011109968418",
    "is_billing" => false
]);

$order->billing_address_data = $order->shipping_address_data;

try {
    $res = $srv->createFullShipment($order, ["weight"=>0.5, "length"=>10, "breadth"=>10, "height"=>10]);
    print_r($res);
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
