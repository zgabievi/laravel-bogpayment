<?php

namespace Zorb\BOGPayment\Facades;

use Zorb\BOGPayment\BOGPayment as BOGPaymentService;
use Illuminate\Support\Facades\Facade;

class BOGPayment extends Facade
{
    //
    protected static function getFacadeAccessor()
    {
        return BOGPaymentService::class;
    }
}
