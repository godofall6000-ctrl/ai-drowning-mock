<?php
/**
 * AI Detection for AI Drowning Detection System
 * Native PHP implementation using GD library for basic human detection
 */

class AIDetector {
    private $skin_threshold_min = 0.3;
    private $skin_threshold_max = 0.8;
    private $min_human_area = 1000;
    private $max_human_area = 50000;
    private $aspect_ratio_min = 0.3;
    private $aspect_ratio_max = 1.2;

    /**
     * Detect humans in frame using basic image analysis
     * @param resource $image GD image resource
     * @return array Detection results
     */
    public function detectHumans($image) {
        if (!$image) {
            return [];
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // Convert to HSV for better skin detection
        $hsv_image = $this->rgbToHsvImage($image);

        // Detect skin regions
        $skin_regions = $this->detectSkinRegions($hsv_image, $width, $height);

        // Analyze regions for human-like characteristics
        $humans = [];
        foreach ($skin_regions as $region) {
            if ($this->isHumanLike($region, $width, $height)) {
                $humans[] = [
                    'bbox' => $region['bbox'],
                    'confidence' => $region['confidence'],
                    'area' => $region['area'],
                    'center' => $region['center']
                ];
            }
        }

        return $humans;
    }

    /**
     * Convert RGB image to HSV
     */
    private function rgbToHsvImage($image) {
        $width = imagesx($image);
        $height = imagesy($image);

        $hsv_image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $hsv = $this->rgbToHsv($r, $g, $b);

                // Store H, S, V in RGB channels for easy access
                $h_val = (int)($hsv['h'] * 255 / 360);
                $s_val = (int)($hsv['s'] * 255);
                $v_val = (int)($hsv['v'] * 255);

                $color = imagecolorallocate($hsv_image, $h_val, $s_val, $v_val);
                imagesetpixel($hsv_image, $x, $y, $color);
            }
        }

