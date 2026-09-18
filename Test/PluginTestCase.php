<?php

namespace Kanboard\Plugin\FrappeGantt\Test;

use Kanboard\Core\Plugin\SchemaHandler;
use Kanboard\Core\Tool;
use Kanboard\Plugin\FrappeGantt\Plugin;
use KanboardTests\units\Base;

/**
 * Base class for the plugin's tests.
 *
 * Kanboard's test bootstrap builds the core container and the core schema
 * only. Anything a plugin adds is normally wired up by the plugin loader
 * during a real request, so it has to be set up here instead: the plugin's
 * migrations, and its entries in the dependency injection container.
 */
abstract class PluginTestCase extends Base
{
    public function setUp(): void
    {
        parent::setUp();

        $handler = new SchemaHandler($this->container);
        $handler->loadSchema('FrappeGantt');

        $plugin = new Plugin($this->container);
        Tool::buildDIC($this->container, $plugin->getClasses());
        Tool::buildDICHelpers($this->container, $plugin->getHelpers());
    }
}
