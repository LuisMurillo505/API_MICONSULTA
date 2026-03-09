<?php

namespace App\Http\Controllers\InventarioPanel;

use App\Models\Inventario\Marcas;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class MarcasController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try{
            return Marcas::withCount('articulos')->get();
        }catch(Exception $e){
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
                'nombre'=>'required|string'
            ]);

            $marcas = Marcas::create([
                'nombre' => $validated['nombre'],
                'clinica_id' => auth()->user()->getAttribute('clinica_id')
            ]);

            return response()->json($marcas->load('articulos'), 201);
        }catch(Exception $e){
            // Es vital retornar el error para que React sepa qué pasó
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener usuarios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Marcas $marcas)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Marcas $marcas)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request,int $marca_id)
    {
        try{
            $validated=$request->validate([
                'nombre'=>'required|string'
            ]); 
            $marca=Marcas::findOrFail($marca_id);

            $marca->update([
                'nombre' => $validated['nombre']
            ]);

            return response()->json($marca->load('articulos'), 201);
        }catch(Exception $e){
            // Es vital retornar el error para que React sepa qué pasó
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener datos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Marcas $marcas)
    {
        //
    }
}
