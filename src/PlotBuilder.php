<?php

namespace Afonso\Plotta;

class PlotBuilder
{
    const PLOT_MARGIN = 10;
    const COLORS = [
        [0x00, 0x00, 0xff],
        [0xff, 0x00, 0x00],
        [0x00, 0xff, 0x00],
    ];

    private $width;

    private $height;

    private $data = [];

    public function withDimensions(int $width, int $height): PlotBuilder
    {
        $this->width = $width;
        $this->height = $height;
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

        $img = imagecreatetruecolor($this->width, $this->height);
        imageantialias($img, true);

        // Fill
        imagefill(
            $img,
            0,
            0,
            imagecolorallocate($img, 0xff, 0xff, 0xff)
        );

        // Axes
        imageline(
            $img,
            self::PLOT_MARGIN,
            self::PLOT_MARGIN,
            self::PLOT_MARGIN,
            $this->height - self::PLOT_MARGIN,
            imagecolorallocate($img, 0x00, 0x00, 0x00)
        );
        imageline(
            $img,
            self::PLOT_MARGIN,
            $this->height - self::PLOT_MARGIN,
            $this->width - self::PLOT_MARGIN,
            $this->height - self::PLOT_MARGIN,
            imagecolorallocate($img, 0x00, 0x00, 0x00)
        );

        // Data
        $plotAreaTopY = self::PLOT_MARGIN;
        $plotAreaBottomY = $this->height - self::PLOT_MARGIN;
        foreach ($this->data as $idx => $series) {
            [$r, $g, $b] = self::COLORS[$idx % count(self::COLORS)];
            $lineColor = imagecolorallocate($img, $r, $g, $b);
            // $lineColor = imagecolorallocate($img, rand(0, 255), rand(0, 255), rand(0, 255));
            for ($i = 1; $i < count($series); $i++) {
                $fromValue = $series[$i - 1];
                $toValue = $series[$i];

                $fromX = self::PLOT_MARGIN + ($i - 1) / (count($series) - 1) * ($this->width - self::PLOT_MARGIN * 2);
                $fromY = $this->interpolateYCoord($plotAreaTopY, $plotAreaBottomY, $fromValue, $maxValue, $minValue);
                $toX = self::PLOT_MARGIN + ($i) / (count($series) - 1) * ($this->width - self::PLOT_MARGIN * 2);
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
}
