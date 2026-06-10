@extends('layouts.vendor.app')
@section('title', translate('Pickup_Addresses'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <h2 class="fs-20 mb-0 text-capitalize d-flex align-items-center gap-2">
                <i class="fi fi-sr-marker"></i>
                <span>{{ translate('Pickup_Addresses') }}</span>
            </h2>
            <button type="button" class="btn btn--primary" id="sr-add-address-btn">
                <i class="fi fi-sr-plus-small"></i> {{ translate('Add_Pickup_Address') }}
            </button>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive datatable-custom">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table w-100 fs-12">
                        <thead class="thead-light thead-50 text-capitalize">
                            <tr>
                                <th>{{ translate('SL') }}</th>
                                <th>{{ translate('Pickup_Contact_Name') }}</th>
                                <th>{{ translate('Address') }}</th>
                                <th>{{ translate('City') }} / {{ translate('State') }} / {{ translate('Pincode') }}</th>
                                <th>{{ translate('Phone') }} / {{ translate('Email') }}</th>
                                <th>{{ translate('Nickname') }}</th>
                                <th>{{ translate('Default') }}</th>
                                <th class="text-center">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($addresses as $key => $address)
                                <tr>
                                    <td>{{ $addresses->firstItem() + $key }}</td>
                                    <td class="text-wrap"><strong>{{ $address->name }}</strong></td>
                                    <td class="text-wrap" style="max-width:240px;">
                                        {{ $address->address }}{{ $address->address_2 ? ', ' . $address->address_2 : '' }}
                                    </td>
                                    <td>{{ $address->city }}, {{ $address->state }} - {{ $address->pin_code }}<br>{{ $address->country }}</td>
                                    <td>{{ $address->phone }}<br>{{ $address->email }}</td>
                                    <td><span class="badge badge-soft-info">{{ $address->pickup_nickname }}</span></td>
                                    <td>
                                        @if($address->is_default)
                                            <span class="badge badge-soft-success">{{ translate('Default') }}</span>
                                        @else
                                            <button type="button" class="btn btn-outline-secondary btn-sm sr-set-default" data-id="{{ $address->id }}">
                                                {{ translate('Set_default') }}
                                            </button>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-outline-danger btn-sm sr-delete"
                                                data-id="{{ $address->id }}" data-toggle="tooltip" title="{{ translate('Delete') }}">
                                            <i class="fi fi-sr-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/empty-state-icon/default.png') }}" alt="" width="100">
                                        <p class="text-muted mt-3 mb-0">{{ translate('No_pickup_address_yet_add_one_to_continue') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($addresses->hasPages())
                    <div class="d-flex justify-content-end p-3">
                        {{ $addresses->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    @include('vendor-views.shiprocket.partials.pickup-address-modal', ['showVendorSelect' => false])
@endsection

@push('script')
<script>
    (function () {
        var token = '{{ csrf_token() }}';
        var storeUrl = '{{ route('vendor.business-settings.shiprocket.pickup-addresses.store') }}';
        var defaultUrl = '{{ route('vendor.business-settings.shiprocket.pickup-addresses.default') }}';
        var deleteUrlTemplate = '{{ route('vendor.business-settings.shiprocket.pickup-addresses.delete', ['id' => '__ID__']) }}';
        var prefill = @json($prefill);

        var $modal = $('#sr-pickup-modal');
        var $form = $('#sr-pickup-form');

        function srToast(type, msg) {
            if (!msg) { msg = '{{ translate('Something_went_wrong') }}'; }
            if (typeof toastMagic !== 'undefined' && toastMagic && typeof toastMagic[type] === 'function') { toastMagic[type](msg); }
            else if (window.toastr && typeof toastr[type] === 'function') { toastr[type](msg); }
            else { alert(msg); }
        }
        function srShow($el) { if ($.fn.modal) { $el.modal('show'); } else if (window.bootstrap && bootstrap.Modal) { bootstrap.Modal.getOrCreateInstance($el[0]).show(); } }
        function srHide($el) { if ($.fn.modal) { $el.modal('hide'); } else if (window.bootstrap && bootstrap.Modal) { bootstrap.Modal.getOrCreateInstance($el[0]).hide(); } }
        function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

        $('#sr-add-address-btn').on('click', function () {
            $form.find('.sr-field-error').remove();
            $.each(prefill, function (k, v) { var $f = $form.find('[name="' + k + '"]'); if ($f.length && !$f.val()) { $f.val(v); } });
            srShow($modal);
        });
        $modal.on('click', '.sr-modal-close', function () { srHide($modal); });

        $form.on('submit', function (e) {
            e.preventDefault();
            var $btn = $form.find('button[type="submit"]').prop('disabled', true);
            $form.find('.sr-field-error').remove();
            $.ajax({
                url: storeUrl, type: 'POST', data: $form.serialize() + '&_token=' + encodeURIComponent(token),
                success: function (res) {
                    $btn.prop('disabled', false);
                    if (res.status === 1) { location.reload(); }
                    else { srToast('error', res.message); }
                },
                error: function (xhr) {
                    $btn.prop('disabled', false);
                    if (xhr.status === 422 && xhr.responseJSON) {
                        $.each(xhr.responseJSON.errors || {}, function (f, m) {
                            $form.find('[name="' + f + '"]').after('<small class="text-danger sr-field-error d-block mt-1">' + esc(m[0]) + '</small>');
                        });
                        srToast('error', '{{ translate('Please_correct_the_highlighted_fields') }}');
                    } else {
                        srToast('error', (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : '{{ translate('Something_went_wrong') }}');
                    }
                }
            });
        });

        $(document).on('click', '.sr-set-default', function () {
            $.ajax({
                url: defaultUrl, type: 'POST', data: { _token: token, id: $(this).data('id') },
                success: function (res) { if (res.status === 1) { location.reload(); } else { srToast('error', res.message); } },
                error: function () { srToast('error', '{{ translate('Something_went_wrong') }}'); }
            });
        });

        $(document).on('click', '.sr-delete', function () {
            if (!confirm('{{ translate('Are_you_sure_you_want_to_delete_this_pickup_address?') }}')) { return; }
            $.ajax({
                url: deleteUrlTemplate.replace('__ID__', $(this).data('id')), type: 'POST', data: { _token: token, _method: 'DELETE' },
                success: function (res) { if (!res || res.status !== 0) { location.reload(); } else { srToast('error', res.message); } },
                error: function () { srToast('error', '{{ translate('Something_went_wrong') }}'); }
            });
        });
    })();
</script>
@endpush
