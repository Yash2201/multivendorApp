@extends('layouts.admin.app')
@section('title', translate('Shiprocket_Shipments'))

@section('content')
<div class="content container-fluid">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
        <h2 class="fs-20 mb-0 d-flex align-items-center gap-2">
            <i class="fi fi-sr-truck-moving"></i>
            <span>{{ translate('Shiprocket_Shipments') }}</span>
            <span class="badge badge-soft-dark fs-12 ml-2">{{ $statusCounts['all'] }}</span>
        </h2>
        <form action="{{ route('admin.business-settings.shiprocket.sync-all') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn--primary btn-sm" onclick="return confirm('{{ translate('Sync_all?') }}')">
                <i class="fi fi-sr-refresh"></i> {{ translate('Sync_All') }}
            </button>
        </form>
    </div>

    <div class="card mb-3">
        <div class="card-body p-2">
            <div class="d-flex flex-wrap gap-2">
                @foreach(['all' => '', 'pending' => 'pending', 'in_transit' => 'in_transit', 'delivered' => 'delivered', 'cancelled' => 'cancelled', 'rto_initiated' => 'rto_initiated'] as $label => $val)
                    <a href="{{ route('admin.business-settings.shiprocket.index', $val ? ['status' => $val] : []) }}"
                       class="btn btn-sm {{ $status === ($val ?: null) && ($val || !$status) ? 'btn--primary' : 'btn-outline-primary' }}">
                        {{ translate(ucfirst(str_replace('_', ' ', $label))) }} ({{ $statusCounts[$label] ?? 0 }})
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form action="{{ route('admin.business-settings.shiprocket.index') }}" method="GET" class="row g-2">
                <div class="col-md-9">
                    <input type="text" name="searchValue" value="{{ $searchValue }}" class="form-control" placeholder="{{ translate('Search_by_AWB_Order_ID_Courier') }}">
                </div>
                @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn--primary flex-grow-1">{{ translate('Search') }}</button>
                    <a href="{{ route('admin.business-settings.shiprocket.index') }}" class="btn btn-outline-secondary"><i class="fi fi-sr-refresh"></i></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table w-100 fs-12">
                    <thead class="thead-light thead-50">
                        <tr>
                            <th>{{ translate('SL') }}</th>
                            <th>{{ translate('Order') }}</th>
                            <th>{{ translate('Vendor') }}</th>
                            <th>{{ translate('AWB') }}</th>
                            <th>{{ translate('Courier') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Created') }}</th>
                            <th class="text-center">{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($shipments as $key => $shipment)
                            @php
                                $colors = ['pending'=>'warning','order_created'=>'info','awb_assigned'=>'info','pickup_scheduled'=>'primary','picked_up'=>'primary','in_transit'=>'primary','out_for_delivery'=>'info','delivered'=>'success','rto_initiated'=>'danger','rto_delivered'=>'danger','cancelled'=>'danger','failed'=>'danger'];
                            @endphp
                            <tr>
                                <td>{{ $shipments->firstItem() + $key }}</td>
                                <td><a href="{{ route('admin.orders.details', [$shipment->order_id]) }}" class="text-primary fw-semibold">#{{ $shipment->order_id }}</a></td>
                                <td>{{ $shipment->seller?->shop?->name ? Str::limit($shipment->seller->shop->name, 20) : translate('In-House') }}</td>
                                <td>{!! $shipment->awb_code ? '<span class="badge badge-soft-info">'.$shipment->awb_code.'</span>' : '-' !!}</td>
                                <td>{{ $shipment->courier_name ?? '-' }}</td>
                                <td><span class="badge badge-soft-{{ $colors[$shipment->shipment_status] ?? 'secondary' }} text-capitalize">{{ str_replace('_',' ',$shipment->shipment_status) }}</span></td>
                                <td>{{ $shipment->created_at->format('d M, Y') }}</td>
                                <td class="text-center">
                                    <a href="{{ route('admin.business-settings.shiprocket.details', $shipment->id) }}" class="btn btn-outline-primary btn-sm"><i class="fi fi-sr-eye"></i></a>
                                    @if($shipment->isCancellable())
                                        <form action="{{ route('admin.business-settings.shiprocket.cancel') }}" method="POST" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="shipment_id" value="{{ $shipment->id }}">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('{{ translate('Cancel?') }}')"><i class="fi fi-sr-cross-small"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center py-4 text-muted">{{ translate('No_shipments_found') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-end p-3">{{ $shipments->appends(request()->query())->links() }}</div>
        </div>
    </div>
</div>
@endsection
