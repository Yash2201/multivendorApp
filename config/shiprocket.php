<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shiprocket API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Shiprocket shipping integration. Register at
    | https://app.shiprocket.in/ to get your credentials.
    |
    */

    'email' => env('SHIPROCKET_EMAIL', ''),
    'password' => env('SHIPROCKET_PASSWORD', ''),

    'base_url' => env('SHIPROCKET_BASE_URL', 'https://apiv2.shiprocket.in/v1/external'),

    /*
    |--------------------------------------------------------------------------
    | Token Cache Duration
    |--------------------------------------------------------------------------
    |
    | Shiprocket tokens are valid for 10 days. We cache for 9 days to be safe.
    | Value is in minutes: 9 days = 12960 minutes.
    |
    */

    'token_cache_minutes' => env('SHIPROCKET_TOKEN_CACHE_MINUTES', 12960),

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Token used to validate incoming webhook requests from Shiprocket.
    | Set this to a strong random string and configure the same in
    | Shiprocket dashboard under Settings > API > Webhooks.
    |
    */

    'webhook_token' => env('SHIPROCKET_WEBHOOK_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Default Pickup Location
    |--------------------------------------------------------------------------
    |
    | The default pickup location name as configured in your Shiprocket
    | dashboard. Each vendor can optionally have their own pickup location.
    |
    */

    'default_pickup_location' => env('SHIPROCKET_DEFAULT_PICKUP_LOCATION', 'Primary'),

    /*
    |--------------------------------------------------------------------------
    | Default Destination State
    |--------------------------------------------------------------------------
    |
    | Used only when an older order address has no valid state saved. Shiprocket
    | requires a real Indian state name while creating an order.
    |
    */

    'default_state' => env('SHIPROCKET_DEFAULT_STATE', 'Gujarat'),

    /*
    |--------------------------------------------------------------------------
    | Auto-assign Courier
    |--------------------------------------------------------------------------
    |
    | When true, Shiprocket will automatically select the best courier
    | based on price and rating. When false, vendors choose manually.
    |
    */

    'auto_assign_courier' => env('SHIPROCKET_AUTO_ASSIGN_COURIER', true),

    /*
    |--------------------------------------------------------------------------
    | Status Sync Interval
    |--------------------------------------------------------------------------
    |
    | How often (in minutes) the cron job should sync shipment statuses.
    | Default is 30 minutes. Webhooks provide real-time updates as primary.
    |
    */

    'sync_interval_minutes' => env('SHIPROCKET_SYNC_INTERVAL', 30),

    /*
    |--------------------------------------------------------------------------
    | Shiprocket Status to Order Status Mapping
    |--------------------------------------------------------------------------
    |
    | Maps Shiprocket shipment statuses to your application's order statuses.
    | Modify these mappings to match your business workflow.
    |
    */

    'status_mapping' => [
        // Shiprocket numeric status codes => 6valley order statuses
        1  => 'confirmed',        // AWB Assigned
        2  => 'confirmed',        // Label Generated
        3  => 'confirmed',        // Pickup Scheduled / Generated
        4  => 'confirmed',        // Pickup Queued
        5  => 'confirmed',        // Manifest Generated
        6  => 'processing',       // Shipped / Picked Up
        7  => 'out_for_delivery', // Delivered (we use separate check)
        8  => 'canceled',         // Cancelled
        9  => 'returned',         // RTO Initiated
        10 => 'returned',         // RTO Delivered
        12 => 'processing',       // Lost
        13 => 'processing',       // Pickup Error
        14 => 'returned',         // RTO Acknowledged
        15 => 'processing',       // Pickup Rescheduled
        16 => 'canceled',         // Cancellation Requested
        17 => 'out_for_delivery', // Out For Delivery
        18 => 'processing',       // In Transit
        19 => 'processing',       // Out For Pickup
        20 => 'processing',       // Pickup Exception
        21 => 'processing',       // Undelivered
        22 => 'processing',       // Delayed
        23 => 'processing',       // Partial Delivered
        24 => 'processing',       // Destroyed
        25 => 'processing',       // Damaged
        26 => 'processing',       // Fulfilled
        38 => 'processing',       // Reached at Destination Hub
        39 => 'processing',       // Misrouted
        40 => 'returned',         // RTO_NDR
        41 => 'returned',         // RTO_OFD
        42 => 'processing',       // Picked Up
        43 => 'processing',       // Self Fulfilled
        44 => 'processing',       // Disposed Off
        45 => 'canceled',         // Cancelled Before Dispatching
        46 => 'returned',         // RTO In Intransit
        47 => 'processing',       // QC Failed
        48 => 'processing',       // Reached Warehouse
        49 => 'processing',       // Custom Cleared
        50 => 'processing',       // In Flight
        51 => 'processing',       // Handover to Courier
        52 => 'processing',       // Shipment Booked
        53 => 'processing',       // In Transit Overseas
        54 => 'processing',       // Connection Aligned
        55 => 'processing',       // Reached Destination Country
        56 => 'processing',       // Custom Cleared Destination Country
        57 => 'out_for_delivery', // Dispatch from Destination Warehouse
        58 => 'processing',       // NDR - Contact Customer
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivered Status Codes
    |--------------------------------------------------------------------------
    |
    | Shiprocket status codes that indicate the shipment is delivered.
    | These trigger the 'delivered' order status in the application.
    |
    */

    'delivered_status_codes' => [7],

];
