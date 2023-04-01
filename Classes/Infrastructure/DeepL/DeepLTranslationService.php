<?php
declare(strict_types=1);

namespace Sitegeist\LostInTranslation\Infrastructure\DeepL;

use Exception;
use Neos\Flow\Annotations\InjectConfiguration;
use Neos\Flow\Annotations\Scope;
use Neos\Flow\Http\Client\Browser;
use Neos\Flow\Http\Client\CurlEngine;
use Neos\Http\Factories\ServerRequestFactory;
use Neos\Http\Factories\StreamFactory;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Sitegeist\LostInTranslation\Domain\TranslationServiceInterface;

#[Scope("singleton")]
class DeepLTranslationService implements TranslationServiceInterface
{
    protected const INTERNAL_GLOSSARY_KEY_SEPARATOR = '-';

    #[InjectConfiguration(path: "DeepLApi")]
    protected array $settings;
    #[InjectConfiguration(path: "DeepLApi.glossary.languagePairs", package: "Sitegeist.LostInTranslation")]
    protected array $languagePairs;

    protected string $baseUri;
    protected string $authenticationKey;

    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly ServerRequestFactory $serverRequestFactory,
        protected readonly StreamFactory $streamFactory,
    ) {}

    public function initializeObject(): void
    {
        $deeplAuthenticationKey = new DeepLAuthenticationKey($this->settings['authenticationKey']);
        $this->baseUri = $deeplAuthenticationKey->isFree() ? $this->settings['baseUriFree'] : $this->settings['baseUri'];
        $this->authenticationKey = $deeplAuthenticationKey->getAuthenticationKey();
    }

    protected function getBaseRequest(string $method, string $path): RequestInterface
    {
        return $this->serverRequestFactory->createServerRequest($method, $this->baseUri . $path)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Authorization', sprintf('DeepL-Auth-Key %s', $this->authenticationKey))
        ;
    }

    protected function getTranslateRequest(): RequestInterface
    {
        return $this->getBaseRequest('POST', 'translate')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ;
    }

    protected function getGlossaryLanguagePairsRequest(): RequestInterface
    {
        return $this->getBaseRequest('GET', 'glossary-language-pairs');
    }

    protected function getGlossariesRequest(): RequestInterface
    {
        return $this->getBaseRequest('GET', 'glossaries');
    }

    protected function getDeleteGlossaryRequest(string $glossaryId): RequestInterface
    {
        return $this->getBaseRequest('DELETE', "glossaries/$glossaryId");
    }

    protected function getCreateGlossaryRequest(): RequestInterface
    {
        return $this->getBaseRequest('POST', 'glossaries')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ;
    }

    /**
     * @throws ClientExceptionInterface
     */
    protected function sendRequest(RequestInterface $request): ResponseInterface
    {
        $browser = new Browser();
        $engine = new CurlEngine();
        $engine->setOption(CURLOPT_TIMEOUT, 0);
        $browser->setRequestEngine($engine);
        return $browser->sendRequest($request);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function sendGetRequest(RequestInterface $request): array
    {
        $response = $this->sendRequest($request);
        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true);
        } else {
            $this->handleApiErrorResponse($response);
        }
    }

    /**
     * @throws Exception
     */
    protected function handleApiErrorResponse(ResponseInterface $response): void
    {
        $content = json_decode($response->getBody()->getContents(), true);
        $detail = (is_array($content) && isset($content['detail']) ? $content['detail'] : null);
        $code = $response->getStatusCode();
        $reason = $response->getReasonPhrase();
        $message = "DeepL API error, HTTP Status $code ($reason)" . ($detail ? ": $detail" : '');
        $this->logger->error($message);
        throw new Exception($message);
    }

    /**
     * @param array<string,string> $texts
     * @param string $targetLanguage
     * @param string|null $sourceLanguage
     * @return array
     * @throws ClientExceptionInterface
     */
    public function translate(array $texts, string $targetLanguage, ?string $sourceLanguage = null): array
    {
        // store keys and values separately for later reunion
        $keys = array_keys($texts);
        $values = array_values($texts);

        // request body ... this has to be done manually because of the non php ish format
        // with multiple text arguments
        $body = http_build_query($this->settings['defaultOptions']);
        if ($sourceLanguage) {
            $body .= '&source_lang=' . urlencode($sourceLanguage);
        }
        $body .= '&target_lang=' . urlencode($targetLanguage);
        foreach($values as $part) {
            // All ignored terms will be wrapped in a <ignored> tag
            // which will be ignored by DeepL
            if (isset($this->settings['ignoredTerms']) && count($this->settings['ignoredTerms']) > 0) {
                $part = preg_replace('/(' . implode('|', $this->settings['ignoredTerms']) . ')/i', '<ignore>$1</ignore>', $part);
            }

            $body .= '&text=' . urlencode($part);
        }

        // the DeepL API is not consistent here - the "translate" endpoint requires the locale
        // for some languages, while the glossary can only handle pure languages - no locales -
        // so we extract the raw language from the configured languages that are used for "translate"
        list($glossarySourceLanguage) = explode('-', $sourceLanguage);
        list($glossaryTargetLanguage) = explode('-', $targetLanguage);
        $glossaryId = $this->getGlossaryId($glossarySourceLanguage, $glossaryTargetLanguage);
        if ($glossaryId !== null) {
            $body .= '&glossary_id=' . urlencode($glossaryId);
        }

        $apiRequest = $this
            ->getTranslateRequest()
            ->withBody($this->streamFactory->createStream($body))
        ;

        $apiResponse = $this->sendRequest($apiRequest);

        if ($apiResponse->getStatusCode() == 200) {
            $returnedData = json_decode($apiResponse->getBody()->getContents(), true);
            if (is_null($returnedData)) {
                return $texts;
            }
            $translations = array_map(
                function($part) {
                    return preg_replace('/(<ignore>|<\/ignore>)/i', '', $part['text']);
                },
                $returnedData['translations']
            );
            return array_combine($keys, $translations);
        } else {
            if ($apiResponse->getStatusCode() === 403) {
                $this->logger->critical('Your DeepL API credentials are either wrong, or you don\'t have access to the requested API.');
            } elseif ($apiResponse->getStatusCode() === 429) {
                $this->logger->warning('You sent too many requests to the DeepL API.');
            } elseif ($apiResponse->getStatusCode() === 456) {
                $this->logger->warning('You reached your DeepL API character limit. Upgrade your plan or wait until your quota is filled up again.');
            } elseif ($apiResponse->getStatusCode() === 400) {
                $this->logger->warning('Your DeepL API request was not well-formed. Please check the source and the target language in particular.', [
                    'sourceLanguage' => $sourceLanguage,
                    'targetLanguage' => $targetLanguage
                ]);
            } else {
                $this->logger->warning('Unexpected status from Deepl API', ['status' => $apiResponse->getStatusCode()]);
            }
            return $texts;
        }
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    protected function getDeepLLanguagePairs(): array
    {
        $request = $this->getGlossaryLanguagePairsRequest();
        return $this->sendGetRequest($request)['supported_languages'];
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function getGlossaries(): array
    {
        $request = $this->getGlossariesRequest();
        return $this->sendGetRequest($request)['glossaries'];
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function getGlossaryId(string $sourceLanguage, string $targetLanguage): string|null
    {
        $requestedInternalKey = $this->getInternalGlossaryKey($sourceLanguage, $targetLanguage);
        $glossaries = $this->getGlossaries();
        foreach ($glossaries as $glossary) {
            if (!$glossary['ready']) {
                continue;
            }
            $currentInternalKey = $this->getInternalGlossaryKey($glossary['source_lang'], $glossary['target_lang']);
            if ($currentInternalKey === $requestedInternalKey) {
                return $glossary['glossary_id'];
            }
        }
        return null;
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function deleteGlossary(string $glossaryId): void
    {
        $request = $this->getDeleteGlossaryRequest($glossaryId);
        $response = $this->sendRequest($request);
        if ($response->getStatusCode() !== 204) {
            $this->handleApiErrorResponse($response);
        }
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function createGlossary(string $body): void
    {
        $bodyStream = $this->streamFactory->createStream($body);
        $request = $this->getCreateGlossaryRequest();
        $request = $request->withBody($bodyStream);

        $response = $this->sendRequest($request);
        if ($response->getStatusCode() !== 201) {
            $this->handleApiErrorResponse($response);
        }
    }

    public function getInternalGlossaryKey(string $sourceLangauge, string $targetLangauge): string
    {
        return strtoupper($sourceLangauge) . self::INTERNAL_GLOSSARY_KEY_SEPARATOR . strtoupper($targetLangauge);
    }

    /**
     * @return string[]
     */
    public function getLanguagesFromInternalGlossaryKey(string $internalGlossaryKey): array
    {
        list($sourceLangauge, $targetLangauge) = explode(self::INTERNAL_GLOSSARY_KEY_SEPARATOR, $internalGlossaryKey);
        return [$sourceLangauge, $targetLangauge];
    }

    /**
     * Only return configured language pairs that are supported by the DeepL API.
     * If $limitToLanguages is provided we also return the paired languages to the provided ones
     * in case they are missing.
     *
     * @throws ClientExceptionInterface
     */
    public function getLanguagePairs(array|null $limitToLanguages = null): array
    {
        $languagePairs = [];

        $limitToLanguagesUpdated = $limitToLanguages;
        $checkForLimitToLanguagesUpdate = false;
        $apiSource = null;
        $apiTarget = null;
        $configuredPairs = $this->languagePairs;
        $apiPairs = $this->getDeepLLanguagePairs();

        foreach ($configuredPairs as $configuredPair) {
            $configuredSource = $configuredPair['source'];
            $configuredTarget = $configuredPair['target'];

            if (
                is_array($limitToLanguages)
                && !in_array($configuredSource, $limitToLanguages)
                && !in_array($configuredTarget, $limitToLanguages)
            ) {
                continue;
            }

            $internalKeyFromConfiguredPair = $this->getInternalGlossaryKey($configuredSource, $configuredTarget);
            foreach ($apiPairs as $apiPair) {
                $apiSource = strtoupper($apiPair['source_lang']);
                $apiTarget = strtoupper($apiPair['target_lang']);
                $internalKeyFromApiPair = $this->getInternalGlossaryKey($apiSource, $apiTarget);
                if ($internalKeyFromConfiguredPair === $internalKeyFromApiPair) {
                    $languagePairs[] = $configuredPair;
                    $checkForLimitToLanguagesUpdate = true;
                    break;
                }
            }

            if ($checkForLimitToLanguagesUpdate && is_array($limitToLanguages)) {
                if (in_array($apiSource, $limitToLanguages) && !in_array($apiTarget, $limitToLanguagesUpdated)) {
                    $limitToLanguagesUpdated[] = $apiTarget;
                } elseif (in_array($apiTarget, $limitToLanguages) && !in_array($apiSource, $limitToLanguagesUpdated)) {
                    $limitToLanguagesUpdated[] = $apiSource;
                }
                $checkForLimitToLanguagesUpdate = false;
            }

        }

        return [$languagePairs, $limitToLanguagesUpdated];
    }

}
