<div class="modal fade" id="refundModal-{{ $refund['id'] }}">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('admin.refund-section.refund.refund-status-update') }}" method="post"
                  id="submit-refund-form-{{$refund['id']}}">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="id" value="{{ $refund->id}}">
                    <input type="hidden" name="refund_status" value="refunded">
                    <div class="text-center">
                        <img class="mb-3"
                             src="{{ dynamicAsset(path: 'public/assets/new/back-end/img/refund-approve.png') }}"
                             alt="{{ translate('refund_approve') }}">
                        <h4 class="mb-4 mx-auto max-w-283">
                            {{ translate('once_you_refund_that_refund_request').', '.translate('then_you_would_not_able_change_any_status') }}
                        </h4>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="">{{ translate('payment_method') }}</label>
                        <div class="select-wrapper">
                            <select class="form-select" name="payment_method">
                                <option value="cash">{{ translate('cash') }}</option>
                                <option value="digitally_paid">{{ translate('digitally_paid') }}</option>
                                @if ($walletStatus == 1 && $walletAddRefund == 1)
                                    <option value="customer_wallet">{{ translate('customer_wallet') }}</option>
                                @endif
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="">{{ translate('payment_info') }}
                            <span class="tooltip-icon cursor-pointer" data-bs-toggle="tooltip"
                                  data-bs-placement="right"
                                  area-label="{{ translate('please_enter_the_payment_information_according_to_your_chosen_payment_method').'.'.translate('without_a_proper_payment_info,you_cannot_change_the_Refund_Status').'.'}}"
                                  data-bs-title="{{ translate('please_enter_the_payment_information_according_to_your_chosen_payment_method').'.'.translate('without_a_proper_payment_info,you_cannot_change_the_Refund_Status').'.'}}">
                                    <i class="fi fi-sr-info"></i>
                                </span>
                        </label>
                        <input type="text" class="form-control" name="payment_info"
                               placeholder="{{ translate('ex').' : '.'Paypal'}}">
                    </div>
                    <div class="d-flex flex-wrap justify-content-end gap-3 mt-3">
                        <button type="button" class="btn btn-secondary px-3"
                                data-bs-dismiss="modal">{{ translate('close') }}</button>
                        <button type="button" class="btn btn-primary form-submit" data-form-id="submit-refund-form-{{$refund['id']}}"
                                data-message="{{ translate('want_to_refund_this_refund_request').'?' }}"
                                data-redirect-route="{{ route('admin.refund-section.refund.list', ['status'=>$refund['status']]) }}">
                            {{ translate('submit') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
