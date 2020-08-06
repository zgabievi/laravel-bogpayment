<?php

namespace Zorb\BOGPayment\Facades;

use Zorb\BOGPayment\BOGPayment as BOGPaymentService;
use Illuminate\Support\Facades\Facade;

class BOGPayment extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return BOGPaymentService::class;
    }
}
