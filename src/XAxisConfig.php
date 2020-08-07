<?php

namespace Afonso\Plotta;

class XAxisConfig extends AxisConfig
{
    /**
     * @var array
     */
    public $labels;

    /**
     * @var string
     */
    public $dateFormat;

    public function __construct(string $name, array $labels, string $dateFormat = null)
    {
        parent::__construct($name);
        $this->labels = $labels;
        $this->dateFormat = $dateFormat;
    }
}
