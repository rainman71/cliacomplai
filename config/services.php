<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
        // Restrict logins to this Google Workspace domain (e.g. rightsizelabs.com).
        // Leave blank to allow any verified Google account (dev only).
        'allowed_domain' => env('AUTH_ALLOWED_DOMAIN'),
        // Drive auto-filing: path to a service-account JSON key + the root folder id
        // (a Drive folder shared with the service account). Blank -> NullDriveFiler.
        'drive_credentials' => env('GOOGLE_DRIVE_CREDENTIALS'),
        'drive_root_folder_id' => env('GOOGLE_DRIVE_ROOT_FOLDER_ID'),
    ],

];
