<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ShiprocketShipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handles incoming webhook notifications from Shiprocket.
 *
 * Configure in Shiprocket Dashboard:
 *   Settings > API > Webhooks > Add webhook URL
 *   URL: https://yourdomain.com/api/logistics/webhook
 *   Token: same value as SHIPROCKET_WEBHOOK_TOKEN in .env
 */
class ShiprocketWebhookController extends Controller
{
    /**
     * Handle incoming Shiprocket webhook.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        // Validate webhook token
        $webhookToken = config('shiprocket.webhook_token');
        $receivedToken = $request->header('X-API-TOKEN') ?? $request->input('token');

        if (!empty($webhookToken) && $receivedToken !== $webhookToken) {
            Log::channel('shiprocket')->warning('Webhook received with invalid token', [
                'received_token' => $receivedToken,
                'ip' => $request->ip(),
            ]);
            return response()->json(['status' => 'unauthorized'], 401);
        }

        $payload = $request->all();

        Log::channel('shiprocket')->info('Webhook received', [
            'payload' => $payload,
        ]);

        try {
            $awbCode = $payload['awb'] ?? null;
            $shipmentId = $payload['shipment_id'] ?? null;
            $orderId = $payload['order_id'] ?? null;
            $currentStatus = $payload['current_status'] ?? null;
            $currentStatusId = $payload['current_status_id'] ?? null;
            $etd = $payload['etd'] ?? null;
            $scans = $payload['scans'] ?? [];

            // Find the shipment record
            $shipment = null;
            if ($awbCode) {
                $shipment = ShiprocketShipment::where('awb_code', $awbCode)->first();
            }
            if (!$shipment && $shipmentId) {
                $shipment = ShiprocketShipment::where('shiprocket_shipment_id', $shipmentId)->first();
            }
            if (!$shipment && $orderId) {
                $shipment = ShiprocketShipment::where('shiprocket_order_id', $orderId)->first();
            }

            if (!$shipment) {
                Log::channel('shiprocket')->warning('Webhook: shipment not found', [
                    'awb_code' => $awbCode,
                    'shipment_id' => $shipmentId,
                    'order_id' => $orderId,
                ]);
                return response()->json(['status' => 'not_found'], 404);
            }

            // Map Shiprocket status to internal status
            $newStatus = $this->mapWebhookStatus($currentStatus, $currentStatusId);

            $updateData = [
                'shipment_status' => $newStatus,
                'last_error' => null,
            ];

            if ($currentStatusId !== null) {
                $updateData['shiprocket_status_code'] = $currentStatusId;
            }

            if ($etd) {
                $updateData['estimated_delivery_date'] = $etd;
            }

            // Check if delivered
            $deliveredCodes = config('shiprocket.delivered_status_codes', [7]);
            if (in_array($currentStatusId, $deliveredCodes) || strtolower($currentStatus ?? '') === 'delivered') {
                $updateData['shipment_status'] = ShiprocketShipment::STATUS_DELIVERED;
                $updateData['delivered_date'] = now();

                // Update the order status to delivered
                $order = Order::find($shipment->order_id);
                if ($order && $order->order_status !== 'delivered') {
                    $order->update([
                        'order_status' => 'delivered',
                        'payment_status' => 'paid',
                    ]);
                }
            }

            // Map order status from config
            if ($currentStatusId !== null) {
                $orderStatusMapping = config('shiprocket.status_mapping', []);
                $mappedOrderStatus = $orderStatusMapping[$currentStatusId] ?? null;

                if ($mappedOrderStatus) {
                    $order = Order::find($shipment->order_id);
                    if ($order && !in_array($order->order_status, ['delivered', 'returned', 'canceled'])) {
                        $order->update(['order_status' => $mappedOrderStatus]);
                    }
                }
            }

            $shipment->update($updateData);

            Log::channel('shiprocket')->info('Webhook processed successfully', [
                'shipment_id' => $shipment->id,
                'awb_code' => $awbCode,
                'new_status' => $newStatus,
                'shiprocket_status_code' => $currentStatusId,
            ]);

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::channel('shiprocket')->error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Map webhook status string/code to internal shipment status.
     */
    private function mapWebhookStatus(?string $statusText, ?int $statusCode): string
    {
        // First try by status code
        if ($statusCode !== null) {
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
                return $statusCode == 10
                    ? ShiprocketShipment::STATUS_RTO_DELIVERED
                    : ShiprocketShipment::STATUS_RTO_INITIATED;
            }

            if (in_array($statusCode, [17, 57])) {
                return ShiprocketShipment::STATUS_OUT_FOR_DELIVERY;
            }

            if (in_array($statusCode, [6, 18, 38, 42, 48, 51, 52, 53, 54, 55, 56])) {
                return ShiprocketShipment::STATUS_IN_TRANSIT;
            }
        }

        // Fallback to text matching
        $statusLower = strtolower($statusText ?? '');

        return match (true) {
            str_contains($statusLower, 'delivered') => ShiprocketShipment::STATUS_DELIVERED,
            str_contains($statusLower, 'out for delivery') => ShiprocketShipment::STATUS_OUT_FOR_DELIVERY,
            str_contains($statusLower, 'in transit') || str_contains($statusLower, 'shipped') => ShiprocketShipment::STATUS_IN_TRANSIT,
            str_contains($statusLower, 'picked up') => ShiprocketShipment::STATUS_PICKED_UP,
            str_contains($statusLower, 'pickup scheduled') => ShiprocketShipment::STATUS_PICKUP_SCHEDULED,
            str_contains($statusLower, 'cancel') => ShiprocketShipment::STATUS_CANCELLED,
            str_contains($statusLower, 'rto') => ShiprocketShipment::STATUS_RTO_INITIATED,
            default => ShiprocketShipment::STATUS_IN_TRANSIT,
        };
    }
}
