<?php

namespace Afonso\Plotta;

use \RuntimeException;

class PlotBuilder
{
    const PLOT_MARGIN = 10;

    const TITLE_FONT_SIZE = 5;

    const AXIS_VALUE_FONT_SIZE = 2;

    const INTER_ELEMENT_SPACING = 10;

    const N_TICKS_Y_AXIS = 10;

    const COLORS = [
        [0x00, 0x00, 0xff],
        [0xff, 0x00, 0x00],
        [0x00, 0xff, 0x00],
    ];

    /**
     * @var int
     */
    private $width;

    /**
     * @var int
     */
    private $height;

    /**
     * @var string
     */
    private $title;

    /**
     * @var \Afonso\Plotta\XAxisConfig
     */
    private $xAxisConfig;

    /**
     * @var \Afonso\Plotta\YAxisConfig
     */
    private $yAxisConfig;

    /**
     * @var array
     */
    private $data = [];

    /**
     * Set the dimensions of the chart, in pixels.
     *
     * @param int $width
     * @param int $height
     * @return self
     */
    public function withDimensions(int $width, int $height): PlotBuilder
    {
        $this->width = $width;
        $this->height = $height;
        return $this;
    }

    /**
     * Set the title of the chart.
     *
     * @param string $title
     * @return self
     */
    public function withTitle(string $title): PlotBuilder
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Set the configuration of the X axis.
     *
     * @param \Afonso\Plotta\XAxisConfig $xAxisConfig
     * @return self
     */
    public function withXAxis(XAxisConfig $xAxisConfig): PlotBuilder
    {
        $this->xAxisConfig = $xAxisConfig;
        return $this;
    }

    /**
     * Set the configuration of the Y axis.
     *
     * @param \Afonso\Plotta\YAxisConfig $yAxisConfig
     * @return self
     */
    public function withYAxis(YAxisConfig $yAxisConfig): PlotBuilder
    {
        $this->yAxisConfig = $yAxisConfig;
        return $this;
    }

    /**
     * Add a time series to the data to be plotted.
     *
     * @param array $data
     * @return self
     */
    public function withData(array $data): PlotBuilder
    {
        $this->data[] = $data;
        return $this;
    }

    /**
     * Generate the chart and save it as a PNG to the specified location.
     *
     * @param string $path
     */
    public function render(string $path): void
    {
        // Check that everything is okay before proceeding
        $this->checkOrFail();

        // Determine the minimum and maximum values of the data series
        $minValue = $this->yAxisConfig->min ?? $this->getMinValue($this->data);
        $maxValue = $this->yAxisConfig->max ?? $this->getMaxValue($this->data);

        // Calculate the key coordinates for the chart components
        $coords = $this->calculateCoordinates();

        // Initialize chart
        $img = $this->initChart($this->width, $this->height);

        // Title
        $this->drawTitle($img, $coords, $this->title);

        // Y Axis
        $this->drawYAxis($img, $coords, $this->yAxisConfig, $minValue, $maxValue);

        // X Axis
        $this->drawXAxis($img, $coords, $this->xAxisConfig);

        // Data
        $this->drawData($img, $coords, $this->data, $minValue, $maxValue);

        // Write to file
        imagepng($img, $path);
    }

    private function checkOrFail(): void
    {
        // Check that the number of data points and labels are the same
        $maxDataPoints = max(array_map('count', $this->data));
        $minDataPoints = min(array_map('count', $this->data));
        $nLabels = count($this->xAxisConfig->labels);
        if ($maxDataPoints != $minDataPoints) {
            throw new RuntimeException("Data series do not have the same number of items. Min: ${minDataPoints}, max: ${maxDataPoints}");
        } elseif ($maxDataPoints != $nLabels) {
            throw new RuntimeException("Number of X axis labels does not match the number of data items. Data points: ${maxDataPoints}, labels: ${nLabels}");
        }
    }

    /**
     * Return the min and max values across all data series.
     *
     * @param float[][] $series
     * @return float[]
     */
    private function getMinAndMaxValues(array $series): array
    {
        return [$this->getMinValue($series), $this->getMaxValue($series)];
    }

    /**
     * Return the min value across all data series.
     *
     * @param float[][] $series
     * @return float
     */
    private function getMinValue(array $series): float
    {
        return min(array_map('min', $series));
    }

    /**
     * Return the max values across all data series.
     *
     * @param float[][] $series
     * @return float
     */
    private function getMaxValue(array $series): float
    {
        return max(array_map('max', $series));
    }

    private function interpolateYCoord(
        int $areaTopY,
        int $areaBottomY,
        int $value,
        int $maxValue,
        int $minValue
    ): int {
        $pct = 1 - ($value - $minValue) / ($maxValue - $minValue);
        return $areaTopY + ($areaBottomY - $areaTopY) * $pct;
    }

