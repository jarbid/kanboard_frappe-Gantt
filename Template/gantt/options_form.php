<?php
/**
 * Renders a form field for every option in the definition.
 *
 * Both the global settings page and the per-project settings page include
 * this partial, so the two can never drift apart from each other or from the
 * options the chart actually understands.
 *
 * @var array $definition
 * @var array $values
 */

$groups = array(
    'layout' => t('Layout'),
    'timeline' => t('Timeline'),
    'holidays' => t('Weekends and holidays'),
    'interaction' => t('Interaction'),
    'data' => t('Kanboard data'),
);

$labels = array(
    'bar_height' => t('Bar height (pixels)'),
    'bar_corner_radius' => t('Bar corner radius (pixels)'),
    'arrow_curve' => t('Dependency arrow curve radius'),
    'padding' => t('Padding around bars (pixels)'),
    'column_width' => t('Column width (pixels, empty for the view mode default)'),
    'upper_header_height' => t('Upper header height (pixels)'),
    'lower_header_height' => t('Lower header height (pixels)'),
    'container_height' => t('Chart height ("auto" or a number of pixels)'),
    'lines' => t('Grid lines'),
    'view_mode' => t('Default view mode'),
    'enabled_view_modes' => t('Selectable view modes'),
    'view_mode_select' => t('Show the view mode selector'),
    'today_button' => t('Show the "Today" button'),
    'infinite_padding' => t('Extend the timeline while scrolling'),
    'scroll_to' => t('Scroll to on load ("today", "start", "end" or a date)'),
    'date_format' => t('Date format'),
    'snap_at' => t('Snap dragging to (for example "1d", empty for the view mode default)'),
    'weekend_days' => t('Weekend days'),
    'highlight_weekends' => t('Highlight weekends'),
    'holidays' => t('Holidays (one "YYYY-MM-DD: Label" per line)'),
    'holiday_color' => t('Holiday colour (any CSS colour, empty for the default)'),
    'ignore_weekends' => t('Exclude weekends from durations'),
    'ignore_dates' => t('Excluded dates (one "YYYY-MM-DD" per line)'),
    'readonly' => t('Read-only chart'),
    'readonly_dates' => t('Do not allow changing dates'),
    'readonly_progress' => t('Do not allow changing progress'),
    'fixed_duration' => t('Keep the duration fixed while dragging'),
    'move_dependencies' => t('Moving a task moves the tasks that depend on it'),
    'auto_move_label' => t('Keep bar labels visible while scrolling'),
    'popup_on' => t('Show the popup on'),
    'hover_on_date' => t('Highlight the column under the cursor'),
    'show_expected_progress' => t('Show expected progress'),
    'task_sort' => t('Task order'),
    'show_subtasks' => t('Show subtasks as child rows'),
    'show_closed_tasks' => t('Include closed tasks'),
    'dependency_links' => t('Link types drawn as dependencies'),
    'open_task_on' => t('Open the task on'),
    'color_source' => t('Bar colour taken from'),
);

$choice_labels = array(
    'lines' => array(
        'both' => t('Both'),
        'vertical' => t('Vertical only'),
        'horizontal' => t('Horizontal only'),
        'none' => t('None'),
    ),
    'popup_on' => array(
        'click' => t('Click'),
        'hover' => t('Hover'),
    ),
    'task_sort' => array(
        'board' => t('Board position'),
        'date' => t('Start date'),
    ),
    'open_task_on' => array(
        'double_click' => t('Double click'),
        'click' => t('Single click'),
        'never' => t('Never'),
    ),
    'color_source' => array(
        'task' => t('Task colour'),
        'category' => t('Category colour'),
        'none' => t('No colour'),
    ),
    'weekend_days' => array(
        '0' => t('Sunday'),
        '1' => t('Monday'),
        '2' => t('Tuesday'),
        '3' => t('Wednesday'),
        '4' => t('Thursday'),
        '5' => t('Friday'),
        '6' => t('Saturday'),
    ),
);

/**
 * Human-readable text for one choice of one option.
 */
$choice_label = function ($option, $choice) use ($choice_labels) {
    return isset($choice_labels[$option][$choice]) ? $choice_labels[$option][$choice] : $choice;
};

// The form helpers read their current value out of an array keyed by field
// name, and "set" options need a plain list to test membership against.
$form_values = array();

foreach ($definition as $name => $option) {
    $form_values[$name] = $option['type'] === 'set' ? array_values($values[$name]) : $values[$name];
}
?>

<?php foreach ($groups as $group => $group_label): ?>
    <fieldset class="kb-gantt-settings-group">
        <legend><?= $this->text->e($group_label) ?></legend>

        <?php foreach ($definition as $name => $option): ?>
            <?php if ($option['group'] !== $group) continue ?>
            <?php $label = isset($labels[$name]) ? $labels[$name] : $name ?>

            <?php if ($option['type'] === 'bool'): ?>
                <div>
                    <?= $this->form->checkbox($name, $label, 1, ! empty($values[$name])) ?>
                </div>

            <?php elseif ($option['type'] === 'set'): ?>
                <?= $this->form->label($label, $name) ?>
                <div>
                    <?php foreach ($option['choices'] as $choice): ?>
                        <span class="form-inline-group">
                            <?= $this->form->checkbox(
                                $name.'['.$this->text->e($choice).']',
                                $choice_label($name, $choice),
                                $choice,
                                in_array($choice, $form_values[$name], true)
                            ) ?>
                        </span>
                    <?php endforeach ?>
                </div>

            <?php elseif ($option['type'] === 'enum'): ?>
                <?= $this->form->label($label, $name) ?>
                <?php
                    $select_options = array();
                    foreach ($option['choices'] as $choice) {
                        $select_options[$choice] = $choice_label($name, $choice);
                    }
                ?>
                <?= $this->form->select($name, $select_options, $form_values) ?>

            <?php elseif ($option['type'] === 'text'): ?>
                <?= $this->form->label($label, $name) ?>
                <?= $this->form->textarea($name, $form_values, array(), array('rows="4"')) ?>

            <?php elseif ($option['type'] === 'int' || $option['type'] === 'nullable_int'): ?>
                <?= $this->form->label($label, $name) ?>
                <?= $this->form->number($name, $form_values) ?>

            <?php else: ?>
                <?= $this->form->label($label, $name) ?>
                <?= $this->form->text($name, $form_values) ?>
            <?php endif ?>
        <?php endforeach ?>
    </fieldset>
<?php endforeach ?>
