<?php

namespace App\Http\Controllers\Vendor\Shipping;

use App\Http\Controllers\BaseController;
use App\Models\Order;
use App\Models\ShiprocketShipment;
use App\Services\ShiprocketService;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class ShiprocketController extends BaseController
{
    public function __construct(
        private readonly ShiprocketService $shiprocketService,
    ) {
    }

    /**
     * @param Request|null $request
     * @param string|null $type
     * @return View|Collection|LengthAwarePaginator|callable|RedirectResponse|null
     */
    public function index(?Request $request, ?string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $sellerId = auth('seller')->id();
        $shipments = ShiprocketShipment::forSeller($sellerId)
            ->with('order')
            ->orderBy('id', 'desc')
            ->paginate(getWebConfig(name: 'pagination_limit'));

        return view('vendor-views.shiprocket.index', compact('shipments'));
    }

    /**
     * Check courier serviceability for an order.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkServiceability(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'weight' => 'required|numeric|min:0.1',
        ]);

        $sellerId = auth('seller')->id();
        $order = Order::where('id', $request->order_id)
            ->where('seller_id', $sellerId)
            ->where('seller_is', 'seller')
            ->with('shippingAddress')
            ->first();

        if (!$order) {
            return response()->json(['status' => 0, 'message' => translate('order_not_found')]);
        }

        $shippingAddress = $order->shipping_address_data
            ? json_decode(json_encode($order->shipping_address_data), true)
            : optional($order->shippingAddress)->toArray();

        $deliveryPincode = $shippingAddress['zip'] ?? '';

        if (empty($deliveryPincode)) {
            return response()->json(['status' => 0, 'message' => translate('delivery_pincode_not_found')]);
        }

        try {
            // Use a default pickup pincode — in production, get this from vendor's pickup location
            $pickupPincode = $request->pickup_pincode ?? '';

            if (empty($pickupPincode)) {
                return response()->json([
                    'status' => 0,
                    'message' => translate('please_provide_pickup_pincode'),
                ]);
            }

            $codAmount = $order->payment_method === 'cash_on_delivery' ? $order->order_amount : 0;

            $couriers = $this->shiprocketService->checkCourierServiceability(
                pickupPincode: $pickupPincode,
                deliveryPincode: $deliveryPincode,
                weight: (float) $request->weight,
                codAmount: $codAmount
            );

            return response()->json([
                'status' => 1,
                'couriers' => $couriers,
                'delivery_pincode' => $deliveryPincode,
                'payment_mode' => $codAmount > 0 ? 'COD' : 'Prepaid',
            ]);
        } catch (\Exception $e) {
            Log::channel('shiprocket')->error('Serviceability check failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['status' => 0, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Create a full shipment via Shiprocket for an order.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function createShipment(Request $request): RedirectResponse
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'weight' => 'required|numeric|min:0.1',
            'length' => 'required|numeric|min:1',
            'breadth' => 'required|numeric|min:1',
            'height' => 'required|numeric|min:1',
        ]);

        $sellerId = auth('seller')->id();
        $order = Order::where('id', $request->order_id)
            ->where('seller_id', $sellerId)
            ->where('seller_is', 'seller')
            ->first();

        if (!$order) {
            ToastMagic::error(translate('order_not_found'));
            return redirect()->back();
        }

        if (!in_array($order->order_status, ['confirmed', 'processing'])) {
            ToastMagic::error(translate('order_must_be_confirmed_or_processing_to_create_shipment'));
            return redirect()->back();
        }

        try {
            $packageInfo = [
                'weight' => $request->weight,
                'length' => $request->length,
                'breadth' => $request->breadth,
                'height' => $request->height,
            ];

            $courierId = $request->courier_id ? (int) $request->courier_id : null;
            $pickupLocation = $request->pickup_location ?: null;

            $shipment = $this->shiprocketService->createFullShipment(
                order: $order,
                packageInfo: $packageInfo,
                courierId: $courierId,
                pickupLocation: $pickupLocation
            );

            ToastMagic::success(translate('shipment_created_successfully') . ' — AWB: ' . ($shipment->awb_code ?? 'Pending'));
        } catch (\Exception $e) {
            Log::channel('shiprocket')->error('Shipment creation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            ToastMagic::error(translate('shipment_creation_failed') . ': ' . $e->getMessage());
        }

        return redirect()->back();
    }

    /**
     * Track a shipment and return tracking info.
     *
     * @param string|int $id  ShiprocketShipment ID
     * @return JsonResponse
     */
    public function trackShipment(string|int $id): JsonResponse
    {
        $sellerId = auth('seller')->id();
        $shipment = ShiprocketShipment::where('id', $id)
            ->where('seller_id', $sellerId)
            ->first();

        if (!$shipment) {
            return response()->json(['status' => 0, 'message' => translate('shipment_not_found')]);
        }

        try {
            $updatedShipment = $this->shiprocketService->syncShipmentStatus($shipment);

            $trackingData = [];
            if (!empty($shipment->awb_code)) {
                $trackingData = $this->shiprocketService->trackByAWB($shipment->awb_code);
            }

            return response()->json([
                'status' => 1,
                'shipment' => $updatedShipment,
                'tracking' => $trackingData,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Cancel a Shiprocket shipment.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function cancelShipment(Request $request): RedirectResponse
    {
        $request->validate([
            'shipment_id' => 'required|exists:shiprocket_shipments,id',
        ]);

        $sellerId = auth('seller')->id();
        $shipment = ShiprocketShipment::where('id', $request->shipment_id)
            ->where('seller_id', $sellerId)
            ->first();

        if (!$shipment) {
            ToastMagic::error(translate('shipment_not_found'));
            return redirect()->back();
        }

        try {
            $this->shiprocketService->cancelFullShipment($shipment);
            ToastMagic::success(translate('shipment_cancelled_successfully'));
        } catch (\Exception $e) {
            ToastMagic::error(translate('cancellation_failed') . ': ' . $e->getMessage());
        }

        return redirect()->back();
    }

    /**
     * Download shipping label PDF.
     *
     * @param string|int $id  ShiprocketShipment ID
     * @return JsonResponse|RedirectResponse
     */
    public function downloadLabel(string|int $id): JsonResponse|RedirectResponse
    {
        $sellerId = auth('seller')->id();
        $shipment = ShiprocketShipment::where('id', $id)
            ->where('seller_id', $sellerId)
            ->first();

        if (!$shipment || empty($shipment->shiprocket_shipment_id)) {
            ToastMagic::error(translate('shipment_not_found'));
            return redirect()->back();
        }

        try {
            if (empty($shipment->label_url)) {
                $labelResponse = $this->shiprocketService->generateLabel([$shipment->shiprocket_shipment_id]);
                $shipment->update(['label_url' => $labelResponse['label_url'] ?? null]);
            }

            if (!empty($shipment->label_url)) {
                return response()->json(['status' => 1, 'url' => $shipment->label_url]);
            }

            return response()->json(['status' => 0, 'message' => translate('label_not_available')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Download manifest PDF.
     *
     * @param string|int $id  ShiprocketShipment ID
     * @return JsonResponse|RedirectResponse
     */
    public function downloadManifest(string|int $id): JsonResponse|RedirectResponse
    {
        $sellerId = auth('seller')->id();
        $shipment = ShiprocketShipment::where('id', $id)
            ->where('seller_id', $sellerId)
            ->first();

        if (!$shipment || empty($shipment->shiprocket_shipment_id)) {
            ToastMagic::error(translate('shipment_not_found'));
            return redirect()->back();
        }

        try {
            if (empty($shipment->manifest_url)) {
                $manifestResponse = $this->shiprocketService->generateManifest([$shipment->shiprocket_shipment_id]);
                $shipment->update(['manifest_url' => $manifestResponse['manifest_url'] ?? null]);
            }

            if (!empty($shipment->manifest_url)) {
                return response()->json(['status' => 1, 'url' => $shipment->manifest_url]);
            }

            return response()->json(['status' => 0, 'message' => translate('manifest_not_available')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()]);
        }
    }
}
