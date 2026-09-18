<?php

namespace Kanboard\Plugin\FrappeGantt\Test;

// PHPUnit loads each test file before Kanboard's bootstrap registers the
// plugin autoloader, so the shared case has to be required explicitly.
require_once __DIR__.'/PluginTestCase.php';

use Kanboard\Filter\TaskProjectFilter;
use Kanboard\Model\ProjectModel;
use Kanboard\Model\SubtaskModel;
use Kanboard\Model\TaskCreationModel;
use Kanboard\Model\TaskLinkModel;
use Kanboard\Plugin\FrappeGantt\Formatter\TaskGanttFormatter;
use Kanboard\Plugin\FrappeGantt\Model\GanttOptionModel;

class TaskGanttFormatterTest extends PluginTestCase
{
    private function build($project_id, array $options = array())
    {
        $defaults = (new GanttOptionModel($this->container))->getDefaults();
        $formatter = new TaskGanttFormatter($this->container);

        return $this->container['taskLexer']
            ->build('')
            ->withFilter(new TaskProjectFilter($project_id))
            ->format($formatter->withOptions(array_merge($defaults, $options)));
    }

    private function index(array $bars)
    {
        $indexed = array();

        foreach ($bars as $bar) {
            $indexed[$bar['id']] = $bar;
        }

        return $indexed;
    }

    public function testFormatsDatesAndFlagsMissingOnes()
    {
        $projectModel = new ProjectModel($this->container);
        $taskCreationModel = new TaskCreationModel($this->container);

        $project_id = $projectModel->create(array('name' => 'Dates'));

        $both = $taskCreationModel->create(array(
            'project_id' => $project_id, 'title' => 'Both dates',
            'date_started' => strtotime('2026-03-02'), 'date_due' => strtotime('2026-03-06'),
        ));
        $none = $taskCreationModel->create(array('project_id' => $project_id, 'title' => 'No dates'));
        $startOnly = $taskCreationModel->create(array(
            'project_id' => $project_id, 'title' => 'Start only',
            'date_started' => strtotime('2026-03-10'),
        ));

        $bars = $this->index($this->build($project_id));

        $this->assertSame('2026-03-02', $bars['task-'.$both]['start']);
        $this->assertSame('2026-03-06', $bars['task-'.$both]['end']);
        $this->assertTrue($bars['task-'.$both]['kb']['has_start']);
        $this->assertTrue($bars['task-'.$both]['kb']['has_due']);
        $this->assertNotContains('kb-gantt-undated', $bars['task-'.$both]['kb']['classes']);

        // A task without dates still needs a range the library can draw.
        $this->assertNotEmpty($bars['task-'.$none]['start']);
        $this->assertSame($bars['task-'.$none]['start'], $bars['task-'.$none]['end']);
        $this->assertFalse($bars['task-'.$none]['kb']['has_start']);
        $this->assertFalse($bars['task-'.$none]['kb']['has_due']);
        $this->assertContains('kb-gantt-undated', $bars['task-'.$none]['kb']['classes']);

        $this->assertSame('2026-03-10', $bars['task-'.$startOnly]['start']);
        $this->assertSame('2026-03-10', $bars['task-'.$startOnly]['end']);
        $this->assertContains('kb-gantt-undated', $bars['task-'.$startOnly]['kb']['classes']);
    }

    public function testEndDateIsNeverBeforeTheStartDate()
    {
        $projectModel = new ProjectModel($this->container);
        $taskCreationModel = new TaskCreationModel($this->container);

        $project_id = $projectModel->create(array('name' => 'Inverted'));
        $task_id = $taskCreationModel->create(array(
            'project_id' => $project_id, 'title' => 'Inverted dates',
            'date_started' => strtotime('2026-04-10'), 'date_due' => strtotime('2026-04-01'),
        ));

        $bars = $this->index($this->build($project_id));

        $this->assertSame('2026-04-10', $bars['task-'.$task_id]['start']);
        $this->assertSame('2026-04-10', $bars['task-'.$task_id]['end']);
    }

