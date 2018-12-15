<?php

namespace NodusFramework\TranslationManager;

use NodusFramework\TranslationManager\Console\TranslationCommand;

/**
 * Translation Manager service provider.
 *
 * @author    Bastian Schur <b.schur@nodus-framework.de>
 *
 * @link      http://www.nodus-framework.de
 */
class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * Command registration.
     */
    public function register()
    {
        $this->commands(TranslationCommand::class);

        $this->mergeConfigFrom(__DIR__.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'nodus_translation_manager.php',
            'nodus_translation_manager');
    }
}
