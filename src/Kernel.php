<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        // Les horaires des séances sont saisis et affichés à l'heure de Paris
        date_default_timezone_set('Europe/Paris');
        parent::boot();
    }
}
