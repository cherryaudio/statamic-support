<?php

use Acoustica\StatamicSupport\Providers\KayakoProvider;
use Acoustica\StatamicSupport\Providers\NullProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Support Provider
    |--------------------------------------------------------------------------
    |
    | The provider to use for submitting support cases. Set this to the key
    | of one of the providers defined below, or 'null' to disable external
    | submission (cases will still be saved locally in Statamic).
    |
    | Supported: "null", "kayako"
    |
    */

    'provider' => env('SUPPORT_PROVIDER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    |
    | Configure each provider here. The 'driver' key specifies which class
    | to use. You can add your own custom providers by creating a class
    | that implements Acoustica\StatamicSupport\Contracts\SupportProvider.
    |
    */

    'providers' => [

        'null' => [
            'driver' => NullProvider::class,
        ],

        'kayako' => [
            'driver' => KayakoProvider::class,
            'url' => env('KAYAKO_URL', 'https://your-instance.kayako.com'),
            'client_id' => env('KAYAKO_CLIENT_ID'),
            'client_secret' => env('KAYAKO_CLIENT_SECRET'),
            'scopes' => env('KAYAKO_SCOPES', 'users conversations'),
            'channel' => env('KAYAKO_CHANNEL', 'MAIL'),
            'channel_id' => env('KAYAKO_CHANNEL_ID', 1),
            'timeout' => env('KAYAKO_TIMEOUT', 30),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Form Configuration
    |--------------------------------------------------------------------------
    |
    | The handle of the Statamic form to listen for. When this form is
    | submitted, it will be validated for spam and sent to the provider.
    |
    */

    'form_handle' => env('SUPPORT_FORM_HANDLE', 'support_contact'),

    /*
    |--------------------------------------------------------------------------
    | Field Mapping
    |--------------------------------------------------------------------------
    |
    | Map your form field handles to the expected keys. This allows you to
    | use different field names in your form while still correctly mapping
    | them to the support provider API.
    |
    */

    'field_mapping' => [
        'email' => 'email',
        'message' => 'message',
    ],

    /*
    |--------------------------------------------------------------------------
    | Spam Validation Settings
    |--------------------------------------------------------------------------
    |
    | Configure spam validation behavior. You can add custom patterns,
    | forbidden words, and adjust message length limits.
    |
    */

    'spam' => [
        // Whether to log spam attempts
        'log_spam' => true,

        // Log channel to use for spam logging
        'log_channel' => 'daily',

        // Minimum message length (messages shorter than this are rejected)
        'min_message_length' => 10,

        // Maximum message length (messages longer than this are rejected)
        'max_message_length' => 10000,

        // Additional spam patterns (regex) - merged with defaults
        'patterns' => [
            // '/your-pattern/i',
        ],

        // Additional forbidden words - merged with defaults
        'forbidden_words' => [
            // 'custom-word',
        ],
    ],

];
