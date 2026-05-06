@extends('layouts.admin.app')
@section('title', translate('Shipment_Details'))

@section('content')
<div class="content container-fluid">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
        <h2 class="fs-20 mb-0 d-flex align-items-center gap-2">
            <i class="fi fi-sr-truck-moving"></i>
            <span>{{ translate('Shipment_Details') }} — #{{ $shipment->shiprocket_order_id }}</span>
        </h2>
        <a href="{{ route('admin.business-settings.shiprocket.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="fi fi-sr-arrow-left"></i> {{ translate('Back') }}
        </a>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">{{ translate('Shipment_Info') }}</h5></div>
                <div class="card-body fs-12">
                    <div class="d-grid gap-2">
                        <div class="d-flex justify-content-between"><span>{{ translate('Order_ID') }}</span><a href="{{ route('admin.orders.details', $shipment->order_id) }}" class="text-primary fw-bold">#{{ $shipment->order_id }}</a></div>
                        <div class="d-flex justify-content-between"><span>{{ translate('Shiprocket_Order_ID') }}</span><strong>{{ $shipment->shiprocket_order_id ?? '-' }}</strong></div>
                        <div class="d-flex justify-content-between"><span>{{ translate('Shipment_ID') }}</span><strong>{{ $shipment->shiprocket_shipment_id ?? '-' }}</strong></div>
                        <div class="d-flex justify-content-between"><span>{{ translate('AWB_Code') }}</span><strong>{{ $shipment->awb_code ?? '-' }}</strong></div>
                        <div class="d-flex justify-content-between"><span>{{ translate('Courier') }}</span><strong>{{ $shipment->courier_name ?? '-' }}</strong></div>
                        @php
                            $colors = ['pending'=>'warning','order_created'=>'info','awb_assigned'=>'info','pickup_scheduled'=>'primary','in_transit'=>'primary','out_for_delivery'=>'info','delivered'=>'success','rto_initiated'=>'danger','cancelled'=>'danger','failed'=>'danger'];
                        @endphp
                        <div class="d-flex justify-content-between"><span>{{ translate('Status') }}</span><span class="badge badge-soft-{{ $colors[$shipment->shipment_status] ?? 'secondary' }} text-capitalize">{{ str_replace('_',' ',$shipment->shipment_status) }}</span></div>
                        <div class="d-flex justify-content-between"><span>{{ translate('Est_Delivery') }}</span><strong>{{ $shipment->estimated_delivery_date ? \Carbon\Carbon::parse($shipment->estimated_delivery_date)->format('d M, Y') : '-' }}</strong></div>
                        <div class="d-flex justify-content-between"><span>{{ translate('Delivered_Date') }}</span><strong>{{ $shipment->delivered_date ? \Carbon\Carbon::parse($shipment->delivered_date)->format('d M, Y H:i') : '-' }}</strong></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">{{ translate('Package_&_Cost') }}</h5></div>
                <div class="card-body fs-12">
                    <div class="d-grid gap-2">
                        <div class="d-flex justify-content-between"><span>{{ translate('Weight') }}</span><strong>{{ $shipment->package_weight ?? '-' }} kg</strong></div>
                        <div class="d-flex justify-content-between"><span>{{ translate('Dimensions') }}</span><strong>{{ $shipment->package_length }}×{{ $shipment->package_breadth }}×{{ $shipment->package_height }} cm</strong></div>
                        <div class="d-flex justify-content-between"><span>{{ translate('Pickup_Location') }}</span><strong>{{ $shipment->pickup_location ?? '-' }}</strong></div>
                        <div class="d-flex justify-content-between"><span>{{ translate('Shipping_Charge') }}</span><strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $shipment->shipping_charge)) }}</strong></div>
                        <div class="d-flex justify-content-between"><span>{{ translate('COD_Charge') }}</span><strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $shipment->cod_charge)) }}</strong></div>
                        <div class="d-flex justify-content-between"><span>{{ translate('Vendor') }}</span><strong>{{ $shipment->seller?->shop?->name ?? translate('In-House') }}</strong></div>
                    </div>
                    <hr>
                    <div class="d-flex flex-wrap gap-2">
                        @if($shipment->label_url)
                            <a href="{{ $shipment->label_url }}" target="_blank" class="btn btn-sm btn-outline-success"><i class="fi fi-sr-label"></i> {{ translate('Label') }}</a>
                        @endif
                        @if($shipment->manifest_url)
                            <a href="{{ $shipment->manifest_url }}" target="_blank" class="btn btn-sm btn-outline-info"><i class="fi fi-sr-document"></i> {{ translate('Manifest') }}</a>
                        @endif
                        @if($shipment->tracking_url)
                            <a href="{{ $shipment->tracking_url }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fi fi-sr-link-alt"></i> {{ translate('Track') }}</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if($shipment->last_error)
        <div class="col-12">
            <div class="alert alert-soft-danger fs-12">
                <strong>{{ translate('Last_Error') }}:</strong> {{ $shipment->last_error }}
                <span class="ms-2">({{ translate('Retries') }}: {{ $shipment->retry_count }})</span>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
