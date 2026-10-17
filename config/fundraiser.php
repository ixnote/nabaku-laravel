<?php

return [
    'jwt_secret' => env('JWT_SECRET'),
    'jwt_ttl' => 2592000,
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
    // The web app reads the token (and email, for resets) from the query string: /pop?token=..., /reset-password?token=...&email=...
    'activation_path' => env('FRONTEND_ACTIVATION_PATH', '/pop'),
    'manager_activation_path' => env('FRONTEND_MANAGER_ACTIVATION_PATH', '/activate-manager/'),
    'reset_path' => env('FRONTEND_RESET_PATH', '/reset-password'),
    'legacy_activation_until' => env('LEGACY_ACTIVATION_UNTIL'),
    'withdrawal_collection' => env('MONGODB_WITHDRAWAL_COLLECTION', 'withdrawalrequests'),
];
