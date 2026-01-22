<?php

namespace Acoustica\StatamicSupport\Contracts;

interface SupportProvider
{
    /**
     * Check if the provider is properly configured.
     */
    public function isConfigured(): bool;

    /**
     * Create a new support case/ticket.
     *
     * @param array $data Case data including:
     *   - name: string
     *   - email: string
     *   - subject: string
     *   - message: string
     *   - priority: string (optional)
     * @return array Response data with at least 'id' key
     * @throws \Exception If the request fails
     */
    public function createCase(array $data): array;

    /**
     * Test the connection to the provider.
     *
     * @return bool
     */
    public function testConnection(): bool;

    /**
     * Get the provider name for logging/display.
     *
     * @return string
     */
    public function getName(): string;
}
