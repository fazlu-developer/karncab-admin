<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class TravelPackageSchema
{
    public static function ensure(?string $connection = 'platform'): void
    {
        $schema = filled($connection) ? Schema::connection($connection) : Schema::getConnection()->getSchemaBuilder();
        if (! $schema->hasTable('travel_packages')) {
            $schema->create('travel_packages', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable();
                $table->string('name')->nullable();
                $table->string('destination')->nullable();
                $table->bigInteger('price_paise')->default(0);
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }
        $cols = [
            'category' => fn (Blueprint $t) => $t->string('category', 40)->nullable(),
            'origin' => fn (Blueprint $t) => $t->string('origin', 120)->nullable(),
            'region' => fn (Blueprint $t) => $t->string('region', 80)->nullable(),
            'duration_label' => fn (Blueprint $t) => $t->string('duration_label', 40)->nullable(),
            'duration_hours' => fn (Blueprint $t) => $t->unsignedInteger('duration_hours')->nullable(),
            'nights' => fn (Blueprint $t) => $t->unsignedInteger('nights')->nullable(),
            'km_included' => fn (Blueprint $t) => $t->unsignedInteger('km_included')->nullable(),
            'vehicle_label' => fn (Blueprint $t) => $t->string('vehicle_label', 80)->nullable(),
            'vehicle_category' => fn (Blueprint $t) => $t->string('vehicle_category', 40)->nullable(),
            'driver_label' => fn (Blueprint $t) => $t->string('driver_label', 80)->nullable(),
            'min_pax' => fn (Blueprint $t) => $t->unsignedInteger('min_pax')->default(2),
            'places' => fn (Blueprint $t) => $t->text('places')->nullable(),
            'inclusions' => fn (Blueprint $t) => $t->text('inclusions')->nullable(),
            'exclusions' => fn (Blueprint $t) => $t->text('exclusions')->nullable(),
            'highlights' => fn (Blueprint $t) => $t->text('highlights')->nullable(),
            'itinerary' => fn (Blueprint $t) => $t->text('itinerary')->nullable(),
            'available_dates' => fn (Blueprint $t) => $t->text('available_dates')->nullable(),
            'gallery' => fn (Blueprint $t) => $t->text('gallery')->nullable(),
            'image_url' => fn (Blueprint $t) => $t->string('image_url', 255)->nullable(),
            'popular' => fn (Blueprint $t) => $t->boolean('popular')->default(false),
        ];
        foreach ($cols as $name => $define) {
            if (! $schema->hasColumn('travel_packages', $name)) {
                $schema->table('travel_packages', $define);
            }
        }
        if (! $schema->hasTable('travel_bookings')) {
            $schema->create('travel_bookings', function (Blueprint $table) {
                $table->id();
                $table->string('public_ref', 40)->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('package_id')->nullable();
                $table->date('travel_date')->nullable();
                $table->unsignedInteger('guests')->default(1);
                $table->string('status', 32)->nullable();
                $table->bigInteger('quote_paise')->default(0);
                $table->timestamps();
            });
        }
        if ($schema->hasTable('travel_bookings')) {
            $bookingCols = [
                'pickup_text' => fn (Blueprint $t) => $t->string('pickup_text', 180)->nullable(),
                'pickup_lat' => fn (Blueprint $t) => $t->decimal('pickup_lat', 10, 7)->nullable(),
                'pickup_lng' => fn (Blueprint $t) => $t->decimal('pickup_lng', 10, 7)->nullable(),
                'return_date' => fn (Blueprint $t) => $t->date('return_date')->nullable(),
                'passengers' => fn (Blueprint $t) => $t->text('passengers')->nullable(),
                'driver_id' => fn (Blueprint $t) => $t->unsignedBigInteger('driver_id')->nullable(),
                'vehicle_id' => fn (Blueprint $t) => $t->unsignedBigInteger('vehicle_id')->nullable(),
                'ride_booking_id' => fn (Blueprint $t) => $t->unsignedBigInteger('ride_booking_id')->nullable(),
                'ticket_no' => fn (Blueprint $t) => $t->string('ticket_no', 40)->nullable(),
                'invoice_no' => fn (Blueprint $t) => $t->string('invoice_no', 40)->nullable(),
                'start_otp' => fn (Blueprint $t) => $t->string('start_otp', 8)->nullable(),
                'advance_paid_paise' => fn (Blueprint $t) => $t->bigInteger('advance_paid_paise')->default(0),
                'advance_payment_ref' => fn (Blueprint $t) => $t->string('advance_payment_ref', 64)->nullable(),
                'platform_commission_paise' => fn (Blueprint $t) => $t->bigInteger('platform_commission_paise')->default(0),
                'driver_advance_paise' => fn (Blueprint $t) => $t->bigInteger('driver_advance_paise')->default(0),
                'collected_outside_paise' => fn (Blueprint $t) => $t->bigInteger('collected_outside_paise')->default(0),
                'reject_reason' => fn (Blueprint $t) => $t->string('reject_reason', 240)->nullable(),
                'approved_at' => fn (Blueprint $t) => $t->timestamp('approved_at')->nullable(),
                'assigned_at' => fn (Blueprint $t) => $t->timestamp('assigned_at')->nullable(),
                'started_at' => fn (Blueprint $t) => $t->timestamp('started_at')->nullable(),
                'completed_at' => fn (Blueprint $t) => $t->timestamp('completed_at')->nullable(),
                'contact_name' => fn (Blueprint $t) => $t->string('contact_name', 120)->nullable(),
                'contact_phone' => fn (Blueprint $t) => $t->string('contact_phone', 20)->nullable(),
                'payment_status' => fn (Blueprint $t) => $t->string('payment_status', 24)->nullable(),
                'payment_method' => fn (Blueprint $t) => $t->string('payment_method', 24)->nullable(),
            ];
            foreach ($bookingCols as $name => $define) {
                if (! $schema->hasColumn('travel_bookings', $name)) {
                    $schema->table('travel_bookings', $define);
                }
            }
        }
    }
}