    /**
     * custom_class is handed to classList.add() by the library, which rejects
     * any value containing a space.
     */
    public function testCustomClassIsAlwaysASingleToken()
    {
        $projectModel = new ProjectModel($this->container);
        $taskCreationModel = new TaskCreationModel($this->container);

        $project_id = $projectModel->create(array('name' => 'Classes'));
        $task_id = $taskCreationModel->create(array('project_id' => $project_id, 'title' => 'No dates'));
        $this->container['subtaskModel']->create(array('task_id' => $task_id, 'title' => 'Sub'));

        foreach ($this->build($project_id, array('show_subtasks' => true)) as $bar) {
            $this->assertStringNotContainsString(' ', $bar['custom_class'], $bar['id']);
            $this->assertNotEmpty($bar['custom_class']);
        }
    }

    public function testDependenciesFollowTheConfiguredLinkTypes()
    {
        $projectModel = new ProjectModel($this->container);
        $taskCreationModel = new TaskCreationModel($this->container);
        $taskLinkModel = new TaskLinkModel($this->container);

        $project_id = $projectModel->create(array('name' => 'Links'));
        $first = $taskCreationModel->create(array('project_id' => $project_id, 'title' => 'First'));
        $second = $taskCreationModel->create(array('project_id' => $project_id, 'title' => 'Second'));

        $blocks_id = $this->container['db']->table('links')->eq('label', 'blocks')->findOneColumn('id');
        $taskLinkModel->create($first, $second, $blocks_id);

        // "First blocks Second", so Second depends on First.
        $bars = $this->index($this->build($project_id));
        $this->assertSame(array('task-'.$first), $bars['task-'.$second]['dependencies']);
        $this->assertSame(array(), $bars['task-'.$first]['dependencies']);

        // Kanboard stores links in both directions; reading only the opposite
        // label must produce exactly the same arrow, not a reversed one.
        $bars = $this->index($this->build($project_id, array('dependency_links' => array('is blocked by'))));
        $this->assertSame(array('task-'.$first), $bars['task-'.$second]['dependencies']);

        // And enabling both must not duplicate it.
        $bars = $this->index($this->build($project_id, array('dependency_links' => array('blocks', 'is blocked by'))));
        $this->assertSame(array('task-'.$first), $bars['task-'.$second]['dependencies']);

        // Turning the link type off removes the arrow.
        $bars = $this->index($this->build($project_id, array('dependency_links' => array('relates to'))));
        $this->assertSame(array(), $bars['task-'.$second]['dependencies']);
    }

    public function testDependenciesOnTasksOutsideTheChartAreDropped()
    {
        $projectModel = new ProjectModel($this->container);
        $taskCreationModel = new TaskCreationModel($this->container);
        $taskLinkModel = new TaskLinkModel($this->container);

        $project_a = $projectModel->create(array('name' => 'Project A'));
        $project_b = $projectModel->create(array('name' => 'Project B'));

        $here = $taskCreationModel->create(array('project_id' => $project_a, 'title' => 'Here'));
        $elsewhere = $taskCreationModel->create(array('project_id' => $project_b, 'title' => 'Elsewhere'));

        $blocks_id = $this->container['db']->table('links')->eq('label', 'blocks')->findOneColumn('id');
        $taskLinkModel->create($elsewhere, $here, $blocks_id);

        // The chart only shows project A, so the arrow has nothing to point at.
        $bars = $this->index($this->build($project_a));
        $this->assertSame(array(), $bars['task-'.$here]['dependencies']);
    }

    public function testSubtasksAreOnlyIncludedWhenEnabled()
    {
        $projectModel = new ProjectModel($this->container);
        $taskCreationModel = new TaskCreationModel($this->container);
        $subtaskModel = new SubtaskModel($this->container);

        $project_id = $projectModel->create(array('name' => 'Subtasks'));
        $task_id = $taskCreationModel->create(array(
            'project_id' => $project_id, 'title' => 'Parent',
            'date_started' => strtotime('2026-05-04'), 'date_due' => strtotime('2026-05-08'),
        ));

        $subtaskModel->create(array('task_id' => $task_id, 'title' => 'Done', 'status' => SubtaskModel::STATUS_DONE));
        $subtaskModel->create(array('task_id' => $task_id, 'title' => 'Doing', 'status' => SubtaskModel::STATUS_INPROGRESS));
        $subtaskModel->create(array('task_id' => $task_id, 'title' => 'Todo', 'status' => SubtaskModel::STATUS_TODO));

        $this->assertCount(1, $this->build($project_id));

        $bars = $this->build($project_id, array('show_subtasks' => true));
        $this->assertCount(4, $bars);

        $progress = array();

        foreach ($bars as $bar) {
            if ($bar['kb']['type'] !== 'subtask') {
                continue;
            }

            // Subtasks have no dates, so they span their parent's range and
            // must not be editable.
            $this->assertSame('2026-05-04', $bar['start']);
            $this->assertSame('2026-05-08', $bar['end']);
            $this->assertFalse($bar['kb']['editable']);
            $progress[$bar['kb']['title']] = $bar['progress'];
        }

        $this->assertSame(array('Done' => 100, 'Doing' => 50, 'Todo' => 0), $progress);
    }

