<li <?= $this->app->checkMenuSelection('UserGanttController') ?>>
    <?= $this->url->icon('sliders', t('My Gantt chart'), 'UserGanttController', 'show', array('plugin' => 'FrappeGantt')) ?>
</li>
