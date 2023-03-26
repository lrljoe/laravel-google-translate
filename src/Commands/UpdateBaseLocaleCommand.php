<?php

namespace Tanmuhittin\LaravelGoogleTranslate\Commands;

use Illuminate\Console\Command;
use Tanmuhittin\LaravelGoogleTranslate\Contracts\ApiTranslatorContract;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class UpdateBaseLocaleCommand extends Command
{
    public $base_locale;
    public $base_locale_file;
    public $locales;
    public $excluded_files;
    public $target_files;
    public $json = false;
    public $force = false;
    public $verbose = false;
    public $views_path = '';

    protected $translator;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translate:updatebase';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Translate Translation files. translate:updatebase';

    /**
     * TranslateFilesCommand constructor.
     * @param ApiTranslatorContract $translator
     * @param string $base_locale
     * @param string $locales
     * @param string $target_files
     * @param bool $force
     * @param bool $json
     * @param bool $verbose
     * @param string $excluded_files
     */
    public function __construct($base_locale = 'en', $locales = 'tr,it', $target_files = '', $force = false, $json = false, $verbose = true, $excluded_files = 'auth,pagination,validation,passwords')
    {
        parent::__construct();
        $this->base_locale = $base_locale;
        $this->locales = array_filter(explode(",", $locales));
        $this->target_files = array_filter(explode(",", $target_files));
        $this->force = $force;
        $this->json = $json;
        $this->verbose = $verbose;
        $this->excluded_files = array_filter(explode(",", $excluded_files));
    }

    /**
     * @throws \Exception
     */
    public function handle()
    {
        //Collect input
        $this->base_locale = $this->ask('What is base locale?', config('app.locale', 'en'));
        $this->base_locale_file = $this->ask('Relative to base locale file?', 'resources/lang/en.json');
        $this->views_path = $this->ask('What is views search path?', 'en.json');

        $currentBaseLocaleContents = file_get_contents(base_path()."/".$this->base_locale_file);
        $originalBaseLocaleArray = $currentBaseLocaleArray = json_decode($currentBaseLocaleContents,true);
        //'/home/joe/awscandc/tables/switchabledemo/vendor/rappasoft/laravel-livewire-tables/resources/lang/en.json'

        $groupKeys = [];
        $stringKeys = [];
        $functions = config('laravel_google_translate.trans_functions', [
            'trans',
            'trans_choice',
            'Lang::get',
            'Lang::choice',
            'Lang::trans',
            'Lang::transChoice',
            '@lang',
            '@choice',
            '__',
            '\$trans.get',
            '\$t'
        ]);
        $groupPattern =                          // See https://regex101.com/r/WEJqdL/6
            "[^\w|>]" .                          // Must not have an alphanum or _ or > before real method
            '(' . implode('|', $functions) . ')' .  // Must start with one of the functions
            "\(" .                               // Match opening parenthesis
            "[\'\"]" .                           // Match " or '
            '(' .                                // Start a new group to match:
            '[a-zA-Z0-9_-]+' .               // Must start with group
            "([.](?! )[^\1)]+)+" .             // Be followed by one or more items/keys
            ')' .                                // Close group
            "[\'\"]" .                           // Closing quote
            "[\),]";                            // Close parentheses or new parameter
        $stringPattern =
            "[^\w]" .                                     // Must not have an alphanum before real method
            '(' . implode('|', $functions) . ')' .             // Must start with one of the functions
            "\(" .                                          // Match opening parenthesis
            "(?P<quote>['\"])" .                            // Match " or ' and store in {quote}
            "(?P<string>(?:\\\k{quote}|(?!\k{quote}).)*)" . // Match any string that can be {quote} escaped
            "\k{quote}" .                                   // Match " or ' previously matched
            "[\),]";                                       // Close parentheses or new parameter
        $finder = new Finder();
        //$finder->in(base_path())->exclude('storage')->exclude('vendor')->name('*.php')->name('*.twig')->name('*.vue')->files();
        $finder->in(base_path().'/vendor/rappasoft/laravel-livewire-tables/resources/views')->files();
        /** @var \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($finder as $file) {
            // Search the current file for the pattern
            if (preg_match_all("/$groupPattern/siU", $file->getContents(), $matches)) {
                // Get all matches
                foreach ($matches[2] as $key) {
                    $groupKeys[] = $key;
                }
            }
            if (preg_match_all("/$stringPattern/siU", $file->getContents(), $matches)) {
                foreach ($matches['string'] as $key) {
                    if (preg_match("/(^[a-zA-Z0-9_-]+([.][^\1)\ ]+)+$)/siU", $key, $groupMatches)) {
                        // group{.group}.key format, already in $groupKeys but also matched here
                        // do nothing, it has to be treated as a group
                        continue;
                    }
                    //TODO: This can probably be done in the regex, but I couldn't do it.
                    //skip keys which contain namespacing characters, unless they also contain a
                    //space, which makes it JSON.
                    if (!(mb_strpos($key, '::') !== FALSE && mb_strpos($key, '.') !== FALSE)
                        || mb_strpos($key, ' ') !== FALSE) {
                        $stringKeys[] = $key;
                        if (!isset($currentBaseLocaleArray[$key]))
                        {
                            $this->line('Found New: ' . $key);
                            $currentBaseLocaleArray[$key] = $key;
                        }
                        else
                        {
                            $this->line('Found Existing: ' . $key);
                        }
                        
                    }
                }
            }
        }
        // Remove duplicates
        $groupKeys = array_unique($groupKeys); // todo: not supporting group keys for now add this feature!
        $stringKeys = array_unique($stringKeys);

        if ($originalBaseLocaleArray != $currentBaseLocaleArray)
        {
            $encoded = json_encode($currentBaseLocaleArray);
            file_put_contents(base_path()."/".$this->base_locale_file, $encoded);
        }
        
        
    }
}
