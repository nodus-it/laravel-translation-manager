<?php

namespace NodusFramework\TranslationManager\Console;

use Illuminate\Console\Command;
use NodusFramework\TranslationManager\TranslationManager;

/**
 * Artisan command for interact with the Translation manager.
 *
 * @author    Bastian Schur <b.schur@nodus-framework.de>
 *
 * @link      http://www.nodus-framework.de
 */
class TranslationCommand extends Command
{
    protected $name = 'nodus:translate';

    protected $description = 'Handling translations';

    /**
     * Name and signatur of the Commands.
     *
     * @var string
     */
    protected $signature = 'nodus:translate {action? : Desired action: overview (default), auto-translate, export, import}  
                                            {--default-locale= : Set a custom default locale}
                                            {--file= : Specify an import file}
                                            {--overwrite : All entries will be exported}
                                            {--seperator=; : CSV cell seperator character}
                                            ';

    /**
     * Translation manager instance
     *
     * @var TranslationManager
     */
    protected $manager;

    /**
     * CSV cell seperator character
     *
     * @var string
     */
    protected $csvSeperator = ';';

    /**
     * Command handler.
     */
    public function handle()
    {
        $this->manager = new TranslationManager($this->option('default-locale'));
        $this->csvSeperator = $this->option('seperator');

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
     * Show's an overview for translation status.
     */
    private function overview()
    {
        foreach ($this->manager->getTranslationFiles() as $lang => $namespaces) {
            $files = 0;
            foreach ($namespaces as $namespace) {
                $files += count($namespace);
            }

            $this->info(
                $lang . ': Found ' . $files . ' files with ' . count($this->manager->getTranslationValues($lang)) .
                ' values' . (config('app.locale', null) == $lang ? ' *primary locale' : '')
            );
        }
    }

    /**
     * Create's an csv export for translating.
     *
     * @throws \Exception
     */
    private function export()
    {
        $translationLocale = $this->ask('In which language do you want to translate?', 'en');
        if ($translationLocale == $this->manager->getDefaultLocale()) {
            $this->warn('It is not possible to translate the default language');

            return;
        }

        /**
         * Create export file.
         */
        $file = fopen('translation_'.$this->manager->getDefaultLocale().'-'.$translationLocale.'.csv', 'w');
        fputcsv($file, ['translation_string', $this->manager->getDefaultLocale(), $translationLocale], $this->csvSeperator);

        if ($this->option('overwrite')) {
            $translations = $this->manager->getTranslationValues($this->manager->getDefaultLocale());
        } else {
            $translations = $this->manager->getUntranslatedValues(
                $this->manager->getDefaultLocale(),
                $translationLocale
            );
        }

        foreach ($translations as $key => $value) {
            fputcsv($file, [$key, $value, ''], $this->csvSeperator);
        }
        fclose($file);
    }

    /**
     * Import's an translated csv file.
     */
    private function import()
    {
        if ($this->option('file') == null) {
            $this->warn('Please specify an import file');

            return;
        }

        $translatedLocaleValues = [];
        $file = fopen($this->option('file'), 'r');

        // Header row
        $line = fgetcsv($file, 0, $this->csvSeperator);
        if ($line[0] == 'translation_string') {
            $translationLocale = $line[2];
        } else {
            $this->warn('The given file seems to have an incorrect format');

            return;
        }

        // Data rows
        while (($line = fgetcsv($file, 0, $this->csvSeperator)) !== false) {
            if (empty($line[2])) {
                continue;
            }

            $translatedLocaleValues[$translationLocale][$line[0]] = $line[2];
        }

        $this->manager->write($translatedLocaleValues);
    }

    /**
     * Auto translation by a Translation service.
     *
     * @throws \Exception
     */
    private function autoTranslate()
    {
        $translationServices = $this->manager->getAutomaticTranslationServices();
        $translationService = new $translationServices[$this->choice('Which automatic translation provider should be used?',
                                                                     array_keys($translationServices), 0)]();
        if (!in_array($this->manager->getDefaultLocale(), $translationService->getAvailableLocales())) {
            $this->warn('Provider '.class_basename($translationService).' doesn\'t support locale "'.$this->manager->getDefaultLocale().'"');

            return;
        }

        $translationLocale = $this->ask('In which language do you want to translate?', 'en');
        if (!in_array($translationLocale, $translationService->getAvailableLocales())) {
            $this->warn('Provider '.class_basename($translationService).' doesn\'t support locale "'.$translationLocale.'"');

            return;
        }

        $translationValues = $this->manager->getUntranslatedValues(
            $this->manager->getDefaultLocale(),
            $translationLocale
        );
        if (!$this->confirm('This translation costs $'.$translationService->calculateCosts($translationValues).' Do you want to continue?')) {
            return;
        }

        $this->info('Translating values...');
        $progress = $this->output->createProgressBar(count($translationValues));

        $translatedLocaleValues = [];
        foreach ($translationValues as $name => $localeString) {
            $translatedLocaleValues[$translationLocale][$name] = $translationService->translate(
                $this->manager->getDefaultLocale(),
                $translationLocale,
                $localeString
            );
            $progress->advance();
        }
        $progress->finish();
        echo "\r\n";

        $this->info('Write values');
        $this->manager->write($translatedLocaleValues);
    }
}
