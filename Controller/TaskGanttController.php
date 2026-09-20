<?php

namespace Kanboard\Plugin\FrappeGantt\Controller;

use Kanboard\Filter\TaskProjectFilter;
use Kanboard\Model\LinkModel;
use Kanboard\Model\TaskLinkModel;
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
            'dependency' => $this->helper->url->to('TaskGanttController', 'saveDependency', array(
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
    /**
     * Create or remove a dependency between two tasks in this project.
     *
     * A dependency is an ordinary Kanboard task link, so this writes the same
     * rows the task view would and the chart picks the change up on its next
     * render. Gated at PROJECT_MEMBER to match core, which allows members to
     * manage task links.
     */
    public function saveDependency()
    {
        $project = $this->getProject();
        $changes = $this->getValidatedJson();

        if ($changes === null) {
            $this->rejectInvalidRequest();
            return;
        }

        $task = $this->getTaskInProject($changes, $project['id']);
        $predecessor = $this->getTaskInProject(
            array('id' => isset($changes['predecessor']) ? $changes['predecessor'] : 0),
            $project['id']
        );

        if ($task === null || $predecessor === null) {
            $this->response->json(array('message' => 'Task not found in this project'), 404);
            return;
        }

        if ($task['id'] === $predecessor['id']) {
            $this->response->json(array('message' => 'A task cannot depend on itself'), 400);
            return;
        }

        $operation = isset($changes['operation']) ? $changes['operation'] : '';

        if ($operation === 'add') {
            $this->addDependency($predecessor['id'], $task['id']);
            return;
        }

        if ($operation === 'remove') {
            $this->removeDependency($predecessor['id'], $task['id']);
            return;
        }

        $this->response->json(array('message' => 'Unknown operation'), 400);
    }

    /**
     * Link the predecessor so that it blocks the dependent task.
     */
    private function addDependency($predecessor_id, $task_id)
    {
        $link_id = $this->getDependencyLinkId();

        if ($link_id === null) {
            $this->response->json(array('message' => 'No dependency link type available'), 400);
            return;
        }

        // Refuse a link that would make the task depend on itself through a
        // chain, since a cycle has no meaningful order.
        if ($this->createsCycle($predecessor_id, $task_id)) {
            $this->response->json(array('message' => 'This would create a circular dependency'), 409);
            return;
        }

        if ($this->taskLinkModel->create($predecessor_id, $task_id, $link_id)) {
            $this->response->json(array('message' => 'OK'), 201);
        } else {
            $this->response->json(array('message' => 'Unable to create the dependency'), 400);
        }
    }

    /**
     * Drop the link that makes the dependent task depend on the predecessor.
     */
    private function removeDependency($predecessor_id, $task_id)
    {
        $labels = $this->ganttOptionModel->getValues($this->request->getIntegerParam('project_id'))['dependency_links'];

        $row = $this->db
            ->table(TaskLinkModel::TABLE)
            ->columns(TaskLinkModel::TABLE.'.id')
            ->join(LinkModel::TABLE, 'id', 'link_id', TaskLinkModel::TABLE)
            ->eq(TaskLinkModel::TABLE.'.task_id', $predecessor_id)
            ->eq(TaskLinkModel::TABLE.'.opposite_task_id', $task_id)
            ->in(LinkModel::TABLE.'.label', $labels)
            ->findOne();

        if (empty($row)) {
            $this->response->json(array('message' => 'Dependency not found'), 404);
            return;
        }

        if ($this->taskLinkModel->remove($row['id'])) {
            $this->response->json(array('message' => 'OK'), 201);
        } else {
            $this->response->json(array('message' => 'Unable to remove the dependency'), 400);
        }
    }

    /**
     * The link type new dependencies are created with: the first enabled one
     * that points from predecessor to dependent.
     *
     * @return int|null
     */
    private function getDependencyLinkId()
    {
        $values = $this->ganttOptionModel->getValues($this->request->getIntegerParam('project_id'));

        foreach ($values['dependency_links'] as $label) {
            if (TaskGanttFormatter::LINK_DIRECTIONS[$label] !== 'forward') {
                continue;
            }

            $id = $this->db->table(LinkModel::TABLE)->eq('label', $label)->findOneColumn('id');

            if (! empty($id)) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * Whether making the predecessor block the task would close a loop.
     */
    private function createsCycle($predecessor_id, $task_id)
    {
        $labels = $this->ganttOptionModel->getValues($this->request->getIntegerParam('project_id'))['dependency_links'];
        $seen = array();
        $queue = array($task_id);

        // Walk forward from the dependent task: if the predecessor is already
        // downstream of it, the new link would close a loop.
        while (! empty($queue)) {
            $current = array_shift($queue);

            if ($current === $predecessor_id) {
                return true;
            }

            if (isset($seen[$current])) {
                continue;
            }

            $seen[$current] = true;

            $rows = $this->db
                ->table(TaskLinkModel::TABLE)
                ->columns(TaskLinkModel::TABLE.'.opposite_task_id')
                ->join(LinkModel::TABLE, 'id', 'link_id', TaskLinkModel::TABLE)
                ->eq(TaskLinkModel::TABLE.'.task_id', $current)
                ->in(LinkModel::TABLE.'.label', $labels)
                ->findAll();

            foreach ($rows as $row) {
                $queue[] = (int) $row['opposite_task_id'];
            }
        }

        return false;
    }

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