    private function calculateCoordinates(): array
    {
        $coords = [];

        $coords['title_top_left'] = [
            'x' => $this->width / 2 - imagefontwidth(self::TITLE_FONT_SIZE) * strlen($this->title) / 2,
            'y' => self::PLOT_MARGIN
        ];
        $coords['y_axis_top_left'] = [
            'x' => self::PLOT_MARGIN,
            'y' => self::PLOT_MARGIN + imagefontheight(self::TITLE_FONT_SIZE) + self::INTER_ELEMENT_SPACING
        ];
        $coords['y_axis_bottom_right'] = [
            'x' => self::PLOT_MARGIN + 75,
            'y' => $this->height - self::PLOT_MARGIN - (imagefontheight(self::AXIS_VALUE_FONT_SIZE) + self::INTER_ELEMENT_SPACING) * 2
        ];
        $coords['x_axis_top_left'] = [
            'x' => $coords['y_axis_bottom_right']['x'],
            'y' => $coords['y_axis_bottom_right']['y']
        ];
        $coords['x_axis_bottom_right'] = [
            'x' => $this->width - self::PLOT_MARGIN,
            'y' => $this->height - self::PLOT_MARGIN,
        ];
        $coords['chart_area_top_left'] = [
            'x' => $coords['y_axis_bottom_right']['x'],
            'y' => $coords['y_axis_top_left']['y']
        ];
        $coords['chart_area_bottom_right'] = [
            'x' => $coords['x_axis_bottom_right']['x'],
            'y' => $coords['x_axis_top_left']['y']
        ];

        return $coords;
    }

    private function initChart(int $width, int $height)
    {
        $img = imagecreatetruecolor($this->width, $this->height);
        imageantialias($img, true);

        // White background fill
        imagefill(
            $img,
            0,
            0,
            imagecolorallocate($img, 0xff, 0xff, 0xff)
        );

        return $img;
    }

    private function drawTitle(&$img, array $coords, string $title): void
    {
        imagestring(
            $img,
            self::TITLE_FONT_SIZE,
            $coords['title_top_left']['x'],
            $coords['title_top_left']['y'],
            $this->title,
            imagecolorallocate($img, 0x00, 0x00, 0x00)
        );
    }

    private function drawYAxis(&$img, array $coords, YAxisConfig $yAxisConfig, float $minValue, float $maxValue): void
    {
        // Main Y axis line
        imageline(
            $img,
            $coords['chart_area_top_left']['x'],
            $coords['chart_area_top_left']['y'],
            $coords['chart_area_top_left']['x'],
            $coords['chart_area_bottom_right']['y'],
            imagecolorallocate($img, 0x00, 0x00, 0x00)
        );

        // Ticks and values
        $tickSpacing = ($coords['y_axis_bottom_right']['y'] - $coords['y_axis_top_left']['y']) / self::N_TICKS_Y_AXIS;
        $valueInterval = ($maxValue - $minValue) / self::N_TICKS_Y_AXIS;
        $valueYOffset = imagefontheight(self::AXIS_VALUE_FONT_SIZE) / 2;
        for ($i = 0; $i < self::N_TICKS_Y_AXIS; $i++) {
            $y = $coords['y_axis_top_left']['y'] + $i * $tickSpacing;
            imageline(
                $img,
                $coords['y_axis_bottom_right']['x'] - 1,
                $y,
                $coords['y_axis_bottom_right']['x'] + 1,
                $y,
                imagecolorallocate($img, 0x00, 0x00, 0x00)
            );

            $label = $maxValue - $i * $valueInterval;
            imagestring(
                $img,
                self::AXIS_VALUE_FONT_SIZE,
                $coords['y_axis_bottom_right']['x'] - imagefontwidth(self::AXIS_VALUE_FONT_SIZE) * strlen($label) - self::INTER_ELEMENT_SPACING,
                $y - $valueYOffset,
                $label,
                imagecolorallocate($img, 0x00, 0x00, 0x00)
            );
        }

        // Name
        imagestringup(
            $img,
            self::AXIS_VALUE_FONT_SIZE,
            $coords['y_axis_top_left']['x'],
            $coords['y_axis_top_left']['y']
                + ($coords['y_axis_bottom_right']['y'] - $coords['y_axis_top_left']['y']) / 2
                + (imagefontwidth(self::AXIS_VALUE_FONT_SIZE) * strlen($yAxisConfig->name)) / 2,
            $yAxisConfig->name,
            imagecolorallocate($img, 0x00, 0x00, 0x00)
        );
    }

