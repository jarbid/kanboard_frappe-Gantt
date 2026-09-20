<?php

namespace Kanboard\Plugin\FrappeGantt\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Plugin\FrappeGantt\Model\GanttOptionModel;

/**
 * Shared plumbing for the three chart pages.
 *
 * @property \Kanboard\Plugin\FrappeGantt\Model\GanttOptionModel   $ganttOptionModel
 * @property \Kanboard\Plugin\FrappeGantt\Model\GanttProgressModel $ganttProgressModel
 */
abstract class BaseGanttController extends BaseController
{
    /**
     * Assemble everything the JS bridge needs for one chart.
     *
     * The whole payload travels in data attributes because Kanboard's default
     * Content-Security-Policy ("default-src 'self'") forbids inline scripts.
     *
     * @param  array  $bars       formatted bars
     * @param  array  $values     resolved option values
     * @param  bool   $editable   whether the viewer may write changes back
     * @param  array  $endpoints  named URLs the bridge posts to
     * @return array
     */
    protected function buildChartConfig(array $bars, array $values, $editable, array $endpoints = array())
    {
        return array(
            'tasks' => $bars,
            'options' => $this->ganttOptionModel->buildLibraryOptions($values, $editable),
            'bridge' => $this->ganttOptionModel->buildBridgeOptions($values) + array(
                'editable' => $editable,
                'endpoints' => $endpoints,
                'csrf_token' => $this->token->getReusableCSRFToken(),
                'labels' => $this->getLabels(),
            ),
        );
    }

    /**
     * Strings the bridge renders client-side. They are translated here so
     * that the JavaScript stays free of a translation layer of its own.
     *
     * @return array
     */
    protected function getLabels()
    {
        return array(
            'start_date' => t('Start date'),
            'due_date' => t('Due date'),
            'assignee' => t('Assignee'),
            'column' => t('Column'),
            'swimlane' => t('Swimlane'),
            'category' => t('Category'),
            'project' => t('Project'),
            'progress' => t('Progress'),
            'duration' => t('Duration'),
            'days' => t('days'),
            'day' => t('day'),
            'not_defined' => t('Not defined'),
            'no_dates' => t('This task has no start date or due date.'),
            'open_task' => t('Open this task'),
            'open_board' => t('Open the board'),
            'open_gantt' => t('Open the Gantt chart'),
            'time_spent' => t('Time spent'),
            'time_estimated' => t('Time estimated'),
            'hours' => t('hours'),
            'subtask' => t('Subtask'),
            'columns' => t('Columns'),
            'zoom_in' => t('Zoom in'),
            'zoom_out' => t('Zoom out'),
            'zoom_fit' => t('Fit the whole plan'),
            'add_predecessor' => t('Add a predecessor'),
            'remove_dependency' => t('Remove a dependency'),
            'pick_predecessor' => t('Click the task this one depends on'),
            'cancel' => t('Cancel'),
            'no_dependencies' => t('This task has no dependencies'),
            'dependency_saved' => t('Dependency saved'),
            'dependency_cycle' => t('This would create a circular dependency'),
            'saved' => t('Saved'),
            'save_error' => t('Unable to save this change'),
            'readonly_subtask' => t('Subtasks have no dates of their own and cannot be moved.'),
        );
    }

    /**
     * Read and validate a JSON payload sent by the bridge.
     *
     * @return array|null null when the payload is unusable
     */
    protected function getValidatedJson()
    {
        $values = $this->request->getJson();

        if (! is_array($values)) {
            return null;
        }

        if (! isset($values['csrf_token']) || ! $this->token->validateReusableCSRFToken($values['csrf_token'])) {
            return null;
        }

        return $values;
    }

    /**
     * Reject a request that failed CSRF validation.
     */
    protected function rejectInvalidRequest()
    {
        $this->response->json(array('message' => 'Invalid request'), 403);
    }

    /**
     * @return GanttOptionModel
     */
    protected function options()
    {
        return $this->ganttOptionModel;
    }
}
