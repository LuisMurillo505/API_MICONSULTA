<?php

namespace App\Http\Controllers\InventarioPanel;

use App\Models\Inventario\Categorias;
use Exception;
use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;


class CategoriasController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        
        try{

            return Categorias::with(['subcategorias' => function($query) {
                $query->withCount('articulos'); 
            }])->get();
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
       
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try{
            $validated=$request->validate([
                'nombre'=>'required|string',
                'subcategorias'=>'required|array',
                'subcategorias.*.nombre'=>'required|string'
            ]);
            $categoria = Categorias::create([
                'nombre' => $validated['nombre'],
                'clinica_id' => auth()->user()->getAttribute('clinica_id')
            ]);

            if ($request->has('subcategorias')) {
                $categoria->subcategorias()->createMany($validated['subcategorias']);
            }

            return response()->json($categoria->load('subcategorias'), 201);

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
    public function show(Categorias $categorias)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Categorias $categorias)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $categoria_id)
    {
        try{
            $categoria = Categorias::findOrFail($categoria_id);

           $categoria->update(['nombre' => $request->nombre]);

            if ($request->has('subcategorias')) {
                $subData = $request->subcategorias;
                
                // 1. Obtener los IDs que vienen del frontend (las que se quedan)
                $keepIds = collect($subData)->pluck('id')->filter()->toArray();

                // 2. Eliminar las subcategorías que NO están en la lista
                // Nota: Esto lanzará un error de SQL si intentas borrar una con artículos (Restricción FK)
                $categoria->subcategorias()->whereNotIn('id', $keepIds)->delete();

                // 3. Procesar cada subcategoría del frontend
                foreach ($subData as $sub) {
                    if (isset($sub['id'])) {
                        // Si tiene ID, solo actualizamos el nombre si cambió
                        $categoria->subcategorias()
                            ->where('id', $sub['id'])
                            ->update(['nombre' => $sub['nombre']]);
                    } else {
                        // Si no tiene ID, es nueva, la creamos
                        $categoria->subcategorias()->create(['nombre' => $sub['nombre']]);
                    }
                }
            }
            return response()->json(['status' => 'success', 'data' => $categoria], 201);       
         }catch(Exception $e){
            // Es vital retornar el error para que React sepa qué pasó
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener usuarios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Categorias $categorias)
    {
        //
    }
}
