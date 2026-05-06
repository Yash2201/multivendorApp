@extends('layouts.vendor.app')
@section('title', translate('Shiprocket_Shipments'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
            <h2 class="fs-20 mb-0 text-capitalize d-flex align-items-center gap-2">
                <i class="fi fi-sr-truck-moving"></i>
                <span>{{ translate('Shiprocket_Shipments') }}</span>
            </h2>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive datatable-custom">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table w-100 fs-12">
                        <thead class="thead-light thead-50 text-capitalize">
                            <tr>
                                <th>{{ translate('SL') }}</th>
                                <th>{{ translate('Order_ID') }}</th>
                                <th>{{ translate('Shiprocket_Order') }}</th>
                                <th>{{ translate('AWB_Code') }}</th>
                                <th>{{ translate('Courier') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Est_Delivery') }}</th>
                                <th class="text-center">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($shipments as $key => $shipment)
                                <tr>
                                    <td>{{ $shipments->firstItem() + $key }}</td>
                                    <td>
                                        <a href="{{ route('vendor.orders.details', [$shipment->order_id]) }}" class="text-primary fw-semibold">
                                            #{{ $shipment->order_id }}
                                        </a>
                                    </td>
                                    <td>{{ $shipment->shiprocket_order_id ?? '-' }}</td>
                                    <td>
                                        @if($shipment->awb_code)
                                            <span class="badge badge-soft-info">{{ $shipment->awb_code }}</span>
                                        @else
                                            <span class="text-muted">{{ translate('Pending') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $shipment->courier_name ?? '-' }}</td>
                                    <td>
                                        @php
                                            $statusColors = [
                                                'pending' => 'warning',
                                                'order_created' => 'info',
                                                'awb_assigned' => 'info',
                                                'pickup_scheduled' => 'primary',
                                                'picked_up' => 'primary',
                                                'in_transit' => 'primary',
                                                'out_for_delivery' => 'info',
                                                'delivered' => 'success',
                                                'rto_initiated' => 'danger',
                                                'rto_delivered' => 'danger',
                                                'cancelled' => 'danger',
                                                'failed' => 'danger',
                                            ];
                                            $color = $statusColors[$shipment->shipment_status] ?? 'secondary';
                                        @endphp
                                        <span class="badge badge-soft-{{ $color }} text-capitalize">
                                            {{ str_replace('_', ' ', $shipment->shipment_status) }}
                                        </span>
                                    </td>
                                    <td>{{ $shipment->estimated_delivery_date ? \Carbon\Carbon::parse($shipment->estimated_delivery_date)->format('d M, Y') : '-' }}</td>
                                    <td class="text-center">
                                        <div class="d-flex gap-2 justify-content-center">
                                            @if($shipment->awb_code)
                                                <a href="javascript:" class="btn btn-outline-info btn-sm track-shipment-btn"
                                                   data-id="{{ $shipment->id }}"
                                                   data-toggle="tooltip" title="{{ translate('Track') }}">
                                                    <i class="fi fi-sr-marker"></i>
                                                </a>
                                            @endif

                                            @if($shipment->tracking_url)
                                                <a href="{{ $shipment->tracking_url }}" target="_blank"
                                                   class="btn btn-outline-primary btn-sm"
                                                   data-toggle="tooltip" title="{{ translate('External_Tracking') }}">
                                                    <i class="fi fi-sr-link-alt"></i>
                                                </a>
                                            @endif

                                            @if($shipment->label_url)
                                                <a href="{{ $shipment->label_url }}" target="_blank"
                                                   class="btn btn-outline-success btn-sm"
                                                   data-toggle="tooltip" title="{{ translate('Download_Label') }}">
                                                    <i class="fi fi-sr-label"></i>
                                                </a>
                                            @endif

                                            @if($shipment->isCancellable())
                                                <form action="{{ route('vendor.business-settings.shiprocket.cancel') }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="shipment_id" value="{{ $shipment->id }}">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm"
                                                            data-toggle="tooltip" title="{{ translate('Cancel') }}"
                                                            onclick="return confirm('{{ translate('Are_you_sure_you_want_to_cancel_this_shipment?') }}')">
                                                        <i class="fi fi-sr-cross-small"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/empty-state-icon/default.png') }}" alt="" width="100">
                                        <p class="text-muted mt-3">{{ translate('No_shipments_found') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end p-3">
                    {{ $shipments->links() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Tracking Modal -->
    <div class="modal fade" id="trackingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Shipment_Tracking') }}</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="tracking-content">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">{{ translate('Loading_tracking_info') }}...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
<script>
    $(document).on('click', '.track-shipment-btn', function() {
        let shipmentId = $(this).data('id');
        $('#tracking-content').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="mt-2">{{ translate("Loading") }}...</p></div>');
        $('#trackingModal').modal('show');

        $.ajax({
            url: '{{ route("vendor.business-settings.shiprocket.track", "") }}/' + shipmentId,
            type: 'GET',
            success: function(response) {
                if (response.status === 1) {
                    let shipment = response.shipment;
                    let html = '<div class="p-2">';
                    html += '<div class="row mb-3">';
                    html += '<div class="col-6"><strong>{{ translate("AWB") }}:</strong> ' + (shipment.awb_code || '-') + '</div>';
                    html += '<div class="col-6"><strong>{{ translate("Courier") }}:</strong> ' + (shipment.courier_name || '-') + '</div>';
                    html += '<div class="col-6 mt-2"><strong>{{ translate("Status") }}:</strong> <span class="badge badge-soft-primary">' + (shipment.shipment_status || '').replace(/_/g, ' ') + '</span></div>';
                    html += '<div class="col-6 mt-2"><strong>{{ translate("Est_Delivery") }}:</strong> ' + (shipment.estimated_delivery_date || '-') + '</div>';
                    html += '</div>';

                    if (response.tracking && response.tracking.tracking_data) {
                        let activities = response.tracking.tracking_data.shipment_track_activities || [];
                        if (activities.length > 0) {
                            html += '<h6 class="mb-2">{{ translate("Tracking_Activities") }}</h6>';
                            html += '<div class="timeline-wrapper" style="max-height:300px;overflow-y:auto;">';
                            activities.forEach(function(activity) {
                                html += '<div class="d-flex gap-3 mb-3 p-2 bg-light rounded">';
                                html += '<div class="flex-shrink-0 text-muted fs-10">' + (activity.date || '') + '</div>';
                                html += '<div><strong>' + (activity['sr-status-label'] || activity.status || '') + '</strong>';
                                html += '<div class="text-muted fs-10">' + (activity.activity || '') + '</div>';
                                html += '<div class="text-muted fs-10">' + (activity.location || '') + '</div>';
                                html += '</div></div>';
                            });
                            html += '</div>';
                        }
                    }
                    html += '</div>';
                    $('#tracking-content').html(html);
                } else {
                    $('#tracking-content').html('<div class="text-center py-4 text-danger">' + response.message + '</div>');
                }
            },
            error: function() {
                $('#tracking-content').html('<div class="text-center py-4 text-danger">{{ translate("Failed_to_load_tracking_info") }}</div>');
            }
        });
    });
</script>
@endpush
