<?php

declare(strict_types=1);

namespace Syriable\Localizator\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Syriable\Localizator\Contracts\TranslationService;

class AITranslationService implements TranslationService
{
    private Client $httpClient;

    private string $provider;

    private array $config;

    private array $supportedLanguages;

    public function __construct(?Client $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new Client;
        $this->provider = Config::get('localizator.ai.provider', 'openai');
        $this->config = Config::get("localizator.ai.{$this->provider}", []);
        $this->supportedLanguages = $this->loadSupportedLanguages();
    }

    public function translate(string $text, string $sourceLanguage, string $targetLanguage): string
    {
        $result = $this->translateBatch([$text], $sourceLanguage, $targetLanguage);

        return $result[0] ?? $text;
    }

    public function translateBatch(array $texts, string $sourceLanguage, string $targetLanguage): array
    {
        if (empty($texts)) {
            return [];
        }

        try {
            switch ($this->provider) {
                case 'openai':
                    return $this->translateWithOpenAI($texts, $sourceLanguage, $targetLanguage);
                case 'claude':
                    return $this->translateWithClaude($texts, $sourceLanguage, $targetLanguage);
                case 'google':
                    return $this->translateWithGoogle($texts, $sourceLanguage, $targetLanguage);
                case 'azure':
                    return $this->translateWithAzure($texts, $sourceLanguage, $targetLanguage);
                default:
                    throw new \InvalidArgumentException("Unsupported AI provider: {$this->provider}");
            }
        } catch (\Exception $e) {
            Log::error("Translation failed: {$e->getMessage()}", [
                'provider' => $this->provider,
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
                'texts_count' => count($texts),
            ]);

            // Return original texts if translation fails
            return $texts;
        }
    }

    public function getSupportedLanguages(): array
    {
        return $this->supportedLanguages;
    }

    public function validateTranslation(string $original, string $translated, string $targetLanguage): bool
    {
        // Basic validation rules
        if (empty($translated) || $translated === $original) {
            return false;
        }

        // Check if placeholders are preserved
        if (Config::get('localizator.validation.validate_placeholders', true)) {
            return $this->validatePlaceholders($original, $translated);
        }

        return true;
    }

