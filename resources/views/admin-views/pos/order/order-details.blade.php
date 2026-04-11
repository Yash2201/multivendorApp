@extends('layouts.admin.app')

@section('title', translate('order_Details'))

@section('content')
    <div class="content container-fluid">

        <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
            <h2 class="fs-20 mb-0 text-capitalize d-flex align-items-center gap-2">
                <img width="20" height="20" src="{{ dynamicAsset(path: 'public/assets/new/back-end/img/all-orders.png') }}" alt="">
                <span>{{translate('order_Details')}}</span>
            </h2>
            <div class="d-flex gap-1 align-items-center">
                <a href="{{ $previousOrder ? route('admin.orders.details', [$previousOrder['id']]) : 'javascript:' }}"
                   class="btn btn-circle text-primary bg-primary bg-opacity-10 border-0 {{ $previousOrder ? '' : 'disabled opacity-40' }}">
                    <i class="fi fi-sr-angle-left d-flex"></i>
                </a>
                <a href="{{ $nextOrder ? route('admin.orders.details', [$nextOrder['id']]) : 'javascript:' }}"
                   class="btn btn-circle text-primary bg-primary bg-opacity-10 border-0 {{ $nextOrder ? '' : 'disabled opacity-40' }}">
                    <i class="fi fi-sr-angle-right d-flex"></i>
                </a>
            </div>
        </div>

        <div class="row g-3" id="printableArea">
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 flex-md-nowrawp justify-content-between mb-4">
                            <div class="d-flex flex-column gap-3 flex-1">
                                <h4 class="text-capitalize fs-16 fw-bold">
                                    {{ translate('Order_Details') }} #{{ $order['id'] ?? '' }}
                                    @if(($order['order_type'] ?? '') == 'POS')
                                        <span>({{ 'POS' }})</span>
                                    @endif
                                </h4>
                                <div class="fs-12">
                                    {{ date('d M, Y , h:i A', strtotime($order['created_at'] ?? null)) }}
                                </div>
                            </div>
                            <div class="text-sm-end flex-grow-1">
                                <div class="d-flex flex-wrap gap-2 justify-content-sm-end">
                                    <a class="btn btn-primary" target="_blank"
                                       href="{{ route('admin.orders.generate-invoice',[$order['id'] ?? '']) }}">
                                        <img
                                            src="{{ dynamicAsset(path: 'public/assets/back-end/img/icons/uil_invoice.svg') }}"
                                            alt="" class="mr-1">
                                        {{ translate('print_Invoice') }}
                                    </a>
                                </div>
                                <div class="d-flex flex-column gap-2 mt-3">
                                    <div class="order-status d-flex justify-content-sm-end gap-10 text-capitalize fs-12">
                                        <span class="text-dark">{{ translate('status') }}: </span>
                                        @if(($order['order_status'] ?? '')=='pending')
                                            <span
                                                class="badge badge-info text-bg-info fw-bold rounded-50 d-flex align-items-center py-1 px-2">
                                                {{ translate(str_replace('_',' ',($order['order_status'] ?? ''))) }}
                                            </span>
                                        @elseif(($order['order_status'] ?? '')=='failed')
                                            <span
                                                class="badge badge-danger text-bg-danger fw-bold rounded-50 d-flex align-items-center py-1 px-2">
                                                {{ translate(str_replace('_',' ',($order['order_status'] ?? ''))) }}
                                            </span>
                                        @elseif(($order['order_status'] ?? '')=='processing' || ($order['order_status'] ?? '')=='out_for_delivery')
                                            <span
                                                class="badge badge-warning text-bg-warning fw-bold rounded-50 d-flex align-items-center py-1 px-2">
                                                {{ translate(str_replace('_',' ',($order['order_status'] ?? ''))) }}
                                            </span>
                                        @elseif(($order['order_status'] ?? '')=='delivered' || ($order['order_status'] ?? '')=='confirmed')
                                            <span
                                                class="badge badge-success text-bg-success fw-bold rounded-50 d-flex align-items-center py-1 px-2">
                                                {{ translate(str_replace('_',' ',($order['order_status'] ?? ''))) }}
                                            </span>
                                        @else
                                            <span
                                                class="badge badge-danger text-bg-danger fw-bold rounded-50 d-flex align-items-center py-1 px-2">
                                                {{ translate(str_replace('_',' ',($order['order_status'] ?? ''))) }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="payment-method d-flex justify-content-sm-end gap-10 text-capitalize fs-12">
                                        <span class="text-dark">{{ translate('payment_Method') }} :</span>
                                        <strong>  {{ translate(str_replace('_',' ',($order['payment_method'] ?? ''))) }}</strong>
                                    </div>
                                    @if(isset($order['transaction_ref']) && (($order['payment_method'] ?? '') != 'cash_on_delivery') && (($order['payment_method'] ?? '') != 'pay_by_wallet') && !isset($order['offline_payments']))
                                        <div
                                            class="reference-code d-flex justify-content-sm-end gap-10 text-capitalize fs-12">
                                            <span class="text-dark">{{ translate('reference_Code') }} :</span>
                                            <strong>{{ translate(str_replace('_',' ',($order['transaction_ref'] ?? '')) ) }} {{ ($order['payment_method'] ?? '') == 'offline_payment' ? '('.($order['payment_by'] ?? '').')':'' }}</strong>
                                        </div>
                                    @endif
                                    <div class="d-flex justify-content-sm-end gap-10 fs-12">
                                        <span class="text-dark">{{translate('payment_Status')}}:</span>
                                        @if(($order['payment_status'] ?? '')=='paid')
                                            <span class="text-success fw-bold">
                                                {{translate('paid')}}
                                            </span>
                                        @else
                                            <span class="text-danger fw-bold">
                                                {{translate('unpaid')}}
                                            </span>
                                        @endif
                                    </div>
                                    @if(getWebConfig('order_verification') && (($order['order_type'] ?? '') == "default_type"))
                                        <span class="d-flex justify-content-sm-end gap-10 fs-12">
                                            <b>
                                                {{translate('order_verification_code')}} : {{$order['verification_code'] ?? '' }}
                                            </b>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive datatable-custom">
                            <table
                                class="table fs-12 table-hover table-borderless align-middle">
                                <thead class="text-capitalize">
                                <tr>
                                    <th>{{translate('SL')}}</th>
                                    <th>{{translate('item_details')}}</th>
                                    <th class="text-center">{{ translate('Qty') }}</th>
                                    <th class="text-end">{{translate('item_price')}}</th>
                                    <th class="text-end">{{translate('discount')}}</th>
                                    <th class="text-end">{{translate('total_price')}}</th>
                                </tr>
                                </thead>

                                <tbody>
                                @php($item_price=0)
                                @php($subtotal=0)
                                @php($total=0)
                                @php($discount=0)
                                @php($product_price=0)
                                @php($total_product_price=0)
                                @foreach(($order['details'] ?? []) as $key=>$detail)
                                        <?php
                                        $isProductUnavailable = (($detail['productAllStatus'] ?? null) === null);
                                        $productDetails = json_decode($detail['product_details'] ?? null, true);
                                        if (!empty($detail['productAllStatus'])) {
                                            $getCurrentThumbnailImage = checkImageStatus(path: $detail['productAllStatus']['thumbnail'] ?? null, storagePath: $detail['productAllStatus']['thumbnail_storage_type'] ?? 'public');
                                        } else {
                                            $getCurrentThumbnailImage = checkImageStatus(path: $productDetails['thumbnail'] ?? null, storagePath: $productDetails['thumbnail_storage_type'] ?? 'public');
                                        }
                                        ?>

                                    @if($productDetails)
                                        <tr>
                                            <td>{{++$key}}</td>
                                            <td>
                                                <div class="{{ $isProductUnavailable ? 'table-row-disabled' : '' }}" data-bs-toggle="tooltip"
                                                     title="{{ $isProductUnavailable ? translate('This_product_has_been_deleted') : '' }}">
                                                    <div class="media align-items-center gap-10">
                                                        <a target="_blank" href="{{ route('admin.products.view', ['addedBy' => (($productDetails['added_by'] ?? '') == 'seller') ? 'vendor' : 'in-house', 'id' => ($productDetails['id'] ?? '')]) }}" class="text-dark">
                                                            <img class="avatar avatar-60 rounded img-fit"
                                                                 src="{{ getStorageImages(path: $getCurrentThumbnailImage, type: 'backend-product') }}"
                                                                 alt="{{ translate('image_description') }}">
                                                        </a>
                                                        <div>
                                                            <h5 class="text-dark fs-12"
                                                                @if(!$isProductUnavailable && strlen($productDetails['name'] ?? '') > 30)
                                                                    data-bs-toggle="tooltip" title="{{ $productDetails['name'] ?? '' }}"
                                                                @endif
                                                            >
                                                                <a target="_blank" href="{{ route('admin.products.view', ['addedBy' => (($productDetails['added_by'] ?? '') == 'seller') ? 'vendor' : 'in-house', 'id' => ($productDetails['id'] ?? '')]) }}" class="text-dark">
                                                                    {{ Str::limit($productDetails['name'] ?? '', 30) }}
                                                                </a>
                                                            </h5>
                                                            <div class="fs-10">
                                                                <strong>{{ translate('unit_price') }} :</strong>
                                                                {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $detail['price'] ?? 0)) }}
                                                            </div>
                                                            @if (!empty($detail['variant'] ?? null))
                                                                <div class="max-w-150px text-wrap fs-10">
                                                                    <strong>{{ translate('variation') }} :</strong>
                                                                    {{ $detail['variant'] ?? '' }}
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @if(isset($productDetails['digital_product_type']) && ($productDetails['digital_product_type'] ?? '') == 'ready_after_sell')
                                                        <button type="button" class="btn btn-sm btn-primary mt-2"
                                                                title="File Upload" data-bs-toggle="modal"
                                                                data-bs-target="#fileUploadModal-{{ $detail['id'] ?? '' }}">
                                                            <i class="fi fi-rr-document"></i> {{ translate('file') }}
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="text-center">{{$detail['qty'] ?? 0}}</td>
                                            <td  class="text-end">{{setCurrencySymbol(amount: usdToDefaultCurrency(amount:  ($detail['price'] ?? 0)*($detail['qty'] ?? 0)), currencyCode: getCurrencyCode()) }}</td>
                                            <td  class="text-end">{{setCurrencySymbol(amount: usdToDefaultCurrency(amount:  $detail['discount'] ?? 0), currencyCode: getCurrencyCode()) }}</td>
                                            @php($item_price+=(($detail['price'] ?? 0)*($detail['qty'] ?? 0)))
                                            @php($subtotal=((($detail['price'] ?? 0)*($detail['qty'] ?? 0)) - ($detail['discount'] ?? 0)))
                                            @php($product_price = ($detail['price'] ?? 0)*($detail['qty'] ?? 0))
                                            @php($total_product_price+=$product_price)
                                            <td  class="text-end">{{setCurrencySymbol(amount: usdToDefaultCurrency(amount:  $subtotal), currencyCode: getCurrencyCode()) }}</td>
                                        </tr>
                                        @php($discount+=($detail['discount'] ?? 0))
                                        @php($total+=$subtotal)
                                    @endif
                                    @php($sellerId= $detail['seller_id'] ?? null)
                                @endforeach
                                </tbody>
                            </table>

                            @foreach(($order['details'] ?? []) as $key=>$detail)
                                    <?php
                                    $productDetails = json_decode($detail['product_details'] ?? null, true);
                                    ?>
                                @if($productDetails && (($productDetails['product_type'] ?? '') == 'digital'))
                                    <div class="modal fade" id="fileUploadModal-{{ $detail['id'] ?? '' }}"
                                         tabindex="-1" aria-labelledby="exampleModalLabel"
                                         aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <form action="{{ route('admin.orders.digital-file-upload-after-sell') }}"
                                                      method="post" enctype="multipart/form-data"
                                                      class="form-advance-validation form-advance-inputs-validation form-advance-file-validation non-ajax-form-validate"
                                                      novalidate="novalidate">
                                                    @csrf
                                                    <div class="modal-body">
                                                        @if(!empty($detail['digital_file_after_sell_full_url'] ?? null) && isset($detail['digital_file_after_sell_full_url']['key']))
                                                            <div class="mb-4">
                                                                {{ translate('uploaded_file') }} :
                                                                <span
                                                                    data-file-path="{{ $detail['digital_file_after_sell_full_url']['path'] ?? '' }}"
                                                                    class="btn btn-success btn-sm getDownloadFileUsingFileUrl"
                                                                    title="{{ translate('download')}}"><i
                                                                        class="fi fi-rr-download"></i>
                                                                    {{ translate('download')}}
                                                                </span>
                                                            </div>
                                                        @elseif(($productDetails['digital_product_type'] ?? '') == 'ready_after_sell' && !empty($detail['digital_file_after_sell'] ?? null))
                                                            <div class="mb-4">
                                                                {{ translate('uploaded_file') }} :
                                                                <a href="{{ asset('storage/app/public/product/digital-product/'.($detail['digital_file_after_sell'] ?? '')) }}"
                                                                   class="btn btn-success btn-sm"
                                                                   title="{{ translate('download')}}"><i
                                                                        class="fi fi-rr-download"></i>
                                                                    {{ translate('download')}}</a>
                                                            </div>
                                                        @elseif(($productDetails['digital_product_type'] ?? '') == 'ready_product' && !empty($productDetails['digital_file_ready'] ?? null))
                                                            <div class="mb-4">
                                                                {{ translate('uploaded_file').':' }}
                                                                <a href="{{ asset('storage/app/public/product/digital-product/'.($productDetails['digital_file_ready'] ?? '')) }}"
                                                                   class="btn btn-success btn-sm"
                                                                   title="Download"><i
                                                                        class="fi fi-rr-download"></i>
                                                                    {{ translate('Download')}}</a>
                                                            </div>
                                                        @endif

                                                        @if(($productDetails['digital_product_type'] ?? '') == 'ready_after_sell')
                                                            <input type="file" data-max-size="{{ getFileUploadMaxSize(type: 'file') }}"
                                                                   name="digital_file_after_sell"
                                                                   class="form-control">
                                                            <input type="hidden" value="{{ $detail['id'] ?? '' }}"
                                                                   name="order_id">
                                                        @endif
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">{{ translate('close') }}</button>
                                                        @if(($productDetails['digital_product_type'] ?? '') == 'ready_after_sell')
                                                            <button type="submit"
                                                                    class="btn btn-primary">{{ translate('upload') }}</button>
                                                        @endif
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        <hr>
                        @php($orderTotalPriceSummary = \App\Utils\OrderManager::getOrderTotalPriceSummary(order: $order))
                        <div class="row justify-content-end">
                            <div class="col-12 col-sm-auto">
                                <div class="overflow-x-auto min-w-300 min-w-100-mobile">
                                    <table class="table table-borderless table-sm mb-0 text-sm-right text-nowrap fs-12">
                                        <tbody>
                                        <tr>
                                            <td class="text-start text-dark text-capitalize">
                                                <span>{{ translate('item_price') }}</span></td>
                                            <td class="text-end text-dark">
                                                <span>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['itemPrice']), currencyCode: getCurrencyCode()) }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-start text-dark text-capitalize">
                                                <span>{{ translate('item_discount') }}</span></td>
                                            <td class="text-end text-dark">
                                                -
                                                <span>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['itemDiscount']), currencyCode: getCurrencyCode()) }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-start text-dark"><span>{{ translate('extra_discount') }}</span>
                                            </td>
                                            <td class="text-end text-dark">
                                                <span>- {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['extraDiscount']), currencyCode: getCurrencyCode()) }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-start text-dark text-capitalize">
                                                <span>{{ translate('sub_total') }}</span></td>
                                            <td class="text-end text-dark">
                                                <span>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['subTotal']), currencyCode: getCurrencyCode()) }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-start text-dark"><span>{{ translate('coupon_discount') }}</span>
                                            </td>
                                            <td class="text-end text-dark">
                                                <span>-
                                                    {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['couponDiscount']), currencyCode: getCurrencyCode()) }}</span>
                                            </td>
                                        </tr>
                                        @if($orderTotalPriceSummary['referAndEarnDiscount'] > 0)
                                            <tr>
                                                <td class="text-start text-dark">
                                                    <span>{{ translate('referral_discount') }}</span></td>
                                                <td class="text-end text-dark">
                                                    <span>-
                                                        {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['referAndEarnDiscount']), currencyCode: getCurrencyCode()) }}</span>
                                                </td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <td class="text-start text-dark text-uppercase"><span>{{ translate('vat') }}
                                                    /{{ translate('tax') }}</span></td>
                                            <td class="text-end text-dark">
                                                <span>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['taxTotal']), currencyCode: getCurrencyCode()) }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-start text-dark">
                                                <strong>{{ translate('total') }}</strong>
                                                <span class="fs-10 fw-medium">{{ $orderTotalPriceSummary['tax_model'] == 'include' ? '('.translate('Tax_:_Inc.').')' : '' }}</span>
                                            </td>
                                            <td class="text-end text-dark">
                                                <strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['totalAmount']), currencyCode: getCurrencyCode()) }}</strong>
                                            </td>
                                        </tr>
                                        @if ($order->order_type == 'pos' || $order->order_type == 'POS')
                                            <tr>
                                                <td class="text-start text-dark"><span>{{ translate('paid_amount') }}</span>
                                                </td>
                                                <td class="text-end text-dark">
                                                    <span>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['paidAmount']), currencyCode: getCurrencyCode()) }}</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-start text-dark"><span>{{ translate('change_amount') }}</span>
                                                </td>
                                                <td class="text-end text-dark">
                                                    <span>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['changeAmount']), currencyCode: getCurrencyCode()) }}</span>
                                                </td>
                                            </tr>
                                        @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 d-flex flex-column gap-3">
                <div class="card">
                    @if(!empty($order['customer'] ?? null))
                        <div class="card-body">
                            <div class="d-flex gap-2 align-items-center justify-content-between mb-3">
                                <h4 class="d-flex gap-2 fs-14 fw-bold mb-0">
                                    <img
                                        src="{{ dynamicAsset(path: 'public/assets/new/back-end/img/vendor-information.png')}}"
                                        alt="">
                                    {{translate('customer_information')}}
                                </h4>
                            </div>
                            <div class="media flex-wrap gap-3 gap-sm-4">
                                <div class="">
                                    <img class="avatar rounded-circle avatar-70 object-fit-cover"
                                         src="{{ getStorageImages(path: ($order['customer']['image_full_url'] ?? null) , type: 'backend-basic') }}"
                                         alt="{{translate('Image')}}">
                                </div>
                                <div class="media-body d-flex flex-column gap-1">
                                    <span class="text-dark"><span class="fw-semibold">{{ ($order['customer']['f_name'] ?? '') . ' ' . ($order['customer']['l_name'] ?? '') }} </span></span>
                                    @if((($order['customer']['email'] ?? '') !== 'walking@customer.com'))
                                        <span class="text-dark fs-12"> <span class="fw-bold">{{ $orderCount ?? 0 }}</span> {{translate('orders')}}</span>
                                        <span class="text-dark break-all fs-12">
                                            <span class="fw-semibold">{{ $order['customer']['phone'] ?? '' }}</span>
                                        </span>
                                        <span class="text-dark break-all fs-12">
                                            {{ $order['customer']['email'] ?? '' }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="card-body">
                            <div class="media align-items-center">
                                <span>{{ translate('no_customer_found') }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="locationModal" tabindex="-1" role="dialog" aria-labelledby="locationModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0 d-flex justify-content-end">
                    <h3 class="modal-title text-center flex-grow-1"
                        id="locationModalLabel">{{translate('location_Data')}}</h3>
                    <button type="button" class="btn-close border-0 btn-circle bg-section2 shadow-none"
                            data-bs-dismiss="modal" aria-label="Close">
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 modal_body_map">
                            <div class="location-map" id="location-map">
                                <div class="w-100 h-200" id="location_map_canvas"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <span id="route-admin-orders-payment-status" data-url="{{ route('admin.orders.payment-status') }}"></span>
@endsection

@push('script')
@endpush
