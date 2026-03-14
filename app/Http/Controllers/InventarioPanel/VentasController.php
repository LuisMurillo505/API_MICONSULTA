<?php

namespace App\Http\Controllers\InventarioPanel;

use App\Models\Inventario\ConceptoMovimientos;
use App\Models\Inventario\Ventas;
use App\Models\Inventario\AlmacenArticulo;
use App\Services\KardexService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class VentasController extends Controller
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
            $venta=Ventas::with('kardex','usuario.personal','detalleVenta.articulos')
            ->orderBy('created_at','desc')
            ->get();
            return response()->json($venta);
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

            //validacion del formulario
            $validated=$request->validate([
                'cliente'=>'required|string',
                'total_venta'=>'required|numeric',
                'fecha'=>'required|date',
                'detalles'=> 'required|array',
                'detalles.*.articulo_id' => 'required|integer',
                'detalles.*.almacenArticulo_id' => 'nullable|integer',
                'detalles.*.precio_unitario' => 'required|numeric',
                'detalles.*.cantidad' => 'required|integer',
                'detalles.*.subtotal' => 'required|numeric',
            ]);

            //agregar conceptomovimiento_id a la validacion
            $validated['conceptomovimiento_id']=ConceptoMovimientos::VENTA_S; //Venta

            DB::beginTransaction();
          
            //creamos la venta
            $venta = Ventas::create([
                'clinica_id'=>auth()->user()->clinica_id,
                'usuario_id' => auth()->id(),
                'nombre_cliente' => $validated['cliente'],
                'total_venta'=>$validated['total_venta'],
                'fecha'=>$validated['fecha']
            ]);

            //creamos el detallle de la venta
            $venta->detalleVenta()->createMany($validated['detalles']);
            //generamos el kardex de la venta
            $this->kardexService->crearKardex($validated,$venta->getAttribute('id'));

         
            foreach ($validated['detalles'] as $detalle) {
                //encontramos el almacen donde se encuentra el articulo vendido
                $almacenArticulo=AlmacenArticulo::findOrFail($detalle['almacenArticulo_id']);

                //validacion por si no hay suficiente stock
                if ($almacenArticulo->stock < $detalle['cantidad']) {
                    throw new Exception("Stock insuficiente para: {$almacenArticulo->articulos->nombre}. Disponible: {$almacenArticulo->stock}");
                }

                //descontamos del stock
                $almacenArticulo->decrement('stock', $detalle['cantidad']);
            }

            DB::commit();

            // Cargamos los detalles para devolver la respuesta completa
            return response()->json($venta->load('detalleVenta'), 201);
            

        }catch(Exception $e){

            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener datos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $usuario_id)
    {
        try{
            $venta=Ventas::with('kardex','usuario','detalleVenta.articulos')
            ->orderBy('created_at','desc')
            ->where('usuario_id',$usuario_id)
            ->get();
            return response()->json($venta);
        }catch(Exception $e){
            // Es vital retornar el error para que React sepa qué pasó
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener datos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Ventas $ventas)
    {
        //
    }

    /** 
     * Update the specified resource in storage.
     */
    public function update(Request $request, Ventas $ventas)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Ventas $ventas)
    {
        //
    }
}
