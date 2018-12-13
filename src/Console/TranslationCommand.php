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
    protected $signature = 'nodus:translate {action? : Desired action: overview(default),auto-translate,export,import}  
                                            {--default-locale= : Set a custom default locale}
                                            {--file= : Specify an import file}
                                            ';

    /**
     * Command handler
     */
    public function handle()
    {
        switch ($this->argument('action')) {
            case 'auto-translate':
                $this->autoTranslate();
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
            $files = 0;
            foreach ($namespaces as $namespace) {
                $files += count($namespace);
            }
            $this->info($lang . ': Found ' . $files . ' files with ' . count($translationManager->getTranslationValues($lang)) . ' values' . (config('app.locale',
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
         * Create export file
         */
        $file = fopen('translation_' . $translationManager->getDefaultLocale() . '-' . $translationLocale . '.csv',
            'w');
        fputcsv($file, ['translation_string', $translationManager->getDefaultLocale(), $translationLocale], ';');
        foreach ($translationManager->getUntranslatedValues($translationManager->getDefaultLocale(),
            $translationLocale) as $key => $value) {
            fputcsv($file, [
                $key,
                $value,
                ''
            ], ';');
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
        $translatedLocaleValues = [];
        $file = fopen($this->option('file'), 'r');
        while (($line = fgetcsv($file, 0, ';')) !== false) {
            if ($line[0] == 'translation_string') {
                $translationLocale = $line[2];
            }
            if (!empty($line[2]) && $line[0] != 'translation_string') {
                $translatedLocaleValues[$translationLocale][$line[0]] = $line[2];
            }
        }
        (new TranslationManager())->write($translatedLocaleValues);

    }

    /**
     * Auto translation by a Translation service
     *
     * @throws \Exception
     */
    private function autoTranslate()
    {
        $translationManager = new TranslationManager($this->option('default-locale'));

        $translationServices = $translationManager->getAutomaticTranslationServices();
        $translationService = new $translationServices[$this->choice('Which automatic translation provider should be used?',
            array_keys($translationServices), 0)];
        if (!in_array($translationManager->getDefaultLocale(), $translationService->getAvailableLocales())) {
            $this->warn('Provider ' . class_basename($translationService) . ' doesn\'t support locale "' . $translationManager->getDefaultLocale() . '"');
            return;
        }

        $translationLocale = $this->ask('In which language do you want to translate?', 'en');
        if (!in_array($translationLocale, $translationService->getAvailableLocales())) {
            $this->warn('Provider ' . class_basename($translationService) . ' doesn\'t support locale "' . $translationLocale . '"');
            return;
        }


        $translationValues = $translationManager->getUntranslatedValues($translationManager->getDefaultLocale(),
            $translationLocale);
        if (!$this->confirm('This translation costs $' . $translationService->calculateCosts($translationValues) . ' Do you want to continue?')) {
            return;
        }

        $this->info('Translating values...');
        $progress = $this->output->createProgressBar(count($translationValues));
        foreach ($translationValues as $name => $localeString) {
            $translatedLocaleValues[$translationLocale][$name] = $translationService->translate($translationManager->getDefaultLocale(),
                $translationLocale, $localeString);
            $progress->advance();
        }
        $progress->finish();
        echo "\r\n";


        $this->info('Write values');
        $translationManager->write($translatedLocaleValues);
    }
}