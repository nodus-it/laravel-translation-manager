<?php

namespace NodusFramework\TranslationManager\Services\External;

/**
 * Translation Manager AWS Translation Service
 *
 * @package   NodusFramework\TranslationManager
 * @author    Bastian Schur <b.schur@nodus-framework.de>
 * @link      http://www.nodus-framework.de
 */
class AWSTranslationService extends TranslationService
{
    private $translationClient = null;

    protected $pricePerCharacter = 0.000015;

    protected $availableLocales = [
        'ar',
        'zh',
        'zh-TW',
        'cs',
        'da',
        'nl',
        'en',
        'fi',
        'fr',
        'de',
        'he',
        'id',
        'it',
        'ja',
        'ko',
        'pl',
        'pt',
        'ru',
        'es',
        'sv',
        'tr'
    ];

    /**
     * Checks the Service requirements
     *
     * @return bool
     */
    public static function checkRequirements()
    {
        if (!class_exists('\Aws\Translate\TranslateClient')) {
            return false;
        }

        if (config('nodus_translation_manager.automatic_mode.provider.aws.credentials.key', null) == null) {
            return false;
        }

        if (config('nodus_translation_manager.automatic_mode.provider.aws.credentials.secret', null) == null) {
            return false;
        }

        return true;
    }

    /**
     * Create or get the AWS translation client
     *
     * @return \Aws\Translate\TranslateClient|null
     */
    private function getTranslationClient()
    {
        if ($this->translationClient == null) {
            $this->translationClient = new \Aws\Translate\TranslateClient([
                'version' => 'latest',
                'region' => 'eu-west-1',
                'credentials' => config('nodus_translation_manager.automatic_mode.provider.aws.credentials'),
            ]);
        }


        return $this->translationClient;
    }

    /**
     * Translate a locale string
     *
     * @param string $sourceLocale Source locale
     * @param string $targetLocale Target locale
     * @param string $localeString Locale string
     * @return string|null
     */
    public function translate($sourceLocale, $targetLocale, $localeString)
    {
        if (strlen($localeString) == 0) {
            return null;
        }

        $variables = [];
        preg_match_all('/:([a-z_]{1,})/m', $localeString, $matches, PREG_SET_ORDER, 0);
        foreach ($matches as $match) {
            $variables[] = $match[0];
            $localeString = str_replace($match[0], 'NODPLHL' . count($variables), $localeString);
        }

        $result = $this->getTranslationClient()->translateText([
            'SourceLanguageCode' => $sourceLocale,
            'TargetLanguageCode' => $targetLocale,
            'TerminologyNames' => [],
            'Text' => $localeString,
        ]);

        if ($result->get('@metadata')['statusCode'] == 200) {
            $translatedString = $result->get('TranslatedText');
            foreach ($variables as $key => $variable) {
                $translatedString = str_replace('NODPLHL' . ($key + 1), $variable, $translatedString);
            }
            return $translatedString;
        }

        return null;
    }
}