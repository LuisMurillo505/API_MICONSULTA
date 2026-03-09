<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CleanSpecificTables extends Command
{
    // El nombre con el que llamarás al comando
    protected $signature = 'migrate:clean-only';

    protected $description = 'Borra tablas específicas y limpia su rastro en la tabla de migraciones';

    public function handle()
    {
        // Define aquí los nombres de las tablas que quieres eliminar
        $tablasABorrar = ['personal_access_tokens','categorias', 'subcategorias', 'almacenes','marcas','articulos','tipo_movimientos',
                          'concepto_movimientos','ventas','kardexes','movimientos_inventario','detalle_ventas',
                          'almacen_articulos'];

        if ($this->confirm("¿Estás seguro de que quieres borrar estas tablas: " . implode(', ', $tablasABorrar) . "?")) {
            
            Schema::disableForeignKeyConstraints();

            foreach ($tablasABorrar as $tabla) {
                // 1. Borrar la tabla físicamente
                Schema::dropIfExists($tabla);

                // 2. Limpiar el registro en la tabla 'migrations' para poder volver a migrar
                DB::table('migrations')
                    ->where('migration', 'like', "%create_{$tabla}_table%")
                    ->delete();

                $this->line("<fg=yellow>Eliminada:</> {$tabla}");
            }

            Schema::enableForeignKeyConstraints();
            $this->info("Operación finalizada con éxito.");
        }
    }
}