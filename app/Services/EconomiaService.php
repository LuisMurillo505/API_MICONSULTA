<?php

namespace App\Services;

use App\Models\Payment;

class EconomiaService
{
    public function ingresos(){

        $ingresos=Payment::sum('net_amount');

        return $ingresos;
    }
}
