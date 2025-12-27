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

/**
 * Sube un archivo a un bucket de almacenamiento (ej. Google Cloud Storage).
 *
 * Este método toma un archivo local, lee su contenido y lo sube al bucket
 * configurado, guardándolo en la ruta de destino especificada.
 *
 * @param string $filePath
 * Ruta local completa del archivo que se desea subir.
 *
 * @param string $destinationPath
 * Ruta y nombre con el que se almacenará el archivo dentro del bucket.
 *
 * @return string
 * Devuelve la ruta de destino del archivo dentro del bucket.
 *
 * @throws \Exception
 * Puede lanzar una excepción si el archivo no existe o ocurre un error
 * durante la subida al bucket.
 */
    public function upload(string $filePath,string $destinationPath){

         $this->bucket->upload(
            file_get_contents($filePath), // Cargar el contenido del archivo
            ['name' => $destinationPath] // Definir la ruta de destino
        );

        return $destinationPath;
    }

/**
 * Genera la URL pública de un archivo almacenado en Google Cloud Storage.
 *
 * Construye la URL pública a partir del nombre del bucket configurado
 * y la ruta del archivo dentro del mismo.
 *
 * @param string $path  Ruta del archivo dentro del bucket.
 *
 * @return string  URL pública del archivo.
 */
    public function getPublicUrl(string $path)
    {
        return sprintf('https://storage.googleapis.com/%s/%s',
            env('GOOGLE_CLOUD_STORAGE_BUCKET'),
            $path
        );
    }

/**
 * Genera una URL firmada (Signed URL) temporal para acceder a un archivo
 * almacenado en Google Cloud Storage.
 *
 * La URL permite el acceso público al archivo únicamente durante el
 * tiempo especificado, sin necesidad de que el usuario tenga permisos
 * directos sobre el bucket.
 *
 * @param string $path    Ruta del archivo dentro del bucket (ej. "documentos/archivo.pdf").
 * @param int    $minutes Tiempo de vigencia de la URL en minutos (por defecto 10).
 *
 * @return string URL firmada válida por el tiempo indicado.
 */
    public function getSignedUrl(string $path, int $minutes = 10)
    {
        $object = $this->bucket->object($path);

        return $object->signedUrl(
            now()->addMinutes($minutes),
            [
                'version' => 'v4'
            ]
        );
    }

/**
 * Elimina un archivo del bucket de Google Cloud Storage.
 *
 * @param string $path Ruta del archivo dentro del bucket
 *
 * @return void
 */
    public function delete(string $path)
    {
        $object = $this->bucket->object($path);
        $object->delete();
    }

}