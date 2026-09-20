<?php

namespace Kanboard\Plugin\FrappeGantt;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Security\Role;
use Kanboard\Core\Translator;
use Kanboard\Plugin\FrappeGantt\Formatter\ProjectGanttFormatter;
use Kanboard\Plugin\FrappeGantt\Formatter\TaskGanttFormatter;
use Kanboard\Plugin\FrappeGantt\Model\GanttOptionModel;
use Kanboard\Plugin\FrappeGantt\Model\GanttProgressModel;

/**
 * Frappe Gantt for Kanboard
 *
 * Renders Kanboard projects, tasks and subtasks with the Frappe Gantt
 * library, exposing the full upstream feature set.
 */
class Plugin extends Base
{
    const VENDOR_VERSION_FILE = __DIR__.'/Assets/vendor/VERSION';

    public function initialize()
    {
        $this->registerRoutes();
        $this->registerAccessMap();
        $this->registerTemplateHooks();
        $this->registerAssets();
    }

    public function getClasses()
    {
        return array(
            'Plugin\FrappeGantt\Model' => array(
                'GanttProgressModel',
                'GanttOptionModel',
            ),
        );
    }

    public function onStartup()
    {
        Translator::load($this->languageModel->getCurrentLanguage(), __DIR__.'/Locale');
    }

    /**
     * Routes are registered with the "plugin" flag so that Kanboard builds
     * plugin-aware URLs for them.
     */
    private function registerRoutes()
    {
        $this->route->addRoute('gantt/frappe/:project_id', 'TaskGanttController', 'show', 'FrappeGantt');
        $this->route->addRoute('gantt/frappe/:project_id/:sorting', 'TaskGanttController', 'show', 'FrappeGantt');
        $this->route->addRoute('gantt/frappe-projects', 'ProjectGanttController', 'show', 'FrappeGantt');
        $this->route->addRoute('gantt/frappe-mine', 'UserGanttController', 'show', 'FrappeGantt');
    }

    private function registerAccessMap()
    {
        $this->projectAccessMap->add('TaskGanttController', array('save', 'saveProgress'), Role::PROJECT_MEMBER);
        $this->projectAccessMap->add('ProjectSettingsController', array('show', 'save'), Role::PROJECT_MANAGER);
        $this->applicationAccessMap->add('ProjectGanttController', 'save', Role::APP_MANAGER);
    }

    private function registerTemplateHooks()
    {
        $this->template->hook->attach('template:project-header:view-switcher', 'FrappeGantt:project_header/views');
        $this->template->hook->attach('template:project:dropdown', 'FrappeGantt:project/dropdown');
        $this->template->hook->attach('template:project:sidebar', 'FrappeGantt:project/sidebar');
        $this->template->hook->attach('template:project-list:menu:after', 'FrappeGantt:project_list/menu');
        $this->template->hook->attach('template:dashboard:sidebar', 'FrappeGantt:project_list/dashboard');
        $this->template->hook->attach('template:config:sidebar', 'FrappeGantt:config/sidebar');
    }

    /**
     * The Frappe Gantt stylesheet is tiny and the Kanboard theme bridge has to
     * be present wherever the chart is embedded, so both are loaded globally.
     * The library itself is only pulled in by the chart templates.
     */
    private function registerAssets()
    {
        $this->hook->on('template:layout:css', array('template' => 'plugins/FrappeGantt/Assets/vendor/frappe-gantt.css'));
        $this->hook->on('template:layout:css', array('template' => 'plugins/FrappeGantt/Assets/kanboard-gantt.css'));
    }

    public function getPluginName()
    {
        return 'FrappeGantt';
    }

    public function getPluginDescription()
    {
        return t('Gantt charts for Kanboard powered by the Frappe Gantt library');
    }

    public function getPluginAuthor()
    {
        return 'jarbid';
    }

    public function getPluginVersion()
    {
        return '1.0.1';
    }

    public function getPluginHomepage()
    {
        return 'https://github.com/jarbid/kanboard_frappe-Gantt';
    }

    public function getCompatibleVersion()
    {
        return '>=1.2.20';
    }

    /**
     * Version of the bundled upstream library, shown on the settings page.
     *
     * @return string
     */
    public static function getVendorVersion()
    {
        if (! file_exists(self::VENDOR_VERSION_FILE)) {
            return 'unknown';
        }

        $values = parse_ini_file(self::VENDOR_VERSION_FILE);

        return isset($values['FRAPPE_GANTT_VERSION']) ? $values['FRAPPE_GANTT_VERSION'] : 'unknown';
    }
}
