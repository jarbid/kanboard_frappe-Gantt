<section id="main">
    <div class="page-header">
        <h2><?= t('Gantt chart for all projects') ?></h2>
    </div>

    <?php if ($has_tasks): ?>
        <?= $this->render('FrappeGantt:gantt/chart', array(
            'chart' => $chart,
            'scope' => 'all-projects',
        )) ?>

        <?php if ($editable): ?>
            <p class="alert alert-info">
                <?= t('Moving or resizing a bar changes the start date and the end date of the project.') ?>
            </p>
        <?php endif ?>
    <?php else: ?>
        <p class="alert"><?= t('There is no project.') ?></p>
    <?php endif ?>
</section>
