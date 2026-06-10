<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$email = config('shiprocket.email');
$password = config('shiprocket.password');
$baseUrl = config('shiprocket.base_url');

echo "=== Shiprocket Configuration ===\n";
echo "Email: " . ($email ? "SET" : "NOT SET") . "\n";
echo "Password: " . ($password ? "SET (length: " . strlen($password) . ")" : "NOT SET") . "\n";
echo "Base URL: " . $baseUrl . "\n\n";

$srv = new App\Services\ShiprocketService();
try {
    $token = $srv->getToken();
    echo "Token retrieved successfully!\n";
    echo "Token (first 50 chars): " . substr($token, 0, 50) . "...\n";
} catch (\Exception $e) {
    echo "Error getting token: " . $e->getMessage() . "\n";
}
