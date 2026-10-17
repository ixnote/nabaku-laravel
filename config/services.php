<?php

return [
    'flutterwave' => ['url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'), 'secret' => env('FLUTTERWAVE_SECRET_KEY'), 'hash' => env('FLW_SECRET_HASH'), 'redirect' => env('FLUTTERWAVE_REDIRECT_URI'), 'signature_mode' => env('FLUTTERWAVE_SIGNATURE_MODE', 'legacy')],
    'monnify' => ['url' => env('MONNIFY_BASE_URL', 'https://sandbox.monnify.com'), 'key' => env('MONNIFY_API_KEY'), 'secret' => env('MONNIFY_SECRET_KEY'), 'wallet' => env('MONNIFY_PAYOUT_WALLET')],
    'cloudinary' => ['cloud' => env('CLOUDINARY_CLOUD_NAME'), 'key' => env('CLOUDINARY_API_KEY'), 'secret' => env('CLOUDINARY_API_SECRET'), 'algorithm' => env('CLOUDINARY_SIGNATURE_ALGORITHM', 'sha1')],
];
