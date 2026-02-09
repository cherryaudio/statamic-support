<?php

namespace Acoustica\StatamicSupport\Providers;

use Acoustica\StatamicSupport\Contracts\SupportProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KayakoProvider implements SupportProvider
{
    protected string $baseUrl;
    protected string $clientId;
    protected string $clientSecret;
    protected string $scopes;
    protected int $timeout;
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->baseUrl = rtrim($config['url'] ?? '', '/');
        $this->clientId = $config['client_id'] ?? '';
        $this->clientSecret = $config['client_secret'] ?? '';
        $this->scopes = $config['scopes'] ?? 'users conversations';
        $this->timeout = $config['timeout'] ?? 30;
    }

    public function getName(): string
    {
        return 'Kayako';
    }

    public function isConfigured(): bool
    {
        return !empty($this->baseUrl)
            && !empty($this->clientId)
            && !empty($this->clientSecret);
    }

    protected function getAccessToken(): string
    {
        $cached = Cache::get('kayako_oauth_token');

        if ($cached) {
            return $cached;
        }

        $response = Http::asForm()
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/oauth/token", [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => $this->scopes,
            ]);

        if (!$response->successful()) {
            Log::error('Kayako OAuth token request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to obtain Kayako OAuth access token: ' . $response->body());
        }

        $data = $response->json();
        $token = $data['access_token'] ?? null;
        $expiresIn = $data['expires_in'] ?? 3600;

        if (!$token) {
            throw new \Exception('Kayako OAuth response did not contain an access token.');
        }

        // Cache for expires_in minus 60 seconds to account for clock skew
        $cacheTtl = max($expiresIn - 60, 0);
        Cache::put('kayako_oauth_token', $token, $cacheTtl);

        return $token;
    }

    protected function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->getAccessToken())->timeout($this->timeout);
    }

    public function createCase(array $data): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Kayako provider is not configured.');
        }

        $requester = $this->findOrCreateRequester($data['email']);

        $caseData = [
            'subject' => 'Support Request',
            'contents' => $this->formatContents($data),
            'requester_id' => $requester['id'],
            'channel' => $this->config['channel'] ?? 'MAIL',
            'channel_id' => $this->config['channel_id'] ?? 1,
            'channel_options' => ['html' => true],
        ];

        $response = $this->httpClient()
            ->post("{$this->baseUrl}/api/v1/cases.json", $caseData);

        if (!$response->successful()) {
            Log::error('Kayako API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to create Kayako case: ' . $response->body());
        }

        $responseData = $response->json();

        return [
            'id' => $responseData['data']['id'] ?? null,
            'raw' => $responseData,
        ];
    }

    public function testConnection(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = $this->httpClient()
                ->get("{$this->baseUrl}/api/v1/me.json");

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Kayako connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function findOrCreateRequester(string $email): array
    {
        Log::info('Kayako creating new user', ['email' => $email]);

        $createResponse = $this->httpClient()
            ->post("{$this->baseUrl}/api/v1/users.json", [
                'full_name' => $email,
                'email' => $email,
                'role_id' => $this->config['customer_role_id'] ?? 4,
            ]);

        if ($createResponse->successful()) {
            $newUserId = $createResponse->json('data.id');
            Log::info('Kayako user created', [
                'email' => $email,
                'new_user_id' => $newUserId,
            ]);
            return ['id' => $newUserId];
        }

        // If user already exists, search for them by email
        if ($this->isDuplicateEmailError($createResponse)) {
            Log::info('Kayako user already exists, searching by email', ['email' => $email]);
            return $this->searchUserByEmail($email);
        }

        Log::error('Failed to find or create Kayako requester', [
            'email' => $email,
            'response' => $createResponse->body(),
        ]);

        throw new \Exception('Failed to find or create requester in Kayako');
    }

    protected function isDuplicateEmailError($response): bool
    {
        if ($response->status() !== 400) {
            return false;
        }

        $errors = $response->json('errors', []);

        foreach ($errors as $error) {
            if (($error['code'] ?? '') === 'FIELD_DUPLICATE' && ($error['parameter'] ?? '') === 'email') {
                return true;
            }
        }

        return false;
    }

    protected function searchUserByEmail(string $email): array
    {
        $filterResponse = $this->httpClient()
            ->post("{$this->baseUrl}/api/v1/users/filter.json", [
                'predicates' => [
                    'collection_operator' => 'OR',
                    'collections' => [
                        [
                            'proposition_operator' => 'AND',
                            'propositions' => [
                                [
                                    'field' => 'identityemails.address',
                                    'operator' => 'comparison_equalto',
                                    'value' => $email,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        if ($filterResponse->successful()) {
            $users = $filterResponse->json('data', []);

            if (!empty($users[0]['id'])) {
                Log::info('Kayako found existing user via filter', [
                    'email' => $email,
                    'user_id' => $users[0]['id'],
                ]);
                return ['id' => $users[0]['id']];
            }
        }

        Log::error('Kayako user exists but filter failed to find them', [
            'email' => $email,
            'filter_status' => $filterResponse->status(),
            'filter_body' => $filterResponse->body(),
        ]);

        throw new \Exception('Failed to find or create requester in Kayako');
    }

    protected function formatContents(array $data): string
    {
        $contents = e($data['message']);

        $contents .= "\n\n---\nSubmitted via Support Contact Form";
        $contents .= "\nEmail: " . e($data['email']);

        return nl2br($contents);
    }

}
