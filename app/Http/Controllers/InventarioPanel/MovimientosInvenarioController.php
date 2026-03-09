<?php

namespace App\Http\Controllers\InventarioPanel;

use App\Models\Inventario\MovimientosInventario;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class MovimientosInvenarioController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try{
            $movimientosInventario=MovimientosInventario::all();

            return response()->json($movimientosInventario);;
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
    public function show(MovimientosInventario $movimientosInvenario)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MovimientosInventario $movimientosInvenario)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MovimientosInventario $movimientosInvenario)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MovimientosInventario $movimientosInvenario)
    {
        //
    }
}
