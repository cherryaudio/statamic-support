<?php

namespace Acoustica\StatamicSupport\Services;

use Illuminate\Support\Facades\Log;

class SpamValidationService
{
    /**
     * Spam patterns to check against.
     */
    protected array $spamPatterns;

    /**
     * Forbidden words that result in immediate rejection.
     */
    protected array $forbiddenWords;

    /**
     * Configuration options.
     */
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->spamPatterns = $config['patterns'] ?? $this->getDefaultPatterns();
        $this->forbiddenWords = $config['forbidden_words'] ?? $this->getDefaultForbiddenWords();
    }

    /**
     * Get default spam patterns.
     */
    protected function getDefaultPatterns(): array
    {
        return [
            // URLs in message (excessive linking)
            '/\b(https?:\/\/[^\s]+){3,}/i',

            // Common spam phrases
            '/\b(buy now|click here|act now|limited time|free money|lottery winner|you have won|congratulations you|dear friend|make money fast|work from home opportunity|double your|triple your|investment opportunity|nigerian prince|wire transfer|western union)\b/i',

            // Excessive use of caps
            '/[A-Z\s]{30,}/',

            // Crypto/financial scam keywords
            '/\b(bitcoin|crypto|btc|ethereum|wallet address|blockchain opportunity|trading bot|forex|binary options)\b/i',

            // Pharma spam
            '/\b(viagra|cialis|pharmacy|prescription|pills|meds online|cheap medications)\b/i',

            // SEO spam
            '/\b(seo services|link building|backlinks|google ranking|first page|search engine optimization)\b/i',

            // Suspicious email domains often used by spammers
            '/@(mail\.ru|yandex\.|qq\.com|163\.com|126\.com)/i',

            // Excessive special characters (common in spam)
            '/[!$%]{5,}/',

            // HTML/script injection attempts
            '/<script|<iframe|javascript:|onclick|onerror/i',

            // Repetitive characters (like "aaaaaaa" or "!!!!!")
            '/(.)\1{7,}/',
        ];
    }

    /**
     * Get default forbidden words.
     */
    protected function getDefaultForbiddenWords(): array
    {
        return [
            'casino',
            'gambling',
            'porn',
            'xxx',
            'adult content',
            'nude',
            'sex video',
        ];
    }

    /**
     * Validate submission data for spam patterns.
     *
     * @param array $data The form submission data
     * @return array ['is_spam' => bool, 'reason' => string|null]
     */
    public function validate(array $data): array
    {
        $textToCheck = implode(' ', [
            $data['name'] ?? '',
            $data['email'] ?? '',
            $data['subject'] ?? '',
            $data['message'] ?? '',
        ]);

        // Check for forbidden words first
        foreach ($this->forbiddenWords as $word) {
            if (stripos($textToCheck, $word) !== false) {
                $this->logSpamAttempt($data, "Forbidden word detected: {$word}");
                return [
                    'is_spam' => true,
                    'reason' => 'forbidden_word',
                ];
            }
        }

        // Check spam patterns
        foreach ($this->spamPatterns as $pattern) {
            if (preg_match($pattern, $textToCheck)) {
                $this->logSpamAttempt($data, "Pattern matched: {$pattern}");
                return [
                    'is_spam' => true,
                    'reason' => 'pattern_match',
                ];
            }
        }

        // Check for suspicious name patterns
        if ($this->hasSuspiciousName($data['name'] ?? '')) {
            $this->logSpamAttempt($data, 'Suspicious name pattern');
            return [
                'is_spam' => true,
                'reason' => 'suspicious_name',
            ];
        }

        // Check for message length
        $messageLength = strlen($data['message'] ?? '');
        $minLength = $this->config['min_message_length'] ?? 10;
        $maxLength = $this->config['max_message_length'] ?? 10000;

        if ($messageLength < $minLength) {
            return [
                'is_spam' => true,
                'reason' => 'message_too_short',
            ];
        }

        if ($messageLength > $maxLength) {
            $this->logSpamAttempt($data, 'Message too long');
            return [
                'is_spam' => true,
                'reason' => 'message_too_long',
            ];
        }

        // Check for gibberish names
        if ($this->isGibberish($data['name'] ?? '')) {
            $this->logSpamAttempt($data, 'Gibberish name detected');
            return [
                'is_spam' => true,
                'reason' => 'gibberish_name',
            ];
        }

        return [
            'is_spam' => false,
            'reason' => null,
        ];
    }

    /**
     * Check if a name looks suspicious.
     *
     * @param string $name
     * @return bool
     */
    protected function hasSuspiciousName(string $name): bool
    {
        // Name is all numbers
        if (preg_match('/^[\d\s]+$/', $name)) {
            return true;
        }

        // Name contains URL
        if (preg_match('/https?:\/\//', $name)) {
            return true;
        }

        // Name is too long
        if (strlen($name) > 100) {
            return true;
        }

        return false;
    }

    /**
     * Basic gibberish detection for names.
     *
     * @param string $text
     * @return bool
     */
    protected function isGibberish(string $text): bool
    {
        // Check for excessive consonant clusters (unlikely in real names)
        if (preg_match('/[bcdfghjklmnpqrstvwxyz]{6,}/i', $text)) {
            return true;
        }

        // Check for random character patterns
        if (preg_match('/^[a-z]{2,3}\d+[a-z]*$/i', $text)) {
            return true;
        }

        return false;
    }

    /**
     * Log a spam attempt for monitoring.
     *
     * @param array $data
     * @param string $reason
     */
    protected function logSpamAttempt(array $data, string $reason): void
    {
        if ($this->config['log_spam'] ?? true) {
            Log::channel($this->config['log_channel'] ?? 'daily')->info('Spam submission blocked', [
                'reason' => $reason,
                'email' => $data['email'] ?? 'unknown',
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }
    }

    /**
     * Add a custom spam pattern.
     *
     * @param string $pattern
     * @return self
     */
    public function addPattern(string $pattern): self
    {
        $this->spamPatterns[] = $pattern;
        return $this;
    }

    /**
     * Add a forbidden word.
     *
     * @param string $word
     * @return self
     */
    public function addForbiddenWord(string $word): self
    {
        $this->forbiddenWords[] = $word;
        return $this;
    }
}
