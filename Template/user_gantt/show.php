<section id="main">
    <div class="page-header">
        <h2><?= t('My Gantt chart') ?></h2>
    </div>

    <?php if ($has_tasks): ?>
        <?= $this->render('FrappeGantt:gantt/chart', array(
            'chart' => $chart,
            'scope' => 'my-tasks',
        )) ?>
        <p class="alert alert-info">
            <?= t('This chart is read-only. Open a project to change the dates of its tasks.') ?>
        </p>
    <?php else: ?>
        <p class="alert"><?= t('There is no task assigned to you.') ?></p>
    <?php endif ?>
</section>
