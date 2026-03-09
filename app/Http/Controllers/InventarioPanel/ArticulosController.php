<?php

namespace App\Http\Controllers\InventarioPanel;

use App\Models\Inventario\Articulos;
use App\Models\Status;
use App\Services\GoogleCloudStorageService;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ArticulosController extends Controller
{
    protected $gcs;
    public function __construct(GoogleCloudStorageService $gcs){
        $this->gcs = $gcs;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try{
            $articulos = Articulos::with('marca','status','subcategoria','subcategoria.categoria','almacenArticulos.almacenes')
            ->withSum('almacenArticulos as total_almacen', 'stock')
            // ->withSum('articuloUsuario as total_asignado', 'stock')
            ->withSum('detalleVenta as piezas_vendidas', 'cantidad')
            ->with([
                'detalleVenta' => function($query) {
                    $query->selectRaw('articulo_id, SUM(precio_unitario * cantidad) as total_calculado')
                        ->groupBy('articulo_id');
                }
            ])
            ->get()
            ->map(function ($articulo) {
                // $articulo->stock_global = ($articulo->total_almacen ?? 0) + ($articulo->total_asignado ?? 0);
                
                // Sumamos los totales de la relación cargada
                $articulo->total_vendido = $articulo->detalleVenta->sum('total_calculado');
                
                return $articulo;
            });
            return response()->json($articulos);
        
        }catch(Exception $e){
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener datos: ' . $e->getMessage()
            ], 500);
        }
    }

    public function ReportesArticulos()
    {
        try{
            Articulos::withCount(['almacenArticulos as alertas_stock' => function($query) {
                $query->where('stock', '<', 10);
            }])->get();
        
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
                'nombre'=>'required|string',
                'clave'=>'required|string',
                'subcategoria_id'=>'required|integer',
                'foto' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'marca_id'=>'required|integer',
                'costo'=>'required|numeric',
                'precio'=>'required|numeric',
                'precio_sugerido'=>'required|numeric',
            ]);

            $urlImage=null;

            if($request->hasFile('foto')){
                $file = $request->file('foto');
                $nombreArchivo= "Articulo_". time().".".$file->getClientOriginalExtension();
                $destinationPath= "Articulos/".$nombreArchivo;
                $urlImage=$this->gcs->getPublicUrl($destinationPath);
                $this->gcs->upload($file->getRealPath(),$destinationPath);
            }

            $articulo = Articulos::create([
                'clinica_id' => auth()->user()->getAttribute('clinica_id'),
                'nombre' => $validated['nombre'],
                'clave' => $validated['clave'],
                'foto' => $urlImage,
                'subcategoria_id'=>$validated['subcategoria_id'],
                'marca_id'=>$validated['marca_id'],
                'costo'=>$validated['costo'],
                'precio'=>$validated['precio'],
                'precio_sugerido'=>$validated['precio_sugerido'],
                'status_id'=>Status::ACTIVE,
            ]);
            return response()->json($articulo, 201);
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
    public function show(Articulos $articulos)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Articulos $articulos)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $articulo_id)
    {
         try{
            $validated=$request->validate([
                'nombre'=>'required|string',
                'clave'=>'required|string',
                'foto' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'subcategoria_id'=>'required|integer',
                'marca_id'=>'required|integer',
                'costo'=>'required|numeric',
                'precio'=>'required|numeric',
                'precio_sugerido'=>'required|numeric'
            ]);
            $articulo=Articulos::findOrFail($articulo_id);

            $urlImage=null;

            if($request->hasFile('foto')){
                $file = $request->file('foto');
                $nombreArchivo= "Articulo_". time().".".$file->getClientOriginalExtension();
                $destinationPath= "Articulos/".$nombreArchivo;
                $urlImage=$this->gcs->getPublicUrl($destinationPath);

                $this->gcs->upload($file->getRealPath(),$destinationPath);
                if($articulo->getAttribute('foto')){
                    $oldPath = str_replace(
                        "https://storage.googleapis.com/" . env('GOOGLE_CLOUD_STORAGE_BUCKET') . "/", 
                        "", 
                        $articulo->foto
                    );
                   try {
                        $this->gcs->delete($oldPath);
                    } catch (Exception $e) {
                        \Log::error("No se pudo borrar la imagen vieja: " . $e->getMessage());
                    }
                }
            }

            $articulo->update([
                'nombre' => $validated['nombre'],
                'clave' => $validated['clave'],
                'foto' => $urlImage ?? $articulo->getAttribute('foto'),
                'subcategoria_id'=>$validated['subcategoria_id'],
                'marca_id'=>$validated['marca_id'],
                'costo'=>$validated['costo'],
                'precio'=>$validated['precio'],
                'precio_sugerido'=>$validated['precio_sugerido'],
                'status_id'=>1,
            ]);
            return response()->json($articulo, 201);
        }catch(Exception $e){
            // Es vital retornar el error para que React sepa qué pasó
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener datos: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(int $articulo_id, int $status_id)
    {
         try{
           
            $articulo=Articulos::findOrFail($articulo_id);

            $articulo->update([
                'status_id'=>$status_id
            ]);

            return response()->json($articulo, 201);
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
    public function destroy(Articulos $articulos)
    {
        //
    }
}
