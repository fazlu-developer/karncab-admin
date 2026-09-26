<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CorporatePlanSchema
{
    public static function ensure(?string $connection = 'platform'): void
    {
        $schema = Schema::connection($connection);
        $db = DB::connection($connection);
        if (! $schema->hasTable('corporate_plans')) {
            $schema->create('corporate_plans', function (Blueprint $table) {
                $table->id();
                $table->string('plan_key', 40)->nullable()->index();
                $table->string('title', 120);
                $table->string('subtitle', 180)->nullable();
                $table->string('pricing_mode', 24)->default('VEHICLE');
                $table->unsignedBigInteger('price_paise')->default(0);
                $table->string('price_label', 80)->nullable();
                $table->decimal('gst_percent', 5, 2)->default(5);
                $table->text('highlights')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('status', 24)->default('PUBLISHED');
                $table->timestamps();
            });
        }
        foreach ([
            'plan_key' => fn (Blueprint $t) => $t->string('plan_key', 40)->nullable(),
            'subtitle' => fn (Blueprint $t) => $t->string('subtitle', 180)->nullable(),
            'pricing_mode' => fn (Blueprint $t) => $t->string('pricing_mode', 24)->default('VEHICLE'),
            'price_paise' => fn (Blueprint $t) => $t->unsignedBigInteger('price_paise')->default(0),
            'price_label' => fn (Blueprint $t) => $t->string('price_label', 80)->nullable(),
            'gst_percent' => fn (Blueprint $t) => $t->decimal('gst_percent', 5, 2)->default(5),
            'highlights' => fn (Blueprint $t) => $t->text('highlights')->nullable(),
            'sort_order' => fn (Blueprint $t) => $t->unsignedInteger('sort_order')->default(0),
            'status' => fn (Blueprint $t) => $t->string('status', 24)->default('PUBLISHED'),
        ] as $name => $define) {
            if (! $schema->hasColumn('corporate_plans', $name)) {
                $schema->table('corporate_plans', $define);
            }
        }
        if (! $schema->hasTable('corporate_travel_bookings')) {
            $schema->create('corporate_travel_bookings', function (Blueprint $table) {
                $table->id();
                $table->string('public_ref', 24)->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->unsignedBigInteger('corporate_account_id')->nullable();
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->string('plan_key', 40)->nullable();
                $table->string('category', 40)->nullable();
                $table->string('pickup_text', 220)->nullable();
                $table->string('drop_text', 220)->nullable();
                $table->date('travel_date')->nullable();
                $table->string('pickup_time', 8)->nullable();
                $table->unsignedBigInteger('quote_paise')->nullable();
                $table->string('status', 32)->nullable();
                $table->timestamps();
            });
        }
        if ($db->table('corporate_plans')->count() === 0) {
            $now = now();
            $db->table('corporate_plans')->insert([
                [
                    'plan_key' => 'ON_DEMAND',
                    'title' => 'On-Demand Booking',
                    'subtitle' => 'Instant booking for business travel',
                    'pricing_mode' => 'VEHICLE',
                    'price_paise' => 0,
                    'price_label' => null,
                    'gst_percent' => 5,
                    'highlights' => json_encode(['Instant booking for business travel', 'Pay per trip', 'GST invoice available']),
                    'sort_order' => 1,
                    'status' => 'PUBLISHED',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'plan_key' => 'MONTHLY',
                    'title' => 'Monthly Plan',
                    'subtitle' => 'Fixed monthly billing',
                    'pricing_mode' => 'FIXED',
                    'price_paise' => 0,
                    'price_label' => null,
                    'gst_percent' => 5,
                    'highlights' => json_encode(['Fixed monthly billing', 'Best rates for regular travel', 'Dedicated account manager']),
                    'sort_order' => 2,
                    'status' => 'PUBLISHED',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'plan_key' => 'EMPLOYEE',
                    'title' => 'Employee Transport',
                    'subtitle' => 'Daily office commute',
                    'pricing_mode' => 'QUOTE',
                    'price_paise' => 0,
                    'price_label' => 'Custom Get Quote',
                    'gst_percent' => 5,
                    'highlights' => json_encode(['Daily pickup & drop', 'Multiple stops support', 'Route management']),
                    'sort_order' => 3,
                    'status' => 'PUBLISHED',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'plan_key' => 'EVENT_BULK',
                    'title' => 'Event & Bulk Travel',
                    'subtitle' => 'Conferences, training, events',
                    'pricing_mode' => 'QUOTE',
                    'price_paise' => 0,
                    'price_label' => 'Custom Get Quote',
                    'gst_percent' => 5,
                    'highlights' => json_encode(['Conferences, training, events', 'Multiple vehicles', 'Dedicated support']),
                    'sort_order' => 4,
                    'status' => 'PUBLISHED',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }
}
