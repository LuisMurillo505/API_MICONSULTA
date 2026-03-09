<?php

namespace App\Http\Controllers\InventarioPanel;

use App\Models\Inventario\Kardex;
use App\Models\Inventario\ConceptoMovimientos;
use App\Services\KardexService;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class KardexController extends Controller
{
    protected $kardexService;
    public function __construct(KardexService $kardexService){
        $this->kardexService=$kardexService;
    }   
    /**
     * Display a listing of the resource.
     */
    public function indexA()
    {
        try{
            //conceptomovimiento_id[
            //1=>Compra/entrada
            //3=>Inventario Fisico/entrada
            //6=>Devolucion/salida
            //8=>Inventario Fisico/Salida]
            $kardex=Kardex::whereIn('conceptomovimiento_id', [
                ConceptoMovimientos::COMPRA_E, 
                ConceptoMovimientos::INVENTARIOFISICO_E, 
                ConceptoMovimientos::DEVOLUCION_S, 
                ConceptoMovimientos::INVENTARIOFISICO_S])
                ->with( 'conceptoMovimiento')->orderBy('created_at','desc')
                ->get();

            return response()->json($kardex);;
        }catch(Exception $e){
            // Es vital retornar el error para que React sepa qué pasó
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener usuarios: ' . $e->getMessage()
            ], 500);
        }
    }

    public function indexB()
    {
        try{
            //conceptomovimiento_id[
            //2=>Devolucion/entrada
            //4=>Asignacion/salida]
             $kardex=Kardex::whereIn('conceptomovimiento_id', [ 
                ConceptoMovimientos::ASIGNACION_S])
                ->with('receptor', 'conceptoMovimiento')
                ->orderBy('created_at','desc')
                ->get();

                return response()->json($kardex);;
        }catch(Exception $e){
            // Es vital retornar el error para que React sepa qué pasó
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener usuarios: ' . $e->getMessage()
            ], 500);
        }
    }

    public function indexC()
    {
        try{
            
             $kardex=Kardex::whereIn('conceptomovimiento_id', [
                ConceptoMovimientos::TRASPASO_S])
                ->with('receptor','emisor', 'conceptoMovimiento')
                ->orderBy('created_at','desc')
                ->get();

                return response()->json($kardex);;
        }catch(Exception $e){
            // Es vital retornar el error para que React sepa qué pasó
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener usuarios: ' . $e->getMessage()
            ], 500);
        }
    }

    public function indexD()
    {
        try{
            $kardex=Kardex::whereIn('conceptomovimiento_id', [
                ConceptoMovimientos::DEVOLUCION_E])
                ->with('receptor','emisor', 'conceptoMovimiento')
                ->orderBy('created_at','desc')
                ->get();

                return response()->json($kardex);;
        }catch(Exception $e){
            // Es vital retornar el error para que React sepa qué pasó
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener usuarios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Kardex $kardex)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Kardex $kardex)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Kardex $kardex)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Kardex $kardex)
    {
        //
    }
}
