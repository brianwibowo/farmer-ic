<?php

return [
    'max_dimension' => 2048,
    'target_bytes' => 512 * 1024,
    'quality' => 82,
    'min_quality' => 62,
    // Compression budget only: larger user uploads are stored as originals.
    'max_processing_pixels' => 24_000_000,
];
