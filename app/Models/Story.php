<?php
namespace App\Models;
use App\Models\User;
use App\Models\Administrator;
use Illuminate\Database\Eloquent\Model;

class Story extends Model
{
    protected $fillable = [
        'title',
        'image_url',
        'video_url',
        'is_active',
        'expires_at',
        'administrator_id'
    ];

    public function administrator()
    {
        return $this->belongsTo(Administrator::class);
    }
}