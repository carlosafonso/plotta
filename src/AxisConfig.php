<?php

namespace Afonso\Plotta;

abstract class AxisConfig
{
    public $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
