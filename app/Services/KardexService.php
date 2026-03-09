<?php

namespace App\Services;

use App\Models\Inventario\Kardex;
use App\Models\Inventario\ConceptoMovimientos;
use App\Models\Inventario\AlmacenArticulo;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class KardexService{

    private function generarFolio(string $conceptomovimiento_id): string
    {
        $fecha = Carbon::now()->format('Ymd');

        $conceptoMovimiento=ConceptoMovimientos::findOrFail($conceptomovimiento_id);

        $abreviaturaTipo = Str::upper(Str::limit($conceptoMovimiento->getAttribute('nombre'), 3, ''));

        $ultimo = Kardex::where('conceptomovimiento_id', $conceptomovimiento_id)
            ->whereDate('created_at', Carbon::today())
            ->orderBy('id', 'desc')
            ->first();

        $consecutivo = $ultimo
            ? str_pad((int)substr($ultimo->getAttribute('folio'), -6) + 1, 6, '0', STR_PAD_LEFT)
            : '000001';

        return "{$abreviaturaTipo}-{$fecha}-{$consecutivo}";
    }
    public function crearKardex(array $data,?int $venta_id=null){
        try{
            $kardex=Kardex::create([
                'folio'     => $this->generarFolio($data['conceptomovimiento_id']),
                'usuario_id'=> auth()->id(),
                // 'receptor_id'=>$data['receptor_id'] ?? null,
                // 'emisor_id'=>$data['emisor_id'] ?? null,
                'fecha'=>  $data['fecha'],
                'almacen_id'=>$data['almacen_id'] ?? null,
                'venta_id'=>$venta_id,
                'conceptomovimiento_id' => $data['conceptomovimiento_id'],                    
            ]);
            return $kardex;
        }catch(Exception $e){ throw $e;}

    }

    //entrada compra e inventario fisico (a almacen)
    public function crearEntrada(array $data,Kardex $kardex){
        try{
            foreach ($data['detalles'] as $det) {

                $kardex->movimientoInventario()->create([
                    'articulo_id' => $det['articulo_id'], 
                    'cantidad'    => $det['cantidad'],
                ]);

                $almacenArticulo=AlmacenArticulo::where('almacen_id',$data['almacen_id'])
                ->where('articulo_id',$det['articulo_id'])->first();

                if($almacenArticulo){
                    $almacenArticulo->increment('stock',$det['cantidad']);
                }else{
                    AlmacenArticulo::create([
                        'articulo_id' => $det['articulo_id'], 
                        'almacen_id'  => $data['almacen_id'],
                        'stock'       => $det['cantidad'],
                    ]);
                }     
            }
        }catch(Exception $e){
            throw $e;
        }
    }

    //Devolucion e inventario fisico (de almacen)
    public function crearSalida(array $data, Kardex $kardex){
        try{
            foreach($data['detalles'] as $det){

                $almacenArticulo=AlmacenArticulo::findOrFail($det['articulo_id']);

                if($almacenArticulo->getAttribute('stock')<$det['cantidad']){
                    throw new \InvalidArgumentException('No hay aticulos suficentes en stock');
                }

                $kardex->movimientoInventario()->create([
                    'articulo_id' => $almacenArticulo->getAttribute('articulo_id'), 
                    'cantidad'    => $det['cantidad'],
                ]);
                
                $almacenArticulo->decrement('stock', $det['cantidad']);
            }
        }catch(Exception $e){
            throw $e;
        }
    }

    //Asignacion de articulos a distribuidores
    // public function crearAsignacion(array $data, Kardex $kardex){
    //     try{
    //         //proceso para guardar los nombres de los articulos asignados
    //         $articulosAsig=[];
    //         $distribuidor=Usuario::findOrFail($data['receptor_id']);
    //         foreach($data['detalles'] as $det){

    //             // $almacenArticulo=AlmacenArticulo::findOrFail($det['almacenArticulo_id']);
    //             $almacenArticulo = AlmacenArticulo::with('articulos')
    //                 ->find($det['detalle_id']);

    //             $articulosAsig[]=$almacenArticulo->articulos->getAttribute('nombre')." - ".  $det['cantidad'] . "Piezas";

    //             if($almacenArticulo->getAttribute('stock')<$det['cantidad']){
    //                 throw new \InvalidArgumentException('No hay aticulos suficentes en stock');
    //             }

    //             $kardex->movimientoInventario()->create([
    //                 'articulo_id' => $almacenArticulo->getAttribute('articulo_id'), 
    //                 'cantidad'    => $det['cantidad'],
    //             ]);

    //             $articuloUsuario=ArticuloUsuario::where('usuario_id', $data['receptor_id'])
    //             ->where('articulo_id',$almacenArticulo->getAttribute('articulo_id'))->first();

    //             if($articuloUsuario){
    //                 $articuloUsuario->increment('stock', $det['cantidad']);
    //             }else{
    //                 $articuloUsuario=ArticuloUsuario::create([
    //                     'usuario_id'    => $data['receptor_id'],
    //                     'articulo_id'  => $almacenArticulo->getAttribute('articulo_id'),
    //                     'stock'  => $det['cantidad'],
    //                 ]);
    //             }
    //             $almacenArticulo->decrement('stock', $det['cantidad']);
    //         }
    //         // Enviar correo a distribuidor
    //         Mail::to($distribuidor->email)->send(new \App\Mail\AssignmentDistributorMail($distribuidor, $articulosAsig));

    //     }catch(Exception $e){
    //         throw $e;
    //     }
    // }

    //devolucion de articulos por parte de los distribuidores a almacen
    // public function crearDevolucion(array $data, Kardex $kardex){
    //     try{
    //         foreach($data['detalles'] as $det){

    //             $articuloUsuario=ArticuloUsuario::findOrFail($det['detalle_id']);

    //              if($articuloUsuario->getAttribute('stock')<$det['cantidad']){
    //                 throw new \InvalidArgumentException('No hay aticulos suficentes en stock');
    //             }

    //             $kardex->movimientoInventario()->create([
    //                 'articulo_id' => $articuloUsuario->getAttribute('articulo_id'), 
    //                 'cantidad'    => $det['cantidad'],
    //             ]);

    //             $almacenArticulo=AlmacenArticulo::where('almacen_id',$data['almacen_id'])
    //             ->where('articulo_id',$articuloUsuario->getAttribute('articulo_id'))->first();

    //             if($almacenArticulo){
    //                 $almacenArticulo->increment('stock', $det['cantidad']);
    //             }else{
    //                 AlmacenArticulo::create([
    //                     'articulo_id' => $articuloUsuario->getAttribute('articulo_id'), 
    //                     'almacen_id'  => $data['almacen_id'],
    //                     'stock'       => $det['cantidad'],
    //                 ]);
    //             }
    //             $articuloUsuario->decrement('stock', $det['cantidad']);
    //         }
    //     }catch(Exception $e){
    //         throw $e;
    //     }
    // }

        //traspaso de articulos entre distribuidores
    // public function crearTraspaso(array $data, Kardex $kardex){
    //     try{
    //         foreach($data['detalles'] as $det){

    //             $emisor=ArticuloUsuario::findOrFail($det['detalle_id']);

    //              if($emisor->getAttribute('stock')<$det['cantidad']){
    //                 throw new \InvalidArgumentException('No hay aticulos suficientes en stock');
    //             }

    //             $kardex->movimientoInventario()->create([
    //                 'articulo_id' => $emisor->getAttribute('articulo_id'), 
    //                 'cantidad'    => $det['cantidad'],
    //             ]);

    //             $receptor=ArticuloUsuario::where('usuario_id',$data['receptor_id'])
    //             ->where('articulo_id',$emisor->getAttribute('articulo_id'))->first();

    //             if($receptor){
    //                 $receptor->increment('stock', $det['cantidad']);
    //             }else{
    //                 $receptor=ArticuloUsuario::create([
    //                     'usuario_id'    => $data['receptor_id'],
    //                     'articulo_id'  => $emisor->getAttribute('articulo_id'),
    //                     'stock'  => $det['cantidad'],
    //                 ]);
    //             }
    //             $emisor->decrement('stock', $det['cantidad']);
    //         }
    //     }catch(Exception $e){
    //         throw $e;
    //     }
    // }
}