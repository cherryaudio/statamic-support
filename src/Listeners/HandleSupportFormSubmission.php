<?php

namespace Acoustica\StatamicSupport\Listeners;

use Acoustica\StatamicSupport\Contracts\SupportProvider;
use Acoustica\StatamicSupport\Jobs\SubmitToKayakoJob;
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

            // Mark as spam so staff can review, but skip Kayako dispatch
            $event->submission->set('is_spam', true);
            $event->submission->set('spam_reason', $spamResult['reason']);

            return;
        }

        // Check if provider is configured
        if (!$this->provider->isConfigured()) {
            Log::warning('Support: provider not configured, submission saved locally only');
            return;
        }

        // Dispatch job to queue for async processing
        $queue = config('support.queue', 'default');
        SubmitToKayakoJob::dispatch($mappedData)->onQueue($queue);

        Log::info('Support: submission queued for processing', [
            'email' => $mappedData['email'],
            'queue' => $queue,
        ]);
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
