<?php

declare(strict_types=1);

namespace CollectHouse\XML\XSDReader\Utils;

use CollectHouse\XML\XSDReader\Entities\SchemaCache;
use Illuminate\Support\Facades\Storage;

class SchemaFileUtils
{
    public static function cacheFile($namespace, $file): string
    {
        $file = self::sanitizeUrl(urldecode($file));
        if(str_contains($file, '.xsd')){
            $filename = explode('.xsd', $file, 2)[0].'.xsd';
        } else {
            $filename = $file.'.xsd';
        }
        $cached = self::getCachedFile($namespace, $file);
        if(!$cached) {
            $cached = self::downloadFile($file, 'StormCode/SchemaReader/'.str_replace([':','/'], ['','_'], $filename));
            SchemaCache::create([
                'schema_namespace' => $namespace,
                'schema_download_uri' => $file,
                'schema_path' => $cached
            ]);
        }

        return str_replace('\\', '/', storage_path('app/'.$cached));
    }

    private static function sanitizeUrl($url)
    {
        return str_replace(
            [
                ' ',
            ], 
            [
                '',
            ], 
            $url
        );
    }

    private static function downloadFile($fileUrl, $filepath) 
    {
        $out = file_get_contents($fileUrl);
        $finfo = new \finfo(FILEINFO_MIME);
        if(!str_contains($finfo->buffer($out), 'text/xml')) {
            throw new \Exception('Zły mimetype pliku schematu');
        }
        Storage::put($filepath, $out);

        return $filepath;
    }

    public static function getCachedFile($namespace, $file): string | bool
    {
        $schemas = SchemaCache::where('schema_download_uri', $file)->get();
        if($schemas->count() > 0) {
            return $schemas->first()->schema_path;
        }
        return false;
    }
}
