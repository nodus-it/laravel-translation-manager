<?php

namespace NodusFramework\TranslationManager\Services\External;


/**
 * Translation Services base class
 *
 * @package   NodusFramework\TranslationManager
 * @author    Bastian Schur <b.schur@nodus-framework.de>
 * @link      http://www.nodus-framework.de
 */
abstract class TranslationService
{
    protected $availableLocales = [];

    protected $pricePerCharacter = 0;

    /**
     * Checks the Service requirements
     *
     * @return bool
     */
    abstract public static function checkRequirements();

    /**
     * Translate a locale string
     *
     * @param string $sourceLocale Source locale
     * @param string $targetLocale Target locale
     * @param string $localeString Locale string
     * @return string|null
     */
    abstract public function translate($sourceLocale, $targetLocale, $localeString);

    /**
     * Returns available locales for the provider
     *
     * @return array
     */
    public function getAvailableLocales()
    {
        return $this->availableLocales;
    }

    /**
     * Calculate the translation costs for the provider
     *
     * @param array $localeStrings Strings to translate
     * @return float Costs in $
     */
    public function calculateCosts($localeStrings)
    {
        $characters = 0;
        foreach ($localeStrings as $value) {
            $characters += strlen($value);
        }

        return round($characters * $this->pricePerCharacter, 2);
    }
}