<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait CreatesPlatformSchema
{
    protected function setUpPlatformSchema(): void
    {
        $schema = Schema::connection('platform');

        $schema->dropAllTables();

        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('role');
            $table->string('status')->default('ACTIVE');
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('password_hash');
            $table->string('last_address')->nullable();
            $table->unsignedInteger('state_id')->nullable();
            $table->unsignedInteger('district_id')->nullable();
            $table->decimal('last_lat', 10, 7)->nullable();
            $table->decimal('last_lng', 10, 7)->nullable();
            $table->timestamp('location_updated_at')->nullable();
            $table->string('emergency_name')->nullable();
            $table->string('emergency_phone')->nullable();
            $table->timestamps();
        });
        $schema->create('drivers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('fleet_owner_id')->nullable();
            $table->boolean('online')->default(false);
            $table->string('duty_status')->default('offline');
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->string('city')->nullable();
            $table->string('license_no')->nullable();
            $table->string('kyc_status')->default('pending');
            $table->string('kyc_rejected_reason')->nullable();
            $table->string('emergency_name')->nullable();
            $table->string('emergency_phone')->nullable();
            $table->timestamps();
        });
        $schema->create('states', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        $schema->create('districts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('state_id');
            $table->string('name');
        });
        $schema->create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('public_ref');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->unsignedInteger('district_id')->nullable();
            $table->unsignedBigInteger('corporate_account_id')->nullable();
            $table->string('product')->default('LOCAL_CAB');
            $table->string('category')->default('SEDAN');
            $table->string('status')->default('REQUESTED');
            $table->string('pickup_text')->default('');
            $table->string('drop_text')->default('');
            $table->bigInteger('quote_paise')->nullable();
            $table->json('quote_snapshot')->nullable();
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->integer('coupon_discount_paise')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->boolean('booked_for_other')->default(false);
            $table->string('passenger_name')->nullable();
            $table->string('passenger_phone')->nullable();
            $table->unsignedBigInteger('family_member_id')->nullable();
            $table->string('start_otp')->nullable();
            $table->string('end_otp')->nullable();
            $table->string('share_token')->nullable()->unique();
            $table->timestamp('share_expires_at')->nullable();
            $table->decimal('pickup_lat', 10, 7)->nullable();
            $table->decimal('pickup_lng', 10, 7)->nullable();
            $table->decimal('drop_lat', 10, 7)->nullable();
            $table->decimal('drop_lng', 10, 7)->nullable();
            $table->decimal('distance_km', 10, 2)->nullable();
            $table->text('polyline')->nullable();
            $table->timestamp('return_at')->nullable();
            $table->string('flight_number')->nullable();
            $table->string('train_number')->nullable();
            $table->string('terminal')->nullable();
            $table->string('instructions')->nullable();
            $table->timestamp('trip_started_at')->nullable();
            $table->timestamp('trip_ended_at')->nullable();
            $table->timestamps();
        });
        foreach ([
            'wallet_ledger' => function (Blueprint $table) {
                $table->id();
                $table->string('public_ref')->unique();
                $table->unsignedBigInteger('wallet_id');
                $table->unsignedBigInteger('booking_id')->nullable();
                $table->unsignedBigInteger('owner_user_id')->nullable();
                $table->string('account')->nullable();
                $table->string('owner_type')->nullable();
                $table->string('direction')->nullable();
                $table->string('kind')->default('trip');
                $table->bigInteger('amount_paise')->default(0);
                $table->bigInteger('commission_paise')->default(0);
                $table->bigInteger('gross_paise')->default(0);
                $table->bigInteger('balance_before_paise')->default(0);
                $table->bigInteger('balance_after_paise')->default(0);
                $table->string('payment_ref')->nullable();
                $table->string('status')->default('posted');
                $table->string('note')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->unique(['booking_id', 'wallet_id', 'kind', 'direction'], 'wallet_ledger_booking_post_key');
            },
            'parcel_shipments' => function (Blueprint $table) {
                $table->id();
                $table->string('public_ref')->nullable();
                $table->string('status')->nullable();
                $table->string('pickup_text')->nullable();
                $table->string('drop_text')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('driver_id')->nullable();
                $table->unsignedBigInteger('vehicle_id')->nullable();
                $table->unsignedInteger('district_id')->nullable();
                $table->bigInteger('quote_paise')->nullable();
                $table->timestamps();
            },
            'bulk_bookings' => function (Blueprint $table) {
                $table->id();
                $table->string('public_ref')->nullable();
                $table->string('status')->nullable();
                $table->string('event_key')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
            },
            'fleet_owners' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('trade_name')->nullable();
                $table->string('gstin')->nullable();
            },
            'support_tickets' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('booking_id')->nullable();
                $table->unsignedInteger('district_id')->nullable();
                $table->unsignedBigInteger('assigned_agent_id')->nullable();
                $table->string('public_ref')->nullable();
                $table->string('kind')->nullable();
                $table->string('status')->default('open');
                $table->string('subject')->nullable();
                $table->string('category')->default('other');
                $table->string('description', 2000)->default('');
                $table->string('priority')->default('medium');
                $table->string('resolution', 2000)->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
            },
            'support_messages' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id');
                $table->unsignedBigInteger('author_id');
                $table->boolean('from_staff')->default(false);
                $table->string('body', 2000);
                $table->timestamp('created_at')->nullable();
            },
            'support_attachments' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id');
                $table->string('storage_key');
                $table->string('mime')->nullable();
                $table->string('original_name')->nullable();
                $table->integer('bytes')->default(0);
                $table->timestamp('created_at')->nullable();
            },
            'support_ticket_events' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id');
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->string('action');
                $table->string('from_status')->nullable();
                $table->string('to_status')->nullable();
                $table->string('note', 500)->nullable();
                $table->timestamp('created_at')->nullable();
            },
            'driver_documents' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('driver_id');
                $table->string('type');
                $table->string('status')->default('pending');
                $table->string('storage_key')->nullable();
                $table->string('original_name')->nullable();
                $table->string('mime')->nullable();
                $table->integer('size_bytes')->default(0);
                $table->string('checksum_sha256')->nullable();
                $table->date('expires_at')->nullable();
                $table->timestamps();
            },
            'ad_campaigns' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('advertiser_user_id')->nullable();
                $table->string('title')->nullable();
                $table->string('status')->default('pending');
                $table->string('business_name')->nullable();
                $table->text('business_info')->nullable();
                $table->string('category')->default('local_businesses');
                $table->string('campaign_type')->default('banner');
                $table->string('banner_key')->nullable();
                $table->string('banner_mime')->nullable();
                $table->string('target_city')->nullable();
                $table->unsignedInteger('state_id')->nullable();
                $table->unsignedInteger('district_id')->nullable();
                $table->timestamp('starts_on')->nullable();
                $table->timestamp('ends_on')->nullable();
                $table->bigInteger('budget_paise')->default(0);
                $table->bigInteger('budget_used_paise')->default(0);
                $table->integer('impressions')->default(0);
                $table->integer('clicks')->default(0);
                $table->string('cta_url')->nullable();
                $table->string('rejected_reason')->nullable();
                $table->unsignedBigInteger('reviewed_by_id')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
            },
            'ad_events' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('campaign_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('kind');
                $table->string('placement');
                $table->timestamp('created_at')->nullable();
            },
            'safety_incidents' => function (Blueprint $table) {
                $table->id();
                $table->string('public_ref')->nullable();
                $table->string('type')->nullable();
                $table->string('kind')->nullable();
                $table->string('status')->default('open');
                $table->string('description')->nullable();
                $table->unsignedBigInteger('reporter_user_id')->nullable();
                $table->unsignedBigInteger('booking_id')->nullable();
                $table->unsignedBigInteger('driver_id')->nullable();
                $table->unsignedInteger('district_id')->nullable();
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();
                $table->string('location_text')->nullable();
                $table->string('actor_role')->nullable();
                $table->string('emergency_name')->nullable();
                $table->string('emergency_phone')->nullable();
                $table->string('admin_note')->nullable();
                $table->timestamps();
            },
            'vehicles' => function (Blueprint $table) {
                $table->id();
                $table->string('registration_no')->nullable()->unique();
                $table->string('status')->nullable();
                $table->string('category')->nullable();
                $table->unsignedInteger('district_id')->nullable();
                $table->unsignedBigInteger('fleet_owner_id')->nullable();
                $table->unsignedBigInteger('driver_id')->nullable();
                $table->string('brand')->nullable();
                $table->string('model')->nullable();
                $table->unsignedInteger('year')->nullable();
                $table->string('color')->nullable();
                $table->string('fuel')->nullable();
                $table->decimal('last_lat', 10, 7)->nullable();
                $table->decimal('last_lng', 10, 7)->nullable();
                $table->timestamp('last_fix_at')->nullable();
                $table->timestamps();
            },
            'vehicle_documents' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vehicle_id');
                $table->string('type');
                $table->string('status')->default('pending');
                $table->string('storage_key')->nullable();
                $table->string('original_name')->nullable();
                $table->string('mime')->nullable();
                $table->integer('size_bytes')->default(0);
                $table->string('checksum_sha256')->nullable();
                $table->date('expires_at')->nullable();
                $table->timestamps();
            },
            'payments' => function (Blueprint $table) {
                $table->id();
                $table->string('public_ref')->nullable();
                $table->unsignedBigInteger('invoice_id')->nullable();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('method')->nullable();
                $table->string('kind')->default('payment');
                $table->string('intent')->default('capture');
                $table->bigInteger('amount_paise')->default(0);
                $table->string('status')->nullable();
                $table->string('gateway')->nullable();
                $table->string('gateway_order_id')->nullable();
                $table->string('gateway_payment_id')->nullable();
                $table->string('failure_code')->nullable();
                $table->string('failure_note')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->string('verified_source')->nullable();
                $table->unsignedInteger('attempt_no')->default(1);
                $table->string('note')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('booking_id')->nullable();
                $table->timestamps();
            },
            'invoices' => function (Blueprint $table) {
                $table->id();
                $table->string('public_ref')->unique();
                $table->string('kind')->default('ride');
                $table->unsignedBigInteger('customer_id');
                $table->unsignedBigInteger('booking_id')->nullable();
                $table->string('status')->default('issued');
                $table->string('currency')->default('INR');
                $table->bigInteger('subtotal_paise')->default(0);
                $table->bigInteger('tax_paise')->default(0);
                $table->bigInteger('total_paise')->default(0);
                $table->bigInteger('paid_paise')->default(0);
                $table->bigInteger('refunded_paise')->default(0);
                $table->json('lines')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamps();
            },
            'payment_events' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payment_id');
                $table->string('source');
                $table->string('event_type');
                $table->string('gateway_event_id')->nullable()->unique();
                $table->json('payload')->nullable();
                $table->boolean('signature_valid')->default(false);
                $table->timestamp('created_at')->nullable();
            },
            'wallets' => function (Blueprint $table) {
                $table->id();
                $table->string('owner_type')->nullable();
                $table->unsignedBigInteger('owner_user_id')->nullable();
                $table->bigInteger('balance_paise')->default(0);
                $table->unique(['owner_type', 'owner_user_id']);
            },
            'booking_ratings' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('booking_id')->nullable();
                $table->integer('stars')->default(0);
                $table->string('from_role')->nullable();
                $table->string('comment')->nullable();
                $table->timestamp('created_at')->nullable();
            },
            'corporate_accounts' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('owner_user_id')->nullable();
                $table->string('company_name')->nullable();
                $table->string('gstin')->nullable();
                $table->string('status')->nullable();
            },
            'coupons' => function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('title')->nullable();
                $table->string('subtitle')->nullable();
                $table->string('kind')->default('percent');
                $table->integer('percent')->default(0);
                $table->integer('amount_paise')->default(0);
                $table->integer('max_discount_paise')->default(0);
                $table->integer('min_fare_paise')->default(0);
                $table->string('product')->nullable();
                $table->unsignedInteger('state_id')->nullable();
                $table->unsignedInteger('district_id')->nullable();
                $table->string('audience')->default('all');
                $table->integer('usage_limit')->default(0);
                $table->integer('user_limit')->default(0);
                $table->timestamp('starts_on')->nullable();
                $table->timestamp('ends_on')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            },
            'coupon_redemptions' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coupon_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('booking_id')->nullable()->unique();
                $table->integer('discount_paise')->default(0);
                $table->timestamp('created_at')->nullable();
            },
            'loyalty_accounts' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->integer('points')->default(0);
                $table->timestamps();
            },
            'loyalty_ledger' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('booking_id')->nullable();
                $table->string('kind');
                $table->integer('points')->default(0);
                $table->timestamp('created_at')->nullable();
            },
            'system_settings' => function (Blueprint $table) {
                $table->id();
                $table->string('key');
                $table->text('value')->nullable();
            },
            'platform_audit_events' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->string('domain')->nullable();
                $table->string('action')->nullable();
                $table->string('entity_type')->nullable();
                $table->string('entity_id')->nullable();
                $table->timestamp('created_at')->nullable();
            },
            'user_notifications' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('title');
                $table->string('body');
                $table->string('kind')->nullable();
                $table->string('entity_type')->nullable();
                $table->string('entity_id')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('created_at')->nullable();
            },
            'notification_deliveries' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('event');
                $table->string('channel');
                $table->string('title');
                $table->string('body');
                $table->string('status')->default('sent');
                $table->string('provider_note')->nullable();
                $table->string('entity_type')->nullable();
                $table->string('entity_id')->nullable();
                $table->timestamp('created_at')->nullable();
            },
            'push_devices' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('token')->nullable();
                $table->string('platform')->nullable();
            },
            'franchises' => function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('district_id');
                $table->unsignedInteger('state_id');
                $table->unsignedBigInteger('owner_user_id');
                $table->unsignedBigInteger('parent_user_id')->nullable();
                $table->string('kind')->default('EXCLUSIVE_FRANCHISE');
                $table->string('status')->default('APPLIED');
                $table->string('active_district_key', 16)->nullable()->unique();
                $table->string('trade_name')->default('');
                $table->string('gstin')->nullable();
                $table->string('pan')->nullable();
                $table->string('contact_phone')->nullable();
                $table->string('kyc_status')->default('pending');
                $table->string('agreement_status')->default('unsigned');
                $table->bigInteger('fee_amount_paise')->default(0);
                $table->timestamp('fee_paid_at')->nullable();
                $table->decimal('commission_percent', 5, 2)->default(0);
                $table->timestamp('starts_on')->nullable();
                $table->timestamp('ends_on')->nullable();
                $table->timestamp('terminated_at')->nullable();
                $table->string('termination_reason')->nullable();
                $table->string('notes')->nullable();
                $table->timestamps();
            },
            'franchise_documents' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('franchise_id');
                $table->string('type');
                $table->string('status')->default('pending');
                $table->string('storage_key')->unique();
                $table->string('original_name')->nullable();
                $table->string('mime')->nullable();
                $table->integer('size_bytes')->default(0);
                $table->string('checksum_sha256')->nullable();
                $table->date('expires_at')->nullable();
                $table->string('rejection_reason')->nullable();
                $table->timestamps();
            },
            'franchise_agreements' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('franchise_id');
                $table->string('version');
                $table->string('title');
                $table->timestamp('signed_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('status')->default('draft');
                $table->timestamp('created_at')->nullable();
            },
            'franchise_fees' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('franchise_id');
                $table->string('kind');
                $table->bigInteger('amount_paise')->default(0);
                $table->string('status')->default('due');
                $table->timestamp('due_on')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->string('note')->nullable();
                $table->timestamp('created_at')->nullable();
            },
            'franchise_renewals' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('franchise_id');
                $table->timestamp('period_start');
                $table->timestamp('period_end');
                $table->string('status')->default('pending');
                $table->timestamp('created_at')->nullable();
            },
            'franchise_events' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('franchise_id');
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->string('action');
                $table->string('from_status')->nullable();
                $table->string('to_status')->nullable();
                $table->string('note')->nullable();
                $table->timestamp('created_at')->nullable();
            },
            'fare_rules' => function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('district_id')->nullable();
                $table->string('product')->nullable();
                $table->string('category')->default('SEDAN');
                $table->decimal('min_km', 8, 2)->default(1);
                $table->decimal('included_km', 8, 2)->default(1);
                $table->integer('per_km_paise')->default(0);
                $table->integer('extra_km_paise')->default(0);
                $table->integer('waiting_paise_per_min')->default(0);
                $table->integer('night_percent')->default(0);
                $table->integer('gst_percent')->default(0);
                $table->integer('cancel_paise')->default(0);
                $table->integer('discount_paise')->default(0);
                $table->integer('discount_percent')->default(0);
                $table->integer('driver_allow_paise')->default(0);
                $table->integer('rental_hours')->nullable();
                $table->integer('extra_hour_paise')->default(15000);
                $table->integer('night_stay_paise')->default(0);
                $table->integer('stop_paise')->default(0);
                $table->boolean('apply_toll')->default(true);
                $table->boolean('apply_parking')->default(true);
                $table->boolean('apply_gst_to_base')->default(true);
                $table->boolean('active')->default(true);
                $table->timestamps();
            },
            'commission_rules' => function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->decimal('percent', 5, 2);
                $table->boolean('on_base_fare')->default(true);
                $table->boolean('on_gst')->default(false);
                $table->boolean('on_toll')->default(false);
                $table->boolean('on_parking')->default(false);
                $table->boolean('on_waiting')->default(false);
                $table->boolean('on_discount')->default(false);
                $table->boolean('on_other')->default(false);
                $table->boolean('on_complete')->default(false);
                $table->boolean('active')->default(true);
                $table->timestamps();
            },
            'travel_packages' => function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable();
                $table->string('status')->nullable();
            },
            'travel_bookings' => function (Blueprint $table) {
                $table->id();
                $table->string('public_ref')->nullable();
                $table->string('status')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
            },
            'user_places' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('kind')->default('SAVED');
                $table->string('title');
                $table->string('subtitle')->nullable();
                $table->string('address');
                $table->decimal('lat', 10, 7)->default(0);
                $table->decimal('lng', 10, 7)->default(0);
                $table->timestamp('created_at')->nullable();
            },
            'family_members' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('name');
                $table->string('phone');
                $table->string('relation')->nullable();
                $table->timestamps();
            },
            'emergency_contacts' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('name');
                $table->string('phone');
                $table->string('relation')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
            },
        ] as $table => $fn) {
            $schema->create($table, $fn);
        }
    }
}
