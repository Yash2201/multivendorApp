{{-- Include this partial in vendor order-details.blade.php --}}
{{-- Usage: @include('vendor-views.shiprocket.partials.create-shipment-form', ['order' => $order]) --}}

@php($existingShipment = $order->shiprocketShipment)

<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 d-flex align-items-center gap-2">
            <i class="fi fi-sr-truck-moving text-primary"></i>
            {{ translate('Shiprocket_Shipping') }}
        </h5>
        @if($existingShipment)
            <?php
                $colors = ['pending'=>'warning','order_created'=>'info','awb_assigned'=>'info','pickup_scheduled'=>'primary','in_transit'=>'primary','out_for_delivery'=>'info','delivered'=>'success','rto_initiated'=>'danger','cancelled'=>'danger'];
            ?>
            <span class="badge badge-soft-{{ $colors[$existingShipment->shipment_status] ?? 'secondary' }} text-capitalize">
                {{ str_replace('_', ' ', $existingShipment->shipment_status) }}
            </span>
        @endif
    </div>
    <div class="card-body">
        @if($existingShipment && $existingShipment->isActive())
            {{-- Show existing shipment info --}}
            <div class="d-grid gap-2 fs-12">
                <div class="d-flex gap-3"><span class="w-140 flex-shrink-0">{{ translate('AWB_Code') }}</span><span>:</span><strong>{{ $existingShipment->awb_code ?? translate('Pending') }}</strong></div>
                <div class="d-flex gap-3"><span class="w-140 flex-shrink-0">{{ translate('Courier') }}</span><span>:</span><strong>{{ $existingShipment->courier_name ?? '-' }}</strong></div>
                <div class="d-flex gap-3"><span class="w-140 flex-shrink-0">{{ translate('Est_Delivery') }}</span><span>:</span><strong>{{ $existingShipment->estimated_delivery_date ? \Carbon\Carbon::parse($existingShipment->estimated_delivery_date)->format('d M, Y') : '-' }}</strong></div>
                @if($existingShipment->tracking_url)
                    <a href="{{ $existingShipment->tracking_url }}" target="_blank" class="btn btn-sm btn-outline-primary w-max-content mt-2">
                        <i class="fi fi-sr-marker"></i> {{ translate('Track_Shipment') }}
                    </a>
                @endif
                @if($existingShipment->label_url)
                    <a href="{{ $existingShipment->label_url }}" target="_blank" class="btn btn-sm btn-outline-success w-max-content">
                        <i class="fi fi-sr-label"></i> {{ translate('Download_Label') }}
                    </a>
                @endif
            </div>
        @elseif(in_array($order->order_status, ['confirmed', 'processing']))
            {{-- Show shipment creation form --}}
            @php($route = request()->is('admin/*') ? route('admin.business-settings.shiprocket.create-shipment') : route('vendor.business-settings.shiprocket.create-shipment'))
            <form action="{{ $route }}" method="POST" id="shiprocket-create-form-{{ $order->id }}">
                @csrf
                <input type="hidden" name="order_id" value="{{ $order->id }}">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fs-12">{{ translate('Package_Weight') }} (kg) <span class="text-danger">*</span></label>
                        <input type="number" name="weight" step="0.01" min="0.1" value="0.5" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fs-12">{{ translate('Length') }} (cm) <span class="text-danger">*</span></label>
                        <input type="number" name="length" step="0.1" min="1" value="10" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fs-12">{{ translate('Breadth') }} (cm) <span class="text-danger">*</span></label>
                        <input type="number" name="breadth" step="0.1" min="1" value="10" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fs-12">{{ translate('Height') }} (cm) <span class="text-danger">*</span></label>
                        <input type="number" name="height" step="0.1" min="1" value="10" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fs-12">{{ translate('Pickup_Pincode') }}</label>
                        <input type="text" name="pickup_pincode" class="form-control form-control-sm" placeholder="{{ translate('Your_warehouse_pincode') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fs-12">{{ translate('Pickup_Location') }}</label>
                        <input type="text" name="pickup_location" class="form-control form-control-sm" placeholder="{{ translate('Primary') }}" value="{{ config('shiprocket.default_pickup_location') }}">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="button" class="btn btn--primary btn-sm form-alert" data-id="shiprocket-create-form-{{ $order->id }}" data-message="{{ translate('Are_you_sure_you_want_to_create_shipment_via_Shiprocket?') }}">
                        <i class="fi fi-sr-truck-moving"></i> {{ translate('Ship_via_Shiprocket') }}
                    </button>
                </div>
            </form>
        @else
            <p class="text-muted fs-12 mb-0">
                {{ translate('Shiprocket_shipping_is_available_for_confirmed_or_processing_orders') }}.
            </p>
        @endif
    </div>
</div>
