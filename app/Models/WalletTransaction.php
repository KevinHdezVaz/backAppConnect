<?php
namespace App\Models;

use App\Models\Wallet;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $fillable = [
        'wallet_id',
        'type',
        'amount',
        'description',
        'source',
        'source_reference',
        'metadata'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array'
    ];

    // Relaciones
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    // Constantes para tipos de transacción
    const TYPE_CREDIT = 'credit';
    const TYPE_DEBIT = 'debit';

    // Constantes para fuentes de transacción
    const SOURCE_MATCH_REFUND = 'match_refund';
    const SOURCE_REWARD = 'reward';
    const SOURCE_MANUAL_DEPOSIT = 'manual_deposit';
    const SOURCE_PAYMENT = 'payment';

    // Scopes
    public function scopeCredits($query)
    {
        return $query->where('type', self::TYPE_CREDIT);
    }

    public function scopeDebits($query)
    {
        return $query->where('type', self::TYPE_DEBIT);
    }

    public function scopeBySource($query, $source)
    {
        return $query->where('source', $source);
    }
}