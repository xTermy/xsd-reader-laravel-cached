<?php

declare(strict_types=1);

namespace CollectHouse\XML\XSDReader\Utils;

use CollectHouse\XML\XSDReader\Entities\SchemaCache;
use Illuminate\Support\Facades\Storage;

class SchemaFileUtils
{
    public static function cacheFile($namespace, $file): string
    {
        // $file = explode('.xsd', $file, 2)[0].'.xsd';
        $cached = self::getCachedFile($namespace, $file);
        if(!$cached) {
            $fileCont = file_get_contents($file);
            $cached = 'StormCode/SchemaReader/'.str_replace([':','/'], ['','_'], $file);
            Storage::put($cached, $fileCont);
            SchemaCache::create([
                'schema_namespace' => $namespace,
                'schema_download_uri' => $file,
                'schema_path' => $cached
            ]);
        }

        return str_replace('\\', '/', storage_path('app/'.$cached));
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
