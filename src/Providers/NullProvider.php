<?php

namespace Acoustica\StatamicSupport\Providers;

use Acoustica\StatamicSupport\Contracts\SupportProvider;
use Illuminate\Support\Facades\Log;

/**
 * Null provider that logs submissions but doesn't send them anywhere.
 * Useful for development/testing or when no provider is configured.
 */
class NullProvider implements SupportProvider
{
    public function getName(): string
    {
        return 'Null (Local Only)';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function createCase(array $data): array
    {
        Log::info('Support case created (NullProvider - not sent to external service)', [
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
        ]);

        return [
            'id' => 'local-' . uniqid(),
            'provider' => 'null',
        ];
    }

    public function testConnection(): bool
    {
        return true;
    }
}
