<?php
namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function handleMatchRefund($userId, $matchId, $amount, $reason)
    {
        return DB::transaction(function() use ($userId, $matchId, $amount, $reason) {
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $userId],
                ['balance' => 0, 'status' => 'active']
            );

            // Crear la transacción de reembolso
            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'credit',
                'amount' => $amount,
                'description' => "Reembolso por partido cancelado: $reason",
                'source' => 'match_refund',
                'source_reference' => $matchId,
                'metadata' => [
                    'match_id' => $matchId,
                    'refund_reason' => $reason,
                    'original_payment_date' => now()
                ]
            ]);

            // Actualizar el balance
            $wallet->increment('balance', $amount);

            return $transaction;
        });
    }

    public function addReward($userId, $amount, $activityType, $activityId)
    {
        return DB::transaction(function() use ($userId, $amount, $activityType, $activityId) {
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $userId],
                ['balance' => 0, 'status' => 'active']
            );

            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'credit',
                'amount' => $amount,
                'description' => "Recompensa por $activityType",
                'source' => 'reward',
                'source_reference' => $activityId,
                'metadata' => [
                    'activity_type' => $activityType,
                    'activity_id' => $activityId
                ]
            ]);

            $wallet->increment('balance', $amount);

            return $transaction;
        });
    }

    public function useForMatchPayment($userId, $matchId, $amount)
    {
        return DB::transaction(function() use ($userId, $matchId, $amount) {
            $wallet = Wallet::where('user_id', $userId)->firstOrFail();

            if ($wallet->balance < $amount) {
                throw new \Exception('Saldo insuficiente en el monedero');
            }

            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'debit',
                'amount' => $amount,
                'description' => "Pago de partido",
                'source' => 'payment',
                'source_reference' => $matchId,
                'metadata' => [
                    'match_id' => $matchId,
                    'payment_date' => now()
                ]
            ]);

            $wallet->decrement('balance', $amount);

            return $transaction;
        });
    }
}