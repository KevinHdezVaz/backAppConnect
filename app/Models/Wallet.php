<?php
namespace App\Models;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = [
        'user_id',
        'balance',
        'status'
    ];

    protected $casts = [
        'balance' => 'decimal:2'
    ];

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    // Métodos de utilidad
    public function hasEnoughBalance($amount)
    {
        return $this->balance >= $amount;
    }

    public function addMoney($amount)
    {
        $this->increment('balance', $amount);
    }

    public function deductMoney($amount)
    {
        if (!$this->hasEnoughBalance($amount)) {
            throw new \Exception('Saldo insuficiente');
        }
        $this->decrement('balance', $amount);
    }
}