    public function testStoredProgressOverridesTheColumnPosition()
    {
        $projectModel = new ProjectModel($this->container);
        $taskCreationModel = new TaskCreationModel($this->container);

        $project_id = $projectModel->create(array('name' => 'Progress'));
        $task_id = $taskCreationModel->create(array('project_id' => $project_id, 'title' => 'Task'));

        $bars = $this->index($this->build($project_id));
        $this->assertSame(0.0, $bars['task-'.$task_id]['progress']);

        $this->container['ganttProgressModel']->save($task_id, 65);

        $bars = $this->index($this->build($project_id));
        $this->assertSame(65.0, $bars['task-'.$task_id]['progress']);
    }

    public function testColorSourceSelectsTheRightPalette()
    {
        $projectModel = new ProjectModel($this->container);
        $taskCreationModel = new TaskCreationModel($this->container);

        $project_id = $projectModel->create(array('name' => 'Colors'));
        $category_id = $this->container['categoryModel']->create(array(
            'name' => 'Cat', 'project_id' => $project_id, 'color_id' => 'green',
        ));
        $task_id = $taskCreationModel->create(array(
            'project_id' => $project_id, 'title' => 'Task',
            'color_id' => 'red', 'category_id' => $category_id,
        ));

        $byTask = $this->index($this->build($project_id, array('color_source' => 'task')));
        $byCategory = $this->index($this->build($project_id, array('color_source' => 'category')));
        $none = $this->index($this->build($project_id, array('color_source' => 'none')));

        $this->assertNotEmpty($byTask['task-'.$task_id]['color']);
        $this->assertNotEmpty($byCategory['task-'.$task_id]['color']);
        $this->assertNotSame($byTask['task-'.$task_id]['color'], $byCategory['task-'.$task_id]['color']);
        $this->assertSame('', $none['task-'.$task_id]['color']);
    }

    /**
     * The library writes task.name into the label element with innerHTML, so
     * a title containing markup would otherwise be injected into the chart.
     */
    public function testBarLabelsAreEscaped()
    {
        $projectModel = new ProjectModel($this->container);
        $taskCreationModel = new TaskCreationModel($this->container);

        $project_id = $projectModel->create(array('name' => 'Escaping'));
        $task_id = $taskCreationModel->create(array(
            'project_id' => $project_id,
            'title' => '<img src=x onerror="alert(1)"> & done',
        ));
        $this->container['subtaskModel']->create(array(
            'task_id' => $task_id,
            'title' => '<script>alert(2)</script>',
        ));

        foreach ($this->build($project_id, array('show_subtasks' => true)) as $bar) {
            $this->assertStringNotContainsString('<', $bar['name'], $bar['id']);
            $this->assertStringNotContainsString('>', $bar['name'], $bar['id']);
        }

        $bars = $this->index($this->build($project_id));
        $this->assertStringContainsString('&amp;', $bars['task-'.$task_id]['name']);
        $this->assertStringContainsString('&lt;img', $bars['task-'.$task_id]['name']);

        // The raw title is still available to the popup, which escapes it
        // itself when building the HTML.
        $this->assertStringContainsString('<img', $bars['task-'.$task_id]['kb']['title']);
    }

    public function testDescriptionIsPlainTextAndTruncated()
    {
        $projectModel = new ProjectModel($this->container);
        $taskCreationModel = new TaskCreationModel($this->container);

        $project_id = $projectModel->create(array('name' => 'Description'));
        $task_id = $taskCreationModel->create(array(
            'project_id' => $project_id, 'title' => 'Task',
            'description' => '<b>Bold</b> '.str_repeat('word ', 100),
        ));

        $bars = $this->index($this->build($project_id));
        $description = $bars['task-'.$task_id]['description'];

        $this->assertStringNotContainsString('<b>', $description);
        $this->assertStringStartsWith('Bold', $description);
        $this->assertLessThanOrEqual(201, mb_strlen($description));
    }
}
