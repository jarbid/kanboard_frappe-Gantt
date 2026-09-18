<div class="page-header">
    <h2><?= t('Gantt settings') ?></h2>
</div>

<p class="kb-gantt-vendor-version">
    <?= t('Powered by Frappe Gantt %s', $this->text->e($vendor_version)) ?>
</p>

<form method="post" action="<?= $this->url->href('ConfigController', 'save', array('plugin' => 'FrappeGantt')) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>

    <?= $this->render('FrappeGantt:gantt/options_form', array(
        'definition' => $definition,
        'values' => $values,
    )) ?>

    <div class="form-actions">
        <button type="submit" class="btn btn-blue"><?= t('Save') ?></button>
        <?= t('or') ?>
        <?= $this->url->link(t('Reset to defaults'), 'ConfigController', 'reset', array('plugin' => 'FrappeGantt'), true, '', t('Reset all Gantt settings to their default values?')) ?>
    </div>
</form>
