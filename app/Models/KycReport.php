<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KycReport extends Model
{
    protected $fillable = ['user_id','status','metrics','reasons'];
    protected $casts = ['metrics' => 'array', 'reasons' => 'array'];

    public function user() { return $this->belongsTo(\App\Models\User::class); }
}