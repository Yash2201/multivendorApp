@extends('layouts.admin.app')

@section('title', translate('refund_details'))

@section('content')
    <div class="content container-fluid">
        <div class="mb-3">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                <img width="20" src="{{ dynamicAsset(path: 'public/assets/new/back-end/img/refund_transaction.png') }}"
                     alt="{{ translate('refund_details') }}">
                {{ translate('refund_details') }}
            </h2>
        </div>
        <div class="refund-details-card--2 p-4">
            <div class="row g-2">
                <div class="col-xl-4">
                    <div class="card h-100 refund-details-card">
                        <div class="card-body">
                            <h4 class="mb-3">{{ translate('refund_summary') }}</h4>
                            <ul class="dm-info p-0 m-0">
                                <li class="align-items-center">
                                    <span class="left">{{ translate('refund_id') }} </span> <span>:</span>
                                    <span class="right">{{ $refund->id }}</span>
                                </li>
                                <li class="align-items-center">
                                    <span class="left text-capitalize">
                                        {{ translate('refund_requested_date') }}
                                    </span>
                                    <span>:</span>
                                    <span class="right">
                                        {{ date('d M Y, h:s:A',strtotime($refund['created_at'])) }}
                                    </span>
                                </li>
                                <li class="align-items-center">
                                    <span class="left">{{ translate('refund_status') }}</span> <span>:</span>
                                    <span class="right">
                                        @if ($refund['status'] == 'pending')
                                            <span class="badge badge-secondary text-bg-secondary">
                                                {{ translate($refund['status']) }}
                                            </span>
                                        @elseif($refund['status'] == 'approved')
                                            <span class="badge badge-primary text-bg-primary">
                                                {{ translate($refund['status']) }}
                                            </span>
                                        @elseif($refund['status'] == 'refunded')
                                            <span class="badge badge-success text-bg-success">
                                                {{ translate($refund['status']) }}
                                            </span>
                                        @elseif($refund['status'] == 'rejected')
                                            <span class="badge badge-danger text-bg-danger">
                                                {{ translate($refund['status']) }}
                                            </span>
                                        @endif
                                    </span>
                                </li>
                                <li class="align-items-center">
                                    <span class="left">{{ translate('payment_method') }} </span> <span>:</span> <span
                                        class="right">{{ str_replace('_',' ',$order->payment_method) }}</span>
                                </li>
                                <li class="align-items-center">
                                    <span class="left">{{ translate('order_details') }} </span> <span>:</span> <span
                                        class="right"><a
                                            class="badge py-2 badge-primary text-bg-primary border border-primary px-2"
                                            href="{{ route('admin.orders.details',['id'=>$order->id]) }}">{{ translate('view_details') }}</a></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-xl-8">
                    <div class="card h-100 refund-details-card">
                        <div class="card-body">
                            <div class="gap-3 mb-4 d-flex justify-content-between flex-wrap align-items-center">
                                <h4 class="">{{ translate('product_details') }}</h4>
                                <div class="d-flex flex-wrap gap-3">
                                    @if($refund['status'] != 'refunded')
                                        @if($refund['status'] != 'rejected')
                                            <button class="btn bg-danger bg-opacity-10 text-danger p-2 px-3"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#rejectModal-{{$refund['id']}}">
                                                {{ translate('reject') }}
                                            </button>
                                        @endif
                                        @if($refund['status'] != 'approved')
                                            <button class="btn bg-primary bg-opacity-10 text-primary p-2 px-3"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#approveModal-{{ $refund['id'] }}">
                                                {{ translate('approve') }}
                                            </button>
                                        @endif
                                        <button class="btn bg-success bg-opacity-10 text-success p-2 px-3"
                                                data-bs-toggle="modal" data-bs-target="#refundModal-{{$refund['id']}}">
                                            {{ translate('refund') }}
                                        </button>
                                    @endif
                                </div>
                            </div>
                            <div class="refund-details justify-content-between">
                                <div
                                    @if (!$refund?->product)
                                        class="parent-disabled" data-bs-toggle="tooltip"
                                        title="{{ translate('Product_has_been_deleted') }}"
                                    @endif
                                >
                                    <div class="d-flex gap-3 flex-wrap align-items-start">
                                        <div class="img">
                                            <div class="onerror-image border rounded">
                                                <img class="aspect-1"
                                                     src="{{ getStorageImages(path: ($refund->product ? $refund->product->thumbnail_full_url : ''),type: 'backend-product') }}"
                                                     alt="">
                                            </div>
                                        </div>
                                        <div class="--content w-fit-content max-w-280">
                                            <h4>
                                                @if ($refund->product!=null)
                                                    <a href="{{ route('admin.products.view',['addedBy'=>($refund->product->added_by =='seller'?'vendor' : 'in-house'),'id'=>$refund->product->id]) }}">
                                                        {{ $refund->product->name}}
                                                    </a>
                                                @else
                                                    {{ translate('product_name_not_found') }}
                                                @endif
                                            </h4>
                                            @if ($refund->orderDetails->variant)
                                                <div class="font-size-sm text-body">
                                                    <strong><u>{{ translate('variation') }}</u></strong>
                                                    <span>:</span>
                                                    <span
                                                        class="font-weight-bold">{{ $refund->orderDetails->variant}}</span>
                                                </div>
                                            @endif
                                            @if($refund->orderDetails->digital_file_after_sell)
                                                @php($downloadPath =dynamicStorage(path: 'storage/app/public/product/digital-product/'.$refund->orderDetails->digital_file_after_sell))
                                                <a href="{{file_exists( $downloadPath) ?  $downloadPath : 'javascript:' }}"
                                                   class="btn btn-outline--primary btn-sm mt-3 {{file_exists( $downloadPath) ?  $downloadPath : 'download-path-not-found'}}"
                                                   title="{{ translate('download') }}">
                                                    {{ translate('download') }} <i class="tio-download"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                @php($refundDetailsSummery = \App\Utils\OrderManager::getRefundDetailsForSingleOrderDetails(orderDetailsId: $refund->orderDetails['id']))

                                <ul class="dm-info p-0 m-0 w-l-115">
                                    <li>
                                        <span class="left">{{ translate('QTY') }}</span>
                                        <span>:</span>
                                        <span class="right">
                                        <strong>
                                            {{ $refund->orderDetails->qty}}
                                        </strong>
                                    </span>
                                    </li>
                                    <li>
                                        <span class="left">{{ translate('total_price') }} </span>
                                        <span>:</span>
                                        <span class="right">
                                        <strong>
                                            {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $refund->orderDetails->price*$refund->orderDetails->qty), currencyCode: getCurrencyCode()) }}
                                        </strong>
                                    </span>
                                    </li>
                                    <li>
                                        <span class="left">{{ translate('total_discount') }} </span>
                                        <span>:</span>
                                        <span class="right">
                                        <strong>
                                            {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $refundDetailsSummery['product_discount']), currencyCode: getCurrencyCode()) }}
                                        </strong>
                                    </span>
                                    </li>
                                    <li>
                                        <span class="left">{{ translate('coupon_discount') }} </span>
                                        <span>:</span>
                                        <span class="right">
                                        <strong>
                                            {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $refundDetailsSummery['coupon_discount']), currencyCode: getCurrencyCode()) }}
                                        </strong>
                                    </span>
                                    </li>

                                    <li>
                                        <span class="left">{{ translate('Referral_discount') }} </span>
                                        <span>:</span>
                                        <span class="right">
                                        <strong>
                                            {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $refundDetailsSummery['referral_discount']), currencyCode: getCurrencyCode()) }}
                                        </strong>
                                    </span>
                                    </li>

                                    <li>
                                        <span class="left">{{ translate('total_tax') }} </span>
                                        <span>:</span>
                                        <span class="right">
                                        <strong>
                                            {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $refundDetailsSummery['tax']), currencyCode: getCurrencyCode()) }}
                                        </strong>
                                    </span>
                                    </li>

                                    <li>
                                        <span class="left">{{ translate('subtotal') }} </span>
                                        <span>:</span>
                                        <span class="right">
                                        <strong>
                                            {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $refundDetailsSummery['total_refundable_amount']), currencyCode: getCurrencyCode()) }}
                                        </strong>
                                    </span>
                                    </li>

                                    <li>
                                        <span class="left">{{ translate('refundable_amount') }} </span>
                                        <span>:</span>
                                        <span class="right">
                                            <strong>
                                                {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $refundDetailsSummery['total_refundable_amount']), currencyCode: getCurrencyCode()) }}
                                            </strong>
                                        </span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="{{ $order?->seller ? 'col-12 col-lg-6 col-xl-4' : 'col-lg-6 col-xl-4' }}">
                    <div class="card h-100 refund-details-card--2">
                        <div class="card-body">
                            <h4 class="mb-3 text-capitalize">{{ translate('refund_reason_by_customer') }}</h4>
                            <p>
                                {{ $refund->refund_reason }}
                            </p>
                            @if (count($refund->images_full_url) > 0)
                                <div class="gallery grid-gallery">
                                    @foreach ($refund->images_full_url as $key => $photo)
                                        <a href="{{ getStorageImages(path: $photo,type:'backend-basic') }}"
                                           data-lightbox="mygallery" class="d-flex">
                                            <img src="{{ getStorageImages(path: $photo,type:'backend-basic') }}"
                                                 width="65" alt="">
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                @if($order?->seller)
                    <div class="col-lg-6 col-xl-4">
                        <div class="card h-100 refund-details-card--2">
                            <div class="card-body">
                                <h4 class="mb-3 text-capitalize">{{ translate('vendor_info') }}</h4>
                                <div class="key-val-list d-flex flex-column gap-2 min-width--60px">
                                    <div class="key-val-list-item d-flex gap-2 overflow-wrap-anywhere">
                                        <span
                                            class="text-capitalize w-100px flex-shrink-0">{{ translate('shop_name') }}</span>:
                                        @if($order?->seller_is == 'seller')
                                            <span>{{ $order->seller?->shop->name ?? translate('no_data_found') }}</span>
                                        @else
                                            <span>{{ getInHouseShopConfig(key: 'name') }}</span>
                                        @endif
                                    </div>
                                    <div class="key-val-list-item d-flex gap-2 overflow-wrap-anywhere">
                                        <span
                                            class="text-capitalize w-100px flex-shrink-0">{{ translate('email_address') }}</span>:
                                        <span>
                                        @if($order?->seller_is == 'seller')
                                                <a class="text-dark" href="mailto:{{ $order->seller->email }}">
                                                {{ $order->seller?->email ?? translate('no_data_found') }}
                                            </a>
                                            @else
                                                <a class="text-dark"
                                                   href="mailto:{{ getWebConfig(name:'company_email') }}">
                                                {{ getWebConfig(name:'company_email') }}
                                            </a>
                                            @endif
                                    </span>
                                    </div>
                                    <div class="key-val-list-item d-flex gap-2 overflow-wrap-anywhere">
                                        <span
                                            class="text-capitalize w-100px flex-shrink-0">{{ translate('phone_number') }}</span>:
                                        <span>
                                        @if($order?->seller_is == 'seller')
                                                <a class="text-dark" href="tel:{{ $order->seller->phone }}">
                                                {{ $order->seller?->phone ?? translate('no_data_found') }}
                                            </a>
                                            @else
                                                <a class="text-dark"
                                                   href="tel:{{ getInHouseShopConfig(key: 'contact') }}">
                                                {{ getInHouseShopConfig(key: 'contact') }}
                                            </a>
                                            @endif
                                    </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                <div class="col-lg-6 col-xl-4">
                    <div class="card h-100 refund-details-card--2">
                        <div class="card-body">
                            <h4 class="mb-3 text-capitalize">{{ translate('deliveryman_info') }}</h4>
                            <div class="key-val-list d-flex flex-column gap-2 min-width--60px">
                                @if($order->deliveryMan)
                                    <div class="key-val-list-item d-flex gap-2 overflow-wrap-anywhere">
                                        <span
                                            class="text-capitalize w-100px flex-shrink-0">{{ translate('name') }}</span>:
                                        <span>{{ $order->deliveryMan->f_name . ' ' .$order->deliveryMan->l_name}}</span>
                                    </div>
                                    <div class="key-val-list-item d-flex gap-2 overflow-wrap-anywhere">
                                        <span
                                            class="text-capitalize w-100px flex-shrink-0">{{ translate('email_address') }}</span>:
                                        <span>
                                        <a class="text-dark"
                                           href="mailto:{{ $order->deliveryMan->email }}">{{ $order->deliveryMan?->email }}
                                        </a>
                                    </span>
                                    </div>
                                    <div class="key-val-list-item d-flex gap-2 overflow-wrap-anywhere">
                                        <span
                                            class="text-capitalize w-100px flex-shrink-0">{{ translate('phone_number') }} </span>:
                                        <span>
                                        <a class="text-dark"
                                           href="tel:{{ $order->deliveryMan->phone }}">{{ $order->deliveryMan?->phone }}
                                        </a>
                                    </span>
                                    </div>
                                @elseif($order->delivery_type)
                                    <div class="form-group">
                                        <div class="p-2 bg-light rounded">
                                            <div class="media m-1 gap-3">
                                                <img class="avatar rounded-circle"
                                                     src="{{ dynamicAsset(path: 'public/assets/new/back-end/img/third-party-delivery.png')}}"
                                                     alt="{{ translate('image') }}">
                                                <div class="media-body">
                                                    <h5 class="">{{ $order->delivery_service_name ?? translate('not_assign_yet') }}</h5>
                                                    <span
                                                        class="fs-12 text-dark">{{ translate('track_ID').' '.':'.' '.$order->third_party_delivery_tracking_id }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="p-2 bg-light rounded">
                                        <div class="media m-1 gap-3">
                                            <img class="avatar rounded-circle"
                                                 src="{{ dynamicAsset(path: 'public/assets/new/back-end/img/delivery-man.png') }}"
                                                 alt="{{ translate('image') }}">
                                            <div class="media-body">
                                                <h5 class="mt-3">{{ translate('no_delivery_man_assigned') }}</h5>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card refund-details-card--2">
                        <div class="card-body">
                            <div class="card-body d-flex flex-column gap-20">
                                <div class="d-flex justify-content-between align-items-center gap-20 flex-wrap">
                                    <h3 class="mb-0">{{ translate('refund_status_changed_log') }}</h3>
                                </div>
                                <div class="table-responsive">
                                    <table
                                        class="table table-hover text-center table-borderless align-middle">
                                        <thead class="text-capitalize">
                                        <tr>
                                            <th>{{ translate('SL') }}</th>
                                            <th>{{ translate('changed_by') }}</th>
                                            <th>{{ translate('Date') }}</th>
                                            <th>{{ translate('status') }}</th>
                                            <th>{{ translate('approved_/_rejected_note') }}</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach ($refund->refundStatus as $key=>$status)
                                            <tr>
                                                <td>
                                                    {{ $key+1}}
                                                </td>
                                                <td class="text-capitalize">
                                                    {{ $status->change_by == 'seller' ? 'vendor' : $status->change_by}}
                                                </td>
                                                <td>{{date('d M Y, h:s:A',strtotime($refund['created_at'])) }}</td>
                                                <td class="text-capitalize">
                                                    {{ translate($status->status) }}
                                                </td>
                                                <td class="text-break">
                                                    <div class="word-break max-w-360 mx-auto">
                                                        {{ $status->message}}
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                    @if(count($refund->refundStatus)==0)
                                        <div class="text-center p-4">
                                            <img class="mb-3 w-160"
                                                 src="{{ dynamicAsset(path: 'public/assets/new/back-end/img/empty-state-icon/default.png') }}"
                                                 alt="{{ translate('image_description') }}">
                                            <p class="mb-0">{{ translate('no_data_to_show') }}</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if($refund)
        @include('admin-views.refund.partials._approval-modal', ['refund' => $refund, 'walletStatus' => $walletStatus, 'walletAddRefund' => $walletAddRefund])
        @include('admin-views.refund.partials._reject-modal', ['refund' => $refund, 'walletStatus' => $walletStatus, 'walletAddRefund' => $walletAddRefund])
        @include('admin-views.refund.partials._refund-modal', ['refund' => $refund, 'walletStatus' => $walletStatus, 'walletAddRefund' => $walletAddRefund])
    @endif
@endsection

@push('script_2')
    <script src="{{ dynamicAsset(path: 'public/assets/back-end/js/admin/refund.js') }}"></script>
@endpush
