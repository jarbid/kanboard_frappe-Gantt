<?php

namespace Kanboard\Plugin\FrappeGantt\Test;

// PHPUnit loads each test file before Kanboard's bootstrap registers the
// plugin autoloader, so the shared case has to be required explicitly.
require_once __DIR__.'/PluginTestCase.php';

class TranslationTest extends PluginTestCase
{
    const PLUGIN_DIR = __DIR__.'/..';

    /**
     * Every string the plugin passes through t() must be translatable: either
     * Kanboard already ships it, or the plugin's own locale files carry it.
     * Otherwise a translated install silently falls back to English.
     */
    public function testEveryLocaleCoversThePluginStrings()
    {
        $strings = $this->extractTranslatableStrings();
        $this->assertNotEmpty($strings, 'no translatable strings were found');

        foreach ($this->getPluginLocales() as $locale => $translations) {
            $core = $this->getCoreTranslations($locale);
            $missing = array();

            foreach ($strings as $string) {
                if (! isset($translations[$string]) && ! isset($core[$string])) {
                    $missing[] = $string;
                }
            }

            $this->assertSame(array(), $missing, $locale.' is missing translations');
        }
    }

    /**
     * A locale file that translates strings the plugin never uses is dead
     * weight, and usually a sign of a string that was reworded in the code.
     */
    public function testLocalesDoNotCarryUnusedStrings()
    {
        $strings = $this->extractTranslatableStrings();

        foreach ($this->getPluginLocales() as $locale => $translations) {
            $unused = array_diff(array_keys($translations), $strings);

            $this->assertSame(array(), array_values($unused), $locale.' translates unused strings');
        }
    }

    public function testLocaleFilesReturnNonEmptyStringPairs()
    {
        foreach ($this->getPluginLocales() as $locale => $translations) {
            $this->assertNotEmpty($translations, $locale.' is empty');

            foreach ($translations as $source => $target) {
                $this->assertIsString($source, $locale);
                $this->assertIsString($target, $locale);
                $this->assertNotSame('', trim($target), $locale.' has an empty translation for "'.$source.'"');
            }
        }
    }

    /**
     * @return array locale name => translations
     */
    private function getPluginLocales()
    {
        $locales = array();

        foreach ((array) glob(self::PLUGIN_DIR.'/Locale/*/translations.php') as $file) {
            $locales[basename(dirname($file))] = require $file;
        }

        return $locales;
    }

    /**
     * @param  string $locale
     * @return array
     */
    private function getCoreTranslations($locale)
    {
        $file = __DIR__.'/../../../app/Locale/'.$locale.'/translations.php';

        return file_exists($file) ? require $file : array();
    }

    /**
     * Collect the literal arguments of every t('…') call in the PHP sources.
     *
     * @return string[]
     */
    private function extractTranslatableStrings()
    {
        $strings = array();

        $files = array_merge(
            (array) glob(self::PLUGIN_DIR.'/*.php'),
            (array) glob(self::PLUGIN_DIR.'/Controller/*.php'),
            (array) glob(self::PLUGIN_DIR.'/Template/*/*.php')
        );

        foreach ($files as $file) {
            // The negative lookbehind keeps method calls such as
            // ->project('Plugin:template') out of the results.
            if (preg_match_all("/(?<![>\\w])t\\('((?:[^'\\\\]|\\\\.)*)'/", file_get_contents($file), $matches)) {
                foreach ($matches[1] as $match) {
                    $strings[] = stripslashes($match);
                }
            }
        }

        return array_values(array_unique($strings));
    }
}
