<?php

namespace Kanboard\Plugin\FrappeGantt\Test;

// PHPUnit loads each test file before Kanboard's bootstrap registers the
// plugin autoloader, so the shared case has to be required explicitly.
require_once __DIR__.'/PluginTestCase.php';

use Kanboard\Model\ProjectModel;
use Kanboard\Model\TaskCreationModel;
use Kanboard\Model\TaskModel;
use Kanboard\Plugin\FrappeGantt\Model\GanttProgressModel;

class GanttProgressModelTest extends PluginTestCase
{
    public function testSaveGetAndRemove()
    {
        $model = new GanttProgressModel($this->container);
        $projectModel = new ProjectModel($this->container);
        $taskCreationModel = new TaskCreationModel($this->container);

        $project_id = $projectModel->create(array('name' => 'Progress test'));
        $task_id = $taskCreationModel->create(array('project_id' => $project_id, 'title' => 'Task'));

        $this->assertNull($model->get($task_id));

        $this->assertTrue((bool) $model->save($task_id, 42));
        $this->assertSame(42, $model->get($task_id));

        // Saving twice updates in place instead of inserting a duplicate.
        $this->assertTrue((bool) $model->save($task_id, 77));
        $this->assertSame(77, $model->get($task_id));

        $this->assertTrue((bool) $model->remove($task_id));
        $this->assertNull($model->get($task_id));
    }

    public function testProgressIsClamped()
    {
        $model = new GanttProgressModel($this->container);
        $projectModel = new ProjectModel($this->container);
        $taskCreationModel = new TaskCreationModel($this->container);

        $project_id = $projectModel->create(array('name' => 'Clamp test'));
        $task_id = $taskCreationModel->create(array('project_id' => $project_id, 'title' => 'Task'));

        $model->save($task_id, 350);
        $this->assertSame(100, $model->get($task_id));

        $model->save($task_id, -20);
        $this->assertSame(0, $model->get($task_id));
    }

    public function testGetMultiple()
    {
        $model = new GanttProgressModel($this->container);
        $projectModel = new ProjectModel($this->container);
        $taskCreationModel = new TaskCreationModel($this->container);

        $project_id = $projectModel->create(array('name' => 'Multiple test'));
        $first = $taskCreationModel->create(array('project_id' => $project_id, 'title' => 'First'));
        $second = $taskCreationModel->create(array('project_id' => $project_id, 'title' => 'Second'));
        $third = $taskCreationModel->create(array('project_id' => $project_id, 'title' => 'Third'));

        $model->save($first, 10);
        $model->save($third, 30);

        $result = $model->getMultiple(array($first, $second, $third));

        $this->assertSame(10, $result[$first]);
        $this->assertArrayNotHasKey($second, $result, 'a task without a stored value must not appear');
        $this->assertSame(30, $result[$third]);

        $this->assertSame(array(), $model->getMultiple(array()));
    }

    public function testResolvePrefersTheStoredValueAndFallsBackToTheColumn()
    {
        $model = new GanttProgressModel($this->container);
        $projectModel = new ProjectModel($this->container);
        $taskCreationModel = new TaskCreationModel($this->container);

        $project_id = $projectModel->create(array('name' => 'Resolve test'));
        $columns = $this->container['columnModel']->getList($project_id);
        $column_ids = array_keys($columns);

        $task_id = $taskCreationModel->create(array(
            'project_id' => $project_id,
            'title' => 'Task',
            'column_id' => $column_ids[1],
        ));
        $task = $this->container['taskFinderModel']->getById($task_id);

        // No stored value: the board column decides.
        $this->assertSame(25.0, $model->resolve($task, $columns, null));

        // A stored value wins over the column position.
        $this->assertSame(60.0, $model->resolve($task, $columns, 60));

        // A closed task is always complete, whatever is stored.
        $task['is_active'] = TaskModel::STATUS_CLOSED;
        $this->assertSame(100.0, $model->resolve($task, $columns, 60));
    }
}
