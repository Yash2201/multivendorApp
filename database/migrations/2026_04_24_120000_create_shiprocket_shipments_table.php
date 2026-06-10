<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('shiprocket_shipments', function (Blueprint $table) {
            $table->id();

            // Foreign keys to existing tables
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('seller_id')->nullable();

            // Shiprocket identifiers
            $table->string('shiprocket_order_id')->nullable()->index();
            $table->string('shiprocket_shipment_id')->nullable()->index();
            $table->string('shiprocket_channel_order_id')->nullable();
            $table->string('awb_code')->nullable()->index();

            // Courier details
            $table->unsignedBigInteger('courier_id')->nullable();
            $table->string('courier_name')->nullable();

            // Shipment status
            $table->string('shipment_status')->default('pending');
            $table->integer('shiprocket_status_code')->nullable();

            // Tracking & delivery
            $table->text('tracking_url')->nullable();
            $table->date('estimated_delivery_date')->nullable();
            $table->datetime('pickup_scheduled_date')->nullable();
            $table->datetime('delivered_date')->nullable();

            // Documents
            $table->text('label_url')->nullable();
            $table->text('manifest_url')->nullable();
            $table->text('invoice_url')->nullable();

            // Package details (sent to Shiprocket)
            $table->decimal('package_weight', 8, 2)->nullable();
            $table->decimal('package_length', 8, 2)->nullable();
            $table->decimal('package_breadth', 8, 2)->nullable();
            $table->decimal('package_height', 8, 2)->nullable();

            // Pickup location
            $table->string('pickup_location')->nullable();

            // Shipping cost from courier
            $table->decimal('shipping_charge', 10, 2)->default(0);
            $table->decimal('cod_charge', 10, 2)->default(0);

            // Error tracking
            $table->text('last_error')->nullable();
            $table->integer('retry_count')->default(0);

            // Raw API response for debugging
            $table->json('raw_response')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index('seller_id');
            $table->index('shipment_status');
            $table->index(['order_id', 'seller_id']);

            // Foreign key constraints
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('seller_id')->references('id')->on('sellers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('shiprocket_shipments');
    }
};
