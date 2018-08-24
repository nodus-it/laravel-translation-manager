<?php

namespace NodusFramework\TranslationManager;

use Exception;
use Illuminate\Support\Facades\File;

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

/**
 * Translation manager base class
 *
 * @package   NodusFramework\TranslationManager
 * @author    Bastian Schur <b.schur@nodus-framework.de>
 * @link      http://www.nodus-framework.de
 */
class TranslationManager
{
    private $defaultLocale = '';

    /**
     * Create the Translation Manager an set the default locale
     *
     * @param null $defaultLocale
     */
    public function __construct($defaultLocale = null)
    {
        if ($defaultLocale == null) {
            $defaultLocale = config('app.locale', 'en');
        }
        $this->defaultLocale = $defaultLocale;
    }

    /**
     * Get the default locale
     *
     * @return string
     */
    public function getDefaultLocale()
    {
        return $this->defaultLocale;
    }

    /**
     * Get all registered translation namespaces from laravel
     *
     * @return array
     */
    public function getNamespaces()
    {
        return app('translator')->getLoader()->namespaces();
    }

    /**
     * Returns the translation filepaths with lang and namespace as key
     *
     * @return array $result[$lang][$namespace][$filePath1,$filePath2..]
     */
    public function getTranslationFiles($locale = null, $namespace = null)
    {
        $translationFiles = [];
        foreach ($this->getNamespaces() as $ns => $translationPath) {
            if ($namespace != null && $namespace != $ns) {
                continue;
            }
            if (File::isDirectory($translationPath)) {
                foreach (File::directories($translationPath) as $dir) {
                    $l = str_replace($translationPath . DS, '', $dir);
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
     * Returns all translation values for a file
     *
     * @param string $file Translation file
     * @param string $namespace Namespace prefix
     *
     * @return array Array with translation values and usage key as key
     * @throws Exception Throws exception if an translationfile contains not an array
     */
    public function getTranslationValues($locale = null, $namespace = null)
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
                        throw new Exception('Invalid translation file "' . $file . '"');
                    }
                    $result = array_merge($result,
                        $this->getValues($values, ($ns == '') ? '' : $ns . '::' . pathinfo($file)['filename'] . '.'));
                }
            }
        }

        return $result;
    }

    /**
     * Get recursisve values from a translationfile
     *
     * @param array $values Translation values array
     * @param string $prefix Prefix
     *
     * @return array Array with translation values
     */
    private function getValues($values, $prefix)
    {
        $result = [];
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                foreach ($this->getValues($value, $key . '.') as $key => $value) {
                    $result[$prefix . $key] = $value;
                }
            } else {
                $result[$prefix . $key] = $value;
            }
        }

        return $result;
    }

    /**
     * Write the values to translation file
     *
     * @param array $translationValues Array with translated values
     * @param string $translationLocale Locale
     */
    public function writeValues($translationValues, $translationLocale)
    {
        $namespaces = $this->getNamespaces();
        foreach ($translationValues as $ns => $files) {
            if (array_key_exists($ns, $namespaces)) {
                foreach ($files as $fileName => $values) {
                    if (!File::exists($namespaces[$ns] . DS . $translationLocale)) {
                        File::makeDirectory($namespaces[$ns] . DS . $translationLocale);
                    }
                    $this->writeFile($namespaces[$ns] . DS . $translationLocale . DS . $fileName . '.php', $values);
                }
            } else {
                echo 'FEHLER';
            }
        }
    }

    /**
     * Creates the translation file with values
     *
     * @param string $file Translation file
     * @param array $values Values
     */
    private function writeFile($file, $values)
    {
        if (File::exists($file)) {
            $values = array_merge(require $file, $values);
        }
        $export = var_export($values, true);
        $export = preg_replace("/^([ ]*)(.*)/m", '$1$1$2', $export);
        $array = preg_split("/\r\n|\n|\r/", $export);
        $array = preg_replace(["/\s*array\s\($/", "/\)(,)?$/", "/\s=>\s$/"], [null, ']$1', ' => ['], $array);
        $export = join(PHP_EOL, array_filter(["["] + $array));
        file_put_contents($file, '<?php return ' . $export . ';');
    }
}