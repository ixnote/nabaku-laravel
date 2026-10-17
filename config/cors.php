<?php

return ['paths' => ['api/*'], 'allowed_methods' => ['*'], 'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,https://mediumturquoise-wasp-901113.hostingersite.com')), 'allowed_origins_patterns' => [], 'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Request-ID'], 'exposed_headers' => ['X-Request-ID'], 'max_age' => 600, 'supports_credentials' => true];
