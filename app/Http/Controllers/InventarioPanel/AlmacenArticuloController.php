<?php

namespace App\Http\Controllers\InventarioPanel;

use App\Models\Inventario\AlmacenArticulo;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Inventario\TipoMovimiento;
use App\Services\KardexService;
use App\Http\Controllers\Controller;

class AlmacenArticuloController extends Controller
{
    protected $kardexService;
    public function __construct(KardexService $kardexService){
        $this->kardexService=$kardexService;
    }   
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
         try{
            return AlmacenArticulo::with('articulos','almacenes')
            ->where('stock','>',0)
            ->get();

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
                'tipomovimiento_id'=>'required|integer',
                'conceptomovimiento_id'=>'required|integer',
                'fecha' => 'required|date',
                'almacen_id'=>'nullable|integer',
                'detalles'=> 'required|array',
                'detalles.*.articulo_id' => 'required|integer',
                'detalles.*.cantidad' => 'required|integer',
            ]);

            DB::beginTransaction();

            $kardex=$this->kardexService->crearKardex($validated);

            if ($request->has('detalles')) {
                $tipomovimiento=(int)$validated['tipomovimiento_id'];
                match($tipomovimiento){
                    TipoMovimiento::ENTRADA => $this->kardexService->crearEntrada($validated,$kardex),
                    TipoMovimiento::SALIDA =>  $this->kardexService->crearSalida($validated,$kardex),
                    default => throw new \InvalidArgumentException('Tipo de movimiento no válido')
                };
            }

            DB::commit();

            return response()->json(['status' => 'success', 'data' => $kardex], 201);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(AlmacenArticulo $almacenArticulo)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AlmacenArticulo $almacenArticulo)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AlmacenArticulo $almacenArticulo)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AlmacenArticulo $almacenArticulo)
    {
        //
    }
}
