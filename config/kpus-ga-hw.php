<?php

return [
    'timezone' => env('KPUS_GA_HW_TIMEZONE', 'Asia/Jakarta'),
    'run_time' => env('KPUS_GA_HW_RUN_TIME', '09:00'),
    'min_photos' => (int) env('KPUS_GA_HW_MIN_PHOTOS', 2),
    'ai_max_images' => (int) env('KPUS_GA_HW_AI_MAX_IMAGES', 4),
];
