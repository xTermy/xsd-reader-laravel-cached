<?php

namespace CollectHouse\XML\XSDReader;

use Illuminate\Support\ServiceProvider as ServiceProviderParent;

class SchemaServiceProvider extends ServiceProviderParent
{
    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/Migrations');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
