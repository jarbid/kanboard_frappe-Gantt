<?php

namespace Kanboard\Plugin\FrappeGantt\Controller;

use Kanboard\Filter\ProjectIdsFilter;
use Kanboard\Filter\ProjectStatusFilter;
use Kanboard\Filter\ProjectTypeFilter;
use Kanboard\Model\ProjectModel;
use Kanboard\Plugin\FrappeGantt\Formatter\ProjectGanttFormatter;

/**
 * Gantt chart of every project the user can see.
 *
 * @property \Kanboard\Plugin\FrappeGantt\Model\GanttOptionModel $ganttOptionModel
 */
class ProjectGanttController extends BaseGanttController
{
    public function show()
    {
        $project_ids = $this->projectPermissionModel->getActiveProjectIds($this->userSession->getId());

        $filter = $this->projectQuery
            ->withFilter(new ProjectTypeFilter(ProjectModel::TYPE_TEAM))
            ->withFilter(new ProjectStatusFilter(ProjectModel::ACTIVE))
            ->withFilter(new ProjectIdsFilter($project_ids));

        $filter->getQuery()->asc(ProjectModel::TABLE.'.start_date');

        $values = $this->ganttOptionModel->getGlobalValues();
        $editable = $this->helper->user->hasAccess('ProjectGanttController', 'save');

        $bars = $filter->format(new ProjectGanttFormatter($this->container));

        $config = $this->buildChartConfig($bars, $values, $editable, array(
            'dates' => $this->helper->url->href('ProjectGanttController', 'save', array('plugin' => 'FrappeGantt')),
        ));

        $this->response->html($this->helper->layout->app('FrappeGantt:project_gantt/show', array(
            'title' => t('Gantt chart for all projects'),
            'chart' => $config,
            'editable' => $editable,
            'has_tasks' => ! empty($bars),
        )));
    }

    /**
     * Persist a project's start and end date after its bar was moved.
     */
    public function save()
    {
        $values = $this->getValidatedJson();

        if ($values === null) {
            $this->rejectInvalidRequest();
            return;
        }

        if (empty($values['id'])) {
            $this->response->json(array('message' => 'Missing project'), 400);
            return;
        }

        $project_id = (int) $values['id'];

        // The application-level role grants the action, but the user still
        // has to be a manager of this particular project.
        if (! $this->helper->user->hasProjectAccess('ProjectEditController', 'update', $project_id)) {
            $this->response->json(array('message' => 'Forbidden'), 403);
            return;
        }

        $changes = array('id' => $project_id);

        if (! empty($values['start'])) {
            $changes['start_date'] = $this->dateParser->getIsoDate(strtotime($values['start']));
        }

        if (! empty($values['end'])) {
            $changes['end_date'] = $this->dateParser->getIsoDate(strtotime($values['end']));
        }

        if (count($changes) === 1) {
            $this->response->json(array('message' => 'Nothing to do'), 200);
            return;
        }

        if ($this->projectModel->update($changes)) {
            $this->response->json(array('message' => 'OK'), 201);
        } else {
            $this->response->json(array('message' => 'Unable to save this project'), 400);
        }
    }
}