        return $hsv_image;
    }

    /**
     * Convert RGB to HSV
     */
    private function rgbToHsv($r, $g, $b) {
        $r = $r / 255.0;
        $g = $g / 255.0;
        $b = $b / 255.0;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $diff = $max - $min;

        $v = $max;

        if ($max == 0) {
            $s = 0;
        } else {
            $s = $diff / $max;
        }

        if ($diff == 0) {
            $h = 0;
        } else {
            switch ($max) {
                case $r:
                    $h = 60 * (($g - $b) / $diff);
                    break;
                case $g:
                    $h = 60 * (($b - $r) / $diff + 2);
                    break;
                case $b:
                    $h = 60 * (($r - $g) / $diff + 4);
                    break;
            }
            if ($h < 0) {
                $h += 360;
            }
        }

        return ['h' => $h, 's' => $s, 'v' => $v];
    }

    /**
     * Detect skin-colored regions
     */
    private function detectSkinRegions($hsv_image, $width, $height) {
        $skin_pixels = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $hsv = imagecolorat($hsv_image, $x, $y);
                $h = ($hsv >> 16) & 0xFF;
                $s = ($hsv >> 8) & 0xFF;
                $v = $hsv & 0xFF;

                // Convert back to 0-1 range
                $h_norm = ($h / 255.0) * 360;
                $s_norm = $s / 255.0;
                $v_norm = $v / 255.0;

                if ($this->isSkinColor($h_norm, $s_norm, $v_norm)) {
                    $skin_pixels[] = ['x' => $x, 'y' => $y];
                }
            }
        }

        // Group skin pixels into regions
        return $this->groupSkinPixels($skin_pixels);
    }

    /**
     * Check if color is skin-like
     */
    private function isSkinColor($h, $s, $v) {
        // Skin color ranges in HSV
        $hue_min = 0;   // Red to yellow
        $hue_max = 50;  // Up to yellow
        $sat_min = 0.1; // Not too desaturated
        $sat_max = 0.9; // Not too saturated
        $val_min = 0.2; // Not too dark
        $val_max = 1.0; // Not too bright

        return ($h >= $hue_min && $h <= $hue_max) &&
               ($s >= $sat_min && $s <= $sat_max) &&
               ($v >= $val_min && $v <= $val_max);
    }

    /**
     * Group skin pixels into connected regions
     */
    private function groupSkinPixels($skin_pixels) {
        $regions = [];
        $visited = [];

        foreach ($skin_pixels as $pixel) {
            $key = $pixel['x'] . ',' . $pixel['y'];
            if (isset($visited[$key])) continue;

            $region = $this->floodFill($skin_pixels, $pixel, $visited);
            if (count($region) > 50) { // Minimum region size
                $regions[] = $this->analyzeRegion($region);
            }
        }

        return $regions;
    }

    /**
     * Flood fill algorithm to find connected regions
     */
    private function floodFill($all_pixels, $start_pixel, &$visited) {
        $region = [];
        $queue = [$start_pixel];
        $pixel_map = [];

        // Create pixel map for fast lookup
        foreach ($all_pixels as $pixel) {
            $pixel_map[$pixel['x'] . ',' . $pixel['y']] = $pixel;
        }

        while (!empty($queue)) {
            $pixel = array_shift($queue);
            $key = $pixel['x'] . ',' . $pixel['y'];

            if (isset($visited[$key])) continue;
            $visited[$key] = true;

            $region[] = $pixel;

            // Check 4-connected neighbors
            $neighbors = [
                ['x' => $pixel['x'] + 1, 'y' => $pixel['y']],
                ['x' => $pixel['x'] - 1, 'y' => $pixel['y']],
                ['x' => $pixel['x'], 'y' => $pixel['y'] + 1],
                ['x' => $pixel['x'], 'y' => $pixel['y'] - 1]
            ];

            foreach ($neighbors as $neighbor) {
                $n_key = $neighbor['x'] . ',' . $neighbor['y'];
                if (isset($pixel_map[$n_key]) && !isset($visited[$n_key])) {
                    $queue[] = $neighbor;
                }
            }
        }

        return $region;
    }

    /**
     * Analyze a region to extract bounding box and properties
     */
    private function analyzeRegion($region) {
        if (empty($region)) return null;

        $min_x = min(array_column($region, 'x'));
        $max_x = max(array_column($region, 'x'));
        $min_y = min(array_column($region, 'y'));
        $max_y = max(array_column($region, 'y'));

        $width = $max_x - $min_x + 1;
        $height = $max_y - $min_y + 1;
        $area = count($region);

        $center_x = ($min_x + $max_x) / 2;
        $center_y = ($min_y + $max_y) / 2;

        $aspect_ratio = $height > 0 ? $width / $height : 0;

        // Calculate confidence based on area and aspect ratio
        $area_confidence = min(1.0, $area / 10000); // Normalize area confidence
        $ratio_confidence = 1.0 - abs($aspect_ratio - 0.75) / 0.75; // Ideal ratio ~0.75
        $confidence = ($area_confidence + $ratio_confidence) / 2;

        return [
            'bbox' => [$min_x, $min_y, $width, $height],
            'area' => $area,
            'center' => [$center_x, $center_y],
            'aspect_ratio' => $aspect_ratio,
            'confidence' => max(0, min(1, $confidence))
        ];
    }

    /**
     * Check if region looks human-like
     */
    private function isHumanLike($region, $image_width, $image_height) {
        $area = $region['area'];
        $aspect_ratio = $region['aspect_ratio'];
        $bbox = $region['bbox'];

        // Size checks
        if ($area < $this->min_human_area || $area > $this->max_human_area) {
            return false;
        }

        // Aspect ratio check (humans are taller than wide)
        if ($aspect_ratio < $this->aspect_ratio_min || $aspect_ratio > $this->aspect_ratio_max) {
            return false;
        }

        // Position check (humans usually not at very edges)
        $center_x = $region['center'][0];
        $center_y = $region['center'][1];

        $x_margin = $image_width * 0.1;
        $y_margin = $image_height * 0.1;

        if ($center_x < $x_margin || $center_x > $image_width - $x_margin ||
            $center_y < $y_margin || $center_y > $image_height - $y_margin) {
            return false;
        }

        return true;
    }

    /**
     * Analyze drowning risk based on human detection and motion
     */
    public function analyzeDrowningRisk($human_count, $motion_detected, $time_without_motion) {
        if ($human_count == 0) {
            return "No human detected";
        }

        if ($motion_detected) {
            return "Active - Normal swimming";
        }

        if ($time_without_motion > 10) { // seconds
            return "ALERT: Potential drowning - No motion detected";
        }

        return "Monitoring - Minimal motion";
    }

    /**
     * Get human count from detections
     */
    public function getHumanCount($image) {
        $humans = $this->detectHumans($image);
        return count($humans);
    }
}

// Initialize AI detector
$ai_detector = new AIDetector();
?>