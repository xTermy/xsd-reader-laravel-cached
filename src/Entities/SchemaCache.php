<?php

namespace CollectHouse\XML\XSDReader\Entities;

use Illuminate\Database\Eloquent\Model;

class SchemaCache extends Model
{
    protected $table = 'cache__schemas';
    public $timestamps = false;
    protected $fillable = [
        'schema_namespace',
        'schema_download_uri',
        'schema_path'
    ];
}
