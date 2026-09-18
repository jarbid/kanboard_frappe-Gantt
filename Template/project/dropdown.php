<li>
    <?= $this->url->icon('sliders', t('Gantt chart'), 'TaskGanttController', 'show', array(
        'project_id' => $project['id'],
        'plugin' => 'FrappeGantt',
    )) ?>
</li>
