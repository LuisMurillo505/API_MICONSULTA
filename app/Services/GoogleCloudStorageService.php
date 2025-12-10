<?php

namespace App\Services;
;
use Google\Cloud\Storage\StorageClient;

class GoogleCloudStorageService
{

    protected $storage;
    protected $bucket;

    public function __construct(){
         $this->storage = new StorageClient([
            'projectId' => env('GOOGLE_CLOUD_PROJECT_ID'),
            'keyFilePath' => env('GOOGLE_CLOUD_KEY_FILE'),
        ]);

        $this->bucket = $this->storage->bucket(env('GOOGLE_CLOUD_STORAGE_BUCKET'));
    }

     // Subir archivo
    public function upload($filePath, $destinationPath){

         $this->bucket->upload(
            file_get_contents($filePath), // Cargar el contenido del archivo
            ['name' => $destinationPath] // Definir la ruta de destino
        );

        return $destinationPath;
    }

     // Generar URL pública (si el bucket está público)
    public function getPublicUrl($path)
    {
        return sprintf('https://storage.googleapis.com/%s/%s',
            env('GOOGLE_CLOUD_STORAGE_BUCKET'),
            $path
        );
    }

    // Descargar archivo temporalmente (signed URL)
    public function getSignedUrl($path, $minutes = 10)
    {
        $object = $this->bucket->object($path);

        return $object->signedUrl(
            now()->addMinutes($minutes),
            [
                'version' => 'v4'
            ]
        );
    }

    // Eliminar archivo
    public function delete($path)
    {
        $object = $this->bucket->object($path);
        $object->delete();
    }

}