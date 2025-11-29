<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'url',
        'type',
    ];

    public function logs()
    {
        return $this->hasMany(WebhookLog::class);
    }
}
