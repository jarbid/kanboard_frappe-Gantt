<?php

namespace Kanboard\Plugin\FrappeGantt\Controller;

use Kanboard\Filter\TaskProjectFilter;
use Kanboard\Filter\TaskStatusFilter;
use Kanboard\Model\TaskModel;
use Kanboard\Plugin\FrappeGantt\Formatter\TaskGanttFormatter;

/**
 * Gantt chart of the tasks of a single project.
 *
 * @property \Kanboard\Plugin\FrappeGantt\Model\GanttOptionModel   $ganttOptionModel
 * @property \Kanboard\Plugin\FrappeGantt\Model\GanttProgressModel $ganttProgressModel
 */
class TaskGanttController extends BaseGanttController
{
    public function show()
    {
        $project = $this->getProject();
        $values = $this->ganttOptionModel->getValues($project['id']);

        $sorting = $this->request->getStringParam('sorting', '');

        if ($sorting !== 'board' && $sorting !== 'date') {
            $sorting = $values['task_sort'];
        }

        $search = $this->helper->projectHeader->getSearchQuery($project);
        $filter = $this->taskLexer->build($search)->withFilter(new TaskProjectFilter($project['id']));

        // Only force the open-tasks filter when the user's own search does
        // not already say something about the status.
        if (empty($values['show_closed_tasks']) && stripos($search, 'status:') === false) {
            $filter->withFilter(new TaskStatusFilter(TaskModel::STATUS_OPEN));
        }

        if ($sorting === 'date') {
            $filter->getQuery()
                ->asc(TaskModel::TABLE.'.date_started')
                ->asc(TaskModel::TABLE.'.date_due')
                ->asc(TaskModel::TABLE.'.date_creation');
        } else {
            $filter->getQuery()
                ->asc('column_position')
                ->asc(TaskModel::TABLE.'.position');
        }

        $editable = $this->helper->user->hasProjectAccess('TaskGanttController', 'save', $project['id']);

        $bars = $filter->format(
            (new TaskGanttFormatter($this->container))->withOptions($values)
        );

        // url->to() joins parameters with a plain "&". url->href() uses
        // "&amp;" for embedding straight into markup, which would arrive at
        // the browser double-escaped once the JSON payload is attribute
        // escaped, dropping every parameter after the first.
        $config = $this->buildChartConfig($bars, $values, $editable, array(
            'dates' => $this->helper->url->to('TaskGanttController', 'save', array(
                'project_id' => $project['id'],
                'plugin' => 'FrappeGantt',
            )),
            'progress' => $this->helper->url->to('TaskGanttController', 'saveProgress', array(
                'project_id' => $project['id'],
                'plugin' => 'FrappeGantt',
            )),
        ));

        $this->response->html($this->helper->layout->app('FrappeGantt:task_gantt/show', array(
            'project' => $project,
            'title' => $project['name'],
            'description' => $this->helper->projectHeader->getDescription($project),
            'sorting' => $sorting,
            'editable' => $editable,
            'values' => $values,
            'chart' => $config,
            'has_tasks' => ! empty($bars),
        )));
    }

    /**
     * Persist a new start/due date after a bar was dragged or resized.
     */
    public function save()
    {
        $project = $this->getProject();
        $changes = $this->getValidatedJson();

        if ($changes === null) {
            $this->rejectInvalidRequest();
            return;
        }

        $task = $this->getTaskInProject($changes, $project['id']);

        if ($task === null) {
            $this->response->json(array('message' => 'Task not found in this project'), 404);
            return;
        }

        $values = array('id' => $task['id']);

        if (! empty($changes['start'])) {
            $values['date_started'] = $this->parseDate($changes['start']);
        }

        if (! empty($changes['end'])) {
            $values['date_due'] = $this->parseDate($changes['end']);
        }

        if (count($values) === 1) {
            $this->response->json(array('message' => 'Nothing to do'), 200);
            return;
        }

        if ($this->taskModificationModel->update($values)) {
            $this->response->json(array('message' => 'OK'), 201);
        } else {
            $this->response->json(array('message' => 'Unable to save this task'), 400);
        }
    }

    /**
     * Persist the completion percentage after the progress handle was dragged.
     */
    public function saveProgress()
    {
        $project = $this->getProject();
        $changes = $this->getValidatedJson();

        if ($changes === null) {
            $this->rejectInvalidRequest();
            return;
        }

        $task = $this->getTaskInProject($changes, $project['id']);

        if ($task === null) {
            $this->response->json(array('message' => 'Task not found in this project'), 404);
            return;
        }

        if (! isset($changes['progress']) || ! is_numeric($changes['progress'])) {
            $this->response->json(array('message' => 'Missing progress'), 400);
            return;
        }

        if ($this->ganttProgressModel->save($task['id'], round($changes['progress']))) {
            $this->response->json(array('message' => 'OK'), 201);
        } else {
            $this->response->json(array('message' => 'Unable to save the progress'), 400);
        }
    }

    /**
     * Resolve the task referenced by a payload, making sure it really belongs
     * to the project the caller was authorized against.
     *
     * @param  array $changes
     * @param  int   $project_id
     * @return array|null
     */
    private function getTaskInProject(array $changes, $project_id)
    {
        if (empty($changes['id'])) {
            return null;
        }

        $task = $this->taskFinderModel->getById((int) $changes['id']);

        if (empty($task) || (int) $task['project_id'] !== (int) $project_id) {
            return null;
        }

        return $task;
    }

    /**
     * Frappe Gantt sends ISO dates; keep them at midnight local time.
     *
     * @param  string $value
     * @return int
     */
    private function parseDate($value)
    {
        $timestamp = strtotime((string) $value);

        return $timestamp === false ? 0 : $timestamp;
    }
}
