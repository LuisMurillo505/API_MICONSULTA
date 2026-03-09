<?php

namespace App\Http\Controllers\InventarioPanel;

use App\Models\Inventario\Almacenes;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AlmacenesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try{
            return Almacenes::all();
            
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
        try{
            $validated=$request->validate([
                'nombre' => 'required|string'
            ]);
            $almacen = Almacenes::create([
                'nombre' => $validated['nombre'],
                'clinica_id' => auth()->user()->getAttribute('clinica_id')
            ]);
            return response()->json($almacen, 201);
        }catch(Exception $e){
            // Es vital retornar el error para que React sepa qué pasó
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener datos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Almacenes $almacenes)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Almacenes $almacenes)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $almacen_id)
    {
        try{
            $validated=$request->validate([
                'nombre' => 'required|string'
            ]);
            $almacen=Almacenes::findOrFail($almacen_id);
            $almacen->update([
                'nombre' => $validated['nombre'],
            ]);
            return response()->json($almacen, 201);
        }catch(Exception $e){
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener datos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Almacenes $almacenes)
    {
        //
    }
}
