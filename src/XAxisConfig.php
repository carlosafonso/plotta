<?php

namespace Afonso\Plotta;

class XAxisConfig extends AxisConfig
{
    public $labels;

    public function __construct(string $name, array $labels)
    {
        parent::__construct($name);
        $this->labels = $labels;
    }
}
