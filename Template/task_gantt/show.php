<section id="main">
    <?= $this->projectHeader->render($project, 'TaskGanttController', 'show', false, 'FrappeGantt') ?>

    <div class="menu-inline">
        <ul>
            <li <?= $sorting === 'board' ? 'class="active"' : '' ?>>
                <?= $this->url->icon('sort-numeric-asc', t('Sort by position'), 'TaskGanttController', 'show', array('project_id' => $project['id'], 'sorting' => 'board', 'plugin' => 'FrappeGantt')) ?>
            </li>
            <li <?= $sorting === 'date' ? 'class="active"' : '' ?>>
                <?= $this->url->icon('sort-amount-asc', t('Sort by date'), 'TaskGanttController', 'show', array('project_id' => $project['id'], 'sorting' => 'date', 'plugin' => 'FrappeGantt')) ?>
            </li>
            <?php if ($this->user->hasProjectAccess('ProjectSettingsController', 'show', $project['id'])): ?>
                <li>
                    <?= $this->url->icon('cog', t('Gantt settings'), 'ProjectSettingsController', 'show', array('project_id' => $project['id'], 'plugin' => 'FrappeGantt')) ?>
                </li>
            <?php endif ?>
            <li>
                <?= $this->modal->large('plus', t('Add task'), 'TaskCreationController', 'show', array('project_id' => $project['id'])) ?>
            </li>
        </ul>
    </div>

    <?php if ($has_tasks): ?>
        <?= $this->render('FrappeGantt:gantt/chart', array(
            'chart' => $chart,
            'scope' => 'project-'.$project['id'],
        )) ?>

        <?php if ($editable): ?>
            <p class="alert alert-info">
                <?= t('Moving or resizing a bar changes the start date and the due date of the task. Dragging the progress handle changes the completion percentage.') ?>
            </p>
        <?php else: ?>
            <p class="alert alert-info"><?= t('You are not allowed to update tasks in this project.') ?></p>
        <?php endif ?>
    <?php else: ?>
        <p class="alert"><?= t('There is no task in your project.') ?></p>
    <?php endif ?>
</section>
