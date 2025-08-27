<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class Overpass extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'overpass';
    }
}
