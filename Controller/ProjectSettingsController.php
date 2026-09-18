<?php

namespace Kanboard\Plugin\FrappeGantt\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Plugin\FrappeGantt\Model\GanttOptionModel;

/**
 * Per-project Gantt settings.
 *
 * A project either follows the global configuration or overrides it wholesale;
 * a half-inherited configuration would be far harder to reason about.
 *
 * @property \Kanboard\Plugin\FrappeGantt\Model\GanttOptionModel $ganttOptionModel
 */
class ProjectSettingsController extends BaseController
{
    public function show()
    {
        $project = $this->getProject();

        $this->response->html($this->helper->layout->project('FrappeGantt:project/settings', array(
            'project' => $project,
            'title' => t('Gantt settings'),
            'values' => $this->ganttOptionModel->getValues($project['id']),
            'definition' => GanttOptionModel::getDefinition(),
            'is_overriding' => $this->ganttOptionModel->isProjectOverriding($project['id']),
        )));
    }

    public function save()
    {
        $project = $this->getProject();

        // getValues() validates the CSRF token posted by the form and yields
        // an empty array when it does not match.
        $input = $this->request->getValues();

        if (empty($input)) {
            throw new AccessForbiddenException();
        }

        $values = $this->ganttOptionModel->encodeForStorage($input);
        $values[GanttOptionModel::OVERRIDE_KEY] = empty($input['override_global']) ? '0' : '1';

        if ($this->projectMetadataModel->save($project['id'], $values)) {
            $this->flash->success(t('Settings saved successfully.'));
        } else {
            $this->flash->failure(t('Unable to save your settings.'));
        }

        $this->response->redirect($this->helper->url->to('ProjectSettingsController', 'show', array(
            'project_id' => $project['id'],
            'plugin' => 'FrappeGantt',
        )));
    }
}
