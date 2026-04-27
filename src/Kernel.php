<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Kernel principale dell'applicazione Symfony.
 *
 * MicroKernelTrait abilita la configurazione compatta tipica di Symfony 6+:
 * bundle, rotte e servizi vengono caricati automaticamente dalle directory
 * config/packages/, config/routes/ e config/services.yaml
 * senza dover sovrascrivere metodi nel Kernel.
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;
}