    private function drawXAxis(&$img, array $coords, XAxisConfig $xAxisConfig): void
    {
        // Main X axis line
        imageline(
            $img,
            $coords['chart_area_top_left']['x'],
            $coords['chart_area_bottom_right']['y'],
            $coords['chart_area_bottom_right']['x'],
            $coords['chart_area_bottom_right']['y'],
            imagecolorallocate($img, 0x00, 0x00, 0x00)
        );

        // ================
        // Ticks and labels
        // ================
        // The total amount of labels
        $nLabels = count($xAxisConfig->labels);
        // The total number of ticks and labels that we'll print. The higher
        // the number of labels, the fewer of them we'll print so that we don't
        // clutter the axis.
        // Right now this is a function of the order of magnitude of the number
        // of labels. From 1 to 9 elements, we skip none. From 10 to 100, we
        // pick one every ten. From 101 to 1000, we pick one every one hundred,
        // but this is clearly not ideal. We should probably factor in the
        // chart's width, the length of the labels, etc.
        $nTicks = floor($nLabels / (10 ** (floor(log10($nLabels)) - 1)));
        // The number of ticks and labels we skip in between, so that all items
        // that will be printed are evenly distributed across the axis.
        $tickOffset = floor($nLabels / ($nTicks - 1));
        // The space in pixels between each tick.
        $tickSpacing = floor(($coords['x_axis_bottom_right']['x'] - $coords['x_axis_top_left']['x']) / ($nTicks - 1));

        for ($i = 0; $i < $nTicks; $i++) {
            $x = $coords['x_axis_top_left']['x'] + $i * $tickSpacing;
            imageline(
                $img,
                $x,
                $coords['x_axis_top_left']['y'] - 1,
                $x,
                $coords['x_axis_top_left']['y'] + 1,
                imagecolorallocate($img, 0x00, 0x00, 0x00)
            );

            // If the X axis configuration specifies a date format, apply it.
            // Otherwise use the label as-is.
            $label = $xAxisConfig->labels[$i * $tickOffset];
            if ($xAxisConfig->dateFormat !== null) {
                $label = date($xAxisConfig->dateFormat, $label);
            }
            imagestring(
                $img,
                self::AXIS_VALUE_FONT_SIZE,
                $x - imagefontwidth(self::AXIS_VALUE_FONT_SIZE) * strlen($label) / 2,
                $coords['x_axis_top_left']['y'] + self::INTER_ELEMENT_SPACING,
                $label,
                imagecolorallocate($img, 0x00, 0x00, 0x00)
            );
        }

        // Name
        imagestring(
            $img,
            self::AXIS_VALUE_FONT_SIZE,
            $coords['x_axis_top_left']['x']
                + ($coords['x_axis_bottom_right']['x'] - $coords['x_axis_top_left']['x']) / 2
                - imagefontwidth(self::AXIS_VALUE_FONT_SIZE) * strlen($xAxisConfig->name) / 2,
            $coords['x_axis_top_left']['y'] + imagefontheight(self::AXIS_VALUE_FONT_SIZE) + self::INTER_ELEMENT_SPACING * 2,
            $xAxisConfig->name,
            imagecolorallocate($img, 0x00, 0x00, 0x00)
        );
    }

    private function drawData(&$img, array $coords, array $data, int $minValue, int $maxValue): void
    {
        $plotAreaTopY = $coords['chart_area_top_left']['y'];
        $plotAreaBottomY = $coords['chart_area_bottom_right']['y'];
        $nPoints = max(array_map('count', $this->data));
        $segmentWidth = ($coords['chart_area_bottom_right']['x'] - $coords['chart_area_top_left']['x']) / ($nPoints - 1);
        foreach ($data as $idx => $series) {
            [$r, $g, $b] = self::COLORS[$idx % count(self::COLORS)];
            $lineColor = imagecolorallocate($img, $r, $g, $b);

            $fromX = $fromY = null;
            for ($i = 1; $i < count($series); $i++) {
                $fromValue = $series[$i - 1];
                $toValue = $series[$i];

                if ($fromX === null) {
                    $fromX = $coords['chart_area_top_left']['x'] + ($i - 1) * $segmentWidth;
                    $fromY = $this->interpolateYCoord($plotAreaTopY, $plotAreaBottomY, $fromValue, $maxValue, $minValue);
                }

                $toX = $coords['chart_area_top_left']['x'] + $i * $segmentWidth;
                $toY = $this->interpolateYCoord($plotAreaTopY, $plotAreaBottomY, $toValue, $maxValue, $minValue);

                imageline($img, $fromX, $fromY, $toX, $toY, $lineColor);

                $fromX = $toX;
                $fromY = $toY;
            }
        }
    }
}
