[![License](https://poser.pugx.org/nodus-framework/translation-manager/license)](https://packagist.org/packages/nodus-framework/translation-manager)
[![Latest Unstable Version](https://poser.pugx.org/nodus-framework/translation-manager/v/unstable)](https://packagist.org/packages/nodus-framework/translation-manager)
[![Latest Stable Version](https://poser.pugx.org/nodus-framework/translation-manager/v/stable)](https://packagist.org/packages/nodus-framework/translation-manager)

## About
TranslationManager is a package for easy management translation strings in laravel. It has the ability to perform the translation on the console or to create an export with all the strings that can later be imported again.

## Requirements
* Laravel 5.5+

## Installation

Require this package with composer. It is recommended to only require the package for development.

```shell
composer require nodus-framework/translation-manager --dev
```

Laravel 5.5 uses Package Auto-Discovery, so doesn't require you to manually add the ServiceProvider.

## Usage

The default language is automatically adopted by laravel. If you want to change the language, you can use the "--default-locale" option. The default language is always considered complete for the translation and is used as the basis for the translation into other languages

### Show translation status

Shows all languages with count of files and values
```shell
php artisan nodus:translate

de: Found 65 files with 1880 values *primary locale
en: Found 1 files with 6 values
```

### Export 

The export generates a CSV file with the translation key, the translation in the standard language and an empty column for the desired language. The file is stored in the main directory. Currently only the missing values are exported

```shell
php artisan nodus:translate export

In which language do you want to translate? [en]:
 > 
```

### Import

The import processes a CSV file according to the structure of the export. Only one language is imported per file. If the file can be read in, all files and folders for the new language are automatically created. If a file already exists, it will be merged. With duplicate values, the value is taken from the import.
```shell
php artisan nodus:translate import --file=translation_de-en.csv
```

### Automatic translation
This option allows you to automate your translations. An implemented provider is currently available: Amazon AWS Translate. After entering the command below, the provider and target language are queried. Subsequently, the costs for this translation are displayed and must be explicitly confirmed. Special features and requirements for the providers are listed below.

```shell
php artisan nodus:translate auto-translate
```

#### [AWS](https://aws.amazon.com/de/translate/)
**Requirements:**
* AWS-Account
* Package aws/aws-sdk-php
* ENV-Variables: NODUS_TRANSLATION_MANAGER_AWS_KEY, NODUS_TRANSLATION_MANAGER_AWS_SECRET

**Costs**
* 15$ per 1 Million Characters
* Free Tier for 2 Millions Characters per month for 12 month after start using this service

## ToDo-List
* Inline translation service
* More export/import formats