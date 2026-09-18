<?php

namespace Kanboard\Plugin\FrappeGantt\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Plugin\FrappeGantt\Model\GanttOptionModel;
use Kanboard\Plugin\FrappeGantt\Plugin;

/**
 * Global Gantt settings.
 *
 * @property \Kanboard\Plugin\FrappeGantt\Model\GanttOptionModel $ganttOptionModel
 */
class ConfigController extends BaseController
{
    public function show()
    {
        $this->response->html($this->helper->layout->config('FrappeGantt:config/settings', array(
            'title' => t('Settings').' &gt; '.t('Gantt settings'),
            'values' => $this->ganttOptionModel->getGlobalValues(),
            'definition' => GanttOptionModel::getDefinition(),
            'vendor_version' => Plugin::getVendorVersion(),
        )));
    }

    public function save()
    {
        // getValues() validates the CSRF token that the form posted and
        // returns an empty array when it does not match. Saving that array
        // would silently reset every option, so it is rejected outright.
        $input = $this->request->getValues();

        if (empty($input)) {
            throw new AccessForbiddenException();
        }

        $values = $this->ganttOptionModel->encodeForStorage($input);

        if ($this->configModel->save($values)) {
            $this->flash->success(t('Settings saved successfully.'));
        } else {
            $this->flash->failure(t('Unable to save your settings.'));
        }

        $this->response->redirect($this->helper->url->to('ConfigController', 'show', array('plugin' => 'FrappeGantt')));
    }

    /**
     * Restore every option to the library defaults.
     */
    public function reset()
    {
        $this->checkCSRFParam();

        // Store the encoded defaults rather than blank values, so the saved
        // configuration states the defaults explicitly instead of relying on
        // the fallback for every option.
        $this->configModel->save(
            $this->ganttOptionModel->encodeForStorage($this->ganttOptionModel->getDefaults())
        );
        $this->flash->success(t('Settings saved successfully.'));

        $this->response->redirect($this->helper->url->to('ConfigController', 'show', array('plugin' => 'FrappeGantt')));
    }
}
