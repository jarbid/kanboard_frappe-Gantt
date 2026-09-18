<?php

namespace Kanboard\Plugin\FrappeGantt\Controller;

use Kanboard\Filter\TaskAssigneeFilter;
use Kanboard\Filter\TaskProjectsFilter;
use Kanboard\Filter\TaskStatusFilter;
use Kanboard\Model\TaskModel;
use Kanboard\Plugin\FrappeGantt\Formatter\TaskGanttFormatter;

/**
 * Gantt chart of the tasks assigned to the current user, across projects.
 *
 * The chart is read-only: a single project's permissions cannot be assumed
 * across the whole set, and the per-project chart is the place to edit.
 *
 * @property \Kanboard\Plugin\FrappeGantt\Model\GanttOptionModel $ganttOptionModel
 */
class UserGanttController extends BaseGanttController
{
    public function show()
    {
        $user_id = $this->userSession->getId();
        $project_ids = $this->projectPermissionModel->getActiveProjectIds($user_id);
        $values = $this->ganttOptionModel->getGlobalValues();

        $bars = array();

        if (! empty($project_ids)) {
            $filter = $this->taskQuery
                ->withFilter(new TaskProjectsFilter($project_ids))
                ->withFilter(new TaskAssigneeFilter($user_id));

            if (empty($values['show_closed_tasks'])) {
                $filter->withFilter(new TaskStatusFilter(TaskModel::STATUS_OPEN));
            }

            $filter->getQuery()
                ->asc(TaskModel::TABLE.'.date_started')
                ->asc(TaskModel::TABLE.'.date_due')
                ->asc(TaskModel::TABLE.'.date_creation');

            $bars = $filter->format(
                (new TaskGanttFormatter($this->container))->withOptions($values)
            );
        }

        $config = $this->buildChartConfig($bars, $values, false);

        $this->response->html($this->helper->layout->app('FrappeGantt:user_gantt/show', array(
            'title' => t('My Gantt chart'),
            'chart' => $config,
            'editable' => false,
            'has_tasks' => ! empty($bars),
        )));
    }
}
