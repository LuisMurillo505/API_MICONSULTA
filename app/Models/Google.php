<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Google extends Model
{
    protected $table='google'; 

    protected $fillable = [
        'usuario_id',
        'google_token',
        'google_refresh_token',
    ];
    protected $casts = [
    'google_token_expires_in' => 'datetime',
];

    public function usuario(){
        return $this->BelongsTo(User::class,'usuario_id');
    }
}
