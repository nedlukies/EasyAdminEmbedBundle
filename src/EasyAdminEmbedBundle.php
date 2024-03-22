<?php

namespace Madforit\EasyAdminEmbedBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class EasyAdminEmbedBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}
