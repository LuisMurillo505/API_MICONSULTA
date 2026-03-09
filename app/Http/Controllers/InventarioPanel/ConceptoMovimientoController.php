<?php

namespace App\Http\Controllers\InventarioPanel;

use App\Models\Inventario\ConceptoMovimientos;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ConceptoMovimientoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try{
            return response()->json(ConceptoMovimientos::all());
        }catch(Exception $e){
            // Es vital retornar el error para que React sepa qué pasó
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener datos: ' . $e->getMessage()
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
    public function show(ConceptoMovimientos $tipoMovimiento)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ConceptoMovimientos $tipoMovimiento)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ConceptoMovimientos $tipoMovimiento)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ConceptoMovimientos $tipoMovimiento)
    {
        //
    }
}
