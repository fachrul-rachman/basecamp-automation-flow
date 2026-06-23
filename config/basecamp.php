<?php

return [
    'account_id' => env('BASECAMP_ACCOUNT_ID', '4888518'),
    'project_id' => env('BASECAMP_PROJECT_ID', '47333489'),
    'client_id' => env('BASECAMP_CLIENT_ID'),
    'client_secret' => env('BASECAMP_CLIENT_SECRET'),
    'access_token' => env('BASECAMP_ACCESS_TOKEN'),
    'refresh_token' => env('BASECAMP_REFRESH_TOKEN'),
    'token_expires_at' => env('BASECAMP_TOKEN_EXPIRES_AT'),
    'user_agent' => env('BASECAMP_USER_AGENT', 'LMP Basecamp Audit (your-email@example.com)'),
];
