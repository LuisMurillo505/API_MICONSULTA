<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
     protected $table='payments';
     protected $fillable = ['clinica_id', 'plan_id' ,'tarifa_id','payment_id', 'invoiceNumber', 'discount','amount',
          'comision','net_amount','status','date'];
}
