<?php

namespace Afonso\Plotta;

class YAxisConfig extends AxisConfig
{
    /**
     * @var float
     */
    public $min;

    /**
     * @var float
     */
    public $max;

    public function __construct(string $name, float $min = null, float $max = null)
    {
        parent::__construct($name);
        $this->min = $min;
        $this->max = $max;
    }
}
