<?php

namespace Uzbek\Paynet\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Uzbek\Paynet\Paynet
 */
class Paynet extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Uzbek\Paynet\Paynet::class;
    }
}
