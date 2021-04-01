<?php

namespace NodusFramework\TranslationManager;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use NodusFramework\TranslationManager\Services\External\AWSTranslationService;
use NodusFramework\TranslationManager\Services\External\TranslationService;

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

/**
 * Translation manager base class.
 *
 * @author    Bastian Schur <b.schur@nodus-framework.de>
 *
 * @link      http://www.nodus-framework.de
 */
class TranslationManager
{
    /**
     * Default locale
     *
     * @var string|null
     */
    private $defaultLocale;

    /**
     * Available automatic translation services
     *
     * @var string[]|TranslationService[]
     */
    private $automaticTranslationServices = [
        AWSTranslationService::class,
    ];

    /**
     * Create the Translation Manager an set the default locale.
     *
     * @param string|null $defaultLocale
     */
    public function __construct(?string $defaultLocale = null)
    {
        if ($defaultLocale == null) {
            $defaultLocale = config('app.locale', 'en');
        }

        $this->defaultLocale = $defaultLocale;
    }

    /**
     * Get the default locale.
     *
     * @return string
     */
    public function getDefaultLocale()
    {
        return $this->defaultLocale;
    }

    /**
     * Get all registered translation namespaces from laravel.
     *
     * @return array
     */
    public function getNamespaces()
    {
        return app('translator')->getLoader()->namespaces();
    }

    /**
     * Returns the translation filepaths with lang and namespace as key.
     *
     * @param string|null $locale
     * @param string|null $namespace
     *
     * @return array $result[$lang][$namespace][$filePath1,$filePath2..]
     */
    public function getTranslationFiles(?string $locale = null, ?string $namespace = null)
    {
        $translationFiles = [];
        foreach ($this->getNamespaces() as $ns => $translationPath) {
            if ($namespace != null && $namespace != $ns) {
                continue;
            }
            if (File::isDirectory($translationPath)) {
                foreach (File::directories($translationPath) as $dir) {
                    $l = str_replace($translationPath.DS, '', $dir);
                    if ($locale != null && $locale != $l) {
                        continue;
                    }
                    foreach (File::files($dir) as $file) {
                        $translationFiles[$l][$ns][] = $file->getPathname();
                    }
                }
            }
        }

        return $translationFiles;
    }

    /**
     * Returns all translation values for a file.
     *
     * @param string|null $locale    Locale
     * @param string|null $namespace Namespace prefix
     *
     * @return array Array with translation values and usage key as key
     * @throws Exception Throws exception if an translationfile contains not an array
     */
    public function getTranslationValues(?string $locale = null, ?string $namespace = null)
    {
        $result = [];
        foreach ($this->getTranslationFiles($locale, $namespace) as $l => $namespaces) {
            if ($locale != null & $locale != $l) {
                continue;
            }
            foreach ($namespaces as $ns => $files) {
                if ($namespace != null && $namespace != $ns) {
                    continue;
                }
                foreach ($files as $file) {
                    $values = require $file;
                    if (!is_array($values)) {
                        throw new Exception('Invalid translation file "'.$file.'"');
                    }
                    $result = array_merge(
                        $result,
                        $this->getValues($values, ($ns == '') ? '' : $ns.'::'.pathinfo($file)['filename'].'.')
                    );
                }
            }
        }

        return $result;
    }

    public function getUntranslatedValues(string $sourceLocale, string $targetLocale, ?string $namespace = null)
    {
        $untranslatedValues = [];
        $targetLocale = $this->getTranslationValues($targetLocale, $namespace);
        foreach ($this->getTranslationValues($sourceLocale, $namespace) as $name => $sourceValue) {
            if (!array_key_exists($name, $targetLocale)) {
                $untranslatedValues[$name] = $sourceValue;
            }
        }

        return $untranslatedValues;
    }

    /**
     * Get recursisve values from a translationfile.
     *
     * @param array  $values Translation values array
     * @param string $prefix Prefix
     *
     * @return array Array with translation values
     */
    private function getValues(array $values, string $prefix)
    {
        $result = [];
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                foreach ($this->getValues($value, $key.'.') as $key => $value) {
                    $result[$prefix.$key] = $value;
                }
            } else {
                $result[$prefix.$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Write the values to translation file.
     *
     * @param array  $translationValues Array with translated values
     * @param string $translationLocale Locale
     */
    public function writeValues(array $translationValues, string $translationLocale)
    {
        $namespaces = $this->getNamespaces();
        foreach ($translationValues as $ns => $files) {
            if (array_key_exists($ns, $namespaces)) {
                foreach ($files as $fileName => $values) {
                    if (!File::exists($namespaces[$ns].DS.$translationLocale)) {
                        File::makeDirectory($namespaces[$ns].DS.$translationLocale);
                    }
                    $this->writeFile($namespaces[$ns].DS.$translationLocale.DS.$fileName.'.php', $values);
                }
            } else {
                echo 'FEHLER';
            }
        }
    }

    /**
     * Creates the translation file with values.
     *
     * @param string $file   Translation file
     * @param array  $values Values
     */
    private function writeFile(string $file, array $values)
    {
        if (File::exists($file)) {
            $values = array_merge(require $file, $values);
        }
        $export = var_export($values, true);
        $export = preg_replace('/^([ ]*)(.*)/m', '$1$1$2', $export);
        $array = preg_split("/\r\n|\n|\r/", $export);
        $array = preg_replace(["/\s*array\s\($/", "/\)(,)?$/", "/\s=>\s$/"], [null, ']$1', ' => ['], $array);
        $export = implode(PHP_EOL, array_filter(['['] + $array));
        file_put_contents($file, "<?php\n\nreturn ".$export.';');
    }

    public function getAutomaticTranslationServices()
    {
        $services = [];
        foreach ($this->automaticTranslationServices as $translationService) {
            if ($translationService::checkRequirements()) {
                $services[class_basename($translationService)] = $translationService;
            }
        }

        return $services;
    }

    public function write(array $translatedLocaleValues)
    {
        $translationValues = [];
        foreach ($translatedLocaleValues as $locale => $values) {
            $data = [];
            foreach ($values as $key => $value) {
                if ($value == null) {
                    continue;
                }

                if (preg_match('/([a-z:._]{1,}::)?([a-zA-Z_-]{1,}).(.*)/', $key, $matches) !== false) {
                    if (count($matches) == 3) {
                        $ns = '';
                        $translationFile = $matches[1];
                        $key = $matches[2];
                    } else {
                        $ns = substr($matches[1], 0, -2);
                        $translationFile = $matches[2];
                        $key = $matches[3];
                    }
                } else {
                    throw new Exception('Parsing error:'.$key);
                }

                Arr::set($data[$ns][$translationFile], $key, $value);
                $translationValues[$ns][$translationFile] = $data[$ns][$translationFile];
            }
            $this->writeValues($translationValues, $locale);
        }
    }
}