    private function translateWithOpenAI(array $texts, string $sourceLanguage, string $targetLanguage): array
    {
        $apiKey = $this->config['api_key'] ?? null;
        if (! $apiKey) {
            throw new \Exception('OpenAI API key not configured');
        }

        $context = $this->buildTranslationContext($sourceLanguage, $targetLanguage);
        $textsJson = json_encode($texts, JSON_UNESCAPED_UNICODE);

        $prompt = "Translate the following JSON array of strings from {$sourceLanguage} to {$targetLanguage}. {$context} Return only a valid JSON array with the translations in the same order:\n\n{$textsJson}";

        $response = $this->httpClient->post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->config['model'] ?? 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a professional translator. Always return valid JSON arrays.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => $this->config['max_tokens'] ?? 1000,
                'temperature' => $this->config['temperature'] ?? 0.3,
            ],
        ]);

        $result = json_decode($response->getBody()->getContents(), true);
        $translatedText = $result['choices'][0]['message']['content'] ?? '';

        $translations = json_decode($translatedText, true);

        if (! is_array($translations) || count($translations) !== count($texts)) {
            throw new \Exception('Invalid translation response from OpenAI');
        }

        return $translations;
    }

    private function translateWithClaude(array $texts, string $sourceLanguage, string $targetLanguage): array
    {
        $apiKey = $this->config['api_key'] ?? null;
        if (! $apiKey) {
            throw new \Exception('Claude API key not configured');
        }

        $context = $this->buildTranslationContext($sourceLanguage, $targetLanguage);
        $textsJson = json_encode($texts, JSON_UNESCAPED_UNICODE);

        $prompt = "Translate the following JSON array of strings from {$sourceLanguage} to {$targetLanguage}. {$context} Return only a valid JSON array with the translations in the same order:\n\n{$textsJson}";

        $response = $this->httpClient->post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01',
            ],
            'json' => [
                'model' => $this->config['model'] ?? 'claude-3-sonnet-20240229',
                'max_tokens' => $this->config['max_tokens'] ?? 1000,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ],
        ]);

        $result = json_decode($response->getBody()->getContents(), true);
        $translatedText = $result['content'][0]['text'] ?? '';

        $translations = json_decode($translatedText, true);

        if (! is_array($translations) || count($translations) !== count($texts)) {
            throw new \Exception('Invalid translation response from Claude');
        }

        return $translations;
    }

    private function translateWithGoogle(array $texts, string $sourceLanguage, string $targetLanguage): array
    {
        $apiKey = $this->config['api_key'] ?? null;
        if (! $apiKey) {
            throw new \Exception('Google Translate API key not configured');
        }

        $response = $this->httpClient->post('https://translation.googleapis.com/language/translate/v2', [
            'form_params' => [
                'key' => $apiKey,
                'q' => $texts,
                'source' => $this->mapLanguageCode($sourceLanguage, 'google'),
                'target' => $this->mapLanguageCode($targetLanguage, 'google'),
                'format' => 'text',
            ],
        ]);

        $result = json_decode($response->getBody()->getContents(), true);

        if (! isset($result['data']['translations'])) {
            throw new \Exception('Invalid response from Google Translate API');
        }

        return array_map(function ($translation) {
            return $translation['translatedText'];
        }, $result['data']['translations']);
    }

    private function translateWithAzure(array $texts, string $sourceLanguage, string $targetLanguage): array
    {
        $apiKey = $this->config['api_key'] ?? null;
        $region = $this->config['region'] ?? null;
        $endpoint = $this->config['endpoint'] ?? "https://{$region}.api.cognitive.microsoft.com";

        if (! $apiKey || ! $region) {
            throw new \Exception('Azure Translator API key and region not configured');
        }

        $body = array_map(function ($text) {
            return ['text' => $text];
        }, $texts);

        $response = $this->httpClient->post("{$endpoint}/translator/text/v3.0/translate", [
            'headers' => [
                'Ocp-Apim-Subscription-Key' => $apiKey,
                'Ocp-Apim-Subscription-Region' => $region,
                'Content-Type' => 'application/json',
            ],
            'query' => [
                'api-version' => '3.0',
                'from' => $this->mapLanguageCode($sourceLanguage, 'azure'),
                'to' => $this->mapLanguageCode($targetLanguage, 'azure'),
            ],
            'json' => $body,
        ]);

        $result = json_decode($response->getBody()->getContents(), true);

        if (! is_array($result)) {
            throw new \Exception('Invalid response from Azure Translator API');
        }

        return array_map(function ($item) {
            return $item['translations'][0]['text'] ?? '';
        }, $result);
    }

    private function buildTranslationContext(string $sourceLanguage, string $targetLanguage): string
    {
        $contextConfig = Config::get('localizator.ai.context', []);
        $parts = [];

        if (! empty($contextConfig['domain'])) {
            $parts[] = "Domain: {$contextConfig['domain']}";
        }

        if (! empty($contextConfig['tone'])) {
            $parts[] = "Tone: {$contextConfig['tone']}";
        }

        if (! empty($contextConfig['additional_context'])) {
            $parts[] = $contextConfig['additional_context'];
        }

        $parts[] = 'Preserve any placeholders like :name, {count}, etc.';
        $parts[] = 'Maintain the same formatting and structure.';

        return implode('. ', $parts).'.';
    }

    private function validatePlaceholders(string $original, string $translated): bool
    {
        // Extract placeholders from both strings
        preg_match_all('/\{[^}]+\}|:[a-zA-Z_][a-zA-Z0-9_]*/', $original, $originalPlaceholders);
        preg_match_all('/\{[^}]+\}|:[a-zA-Z_][a-zA-Z0-9_]*/', $translated, $translatedPlaceholders);

        // Check if all placeholders are preserved
        $originalSet = array_unique($originalPlaceholders[0]);
        $translatedSet = array_unique($translatedPlaceholders[0]);

        return count($originalSet) === count($translatedSet) &&
               empty(array_diff($originalSet, $translatedSet));
    }

    private function mapLanguageCode(string $languageCode, string $provider): string
    {
        // Map common language codes to provider-specific codes
        $mappings = [
            'google' => [
                'en' => 'en',
                'es' => 'es',
                'fr' => 'fr',
                'de' => 'de',
                'it' => 'it',
                'pt' => 'pt',
                'ru' => 'ru',
                'ja' => 'ja',
                'ko' => 'ko',
                'zh' => 'zh-cn',
                'ar' => 'ar',
            ],
            'azure' => [
                'en' => 'en',
                'es' => 'es',
                'fr' => 'fr',
                'de' => 'de',
                'it' => 'it',
                'pt' => 'pt',
                'ru' => 'ru',
                'ja' => 'ja',
                'ko' => 'ko',
                'zh' => 'zh-Hans',
                'ar' => 'ar',
            ],
        ];

        return $mappings[$provider][$languageCode] ?? $languageCode;
    }

    private function loadSupportedLanguages(): array
    {
        // Return a comprehensive list of supported languages
        return [
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'ru' => 'Russian',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'zh' => 'Chinese',
            'ar' => 'Arabic',
            'nl' => 'Dutch',
            'sv' => 'Swedish',
            'da' => 'Danish',
            'no' => 'Norwegian',
            'fi' => 'Finnish',
            'pl' => 'Polish',
            'tr' => 'Turkish',
            'he' => 'Hebrew',
            'hi' => 'Hindi',
            'th' => 'Thai',
            'vi' => 'Vietnamese',
        ];
    }
}
