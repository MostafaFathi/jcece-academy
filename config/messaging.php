<?php

return [
    'ffprobe' => env('MESSAGING_FFPROBE', 'ffprobe'),
    'broadcast' => (bool) env('MESSAGING_BROADCAST', false),
    'audio_max_seconds' => 120,
    'poll_seconds' => 5,
];
