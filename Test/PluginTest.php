<?php

namespace Kanboard\Plugin\FrappeGantt\Test;

// PHPUnit loads each test file before Kanboard's bootstrap registers the
// plugin autoloader, so the shared case has to be required explicitly.
require_once __DIR__.'/PluginTestCase.php';

use Kanboard\Plugin\FrappeGantt\Plugin;

class PluginTest extends PluginTestCase
{
    public function testPluginMetadata()
    {
        $plugin = new Plugin($this->container);

        $this->assertSame(null, $plugin->initialize());
        $this->assertSame(null, $plugin->onStartup());
        $this->assertSame('FrappeGantt', $plugin->getPluginName());
        $this->assertNotEmpty($plugin->getPluginDescription());
        $this->assertNotEmpty($plugin->getPluginAuthor());
        $this->assertNotEmpty($plugin->getPluginVersion());
        $this->assertNotEmpty($plugin->getPluginHomepage());
        $this->assertNotEmpty($plugin->getCompatibleVersion());
    }

    public function testRegistersItsModels()
    {
        $plugin = new Plugin($this->container);
        $classes = $plugin->getClasses();

        $this->assertArrayHasKey('Plugin\FrappeGantt\Model', $classes);
        $this->assertContains('GanttProgressModel', $classes['Plugin\FrappeGantt\Model']);
        $this->assertContains('GanttOptionModel', $classes['Plugin\FrappeGantt\Model']);
    }

    public function testReportsTheBundledLibraryVersion()
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+/', Plugin::getVendorVersion());
    }

    public function testShipsASchemaForEverySupportedDriver()
    {
        // Kanboard resolves the plugin schema file from ucfirst(DB_DRIVER),
        // so every driver name it accepts needs a matching file.
        foreach (array('Sqlite', 'Mysql', 'Postgres', 'Mssql', 'Dblib', 'Odbc') as $driver) {
            $this->assertFileExists(__DIR__.'/../Schema/'.$driver.'.php', $driver.' schema is missing');
        }
    }
}
