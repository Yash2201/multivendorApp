<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$srv = new App\Services\ShiprocketService();

$testPayload = [
    'order_id' => '100003',
    'order_date' => '2026-04-18 04:50',
    'pickup_location' => 'Primary',
    'billing_customer_name' => 'Test',
    'billing_last_name' => 'User',
    'billing_address' => '123 Main Street',
    'billing_address_2' => 'Apartment 4B',
    'billing_city' => 'Rajkot',
    'billing_pincode' => '360001',
    'billing_state' => 'Gujarat',
    'billing_country' => 'India',
    'billing_email' => 'test@example.com',
    'billing_phone' => '9876543210',
    'billing_isd_code' => '91',
    'shipping_is_billing' => 1,
    'shipping_customer_name' => 'Test',
    'shipping_last_name' => 'User',
    'shipping_address' => '123 Main Street',
    'shipping_address_2' => 'Apartment 4B',
    'shipping_city' => 'Rajkot',
    'shipping_pincode' => '360001',
    'shipping_country' => 'India',
    'shipping_state' => 'Gujarat',
    'shipping_email' => 'test@example.com',
    'shipping_phone' => '9876543210',
    'shipping_isd_code' => '91',
    'order_items' => [
        [
            'name' => 'Test Product',
            'sku' => 'TEST001',
            'units' => 1,
            'selling_price' => 100,
            'discount' => 0,
            'tax' => 0,
            'hsn' => ''
        ]
    ],
    'payment_method' => 'Prepaid',
    'shipping_charges' => 0,
    'total_discount' => 0,
    'sub_total' => 100,
    'length' => 10,
    'breadth' => 10,
    'height' => 10,
    'weight' => 0.5,
];

echo "=== Testing Direct API Call ===\n";
echo "Payload:\n";
echo json_encode($testPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

try {
    $reflection = new ReflectionClass($srv);
    $method = $reflection->getMethod('makeRequest');
    $method->setAccessible(true);
    
    $response = $method->invoke($srv, 'POST', '/orders/create/adhoc', $testPayload);
    
    echo "Response:\n";
    print_r($response);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
