<?php

namespace Acoustica\StatamicSupport\Listeners;

use Acoustica\StatamicSupport\Contracts\SupportProvider;
use Acoustica\StatamicSupport\Services\SpamValidationService;
use Illuminate\Support\Facades\Log;
use Statamic\Events\FormSubmitted;

class HandleSupportFormSubmission
{
    protected SpamValidationService $spamValidator;
    protected SupportProvider $provider;

    public function __construct(SpamValidationService $spamValidator, SupportProvider $provider)
    {
        $this->spamValidator = $spamValidator;
        $this->provider = $provider;
    }

    /**
     * Handle the form submission event.
     *
     * @param FormSubmitted $event
     * @return bool|void
     */
    public function handle(FormSubmitted $event)
    {
        $formHandle = config('support.form_handle', 'support_contact');

        // Only process the configured form
        if ($event->submission->form()->handle() !== $formHandle) {
            return;
        }

        $data = $event->submission->data()->toArray();

        // Map form fields to expected keys using config
        $mappedData = $this->mapFormFields($data);

        // Validate for spam
        $spamResult = $this->spamValidator->validate($mappedData);

        if ($spamResult['is_spam']) {
            Log::info('Support: spam detected', [
                'reason' => $spamResult['reason'],
                'email' => $mappedData['email'] ?? 'unknown',
            ]);

            // Return false to silently discard (appears successful to user)
            return false;
        }

        // Check if provider is configured
        if (!$this->provider->isConfigured()) {
            Log::warning('Support: provider not configured, submission saved locally only');
            return;
        }

        // Submit to provider
        try {
            $response = $this->provider->createCase($mappedData);

            Log::info('Support: case created', [
                'provider' => $this->provider->getName(),
                'case_id' => $response['id'] ?? 'unknown',
                'email' => $mappedData['email'],
            ]);
        } catch (\Exception $e) {
            Log::error('Support: failed to create case', [
                'provider' => $this->provider->getName(),
                'error' => $e->getMessage(),
                'email' => $mappedData['email'],
            ]);

            // Don't throw - let the form submission succeed
            // The submission will be saved locally as backup
        }
    }

    /**
     * Map form field handles to expected keys.
     *
     * @param array $data
     * @return array
     */
    protected function mapFormFields(array $data): array
    {
        $fieldMapping = config('support.field_mapping', [
            'name' => 'name',
            'email' => 'email',
            'subject' => 'subject',
            'message' => 'message',
            'priority' => 'priority',
        ]);

        $mapped = [];

        foreach ($fieldMapping as $expectedKey => $formField) {
            if (isset($data[$formField])) {
                $mapped[$expectedKey] = $data[$formField];
            }
        }

        return $mapped;
    }
}
