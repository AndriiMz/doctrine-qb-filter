<?php

namespace AndriiMz\QbFilter;

use AndriiMz\QbFilter\DependencyInjection\QBExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class QBFilterBundle extends Bundle
{
    /**
     * @return ExtensionInterface
     */
    public function getContainerExtension(): ExtensionInterface
    {
        return new QBExtension();
    }
}
