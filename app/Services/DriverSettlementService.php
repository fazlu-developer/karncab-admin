<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DriverSettlementService
{
    public function ensure(): void
    {
        $schema = Schema::connection('platform');
        if ($schema->hasTable('driver_settlements')) {
            return;
        }
        $schema->create('driver_settlements', function ($table) {
            $table->id();
            $table->unsignedBigInteger('booking_id')->index();
            $table->unsignedBigInteger('driver_id')->nullable()->index();
            $table->unsignedBigInteger('driver_user_id')->nullable();
            $table->unsignedInteger('payable_paise')->default(0);
            $table->unsignedInteger('paid_paise')->default(0);
            $table->string('status', 32)->default('pending')->index();
            $table->json('payouts')->nullable();
            $table->timestamps();
            $table->unique(['booking_id', 'driver_id']);
        });
    }

    public function rows(int $bookingId): array
    {
        $this->ensure();

        return DB::connection('platform')->table('driver_settlements')->where('booking_id', $bookingId)->orderBy('id')->get()->all();
    }

    public function record(User $admin, int $bookingId, int $amountPaise, string $method, string $reference): void
    {
        if ($amountPaise < 1) {
            throw new RuntimeException('Enter a settlement amount.');
        }
        $reference = trim($reference);
        if ($reference === '') {
            throw new RuntimeException('Enter the UTR or transaction reference.');
        }
        $this->ensure();
        $db = DB::connection('platform');
        $db->transaction(function () use ($db, $admin, $bookingId, $amountPaise, $method, $reference) {
            $row = $db->table('driver_settlements')->where('booking_id', $bookingId)->lockForUpdate()->orderBy('id')->first();
            if (! $row) {
                $booking = $db->table('bookings')->where('id', $bookingId)->first();
                if (! $booking || ! $booking->driver_id) {
                    throw new RuntimeException('Assign a driver before recording a settlement.');
                }
                $payable = (int) ($booking->driver_earning_paise ?? 0);
                if ($payable < 1) {
                    $fare = (int) ($booking->final_fare_paise ?? $booking->quote_paise ?? 0);
                    $commission = (int) ($booking->commission_paise ?? round($fare * 0.1));
                    $payable = max(0, $fare - $commission);
                }
                $id = $db->table('driver_settlements')->insertGetId([
                    'booking_id' => $bookingId,
                    'driver_id' => $booking->driver_id,
                    'driver_user_id' => $db->table('drivers')->where('id', $booking->driver_id)->value('user_id'),
                    'payable_paise' => $payable,
                    'paid_paise' => 0,
                    'status' => 'pending',
                    'payouts' => json_encode([]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $row = $db->table('driver_settlements')->where('id', $id)->lockForUpdate()->first();
            }
            $payouts = json_decode((string) ($row->payouts ?? '[]'), true) ?: [];
            foreach ($payouts as $past) {
                if (($past['reference'] ?? '') === $reference) {
                    throw new RuntimeException('This payment reference is already recorded.');
                }
            }
            $paid = (int) $row->paid_paise;
            $payable = (int) $row->payable_paise;
            if ($paid + $amountPaise > $payable) {
                throw new RuntimeException('That amount is more than the remaining driver payable.');
            }
            $paid += $amountPaise;
            $payouts[] = [
                'amountPaise' => $amountPaise,
                'method' => $method,
                'reference' => $reference,
                'adminId' => (int) $admin->id,
                'at' => now()->toIso8601String(),
            ];
            $db->table('driver_settlements')->where('id', $row->id)->update([
                'paid_paise' => $paid,
                'status' => $paid >= $payable ? 'fully_paid' : 'partially_paid',
                'payouts' => json_encode($payouts),
                'updated_at' => now(),
            ]);
        });
    }
}
