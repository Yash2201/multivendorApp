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
        Schema::create('shiprocket_pickup_addresses', function (Blueprint $table) {
            $table->id();

            // Owner scope: null = admin / in-house, otherwise the vendor (seller) it belongs to.
            // This column is the ONLY real isolation boundary — Shiprocket itself uses one
            // shared account, so pickup addresses are scoped per vendor here, not on their side.
            $table->unsignedBigInteger('seller_id')->nullable();

            // Unique nickname registered with Shiprocket (their `pickup_location` value).
            // Namespaced per owner to avoid cross-vendor collisions on the shared account.
            $table->string('pickup_nickname', 36)->unique();

            // Shiprocket "add pickup" address fields (mirrors POST /settings/company/addpickup)
            $table->string('name');                 // shipper / contact person name
            $table->string('email');
            $table->string('phone', 20);
            $table->string('address');
            $table->string('address_2')->nullable();
            $table->string('city', 60);
            $table->string('state', 60);
            $table->string('country', 60)->default('India');
            $table->string('pin_code', 10);

            // One default per owner scope, pre-selected in the shipment picker.
            $table->boolean('is_default')->default(false);

            // Whether the address was successfully registered on Shiprocket.
            $table->boolean('is_synced')->default(false);

            // Diagnostics, mirroring the shiprocket_shipments table conventions.
            $table->text('last_error')->nullable();
            $table->json('raw_response')->nullable();

            $table->timestamps();

            $table->index('seller_id');
            $table->index('is_default');

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
        Schema::dropIfExists('shiprocket_pickup_addresses');
    }
};
