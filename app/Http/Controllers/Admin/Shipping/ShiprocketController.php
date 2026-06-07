<?php

namespace App\Http\Controllers\Admin\Shipping;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Shiprocket\PickupAddressRequest;
use App\Models\Order;
use App\Models\Seller;
use App\Models\ShiprocketPickupAddress;
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
        $searchValue = $request['searchValue'] ?? null;
        $status = $request['status'] ?? null;

        $query = ShiprocketShipment::with(['order', 'seller.shop'])
            ->orderBy('id', 'desc');

        if ($status) {
            $query->where('shipment_status', $status);
        }

        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('awb_code', 'like', "%{$searchValue}%")
                    ->orWhere('shiprocket_order_id', 'like', "%{$searchValue}%")
                    ->orWhere('courier_name', 'like', "%{$searchValue}%")
                    ->orWhereHas('order', function ($oq) use ($searchValue) {
                        $oq->where('id', $searchValue);
                    });
            });
        }

        $shipments = $query->paginate(getWebConfig(name: 'pagination_limit'));

        $statusCounts = [
            'all' => ShiprocketShipment::count(),
            'pending' => ShiprocketShipment::where('shipment_status', 'pending')->count(),
            'in_transit' => ShiprocketShipment::where('shipment_status', 'in_transit')->count(),
            'delivered' => ShiprocketShipment::where('shipment_status', 'delivered')->count(),
            'cancelled' => ShiprocketShipment::where('shipment_status', 'cancelled')->count(),
            'rto_initiated' => ShiprocketShipment::where('shipment_status', 'rto_initiated')->count(),
        ];

        return view('admin-views.shiprocket.index', compact('shipments', 'searchValue', 'status', 'statusCounts'));
    }

    /**
     * Track a shipment and return tracking info.
     *
     * @param string|int $id  ShiprocketShipment ID
     * @return JsonResponse
     */
    public function trackShipment(string|int $id): JsonResponse
    {
        $shipment = ShiprocketShipment::find($id);

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
     * Admin can cancel any shipment.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function cancelShipment(Request $request): RedirectResponse
    {
        $request->validate([
            'shipment_id' => 'required|exists:shiprocket_shipments,id',
        ]);

        $shipment = ShiprocketShipment::find($request->shipment_id);

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
     * Create shipment for admin/in-house orders.
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


        $order = Order::find($request->order_id);

        if (!$order) {
            ToastMagic::error(translate('order_not_found'));
            return redirect()->back();
        }

        if (!in_array($order->order_status, ['confirmed', 'processing'])) {
            ToastMagic::error(translate('order_must_be_confirmed_or_processing_to_create_shipment'));
            return redirect()->back();
        }

        // Safe check using optional chaining
        if (empty($order->shipping_address_data) || empty($order->shipping_address_data->address)) {
            ToastMagic::error(translate('shipping_address_not_found_please_add_address'));
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
            // Selected saved pickup address nickname; falls back to the .env default.
            $pickupLocation = $request->pickup_location ?: null;

            $shipment = $this->shiprocketService->createFullShipment(
                order: $order,
                packageInfo: $packageInfo,
                courierId: $courierId,
                pickupLocation: $pickupLocation
            );

            ToastMagic::success(translate('shipment_created_successfully') . ' — AWB: ' . ($shipment->awb_code ?? 'Pending'));
        } catch (\Exception $e) {
            Log::channel('shiprocket')->error('Admin shipment creation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            ToastMagic::error(translate('shipment_creation_failed') . ': ' . $e->getMessage());
        }

        return redirect()->back();
    }

    /**
     * Manually sync all active shipments.
     *
     * @return RedirectResponse
     */
    public function syncAll(): RedirectResponse
    {
        try {
            $activeShipments = ShiprocketShipment::active()->get();
            $synced = 0;

            foreach ($activeShipments as $shipment) {
                $this->shiprocketService->syncShipmentStatus($shipment);
                $synced++;
            }

            ToastMagic::success(translate('synced') . " {$synced} " . translate('shipments_successfully'));
        } catch (\Exception $e) {
            ToastMagic::error(translate('sync_failed') . ': ' . $e->getMessage());
        }

        return redirect()->back();
    }

    /**
     * View shipment details (admin).
     *
     * @param string|int $id
     * @return View|RedirectResponse
     */
    public function getView(string|int $id): View|RedirectResponse
    {
        $shipment = ShiprocketShipment::with(['order.details', 'order.customer', 'seller.shop'])->find($id);

        if (!$shipment) {
            ToastMagic::error(translate('shipment_not_found'));
            return redirect()->back();
        }

        return view('admin-views.shiprocket.details', compact('shipment'));
    }

    // -------------------------------------------------------------------------
    // Pickup Addresses (admin: all vendors + in-house)
    // -------------------------------------------------------------------------

    /**
     * Standalone management page: every vendor's (and in-house) pickup addresses,
     * grouped by shop and filterable by vendor or free-text search.
     *
     * @param Request $request
     * @return View
     */
    public function pickupAddressesView(Request $request): View
    {
        $search = $request['searchValue'] ?? null;
        $sellerFilter = $request['seller_id'] ?? null;

        $query = ShiprocketPickupAddress::with('seller.shop')
            ->orderByRaw('seller_id IS NULL')   // vendors first, in-house last
            ->orderBy('seller_id')              // keep a shop's addresses adjacent (grouped)
            ->orderByDesc('is_default')
            ->orderByDesc('id');

        if ($sellerFilter !== null && $sellerFilter !== '') {
            $query->forSeller($this->resolveSellerId($sellerFilter));
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('pin_code', 'like', "%{$search}%")
                    ->orWhere('pickup_nickname', 'like', "%{$search}%")
                    ->orWhereHas('seller.shop', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $addresses = $query->paginate(getWebConfig(name: 'pagination_limit'))->appends($request->all());
        $sellers = Seller::approved()->with('shop')->orderBy('f_name')->get();

        return view('admin-views.shiprocket.pickup-addresses', compact('addresses', 'sellers', 'search', 'sellerFilter'));
    }

    /**
     * List pickup addresses (JSON, for the order-details picker). When a
     * `seller_id` is supplied the list is scoped to that owner — a numeric id for a
     * vendor, or an empty value for in-house. With no `seller_id` the admin sees all.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function pickupAddresses(Request $request): JsonResponse
    {
        $query = ShiprocketPickupAddress::with('seller.shop')
            ->orderByDesc('is_default')
            ->orderByDesc('id');

        if ($request->has('seller_id')) {
            $query->forSeller($this->resolveSellerId($request->seller_id));
        }

        return response()->json([
            'status' => 1,
            'addresses' => $query->get(),
            'prefill' => ['country' => 'India'],
        ]);
    }

    /**
     * Add a pickup address. Attributed to a vendor when `seller_id` is provided
     * (e.g. shipping that vendor's order), otherwise stored as in-house.
     *
     * @param PickupAddressRequest $request
     * @return JsonResponse
     */
    public function storePickupAddress(PickupAddressRequest $request): JsonResponse
    {
        $sellerId = $this->resolveSellerId($request->seller_id);

        try {
            $address = $this->shiprocketService->createPickupAddress($request->validated(), $sellerId);

            return response()->json([
                'status' => 1,
                'message' => translate('pickup_address_added_successfully'),
                'address' => $address,
            ]);
        } catch (\Exception $e) {
            Log::channel('shiprocket')->error('Admin pickup address creation failed', [
                'seller_id' => $sellerId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['status' => 0, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Mark any pickup address as default (within its own owner scope).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function setDefaultPickupAddress(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|integer']);

        $address = ShiprocketPickupAddress::find($request->id);

        if (!$address) {
            return response()->json(['status' => 0, 'message' => translate('pickup_address_not_found')]);
        }

        $address->makeDefault();

        return response()->json(['status' => 1, 'message' => translate('default_pickup_address_updated')]);
    }

    /**
     * Delete any pickup address.
     *
     * @param string|int $id
     * @return JsonResponse
     */
    public function deletePickupAddress(string|int $id): JsonResponse
    {
        $address = ShiprocketPickupAddress::find($id);

        if (!$address) {
            return response()->json(['status' => 0, 'message' => translate('pickup_address_not_found')]);
        }

        $sellerId = $address->seller_id;
        $wasDefault = $address->is_default;
        $address->delete();

        // Keep a default within the same owner scope after removing the default one.
        if ($wasDefault) {
            ShiprocketPickupAddress::forSeller($sellerId)->orderByDesc('id')->first()?->makeDefault();
        }

        return response()->json(['status' => 1, 'message' => translate('pickup_address_deleted')]);
    }

    /**
     * Normalise an incoming seller_id into an int owner id or null (in-house).
     *
     * @param mixed $value
     * @return int|null
     */
    private function resolveSellerId(mixed $value): ?int
    {
        return ($value !== null && $value !== '' && (int) $value > 0) ? (int) $value : null;
    }
}
