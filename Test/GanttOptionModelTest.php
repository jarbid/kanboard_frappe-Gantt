<?php

namespace Kanboard\Plugin\FrappeGantt\Test;

// PHPUnit loads each test file before Kanboard's bootstrap registers the
// plugin autoloader, so the shared case has to be required explicitly.
require_once __DIR__.'/PluginTestCase.php';

use Kanboard\Model\ProjectModel;
use Kanboard\Plugin\FrappeGantt\Model\GanttOptionModel;

class GanttOptionModelTest extends PluginTestCase
{
    public function testDefaultsCoverEveryDefinedOption()
    {
        $model = new GanttOptionModel($this->container);
        $defaults = $model->getDefaults();

        foreach (GanttOptionModel::getDefinition() as $name => $definition) {
            $this->assertArrayHasKey($name, $defaults);
            $this->assertSame($definition['default'], $defaults[$name]);
        }
    }

    public function testEncodeAndDecodeRoundTrip()
    {
        $model = new GanttOptionModel($this->container);

        $stored = $model->encodeForStorage(array(
            'bar_height' => '44',
            'column_width' => '60',
            'lines' => 'vertical',
            'view_mode' => 'Week',
            'weekend_days' => array('5', '6'),
            'today_button' => 1,
            'holidays' => "2026-01-01: New Year\n",
        ));

        // Everything is persisted as a string, keyed with the plugin prefix.
        $this->assertSame('44', $stored[GanttOptionModel::PREFIX.'bar_height']);
        $this->assertSame('5,6', $stored[GanttOptionModel::PREFIX.'weekend_days']);
        $this->assertSame('1', $stored[GanttOptionModel::PREFIX.'today_button']);

        // An option absent from the input becomes its "off"/default value
        // rather than disappearing, so unchecking a box really unchecks it.
        $this->assertSame('0', $stored[GanttOptionModel::PREFIX.'show_subtasks']);

        $this->container['configModel']->save($stored);
        $values = $model->getGlobalValues();

        $this->assertSame(44, $values['bar_height']);
        $this->assertSame(60, $values['column_width']);
        $this->assertSame('vertical', $values['lines']);
        $this->assertSame('Week', $values['view_mode']);
        $this->assertSame(array('5', '6'), $values['weekend_days']);
        $this->assertTrue($values['today_button']);
        $this->assertFalse($values['show_subtasks']);
    }

    public function testInvalidEnumFallsBackToTheDefault()
    {
        $model = new GanttOptionModel($this->container);

        $stored = $model->encodeForStorage(array('lines' => 'diagonal'));
        $this->assertSame('both', $stored[GanttOptionModel::PREFIX.'lines']);
    }

    public function testSetOptionsRejectUnknownChoices()
    {
        $model = new GanttOptionModel($this->container);

        $stored = $model->encodeForStorage(array(
            'weekend_days' => array('6', '9', 'nope'),
        ));

        $this->assertSame('6', $stored[GanttOptionModel::PREFIX.'weekend_days']);
    }

    /**
     * An empty stored value means "never configured" for most options, but is
     * a genuine setting for a few. Getting this wrong makes saved settings
     * silently blank out the defaults.
     */
    public function testEmptyValuesOnlyOverrideWhereEmptyIsMeaningful()
    {
        $model = new GanttOptionModel($this->container);

        $this->container['configModel']->save(array(
            GanttOptionModel::PREFIX.'scroll_to' => '',
            GanttOptionModel::PREFIX.'date_format' => '',
            GanttOptionModel::PREFIX.'snap_at' => '',
            GanttOptionModel::PREFIX.'holidays' => '',
            GanttOptionModel::PREFIX.'column_width' => '',
        ));

        $values = $model->getGlobalValues();

        // Meaningless when empty: the default has to survive.
        $this->assertSame('today', $values['scroll_to']);
        $this->assertSame('YYYY-MM-DD', $values['date_format']);

        // Meaningful when empty.
        $this->assertSame('', $values['snap_at']);
        $this->assertSame('', $values['holidays']);
        $this->assertNull($values['column_width']);
    }

