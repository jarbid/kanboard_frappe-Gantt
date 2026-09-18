<li <?= $this->app->checkMenuSelection('ProjectGanttController') ?>>
    <?= $this->url->icon('sliders', t('Gantt chart for all projects'), 'ProjectGanttController', 'show', array('plugin' => 'FrappeGantt')) ?>
</li>
