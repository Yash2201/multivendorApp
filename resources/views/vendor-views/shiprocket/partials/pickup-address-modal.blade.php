{{-- Shared "Add pickup address" modal for the standalone management pages. --}}
{{-- Params: $showVendorSelect (bool, admin only), $sellers (Collection, when showVendorSelect) --}}
@php($showVendorSelect = $showVendorSelect ?? false)

<div class="modal fade" id="sr-pickup-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ translate('Add_Pickup_Address') }}</h5>
                <button type="button" class="btn-close sr-modal-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="sr-pickup-form">
                <div class="modal-body">
                    <div class="row g-3">
                        @if($showVendorSelect)
                            <div class="col-md-12">
                                <label class="form-label fs-12">{{ translate('Assign_to') }} <span class="text-danger">*</span></label>
                                <select name="seller_id" class="form-control form-control-sm js-select2-custom">
                                    <option value="">{{ translate('In-house') }} ({{ translate('Admin') }})</option>
                                    @foreach(($sellers ?? []) as $seller)
                                        <option value="{{ $seller->id }}">
                                            {{ $seller->shop->name ?? trim($seller->f_name . ' ' . $seller->l_name) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
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
                            <input type="text" name="address" class="form-control form-control-sm" maxlength="120" placeholder="{{ translate('e.g._12,_MG_Road') }}" required>
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