    public function testProjectOverridesOnlyApplyWhenEnabled()
    {
        $model = new GanttOptionModel($this->container);
        $projectModel = new ProjectModel($this->container);

        $project_id = $projectModel->create(array('name' => 'Override test'));

        $this->container['configModel']->save(array(GanttOptionModel::PREFIX.'bar_height' => '20'));
        $this->container['projectMetadataModel']->save($project_id, array(
            GanttOptionModel::PREFIX.'bar_height' => '80',
        ));

        // Without the override flag the project follows the global settings.
        $this->assertSame(20, $model->getValues($project_id)['bar_height']);

        $this->container['projectMetadataModel']->save($project_id, array(
            GanttOptionModel::OVERRIDE_KEY => '1',
        ));

        $this->assertTrue($model->isProjectOverriding($project_id));
        $this->assertSame(80, $model->getValues($project_id)['bar_height']);

        // The global settings are untouched by the project override.
        $this->assertSame(20, $model->getGlobalValues()['bar_height']);
    }

    public function testLibraryOptionsOnlyContainLibraryOptions()
    {
        $model = new GanttOptionModel($this->container);
        $options = $model->buildLibraryOptions($model->getDefaults(), true);

        foreach (GanttOptionModel::getDefinition() as $name => $definition) {
            if ($definition['scope'] === 'library') {
                $this->assertArrayHasKey($name, $options, $name.' should be sent to the library');
            } else {
                $this->assertArrayNotHasKey($name, $options, $name.' is a plugin option');
            }
        }

        $this->assertArrayHasKey('language', $options);
    }

    public function testAnEmptySnapAtBecomesNullForTheLibrary()
    {
        $model = new GanttOptionModel($this->container);
        $values = $model->getDefaults();
        $values['snap_at'] = '';

        $this->assertNull($model->buildLibraryOptions($values, true)['snap_at']);
    }

    public function testReadOnlyUsersAlwaysGetAReadOnlyChart()
    {
        $model = new GanttOptionModel($this->container);
        $values = $model->getDefaults();
        $values['readonly'] = false;

        $this->assertTrue($model->buildLibraryOptions($values, false)['readonly']);
        $this->assertFalse($model->buildLibraryOptions($values, true)['readonly']);
    }

    public function testNumericContainerHeightIsSentAsANumber()
    {
        $model = new GanttOptionModel($this->container);

        $values = $model->getDefaults();
        $this->assertSame('auto', $model->buildLibraryOptions($values, true)['container_height']);

        $values['container_height'] = '600';
        $this->assertSame(600, $model->buildLibraryOptions($values, true)['container_height']);
    }

    public function testParseDateListAcceptsLabelsAndSkipsGarbage()
    {
        $model = new GanttOptionModel($this->container);

        $entries = $model->parseDateList(
            "2026-01-01: New Year\n".
            "  2026-12-25:Christmas  \n".
            "\n".
            "not a date\n".
            "2026-07-04"
        );

        $this->assertCount(3, $entries);
        $this->assertSame(array('date' => '2026-01-01', 'label' => 'New Year'), $entries[0]);
        $this->assertSame(array('date' => '2026-12-25', 'label' => 'Christmas'), $entries[1]);
        $this->assertSame(array('date' => '2026-07-04', 'label' => ''), $entries[2]);
    }

    public function testBridgeOptionsExposeTheCallbackInputs()
    {
        $model = new GanttOptionModel($this->container);

        $values = $model->getDefaults();
        $values['holidays'] = "2026-01-01: New Year";
        $values['ignore_dates'] = "2026-02-02";

        $bridge = $model->buildBridgeOptions($values);

        $this->assertSame(array(0, 6), $bridge['weekend_days']);
        $this->assertSame(array(array('date' => '2026-01-01', 'label' => 'New Year')), $bridge['holidays']);
        $this->assertSame(array('2026-02-02'), $bridge['ignore_dates']);
        $this->assertNotEmpty($bridge['holiday_color']);
    }
}
