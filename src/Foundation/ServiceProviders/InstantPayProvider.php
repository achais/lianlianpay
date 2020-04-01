<?php

namespace Achais\LianLianPay\Foundation\ServiceProviders;

use Achais\LianLianPay\InstantPay;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class InstantPayProvider implements ServiceProviderInterface
{
    public function register(Container $pimple)
    {
        $pimple['instantPay'] = function ($pimple) {
            return new InstantPay\InstantPay($pimple['config']);
        };
    }
}