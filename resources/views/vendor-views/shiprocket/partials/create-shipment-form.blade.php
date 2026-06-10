{{-- Include this partial in order-details.blade.php (admin or vendor) --}}
{{-- Usage: @include('vendor-views.shiprocket.partials.create-shipment-form', ['order' => $order]) --}}

@php($existingShipment = $order->shiprocketShipment)
@php($isAdminPanel = request()->is('admin/*'))
@php($srPrefix = $isAdminPanel ? 'admin' : 'vendor')
{{-- For admin: scope the picker to the order's owner (vendor order -> its seller, in-house -> null) --}}
@php($srOrderSellerId = $isAdminPanel ? ($order->seller_is === 'seller' ? $order->seller_id : '') : '')
@php($srShowForm = !($existingShipment && $existingShipment->isActive()) && in_array($order->order_status, ['confirmed', 'processing']))

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
                <div class="d-flex gap-3"><span class="w-140 flex-shrink-0">{{ translate('Pickup_Location') }}</span><span>:</span><strong>{{ $existingShipment->pickup_location ?? '-' }}</strong></div>
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
        @elseif($srShowForm)
            {{-- Show shipment creation form --}}
            @php($route = $isAdminPanel ? route('admin.business-settings.shiprocket.create-shipment') : route('vendor.business-settings.shiprocket.create-shipment'))
            <form action="{{ $route }}" method="POST" id="shiprocket-create-form-{{ $order->id }}">
                @csrf
                <input type="hidden" name="order_id" value="{{ $order->id }}">
                {{-- Holds the selected saved-address nickname (Shiprocket pickup_location) --}}
                <input type="hidden" name="pickup_location" id="sr-selected-nickname-{{ $order->id }}" value="">

                {{-- Pickup address picker --}}
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fs-12 mb-0 fw-semibold">
                            {{ translate('Pickup_Address') }} <span class="text-danger">*</span>
                        </label>
                        <button type="button" class="btn btn-outline-primary btn-sm sr-add-address-btn" data-order="{{ $order->id }}">
                            <i class="fi fi-sr-plus-small"></i> {{ translate('Add_Pickup_Address') }}
                        </button>
                    </div>
                    <div id="sr-pickup-list-{{ $order->id }}" class="d-grid gap-2">
                        <div class="text-center text-muted py-3 fs-12">
                            <div class="spinner-border spinner-border-sm"></div> {{ translate('Loading_addresses') }}...
                        </div>
                    </div>
                </div>

                {{-- Package dimensions --}}
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
                </div>
                <div class="mt-3">
                    <button type="button" class="btn btn--primary btn-sm form-alert sr-create-btn" id="sr-create-btn-{{ $order->id }}"
                            data-id="shiprocket-create-form-{{ $order->id }}"
                            data-message="{{ translate('Are_you_sure_you_want_to_create_shipment_via_Shiprocket?') }}" disabled>
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

@if($srShowForm)
    {{-- Add Pickup Address Modal --}}
    <div class="modal fade" id="sr-pickup-modal-{{ $order->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Add_Pickup_Address') }}</h5>
                    <button type="button" class="btn-close sr-modal-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close">X</button>
                </div>
                <form id="sr-pickup-form-{{ $order->id }}">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fs-12">{{ translate('Pickup_Contact_Name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control form-control-sm" maxlength="50" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fs-12">{{ translate('Phone') }} <span class="text-danger">*</span></label>
                                <input type="text" name="phone" class="form-control form-control-sm" placeholder="{{ translate('10_digit_mobile') }}" maxlength="10" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fs-12">{{ translate('Email') }} <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fs-12">{{ translate('Address_Line') }} <span class="text-danger">*</span></label>
                                <input type="text" name="address" class="form-control form-control-sm" maxlength="120" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fs-12">{{ translate('Address_Line_2') }} ({{ translate('optional') }})</label>
                                <input type="text" name="address_2" class="form-control form-control-sm" maxlength="120">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fs-12">{{ translate('City') }} <span class="text-danger">*</span></label>
                                <input type="text" name="city" class="form-control form-control-sm" maxlength="60" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fs-12">{{ translate('State') }} <span class="text-danger">*</span></label>
                                <input type="text" name="state" class="form-control form-control-sm" maxlength="60" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fs-12">{{ translate('Pincode') }} <span class="text-danger">*</span></label>
                                <input type="text" name="pin_code" class="form-control form-control-sm" placeholder="{{ translate('6_digit_pincode') }}" maxlength="6" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fs-12">{{ translate('Country') }} <span class="text-danger">*</span></label>
                                <input type="text" name="country" class="form-control form-control-sm" value="India" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm sr-modal-close" data-bs-dismiss="modal" data-dismiss="modal">{{ translate('Cancel') }}</button>
                        <button type="submit" class="btn btn--primary btn-sm">
                            <i class="fi fi-sr-disk"></i> {{ translate('Save_Address') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('script')
    <script>
        (function () {
            var orderId = {{ $order->id }};
            var token = '{{ csrf_token() }}';
            var sellerId = '{{ $srOrderSellerId }}';
            var listUrl = '{{ route($srPrefix.'.business-settings.shiprocket.pickup-addresses.list') }}';
            var storeUrl = '{{ route($srPrefix.'.business-settings.shiprocket.pickup-addresses.store') }}';
            var defaultUrl = '{{ route($srPrefix.'.business-settings.shiprocket.pickup-addresses.default') }}';
            var deleteUrlTemplate = '{{ route($srPrefix.'.business-settings.shiprocket.pickup-addresses.delete', ['id' => '__ID__']) }}';

            var $list = $('#sr-pickup-list-' + orderId);
            var $hidden = $('#sr-selected-nickname-' + orderId);
            var $createBtn = $('#sr-create-btn-' + orderId);
            var $modal = $('#sr-pickup-modal-' + orderId);
            var $form = $('#sr-pickup-form-' + orderId);

            // Bail out if this order is not in the shipment-creation state.
            if (!$list.length) { return; }

            function escapeHtml(s) { return $('<div>').text(s == null ? '' : s).html(); }

            function srShowModal($el) {
                if ($.fn.modal) {
                    // Bootstrap 4 — always use jQuery modal
                    $el.modal('show');
                } else if (window.bootstrap && bootstrap.Modal && bootstrap.Modal.getOrCreateInstance) {
                    // Bootstrap 5 fallback (getOrCreateInstance is the correct BS5 API)
                    bootstrap.Modal.getOrCreateInstance($el[0]).show();
                } else {
                    console.error('No Bootstrap modal plugin found', $el);
                }
            }

            function srHideModal($el) {
                if ($.fn.modal) {
                    // Bootstrap 4 — jQuery modal
                    $el.modal('hide');
                } else if (window.bootstrap && bootstrap.Modal && bootstrap.Modal.getOrCreateInstance) {
                    // Bootstrap 5 fallback
                    bootstrap.Modal.getOrCreateInstance($el[0]).hide();
                } else {
                    console.error('No Bootstrap modal plugin found', $el);
                }
            }

            // Show a toast using the panel's global ToastMagic, with safe fallbacks.
            function srToast(type, message) {
                if (!message) { message = '{{ translate('Something_went_wrong') }}'; }
                if (typeof toastMagic !== 'undefined' && toastMagic && typeof toastMagic[type] === 'function') {
                    toastMagic[type](message);
                } else if (window.toastr && typeof toastr[type] === 'function') {
                    toastr[type](message);
                } else {
                    alert(message);
                }
            }

            function setSelected(nickname) {
                $hidden.val(nickname || '');
                $createBtn.prop('disabled', !nickname);
            }

            function renderAddresses(addresses) {
                if (!addresses || !addresses.length) {
                    $list.html('<div class="border rounded p-3 text-center text-muted fs-12">{{ translate('No_pickup_address_yet_add_one_to_continue') }}</div>');
                    setSelected('');
                    return;
                }
                var html = '';
                addresses.forEach(function (a) {
                    var checked = a.is_default ? 'checked' : '';
                    var vendorLabel = (a.seller && a.seller.shop && a.seller.shop.name) ? ' · ' + escapeHtml(a.seller.shop.name) : '';
                    html += '<label class="border rounded p-2 d-flex gap-2 align-items-start mb-0" style="cursor:pointer">';
                    html += '<input type="radio" name="sr_pickup_radio_' + orderId + '" class="mt-1 sr-addr-radio" value="' + escapeHtml(a.pickup_nickname) + '" ' + checked + '>';
                    html += '<span class="flex-grow-1 fs-12">';
                    html += '<strong>' + escapeHtml(a.name) + '</strong>';
                    if (a.is_default) { html += ' <span class="badge badge-soft-success">{{ translate('Default') }}</span>'; }
                    html += vendorLabel;
                    html += '<div class="text-muted">' + escapeHtml(a.address) + (a.address_2 ? ', ' + escapeHtml(a.address_2) : '') + '</div>';
                    html += '<div class="text-muted">' + escapeHtml(a.city) + ', ' + escapeHtml(a.state) + ' - ' + escapeHtml(a.pin_code) + ', ' + escapeHtml(a.country) + '</div>';
                    html += '<div class="text-muted">' + escapeHtml(a.phone) + ' · ' + escapeHtml(a.email) + '</div>';
                    html += '</span>';
                    html += '<span class="d-flex gap-1 flex-shrink-0">';
                    if (!a.is_default) {
                        html += '<a href="javascript:" class="btn btn-outline-secondary btn-sm sr-addr-default" data-id="' + a.id + '" title="{{ translate('Set_default') }}"><i class="fi fi-sr-marker"></i></a>';
                    }
                    html += '<a href="javascript:" class="btn btn-outline-danger btn-sm sr-addr-delete" data-id="' + a.id + '" title="{{ translate('Delete') }}"><i class="fi fi-sr-trash"></i></a>';
                    html += '</span>';
                    html += '</label>';
                });
                $list.html(html);

                var $checked = $list.find('.sr-addr-radio:checked');
                if (!$checked.length) { $checked = $list.find('.sr-addr-radio').first().prop('checked', true); }
                setSelected($checked.val());
            }

            function loadAddresses(selectNickname) {
                $list.html('<div class="text-center text-muted py-3 fs-12"><div class="spinner-border spinner-border-sm"></div> {{ translate('Loading_addresses') }}...</div>');
                $.ajax({
                    url: listUrl,
                    type: 'GET',
                    data: { seller_id: sellerId },
                    success: function (res) {
                        if (res.status === 1) {
                            renderAddresses(res.addresses);
                            $modal.data('prefill', res.prefill || {});
                            if (selectNickname) {
                                var $r = $list.find('.sr-addr-radio[value="' + selectNickname + '"]');
                                if ($r.length) { $r.prop('checked', true); setSelected(selectNickname); }
                            }
                        } else {
                            $list.html('<div class="text-danger fs-12 py-2">' + escapeHtml(res.message) + '</div>');
                        }
                    },
                    error: function () {
                        $list.html('<div class="text-danger fs-12 py-2">{{ translate('Failed_to_load_pickup_addresses') }}</div>');
                    }
                });
            }

            $list.on('change', '.sr-addr-radio', function () { setSelected($(this).val()); });

            $list.on('click', '.sr-addr-default', function () {
                $.ajax({
                    url: defaultUrl, type: 'POST',
                    data: { _token: token, id: $(this).data('id') },
                    success: function (res) {
                        if (res.status === 1) { loadAddresses($hidden.val()); }
                        else { srToast('error', res.message); }
                    },
                    error: function () { srToast('error', '{{ translate('Something_went_wrong') }}'); }
                });
            });

            $list.on('click', '.sr-addr-delete', function () {
                if (!confirm('{{ translate('Are_you_sure_you_want_to_delete_this_pickup_address?') }}')) { return; }
                $.ajax({
                    url: deleteUrlTemplate.replace('__ID__', $(this).data('id')), type: 'POST',
                    data: { _token: token, _method: 'DELETE' },
                    success: function (res) {
                        loadAddresses($hidden.val());
                        if (res && res.status === 0) { srToast('error', res.message); }
                    },
                    error: function () { srToast('error', '{{ translate('Something_went_wrong') }}'); }
                });
            });

            $('.sr-add-address-btn[data-order="' + orderId + '"]').on('click', function () {
                var prefill = $modal.data('prefill') || {};
                $.each(prefill, function (k, v) {
                    var $f = $form.find('[name="' + k + '"]');
                    if ($f.length && !$f.val()) { $f.val(v); }
                });
                $form.find('.sr-field-error').remove();
                srShowModal($modal);
            });

            $modal.on('click', '.sr-modal-close', function () { srHideModal($modal); });

            $form.on('submit', function (e) {
                e.preventDefault();
                var $btn = $form.find('button[type="submit"]').prop('disabled', true);
                $form.find('.sr-field-error').remove();
                $.ajax({
                    url: storeUrl, type: 'POST',
                    data: $form.serialize() + '&_token=' + encodeURIComponent(token) + '&seller_id=' + encodeURIComponent(sellerId),
                    success: function (res) {
                        $btn.prop('disabled', false);
                        if (res.status === 1) {
                            srHideModal($modal);
                            $form[0].reset();
                            loadAddresses(res.address ? res.address.pickup_nickname : null);
                            srToast('success', res.message);
                        } else {
                            // Shiprocket rejected the address (e.g. 422 from /addpickup) — surface it.
                            srToast('error', res.message);
                        }
                    },
                    error: function (xhr) {
                        $btn.prop('disabled', false);
                        if (xhr.status === 422 && xhr.responseJSON) {
                            var errs = xhr.responseJSON.errors || {};
                            $.each(errs, function (field, msgs) {
                                $form.find('[name="' + field + '"]').after('<small class="text-danger sr-field-error d-block mt-1">' + escapeHtml(msgs[0]) + '</small>');
                            });
                            srToast('error', '{{ translate('Please_correct_the_highlighted_fields') }}');
                        } else {
                            srToast('error', (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : '{{ translate('Something_went_wrong') }}');
                        }
                    }
                });
            });

            loadAddresses();
        })();
    </script>
    @endpush
@endif
