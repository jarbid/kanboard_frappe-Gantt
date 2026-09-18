<?php if ($this->user->hasProjectAccess('ProjectSettingsController', 'show', $project['id'])): ?>
    <li <?= $this->app->checkMenuSelection('ProjectSettingsController', 'show', 'FrappeGantt') ?>>
        <?= $this->url->link(t('Gantt settings'), 'ProjectSettingsController', 'show', array('project_id' => $project['id'], 'plugin' => 'FrappeGantt')) ?>
    </li>
<?php endif ?>
