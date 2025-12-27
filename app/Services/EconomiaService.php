<?php

namespace App\Services;

use App\Models\Payment;

class EconomiaService
{
/**
 * Obtiene el total de ingresos registrados en el sistema.
 *
 * Calcula la suma del monto neto (`net_amount`) de todos los pagos
 * almacenados en la tabla de pagos.
 *
 * @return float|int
 * Retorna el total de ingresos acumulados.
 */
    public function ingresos(){

        $ingresos=Payment::sum('net_amount');

        return $ingresos;
    }
}
