<div class="page-header">
    <h2><?= t('Gantt settings') ?></h2>
</div>

<form method="post" action="<?= $this->url->href('ProjectSettingsController', 'save', array('project_id' => $project['id'], 'plugin' => 'FrappeGantt')) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>

    <fieldset>
        <legend><?= t('Scope') ?></legend>
        <?= $this->form->checkbox('override_global', t('Use settings specific to this project'), 1, $is_overriding) ?>
        <p class="form-help">
            <?= t('When this is unchecked the project follows the global Gantt settings and the values below are ignored.') ?>
        </p>
    </fieldset>

    <?= $this->render('FrappeGantt:gantt/options_form', array(
        'definition' => $definition,
        'values' => $values,
    )) ?>

    <div class="form-actions">
        <button type="submit" class="btn btn-blue"><?= t('Save') ?></button>
    </div>
</form>
