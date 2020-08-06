<?php

namespace Afonso\Plotta;

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

    private $width;

    private $height;

    private $title;

    private $data = [];

    public function withDimensions(int $width, int $height): PlotBuilder
    {
        $this->width = $width;
        $this->height = $height;
        return $this;
    }

    public function withTitle(string $title): PlotBuilder
    {
        $this->title = $title;
        return $this;
    }

    public function withYAxis(array $yAxisConfig): PlotBuilder
    {
        $this->yAxisConfig = $yAxisConfig;
        return $this;
    }

    public function withData(array $data): PlotBuilder
    {
        $this->data[] = $data;
        return $this;
    }

    public function render(string $path): void
    {
        // Seed the PRNG so we always get the same color sequence
        // srand(1);

        [$minValue, $maxValue] = $this->getMinAndMaxValues($this->data);

        $coords = $this->calculateCoordinates();

        $img = imagecreatetruecolor($this->width, $this->height);
        imageantialias($img, true);

        // Fill
        imagefill(
            $img,
            0,
            0,
            imagecolorallocate($img, 0xff, 0xff, 0xff)
        );

        // Title
        imagestring(
            $img,
            self::TITLE_FONT_SIZE,
            $coords['title_top_left']['x'],
            $coords['title_top_left']['y'],
            $this->title,
            imagecolorallocate($img, 0x00, 0x00, 0x00)
        );

        // Y Axis
        $this->drawYAxis($img, $coords, $minValue, $maxValue);

        // X Axis
        imageline(
            $img,
            $coords['chart_area_top_left']['x'],
            $coords['chart_area_bottom_right']['y'],
            $coords['chart_area_bottom_right']['x'],
            $coords['chart_area_bottom_right']['y'],
            imagecolorallocate($img, 0x00, 0x00, 0x00)
        );

        // Data
        $plotAreaTopY = $coords['chart_area_top_left']['y'];
        $plotAreaBottomY = $coords['chart_area_bottom_right']['y'];
        $nPoints = max(array_map('count', $this->data));
        $segmentWidth = ($coords['chart_area_bottom_right']['x'] - $coords['chart_area_top_left']['x']) / ($nPoints - 1);
        foreach ($this->data as $idx => $series) {
            [$r, $g, $b] = self::COLORS[$idx % count(self::COLORS)];
            $lineColor = imagecolorallocate($img, $r, $g, $b);
            // $lineColor = imagecolorallocate($img, rand(0, 255), rand(0, 255), rand(0, 255));
            for ($i = 1; $i < count($series); $i++) {
                $fromValue = $series[$i - 1];
                $toValue = $series[$i];

                $fromX = $coords['chart_area_top_left']['x'] + ($i - 1) * $segmentWidth;
                $fromY = $this->interpolateYCoord($plotAreaTopY, $plotAreaBottomY, $fromValue, $maxValue, $minValue);
                $toX = $coords['chart_area_top_left']['x'] + $i * $segmentWidth;
                $toY = $this->interpolateYCoord($plotAreaTopY, $plotAreaBottomY, $toValue, $maxValue, $minValue);

                imageline($img, $fromX, $fromY, $toX, $toY, $lineColor);
            }
        }

        // Write to file
        imagepng($img, $path);
    }

    /**
     * Return the min and max values across all data series.
     *
     * @param float[][] $series
     * @return float[]
     */
    private function getMinAndMaxValues(array $series): array
    {
        $max = max(array_map('max', $series));
        $min = min(array_map('min', $series));
        return [$max, $min];
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
            'y' => $this->height - self::PLOT_MARGIN
        ];
        $coords['chart_area_top_left'] = [
            'x' => $coords['y_axis_bottom_right']['x'],
            'y' => $coords['y_axis_top_left']['y']
        ];
        $coords['chart_area_bottom_right'] = [
            'x' => $this->width - self::PLOT_MARGIN,
            'y' => $this->height - self::PLOT_MARGIN
        ];

        return $coords;
    }

    private function drawYAxis(&$img, array $coords, float $minValue, float $maxValue): void
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

            $label = $minValue + $i * $valueInterval;
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
                + (imagefontwidth(self::AXIS_VALUE_FONT_SIZE) * strlen($this->yAxisConfig['name'])) / 2,
            $this->yAxisConfig['name'],
            imagecolorallocate($img, 0x00, 0x00, 0x00)
        );
    }
}
