<?php

namespace NodusFramework\TranslationManager\Console;

use Illuminate\Console\Command;
use NodusFramework\TranslationManager\TranslationManager;

/**
 * Artisan command for interact with the Translation manager
 *
 * @package   NodusFramework\TranslationManager
 * @author    Bastian Schur <b.schur@nodus-framework.de>
 * @link      http://www.nodus-framework.de
 */
class TranslationCommand extends Command
{
    protected $name = 'nodus:translate';

    protected $description = 'Handling translations';

    /**
     * Name uns Signatur des Commands
     *
     * @var string
     */
    protected $signature = 'nodus:translate {action? : Desired action: overview(default),translate,export,import}  
                                            {--default-locale= : Set a custom default locale}
                                            {--file= : Specify an import file}
                                            ';

    /**
     * Command handler
     */
    public function handle()
    {
        switch ($this->argument('action')) {
            case 'translate':
                $this->translate();
                break;

            case 'export':
                $this->export();
                break;

            case 'import':
                $this->import();
                break;

            case 'overview':
            case null:
                $this->overview();
                break;
        }
    }

    /**
     * Show's an overview for translation status
     */
    private function overview()
    {
        $translationManager = new TranslationManager($this->option('default-locale'));
        foreach ($translationManager->getTranslationFiles() as $lang => $namespaces) {
            $this->info($lang . ': Found ' . count($translationManager->getTranslationFiles($lang)) . ' files with ' . count($translationManager->getTranslationValues($lang)) . ' values' . (config('app.locale',
                    null) == $lang ? ' *primary locale' : ''));
        }
    }

    /**
     * Create's an csv export for translating
     *
     * @throws \Exception
     */
    private function export()
    {
        $translationManager = new TranslationManager($this->option('default-locale'));
        $translationLocale = $this->ask('In which language do you want to translate?', 'en');
        if ($translationLocale == $translationManager->getDefaultLocale()) {
            $this->warn('It is not possible to translate the default language');
            return;
        }
        /**
         * Prepare export values
         */
        $defaultLocaleValues = $translationManager->getTranslationValues($translationManager->getDefaultLocale());
        $translationLocaleValues = $translationManager->getTranslationValues($translationLocale);


        /**
         * Create export file
         */
        $file = fopen('translation_' . $translationManager->getDefaultLocale() . '-' . $translationLocale . '.csv',
            'w');
        fputcsv($file, ['translation_string', $translationManager->getDefaultLocale(), $translationLocale]);
        foreach ($defaultLocaleValues as $key => $value) {
            if ((!array_key_exists($key, $translationLocaleValues))) {
                fputcsv($file, [
                    $key,
                    $value,
                    ''
                ]);
            }
        }
        fclose($file);
    }

    /**
     * Import's an translated csv file
     */
    private function import()
    {
        if ($this->option('file') == null) {
            $this->warn('Pleas specify an import file');
            return;
        }
        $file = fopen($this->option('file'), 'r');
        while (($line = fgetcsv($file)) !== false) {
            if ($line[0] == 'translation_string') {
                $translationLocale = $line[2];
            }
            if (!empty($line[2]) && $line[0] != 'translation_string') {
                if (preg_match('/([a-z:.]{1,}::)?([a-zA-Z]{1,}).(.*)/', $line[0], $matches) !== false) {
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
                    $this->warn('Parsing error:' . $line[0]);
                }
                array_set($data, $key, $line[2]);
                $translationValues[$ns][$translationFile] = $data;
            }
        }
        $translationManager = new TranslationManager();
        $translationManager->writeValues($translationValues, $translationLocale);
    }

    /**
     * Console translation service
     */
    private function translate()
    {
        $this->error('This function is no implemented yet');
    }
}