<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ShiprocketPickupAddress;
use App\Models\ShiprocketShipment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShiprocketService
{
    private string $baseUrl;
    private string $email;
    private string $password;
    private int $tokenCacheMinutes;

    public function __construct()
    {
        $this->baseUrl = config('shiprocket.base_url');
        $this->email = config('shiprocket.email');
        $this->password = config('shiprocket.password');
        $this->tokenCacheMinutes = config('shiprocket.token_cache_minutes', 12960);
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    /**
     * Get a valid Shiprocket API token (cached for ~9 days).
     *
     * @return string
     * @throws \Exception
     */
    public function getToken(): string
    {
        return Cache::remember('shiprocket_api_token', $this->tokenCacheMinutes * 60, function () {
            $response = Http::post("{$this->baseUrl}/auth/login", [
                'email' => $this->email,
                'password' => $this->password,
            ]);

            if ($response->failed()) {
                Log::channel('shiprocket')->error('Authentication failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Shiprocket authentication failed: ' . $response->body());
            }

            $token = $response->json('token');

            if (empty($token)) {
                throw new \Exception('Shiprocket returned empty token');
            }

            Log::channel('shiprocket')->info('Authenticated successfully');
            return $token;
        });
    }

    /**
     * Clear the cached token (useful when token expires prematurely).
     */
    public function clearToken(): void
    {
        Cache::forget('shiprocket_api_token');
    }

    // -------------------------------------------------------------------------
    // Order Management
    // -------------------------------------------------------------------------

    /**
     * Create an order on Shiprocket from an application Order.
     *
     * @param Order $order        The application order (with relations loaded)
     * @param array $packageInfo  Package dimensions: weight, length, breadth, height
     * @param string|null $pickupLocation  Pickup location name (from Shiprocket dashboard)
     * @return array              Shiprocket API response
     * @throws \Exception
     */
    public function createOrder(Order $order, array $packageInfo, ?string $pickupLocation = null): array
    {
        $order->loadMissing(['details.productAllStatus', 'customer', 'shippingAddress', 'billingAddress', 'seller.shop']);

        // Pre-flight: Validate that the pickup location exists in Shiprocket account
        $resolvedPickupLocation = $pickupLocation ?? config('shiprocket.default_pickup_location', 'Primary');
        $this->validatePickupLocation($resolvedPickupLocation);

        $shippingAddress = $this->addressDataToArray($order->shipping_address_data)
            ?: optional($order->shippingAddress)->toArray()
            ?: [];

        $billingAddress = $this->addressDataToArray($order->billing_address_data)
            ?: optional($order->billingAddress)->toArray()
            ?: $shippingAddress;

        if (empty($shippingAddress)) {
            throw new \Exception('Order has no shipping address');
        }

        $orderItems = [];
        foreach ($order->details as $detail) {
            $productDetails = json_decode($detail->product_details, true);
            $orderItems[] = [
                'name' => $productDetails['name'] ?? 'Product #' . $detail->product_id,
                'sku' => $productDetails['code'] ?? 'SKU-' . $detail->product_id,
                'units' => $detail->qty,
                'selling_price' => round($detail->price, 2),
                'discount' => round($detail->discount ?? 0, 2),
                'tax' => round($detail->tax ?? 0, 2),
                'hsn' => $productDetails['hsn_code'] ?? '',
            ];
        }

        $shippingName = $this->splitCustomerName($shippingAddress['contact_person_name'] ?? null);
        $billingName = $this->splitCustomerName($billingAddress['contact_person_name'] ?? $shippingAddress['contact_person_name'] ?? null);
        $shipping = $this->normalizeAddressForShiprocket($shippingAddress, $order, 'shipping');
        $billing = $this->normalizeAddressForShiprocket($billingAddress, $order, 'billing');

        // Check if addresses match - if they do, set shipping_is_billing to 1
        $shippingIsBilling = $this->addressesMatchForShiprocket($billing, $shipping) ? 1 : 0;

        $payload = [
            'order_id' => (string) $order->id,
            'order_date' => $order->created_at->format('Y-m-d H:i'),
            'pickup_location' => $resolvedPickupLocation,
            'billing_customer_name' => $billingName['first_name'],
            'billing_last_name' => $billingName['last_name'],
            'billing_address' => $billing['address'],
            'billing_address_2' => $billing['address_2'],
            'billing_city' => $billing['city'],
            'billing_pincode' => (int) $billing['pincode'],
            'billing_state' => $billing['state'],
            'billing_country' => $billing['country'],
            'billing_email' => $billing['email'],
            'billing_phone' => (int) $billing['phone'],
            'billing_isd_code' => 91,
            'shipping_is_billing' => (int) $shippingIsBilling,
            'shipping_customer_name' => $shippingName['first_name'],
            'shipping_last_name' => $shippingName['last_name'],
            'shipping_address' => $shipping['address'],
            'shipping_address_2' => $shipping['address_2'],
            'shipping_city' => $shipping['city'],
            'shipping_pincode' => (int) $shipping['pincode'],
            'shipping_country' => $shipping['country'],
            'shipping_state' => $shipping['state'],
            'shipping_email' => $shipping['email'],
            'shipping_phone' => (int) $shipping['phone'],
            'shipping_isd_code' => 91,
            'order_items' => $orderItems,
            'payment_method' => $order->payment_method === 'cash_on_delivery' ? 'COD' : 'Prepaid',
            'shipping_charges' => round($order->shipping_cost ?? 0, 2),
            'total_discount' => round($order->discount_amount ?? 0, 2),
            'sub_total' => round($order->order_amount, 2),
            'length' => $packageInfo['length'] ?? 10,
            'breadth' => $packageInfo['breadth'] ?? 10,
            'height' => $packageInfo['height'] ?? 10,
            'weight' => $packageInfo['weight'] ?? 0.5,
        ];

        // Validate payload before sending
        $this->validateShiprocketPayload($payload);

        Log::channel('shiprocket')->info('Shiprocket order payload prepared', [
            'order_id' => $order->id,
            'pickup_location' => $resolvedPickupLocation,
            'payload' => $payload,
        ]);

        $response = $this->makeRequest('POST', '/orders/create/adhoc', $payload);

        Log::channel('shiprocket')->info('Order created on Shiprocket', [
            'order_id' => $order->id,
            'shiprocket_order_id' => $response['order_id'] ?? null,
            'shiprocket_shipment_id' => $response['shipment_id'] ?? null,
        ]);

        return $response;
    }

    /**
     * Cancel a Shiprocket order.
     *
     * @param array $shiprocketOrderIds  Array of Shiprocket order IDs to cancel
     * @return array
     */
    public function cancelOrder(array $shiprocketOrderIds): array
    {
        return $this->makeRequest('POST', '/orders/cancel', [
            'ids' => $shiprocketOrderIds,
        ]);
    }

    // -------------------------------------------------------------------------
    // Courier & AWB
    // -------------------------------------------------------------------------

    /**
     * Check courier serviceability for a given pickup and delivery pincode.
     *
     * @param string $pickupPincode
     * @param string $deliveryPincode
     * @param float  $weight          Weight in kg
     * @param float  $codAmount       COD amount (0 for prepaid)
     * @return array                  Available couriers with rates
     */
    public function checkCourierServiceability(
        string $pickupPincode,
        string $deliveryPincode,
        float $weight = 0.5,
        float $codAmount = 0
    ): array {
        $params = [
            'pickup_postcode' => $pickupPincode,
            'delivery_postcode' => $deliveryPincode,
            'weight' => $weight,
            'cod' => $codAmount > 0 ? 1 : 0,
        ];

        $response = $this->makeRequest('GET', '/courier/serviceability/', null, $params);

        return $response['data']['available_courier_companies'] ?? [];
    }

    /**
     * Generate AWB (Air Waybill) for a shipment.
     *
     * @param string $shipmentId  Shiprocket shipment ID
     * @param int|null $courierId  Courier ID (null for auto-assign)
     * @return array
     */
    public function generateAWB(string $shipmentId, ?int $courierId = null): array
    {
        $payload = [
            'shipment_id' => $shipmentId,
        ];

        if ($courierId) {
            $payload['courier_id'] = $courierId;
        }

        $response = $this->makeRequest('POST', '/courier/assign/awb', $payload);

        Log::channel('shiprocket')->info('AWB generated', [
            'shipment_id' => $shipmentId,
            'awb_code' => $response['response']['data']['awb_code'] ?? null,
            'courier_name' => $response['response']['data']['courier_name'] ?? null,
        ]);

        return $response;
    }

    /**
     * Request pickup for a shipment.
     *
     * @param array $shipmentIds  Array of shipment IDs
     * @return array
     */
    public function requestPickup(array $shipmentIds): array
    {
        return $this->makeRequest('POST', '/courier/generate/pickup', [
            'shipment_id' => $shipmentIds,
        ]);
    }

    // -------------------------------------------------------------------------
    // Tracking
    // -------------------------------------------------------------------------

    /**
     * Track a shipment by AWB code.
     *
     * @param string $awbCode
     * @return array
     */
    public function trackByAWB(string $awbCode): array
    {
        return $this->makeRequest('GET', "/courier/track/awb/{$awbCode}");
    }

    /**
     * Track a shipment by Shiprocket shipment ID.
     *
     * @param string $shipmentId
     * @return array
     */
    public function trackByShipmentId(string $shipmentId): array
    {
        return $this->makeRequest('GET', "/courier/track/shipment/{$shipmentId}");
    }

    /**
     * Track a shipment by order ID.
     *
     * @param string $orderId  Shiprocket order ID (or your channel order ID)
     * @return array
     */
    public function trackByOrderId(string $orderId): array
    {
        return $this->makeRequest('GET', "/courier/track", null, [
            'order_id' => $orderId,
        ]);
    }

    // -------------------------------------------------------------------------
    // Documents (Labels & Manifests)
    // -------------------------------------------------------------------------

    /**
     * Generate shipping label for a shipment.
     *
     * @param array $shipmentIds
     * @return array  Contains 'label_url' key
     */
    public function generateLabel(array $shipmentIds): array
    {
        return $this->makeRequest('POST', '/courier/generate/label', [
            'shipment_id' => $shipmentIds,
        ]);
    }

    /**
     * Generate manifest for a shipment.
     *
     * @param array $shipmentIds
     * @return array  Contains 'manifest_url' key
     */
    public function generateManifest(array $shipmentIds): array
    {
        return $this->makeRequest('POST', '/manifests/generate', [
            'shipment_id' => $shipmentIds,
        ]);
    }

    /**
     * Generate invoice for a Shiprocket order.
     *
     * @param array $orderIds  Shiprocket order IDs
     * @return array  Contains 'invoice_url' key
     */
    public function generateInvoice(array $orderIds): array
    {
        return $this->makeRequest('POST', '/orders/print/invoice', [
            'ids' => $orderIds,
        ]);
    }

    // -------------------------------------------------------------------------
    // Shipment Cancellation
    // -------------------------------------------------------------------------

    /**
     * Cancel a shipment / AWB.
     *
     * @param array $awbCodes  Array of AWB codes to cancel
     * @return array
     */
    public function cancelShipment(array $awbCodes): array
    {
        return $this->makeRequest('POST', '/orders/cancel/shipment/awbs', [
            'awbs' => $awbCodes,
        ]);
    }

    // -------------------------------------------------------------------------
    // Pickup Locations
    // -------------------------------------------------------------------------

    /**
     * Get all pickup locations configured in Shiprocket.
     *
     * @return array
     */
    public function getPickupLocations(): array
    {
        $response = $this->makeRequest('GET', '/settings/company/pickup');
        return $response['data']['shipping_address'] ?? [];
    }

    /**
     * Add a new pickup location (for vendor onboarding).
     *
     * @param array $locationData
     * @return array
     */
    public function addPickupLocation(array $locationData): array
    {
        return $this->makeRequest('POST', '/settings/company/addpickup', $locationData);
    }

    /**
     * Create a pickup address for a vendor (or admin/in-house when $sellerId is null).
     *
     * Registers the address on the shared Shiprocket account under a unique,
     * owner-namespaced nickname, then persists it locally scoped to the owner.
     * The stored nickname is what later flows into createFullShipment() as the
     * Shiprocket `pickup_location`.
     *
     * @param array    $data      Validated pickup address fields (name, email, phone, address, ...)
     * @param int|null $sellerId  Owner seller id; null = admin/in-house
     * @return ShiprocketPickupAddress
     * @throws \Exception
     */
    public function createPickupAddress(array $data, ?int $sellerId = null): ShiprocketPickupAddress
    {
        $nickname = $this->generatePickupNickname($sellerId);

        $payload = [
            'pickup_location' => $nickname,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'address' => $data['address'],
            'address_2' => $data['address_2'] ?? '',
            'city' => $data['city'],
            'state' => $data['state'],
            'country' => $data['country'] ?? 'India',
            'pin_code' => $data['pin_code'],
        ];

        $response = $this->addPickupLocation($payload);

        // Shiprocket can return success=false with HTTP 200 for some validation
        // errors, in which case makeRequest() would not have thrown — guard here so
        // we never store a nickname that does not actually exist on their side.
        if (isset($response['success']) && $response['success'] === false) {
            throw new \Exception($response['message'] ?? 'Shiprocket rejected the pickup address');
        }

        $isFirst = !ShiprocketPickupAddress::query()->forSeller($sellerId)->exists();

        $address = ShiprocketPickupAddress::create([
            'seller_id' => $sellerId,
            'pickup_nickname' => $nickname,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'address' => $data['address'],
            'address_2' => $data['address_2'] ?? null,
            'city' => $data['city'],
            'state' => $data['state'],
            'country' => $data['country'] ?? 'India',
            'pin_code' => $data['pin_code'],
            'is_default' => $isFirst,
            'is_synced' => true,
            'raw_response' => $response,
        ]);

        Log::channel('shiprocket')->info('Pickup address registered', [
            'seller_id' => $sellerId,
            'pickup_nickname' => $nickname,
            'pickup_address_id' => $address->id,
        ]);

        return $address;
    }

    /**
     * Generate a unique, owner-namespaced Shiprocket pickup nickname.
     *
     * Shiprocket caps `pickup_location` at 36 chars and requires it unique within
     * the account; namespacing by owner (v{sellerId}_ / inhouse_) keeps vendors
     * from colliding on the shared account.
     *
     * @param int|null $sellerId
     * @return string
     */
    private function generatePickupNickname(?int $sellerId): string
    {
        $prefix = $sellerId ? 'v' . $sellerId : 'inhouse';

        do {
            $nickname = $prefix . '_' . strtoupper(bin2hex(random_bytes(4)));
        } while (ShiprocketPickupAddress::where('pickup_nickname', $nickname)->exists());

        return $nickname;
    }

    // -------------------------------------------------------------------------
    // High-Level Orchestration (combines multiple API calls)
    // -------------------------------------------------------------------------

    /**
     * Full shipment creation flow: Create Order → Generate AWB → Request Pickup.
     *
     * This is the primary method vendors will use. It orchestrates the complete
     * flow and stores the result in the shiprocket_shipments table.
     *
     * @param Order       $order
     * @param array       $packageInfo     [weight, length, breadth, height]
     * @param int|null    $courierId       Specific courier ID (null = auto-assign)
     * @param string|null $pickupLocation  Pickup location name
     * @return ShiprocketShipment
     * @throws \Exception
     */
    public function createFullShipment(
        Order $order,
        array $packageInfo,
        ?int $courierId = null,
        ?string $pickupLocation = null
    ): ShiprocketShipment {
        // Check if shipment already exists for this order
        $existingShipment = ShiprocketShipment::where('order_id', $order->id)
            ->whereNotIn('shipment_status', [
                ShiprocketShipment::STATUS_CANCELLED,
                ShiprocketShipment::STATUS_FAILED,
            ])
            ->first();

        if ($existingShipment) {
            throw new \Exception('An active shipment already exists for this order (Shipment ID: ' . $existingShipment->shiprocket_shipment_id . ')');
        }

        // Step 1: Create order on Shiprocket
        $createResponse = $this->createOrder($order, $packageInfo, $pickupLocation);

        $shiprocketOrderId = $createResponse['order_id'] ?? null;
        $shipmentId = $createResponse['shipment_id'] ?? null;

        if (empty($shiprocketOrderId) || empty($shipmentId)) {
            Log::channel('shiprocket')->error('Order creation returned incomplete data', [
                'order_id' => $order->id,
                'response' => $createResponse,
            ]);
            throw new \Exception('Shiprocket order creation failed: incomplete response');
        }

        // Create local shipment record
        $shipment = ShiprocketShipment::create([
            'order_id' => $order->id,
            'seller_id' => $order->seller_id,
            'shiprocket_order_id' => $shiprocketOrderId,
            'shiprocket_shipment_id' => $shipmentId,
            'shipment_status' => ShiprocketShipment::STATUS_ORDER_CREATED,
            'package_weight' => $packageInfo['weight'] ?? 0.5,
            'package_length' => $packageInfo['length'] ?? 10,
            'package_breadth' => $packageInfo['breadth'] ?? 10,
            'package_height' => $packageInfo['height'] ?? 10,
            'pickup_location' => $pickupLocation ?? config('shiprocket.default_pickup_location'),
            'raw_response' => $createResponse,
        ]);

        // Step 2: Generate AWB
        try {
            $awbResponse = $this->generateAWB($shipmentId, $courierId);
            $awbData = $awbResponse['response']['data'] ?? [];

            $shipment->update([
                'awb_code' => $awbData['awb_code'] ?? null,
                'courier_id' => $awbData['courier_company_id'] ?? null,
                'courier_name' => $awbData['courier_name'] ?? null,
                'shipment_status' => ShiprocketShipment::STATUS_AWB_ASSIGNED,
                'shipping_charge' => $awbData['freight_charge'] ?? 0,
                'cod_charge' => $awbData['cod_charges'] ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::channel('shiprocket')->warning('AWB generation failed, shipment created without AWB', [
                'order_id' => $order->id,
                'shipment_id' => $shipmentId,
                'error' => $e->getMessage(),
            ]);
            $shipment->update([
                'last_error' => 'AWB generation failed: ' . $e->getMessage(),
            ]);
        }

        // Step 3: Request Pickup (only if AWB was assigned)
        if (!empty($shipment->awb_code)) {
            try {
                $pickupResponse = $this->requestPickup([$shipmentId]);
                $pickupDate = $pickupResponse['response']['pickup_scheduled_date'] ?? null;

                $shipment->update([
                    'shipment_status' => ShiprocketShipment::STATUS_PICKUP_SCHEDULED,
                    'pickup_scheduled_date' => $pickupDate,
                ]);
            } catch (\Exception $e) {
                Log::channel('shiprocket')->warning('Pickup request failed', [
                    'order_id' => $order->id,
                    'shipment_id' => $shipmentId,
                    'error' => $e->getMessage(),
                ]);
                $shipment->update([
                    'last_error' => 'Pickup request failed: ' . $e->getMessage(),
                ]);
            }
        }

        // Step 4: Generate label URL
        try {
            $labelResponse = $this->generateLabel([$shipmentId]);
            $shipment->update([
                'label_url' => $labelResponse['label_url'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::channel('shiprocket')->warning('Label generation failed', [
                'shipment_id' => $shipmentId,
                'error' => $e->getMessage(),
            ]);
        }

        // Step 5: Update Order table with third-party delivery info
        $order->update([
            'delivery_type' => 'third_party_delivery',
            'delivery_service_name' => 'Shiprocket - ' . ($shipment->courier_name ?? 'Pending'),
            'third_party_delivery_tracking_id' => $shipment->awb_code,
        ]);

        Log::channel('shiprocket')->info('Full shipment created successfully', [
            'order_id' => $order->id,
            'shiprocket_order_id' => $shiprocketOrderId,
            'shipment_id' => $shipmentId,
            'awb_code' => $shipment->awb_code,
            'courier' => $shipment->courier_name,
            'status' => $shipment->shipment_status,
        ]);

        return $shipment->fresh();
    }

    /**
     * Sync tracking status for a shipment from Shiprocket API.
     *
     * @param ShiprocketShipment $shipment
     * @return ShiprocketShipment  Updated shipment
     */
    public function syncShipmentStatus(ShiprocketShipment $shipment): ShiprocketShipment
    {
        if (!$shipment->isActive()) {
            return $shipment;
        }

        try {
            $trackingData = [];

            if (!empty($shipment->awb_code)) {
                $trackingData = $this->trackByAWB($shipment->awb_code);
            } elseif (!empty($shipment->shiprocket_shipment_id)) {
                $trackingData = $this->trackByShipmentId($shipment->shiprocket_shipment_id);
            }

            if (empty($trackingData)) {
                return $shipment;
            }

            $trackingInfo = $trackingData['tracking_data'] ?? [];
            $currentStatus = $trackingInfo['shipment_track'][0]['current_status'] ?? null;
            $currentStatusId = $trackingInfo['shipment_status'] ?? null;
            $etd = $trackingInfo['etd'] ?? null;
            $trackUrl = $trackingInfo['track_url'] ?? null;

            $updateData = [
                'last_error' => null,
            ];

            if ($currentStatusId !== null) {
                $updateData['shiprocket_status_code'] = $currentStatusId;
                $updateData['shipment_status'] = $this->mapShiprocketStatus($currentStatusId);
            }

            if ($etd) {
                $updateData['estimated_delivery_date'] = $etd;
            }

            if ($trackUrl) {
                $updateData['tracking_url'] = $trackUrl;
            }

            // Check if delivered
            if (in_array($currentStatusId, config('shiprocket.delivered_status_codes', [7]))) {
                $updateData['shipment_status'] = ShiprocketShipment::STATUS_DELIVERED;
                $updateData['delivered_date'] = now();
            }

            $shipment->update($updateData);

            Log::channel('shiprocket')->info('Shipment status synced', [
                'shipment_id' => $shipment->shiprocket_shipment_id,
                'awb_code' => $shipment->awb_code,
                'status' => $updateData['shipment_status'] ?? $shipment->shipment_status,
                'shiprocket_status_code' => $currentStatusId,
            ]);

        } catch (\Exception $e) {
            Log::channel('shiprocket')->error('Failed to sync shipment status', [
                'shipment_id' => $shipment->shiprocket_shipment_id,
                'error' => $e->getMessage(),
            ]);
            $shipment->update([
                'last_error' => 'Sync failed: ' . $e->getMessage(),
                'retry_count' => $shipment->retry_count + 1,
            ]);
        }

        return $shipment->fresh();
    }

    /**
     * Cancel a full shipment (AWB + Shiprocket order).
     *
     * @param ShiprocketShipment $shipment
     * @return ShiprocketShipment
     * @throws \Exception
     */
    public function cancelFullShipment(ShiprocketShipment $shipment): ShiprocketShipment
    {
        if (!$shipment->isCancellable()) {
            throw new \Exception('Shipment cannot be cancelled in its current status: ' . $shipment->shipment_status);
        }

        // Cancel AWB if assigned
        if (!empty($shipment->awb_code)) {
            try {
                $this->cancelShipment([$shipment->awb_code]);
            } catch (\Exception $e) {
                Log::channel('shiprocket')->warning('AWB cancellation failed', [
                    'awb_code' => $shipment->awb_code,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Cancel Shiprocket order
        if (!empty($shipment->shiprocket_order_id)) {
            try {
                $this->cancelOrder([(int) $shipment->shiprocket_order_id]);
            } catch (\Exception $e) {
                Log::channel('shiprocket')->warning('Shiprocket order cancellation failed', [
                    'shiprocket_order_id' => $shipment->shiprocket_order_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Update local records
        $shipment->update([
            'shipment_status' => ShiprocketShipment::STATUS_CANCELLED,
        ]);

        // Reset order delivery info
        $shipment->order->update([
            'delivery_type' => null,
            'delivery_service_name' => null,
            'third_party_delivery_tracking_id' => null,
        ]);

        Log::channel('shiprocket')->info('Shipment cancelled', [
            'order_id' => $shipment->order_id,
            'shiprocket_order_id' => $shipment->shiprocket_order_id,
            'awb_code' => $shipment->awb_code,
        ]);

        return $shipment->fresh();
    }

    // -------------------------------------------------------------------------
    // Internal Helpers
    // -------------------------------------------------------------------------

    private function splitCustomerName(?string $fullName): array
    {
        $fullName = trim((string) $fullName);

        if ($fullName === '') {
            return [
                'first_name' => 'Customer',
                'last_name' => 'Customer',
            ];
        }

        $parts = preg_split('/\s+/', $fullName) ?: [];
        $firstName = array_shift($parts) ?: 'Customer';
        $lastName = implode(' ', $parts);

        return [
            'first_name' => $firstName,
            'last_name' => $lastName !== '' ? $lastName : $firstName,
        ];
    }

    private function addressDataToArray(mixed $addressData): array
    {
        if (empty($addressData)) {
            return [];
        }

        if (is_array($addressData)) {
            return $addressData;
        }

        // If it's a string, decode it directly
        if (is_string($addressData)) {
            $decoded = json_decode($addressData, true);
            return is_array($decoded) ? $decoded : [];
        }

        // Handle objects - check if it has a scalar property (Eloquent JSON casting)
        if (is_object($addressData)) {
            if (isset($addressData->scalar)) {
                // This is an Eloquent JSON casted attribute wrapping
                $decoded = json_decode($addressData->scalar, true);
                return is_array($decoded) ? $decoded : [];
            }
            // Normal object conversion
            return json_decode(json_encode($addressData), true) ?: [];
        }

        return [];
    }

    private function normalizeAddressForShiprocket(array $shippingAddress, Order $order, string $addressType): array
    {
        $address = trim((string) ($shippingAddress['address'] ?? $shippingAddress['street_address'] ?? ''));
        $address2 = trim((string) ($shippingAddress['address_2'] ?? $shippingAddress['landmark'] ?? ''));
        $city = trim((string) ($shippingAddress['city'] ?? ''));
        $pincode = preg_replace('/\D+/', '', (string) ($shippingAddress['zip'] ?? $shippingAddress['pincode'] ?? $shippingAddress['postal_code'] ?? ''));
        $phone = preg_replace('/\D+/', '', (string) ($shippingAddress['phone'] ?? optional($order->customer)->phone ?? ''));
        
        // Email: Try multiple sources to find a valid email
        $email = trim((string) ($shippingAddress['email'] ?? ''));
        if (!$email) {
            $email = optional($order->customer)->email ?? '';
        }
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Generate a safe fallback email using order ID
            $email = "order{$order->id}@example.com";
        }
        
        $country = trim((string) ($shippingAddress['country'] ?? 'India'));
        $state = $this->resolveIndianState(
            $shippingAddress['state'] ?? null,
            $city,
            $address
        );

        // Extract 10-digit phone number from Indian number (with or without country code)
        if (strlen($phone) >= 12 && str_starts_with($phone, '91')) {
            // Has country code, remove it: 917575757575 -> 7575757575
            $phone = substr($phone, 2);
        } elseif (strlen($phone) > 10) {
            // Take last 10 digits if longer than 10
            $phone = substr($phone, -10);
        }

        if ($address === '') {
            throw new \Exception("Order {$addressType} address is missing address line");
        }

        if ($city === '') {
            throw new \Exception("Order {$addressType} address is missing city");
        }

        if (!preg_match('/^\d{6}$/', $pincode)) {
            throw new \Exception("Order {$addressType} address pincode must be a valid 6 digit Indian pincode (got: '{$pincode}')");
        }

        if (!preg_match('/^\d{10}$/', $phone)) {
            throw new \Exception("Order {$addressType} phone must be a valid 10 digit Indian mobile number (got: '{$phone}')");
        }

        return [
            'address' => $address,
            'address_2' => $address2 ?: $address, // Use address as fallback if address_2 is empty
            'city' => mb_substr($city, 0, 30),
            'pincode' => $pincode,
            'state' => $state,
            'country' => $country !== '' ? $country : 'India',
            'email' => $email,
            'phone' => $phone,
        ];
    }

    private function addressesMatchForShiprocket(array $billing, array $shipping): bool
    {
        foreach (['address', 'address_2', 'city', 'pincode', 'state', 'country', 'email', 'phone'] as $field) {
            if (strcasecmp((string) ($billing[$field] ?? ''), (string) ($shipping[$field] ?? '')) !== 0) {
                return false;
            }
        }

        return true;
    }

    private function resolveIndianState(?string $state, string $city, string $address): string
    {
        $state = trim((string) $state);
        $city = trim($city);

        // If state is already provided and valid, use it
        if ($state !== '' && strcasecmp($state, 'N/A') !== 0 && strcasecmp($state, $city) !== 0) {
            return ucwords(strtolower($state));
        }

        // City to state mapping for common Indian cities
        $cityToState = [
            'rajkot' => 'Gujarat',
            'ahmedabad' => 'Gujarat',
            'surat' => 'Gujarat',
            'vadodara' => 'Gujarat',
            'mumbai' => 'Maharashtra',
            'pune' => 'Maharashtra',
            'delhi' => 'Delhi',
            'bangalore' => 'Karnataka',
            'hyderabad' => 'Telangana',
            'kolkata' => 'West Bengal',
            'chennai' => 'Tamil Nadu',
            'cochin' => 'Kerala',
            'jaipur' => 'Rajasthan',
            'lucknow' => 'Uttar Pradesh',
            'chandigarh' => 'Chandigarh',
        ];

        // Check if city matches a known mapping
        $cityLower = strtolower($city);
        if (isset($cityToState[$cityLower])) {
            return $cityToState[$cityLower];
        }

        // Search for state names in address/city/state text
        $haystack = strtolower($address . ' ' . $city . ' ' . $state);
        $states = [
            'andhra pradesh', 'arunachal pradesh', 'assam', 'bihar', 'chhattisgarh',
            'goa', 'gujarat', 'haryana', 'himachal pradesh', 'jharkhand', 'karnataka',
            'kerala', 'madhya pradesh', 'maharashtra', 'manipur', 'meghalaya', 'mizoram',
            'nagaland', 'odisha', 'punjab', 'rajasthan', 'sikkim', 'tamil nadu',
            'telangana', 'tripura', 'uttar pradesh', 'uttarakhand', 'west bengal',
            'andaman and nicobar islands', 'chandigarh', 'dadra and nagar haveli and daman and diu',
            'delhi', 'jammu and kashmir', 'ladakh', 'lakshadweep', 'puducherry',
        ];

        foreach ($states as $knownState) {
            if (str_contains($haystack, $knownState)) {
                return ucwords($knownState);
            }
        }

        return config('shiprocket.default_state', 'Gujarat');
    }

    /**
     * Map a Shiprocket numeric status code to an internal shipment status.
     *
     * @param int $statusCode
     * @return string
     */
    private function mapShiprocketStatus(int $statusCode): string
    {
        $deliveredCodes = config('shiprocket.delivered_status_codes', [7]);
        if (in_array($statusCode, $deliveredCodes)) {
            return ShiprocketShipment::STATUS_DELIVERED;
        }

        $cancelledCodes = [8, 16, 45];
        if (in_array($statusCode, $cancelledCodes)) {
            return ShiprocketShipment::STATUS_CANCELLED;
        }

        $rtoCodes = [9, 10, 14, 40, 41, 46];
        if (in_array($statusCode, $rtoCodes)) {
            return in_array($statusCode, [10]) ? ShiprocketShipment::STATUS_RTO_DELIVERED : ShiprocketShipment::STATUS_RTO_INITIATED;
        }

        if ($statusCode === 17 || $statusCode === 57) {
            return ShiprocketShipment::STATUS_OUT_FOR_DELIVERY;
        }

        if (in_array($statusCode, [6, 42, 18, 38, 48, 51, 52, 53, 54, 55, 56])) {
            return ShiprocketShipment::STATUS_IN_TRANSIT;
        }

        if (in_array($statusCode, [19, 3, 4, 5])) {
            return ShiprocketShipment::STATUS_PICKUP_SCHEDULED;
        }

        if (in_array($statusCode, [1, 2])) {
            return ShiprocketShipment::STATUS_AWB_ASSIGNED;
        }

        return ShiprocketShipment::STATUS_IN_TRANSIT;
    }

    /**
     * Validate that required Shiprocket address fields are present and valid.
     *
     * @param array $payload
     * @throws \Exception
     */
    private function validateShiprocketPayload(array $payload): void
    {
        // Check billing address fields (always required)
        $billingRequiredFields = [
            'billing_customer_name',
            'billing_address',
            'billing_address_2',
            'billing_city',
            'billing_pincode',
            'billing_state',
            'billing_country',
            'billing_phone',
            'billing_email',
        ];

        foreach ($billingRequiredFields as $field) {
            if (empty($payload[$field])) {
                throw new \Exception("Billing address validation failed: {$field} is missing or empty (value: " . var_export($payload[$field] ?? null, true) . ")");
            }
        }

        // Check shipping address fields (always required)
        $shippingRequiredFields = [
            'shipping_customer_name',
            'shipping_address',
            'shipping_address_2',
            'shipping_city',
            'shipping_pincode',
            'shipping_state',
            'shipping_country',
            'shipping_phone',
            'shipping_email',
        ];

        foreach ($shippingRequiredFields as $field) {
            if (empty($payload[$field])) {
                throw new \Exception("Shipping address validation failed: {$field} is missing or empty (value: " . var_export($payload[$field] ?? null, true) . ")");
            }
        }

        // Validate phone numbers are 10 digits
        if (!preg_match('/^\d{10}$/', (string) $payload['billing_phone'])) {
            throw new \Exception("Billing phone is invalid: {$payload['billing_phone']} (must be 10 digits)");
        }
        if (!preg_match('/^\d{10}$/', (string) $payload['shipping_phone'])) {
            throw new \Exception("Shipping phone is invalid: {$payload['shipping_phone']} (must be 10 digits)");
        }

        // Validate pincodes are 6 digits
        if (!preg_match('/^\d{6}$/', (string) $payload['billing_pincode'])) {
            throw new \Exception("Billing pincode is invalid: {$payload['billing_pincode']} (must be 6 digits)");
        }
        if (!preg_match('/^\d{6}$/', (string) $payload['shipping_pincode'])) {
            throw new \Exception("Shipping pincode is invalid: {$payload['shipping_pincode']} (must be 6 digits)");
        }
    }

    /**
     * Make an authenticated HTTP request to the Shiprocket API.
     *
     * Handles token refresh on 401 and retries once.
     *
     * @param string     $method  HTTP method (GET, POST, PUT, etc.)
     * @param string     $endpoint  API endpoint path (e.g., '/orders/create/adhoc')
     * @param array|null $body  Request body for POST/PUT
     * @param array|null $queryParams  Query parameters for GET
     * @return array  Decoded JSON response
     * @throws \Exception
     */
    /**
     * Validate that the pickup location exists in the Shiprocket account.
     * This is the #1 cause of the misleading 'Please add billing/shipping address' error.
     *
     * @param string $locationName
     * @throws \Exception
     */
    private function validatePickupLocation(string $locationName): void
    {
        try {
            $locations = $this->getPickupLocations();
        } catch (\Exception $e) {
            Log::channel('shiprocket')->warning('Could not validate pickup location', [
                'error' => $e->getMessage(),
            ]);
            return; // Don't block if the check itself fails
        }

        if (empty($locations)) {
            throw new \Exception(
                'No pickup locations found in your Shiprocket account. ' .
                'Please add a pickup/warehouse address at https://app.shiprocket.in/settings/pickup-address ' .
                'before creating shipments.'
            );
        }

        $locationNames = array_map(function ($loc) {
            return $loc['pickup_location'] ?? '';
        }, $locations);

        if (!in_array($locationName, $locationNames)) {
            $available = implode(', ', array_map(fn($n) => '"' . $n . '"', $locationNames));
            throw new \Exception(
                "Pickup location \"{$locationName}\" not found in your Shiprocket account. " .
                "Available locations: {$available}. " .
                "Update your .env SHIPROCKET_DEFAULT_PICKUP_LOCATION or add this location in Shiprocket dashboard."
            );
        }
    }

    /**
     * Turn a failed Shiprocket response into a single human-readable sentence.
     *
     * Shiprocket returns errors in a few shapes:
     *   - { "message": "..." }
     *   - { "errors": { "field": ["msg", ...] } }
     *   - { "field": ["msg", ...] }            (e.g. /addpickup validation)
     * This flattens any of them so the UI shows the actual reason instead of raw JSON.
     *
     * @param \Illuminate\Http\Client\Response $response
     * @return string
     */
    private function extractApiErrorMessage($response): string
    {
        $body = $response->json();

        if (is_array($body)) {
            if (!empty($body['message']) && is_string($body['message'])) {
                return $body['message'];
            }

            // Unwrap a Laravel-style "errors" envelope if present.
            if (!empty($body['errors']) && is_array($body['errors'])) {
                $body = $body['errors'];
            }

            $messages = [];
            foreach ($body as $value) {
                if (is_array($value)) {
                    foreach ($value as $line) {
                        if (is_string($line) && trim($line) !== '') {
                            $messages[] = trim($line);
                        }
                    }
                } elseif (is_string($value) && trim($value) !== '') {
                    $messages[] = trim($value);
                }
            }

            if (!empty($messages)) {
                return implode(' ', array_unique($messages));
            }
        }

        $raw = trim((string) $response->body());
        return $raw !== '' ? $raw : 'Request failed';
    }

    private function makeRequest(string $method, string $endpoint, ?array $body = null, ?array $queryParams = null): array
    {
        $token = $this->getToken();
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        $attempt = 0;
        $maxAttempts = 2;

        while ($attempt < $maxAttempts) {
            $attempt++;

            $request = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ])->timeout(30);

            if ($method === 'GET') {
                $response = $request->get($url, $queryParams ?? []);
            } elseif ($method === 'POST') {
                $response = $request->post($url, $body ?? []);
            } elseif ($method === 'PUT') {
                $response = $request->put($url, $body ?? []);
            } elseif ($method === 'PATCH') {
                $response = $request->patch($url, $body ?? []);
            } elseif ($method === 'DELETE') {
                $response = $request->delete($url, $body ?? []);
            } else {
                throw new \Exception("Unsupported HTTP method: {$method}");
            }

            // Handle token expiration - refresh and retry once
            if ($response->status() === 401 && $attempt < $maxAttempts) {
                Log::channel('shiprocket')->warning('Token expired, refreshing and retrying', [
                    'endpoint' => $endpoint,
                ]);
                $this->clearToken();
                $token = $this->getToken();
                continue;
            }

            if ($response->failed()) {
                $errorMessage = $this->extractApiErrorMessage($response);

                Log::channel('shiprocket')->error('API request failed', [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'error' => $errorMessage,
                    'body' => $response->body(),
                    'attempt' => $attempt,
                ]);

                // Clean, human-readable message for the UI toast; full context stays in the log.
                throw new \Exception("Shiprocket [{$response->status()}]: {$errorMessage}");
            }

            return $response->json() ?? [];
        }

        throw new \Exception("Shiprocket API request failed after {$maxAttempts} attempts");
    }
}
