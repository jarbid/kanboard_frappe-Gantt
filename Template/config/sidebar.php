<li <?= $this->app->checkMenuSelection('ConfigController', 'show', 'FrappeGantt') ?>>
    <?= $this->url->link(t('Gantt settings'), 'ConfigController', 'show', array('plugin' => 'FrappeGantt')) ?>
</li>
