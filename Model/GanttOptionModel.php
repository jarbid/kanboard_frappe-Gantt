<?php

namespace Kanboard\Plugin\FrappeGantt\Model;

use Kanboard\Core\Base;

/**
 * Resolution of Frappe Gantt options.
 *
 * Every option upstream exposes is described once in getDefinition(). That
 * definition drives the settings forms, the persistence layer and the JSON
 * handed to the browser, so the three can never drift apart.
 *
 * Values are resolved in three layers: the upstream defaults, the global
 * Kanboard settings, then the per-project overrides.
 */
class GanttOptionModel extends Base
{
    const PREFIX = 'frappegantt_';

    /**
     * Marks a project as overriding the global settings.
     */
    const OVERRIDE_KEY = 'frappegantt_override_global';

    /**
     * View modes shipped by the library, in upstream order.
     */
    const VIEW_MODES = array('Hour', 'Quarter Day', 'Half Day', 'Day', 'Week', 'Month', 'Year');

    /**
     * Option definitions.
     *
     * Each entry has a type, a default, and optionally a list of choices. The
     * "scope" tells whether the option is a direct Frappe Gantt option
     * ("library") or a Kanboard-side behaviour ("plugin").
     *
     * @return array
     */
    public static function getDefinition()
    {
        return array(
            // ---- Layout -------------------------------------------------
            'bar_height' => array('type' => 'int', 'default' => 30, 'scope' => 'library', 'group' => 'layout'),
            'bar_corner_radius' => array('type' => 'int', 'default' => 3, 'scope' => 'library', 'group' => 'layout'),
            'arrow_curve' => array('type' => 'int', 'default' => 5, 'scope' => 'library', 'group' => 'layout'),
            'padding' => array('type' => 'int', 'default' => 18, 'scope' => 'library', 'group' => 'layout'),
            'column_width' => array('type' => 'nullable_int', 'default' => null, 'scope' => 'library', 'group' => 'layout'),
            'upper_header_height' => array('type' => 'int', 'default' => 45, 'scope' => 'library', 'group' => 'layout'),
            'lower_header_height' => array('type' => 'int', 'default' => 30, 'scope' => 'library', 'group' => 'layout'),
            'container_height' => array('type' => 'string', 'default' => 'auto', 'scope' => 'library', 'group' => 'layout'),
            'lines' => array(
                'type' => 'enum',
                'default' => 'both',
                'choices' => array('both', 'vertical', 'horizontal', 'none'),
                'scope' => 'library',
                'group' => 'layout',
            ),

            // ---- Timeline -----------------------------------------------
            'view_mode' => array(
                'type' => 'enum',
                'default' => 'Day',
                'choices' => self::VIEW_MODES,
                'scope' => 'library',
                'group' => 'timeline',
            ),
            'enabled_view_modes' => array(
                'type' => 'set',
                'default' => self::VIEW_MODES,
                'choices' => self::VIEW_MODES,
                'scope' => 'plugin',
                'group' => 'timeline',
            ),
            'view_mode_select' => array('type' => 'bool', 'default' => true, 'scope' => 'library', 'group' => 'timeline'),
            'today_button' => array('type' => 'bool', 'default' => true, 'scope' => 'library', 'group' => 'timeline'),
            'infinite_padding' => array('type' => 'bool', 'default' => true, 'scope' => 'library', 'group' => 'timeline'),
            'scroll_to' => array('type' => 'string', 'default' => 'today', 'scope' => 'library', 'group' => 'timeline'),
            'date_format' => array('type' => 'string', 'default' => 'YYYY-MM-DD', 'scope' => 'library', 'group' => 'timeline'),
            'snap_at' => array('type' => 'string', 'default' => '', 'allow_empty' => true, 'scope' => 'library', 'group' => 'timeline'),

            // ---- Weekends and holidays ----------------------------------
            'weekend_days' => array(
                'type' => 'set',
                'default' => array('0', '6'),
                'choices' => array('0', '1', '2', '3', '4', '5', '6'),
                'scope' => 'plugin',
                'group' => 'holidays',
            ),
            'highlight_weekends' => array('type' => 'bool', 'default' => true, 'scope' => 'plugin', 'group' => 'holidays'),
            'holidays' => array('type' => 'text', 'default' => '', 'allow_empty' => true, 'scope' => 'plugin', 'group' => 'holidays'),
            'holiday_color' => array('type' => 'string', 'default' => '', 'allow_empty' => true, 'scope' => 'plugin', 'group' => 'holidays'),
            'ignore_weekends' => array('type' => 'bool', 'default' => false, 'scope' => 'plugin', 'group' => 'holidays'),
            'ignore_dates' => array('type' => 'text', 'default' => '', 'allow_empty' => true, 'scope' => 'plugin', 'group' => 'holidays'),

            // ---- Interaction --------------------------------------------
            'readonly' => array('type' => 'bool', 'default' => false, 'scope' => 'library', 'group' => 'interaction'),
            'readonly_dates' => array('type' => 'bool', 'default' => false, 'scope' => 'library', 'group' => 'interaction'),
            'readonly_progress' => array('type' => 'bool', 'default' => false, 'scope' => 'library', 'group' => 'interaction'),
            'fixed_duration' => array('type' => 'bool', 'default' => false, 'scope' => 'library', 'group' => 'interaction'),
            'move_dependencies' => array('type' => 'bool', 'default' => true, 'scope' => 'library', 'group' => 'interaction'),
            'auto_move_label' => array('type' => 'bool', 'default' => false, 'scope' => 'library', 'group' => 'interaction'),
            'popup_on' => array(
                'type' => 'enum',
                'default' => 'click',
                'choices' => array('click', 'hover'),
                'scope' => 'library',
                'group' => 'interaction',
            ),
            'hover_on_date' => array('type' => 'bool', 'default' => false, 'scope' => 'library', 'group' => 'interaction'),
            'show_expected_progress' => array('type' => 'bool', 'default' => false, 'scope' => 'library', 'group' => 'interaction'),

            // ---- Kanboard data mapping ----------------------------------
            'task_sort' => array(
                'type' => 'enum',
                'default' => 'board',
                'choices' => array('board', 'date'),
                'scope' => 'plugin',
                'group' => 'data',
            ),
            'show_subtasks' => array('type' => 'bool', 'default' => false, 'scope' => 'plugin', 'group' => 'data'),
            'show_closed_tasks' => array('type' => 'bool', 'default' => false, 'scope' => 'plugin', 'group' => 'data'),
            'dependency_links' => array(
                'type' => 'set',
                'default' => array('blocks', 'is blocked by', 'targets milestone', 'is a milestone of'),
                'choices' => array('blocks', 'is blocked by', 'targets milestone', 'is a milestone of', 'is a parent of', 'is a child of', 'fixes', 'is fixed by', 'duplicates', 'is duplicated by', 'relates to'),
                'scope' => 'plugin',
                'group' => 'data',
            ),
            'open_task_on' => array(
                'type' => 'enum',
                'default' => 'double_click',
                'choices' => array('double_click', 'click', 'never'),
                'scope' => 'plugin',
                'group' => 'data',
            ),
            'color_source' => array(
                'type' => 'enum',
                'default' => 'task',
                'choices' => array('task', 'category', 'none'),
                'scope' => 'plugin',
                'group' => 'data',
            ),
        );
    }

    /**
     * Resolved values for a project (or globally when $project_id is null).
     *
     * @param  int|null $project_id
     * @return array
     */
    public function getValues($project_id = null)
    {
        $values = $this->getGlobalValues();

        if ($project_id !== null && $this->isProjectOverriding($project_id)) {
            $values = $this->applyStored($values, $this->projectMetadataModel->getAll($project_id));
        }

        return $values;
    }

    /**
     * Global values: upstream defaults overlaid with the saved settings.
     *
     * @return array
     */
    public function getGlobalValues()
    {
        return $this->applyStored($this->getDefaults(), $this->configModel->getAll());
    }

    /**
     * Upstream defaults only.
     *
     * @return array
     */
    public function getDefaults()
    {
        $values = array();

        foreach (self::getDefinition() as $name => $definition) {
            $values[$name] = $definition['default'];
        }

        return $values;
    }

    /**
     * @param  int $project_id
     * @return bool
     */
    public function isProjectOverriding($project_id)
    {
        return (bool) $this->projectMetadataModel->get($project_id, self::OVERRIDE_KEY, 0);
    }

    /**
     * Overlay a flat key/value store (config or project metadata) on top of
     * the current values, decoding each entry according to its type.
     *
     * @param  array $values
     * @param  array $stored
     * @return array
     */
    private function applyStored(array $values, array $stored)
    {
        foreach (self::getDefinition() as $name => $definition) {
            $key = self::PREFIX.$name;

            if (! array_key_exists($key, $stored)) {
                continue;
            }

            if ($this->isUnset($definition, $stored[$key])) {
                continue;
            }

            $values[$name] = $this->decode($definition, $stored[$key]);
        }

        return $values;
    }

    /**
     * Whether a stored value means "never configured" rather than a real
     * setting, in which case the default has to stay in place.
     *
     * The distinction matters because an empty value is meaningful for some
     * options and meaningless for others: an empty "snap_at" really does mean
     * "use the view mode's own snapping", while an empty "scroll_to" is just
     * a missing setting and must not blank out the default.
     *
     * @param  array $definition
     * @param  mixed $raw
     * @return bool
     */
    private function isUnset(array $definition, $raw)
    {
        if ($raw !== '' && $raw !== null) {
            return false;
        }

        switch ($definition['type']) {
            case 'bool':
                // Stored as "0" or "1"; an empty value is not written.
                return true;
            case 'set':
                // An empty set is a real choice: nothing is selected.
                return false;
            case 'nullable_int':
                // Empty is exactly how "no explicit value" is expressed.
                return false;
            default:
                return empty($definition['allow_empty']);
        }
    }

    /**
     * Convert a stored string back to its typed value.
     *
     * @param  array  $definition
     * @param  string $raw
     * @return mixed
     */
    private function decode(array $definition, $raw)
    {
        switch ($definition['type']) {
            case 'bool':
                return (bool) (int) $raw;
            case 'int':
                return (int) $raw;
            case 'nullable_int':
                return $raw === '' || $raw === null ? null : (int) $raw;
            case 'set':
                $items = array_values(array_filter(array_map('trim', explode(',', (string) $raw)), 'strlen'));
                return array_values(array_intersect($items, $definition['choices']));
            case 'enum':
                return in_array($raw, $definition['choices'], true) ? $raw : $definition['default'];
            default:
                return (string) $raw;
        }
    }

    /**
     * Turn submitted form values into the flat, prefixed representation that
     * gets persisted. Unknown keys are dropped.
     *
     * @param  array $input
     * @return array
     */
    public function encodeForStorage(array $input)
    {
        $values = array();

        foreach (self::getDefinition() as $name => $definition) {
            $key = self::PREFIX.$name;

            switch ($definition['type']) {
                case 'bool':
                    $values[$key] = empty($input[$name]) ? '0' : '1';
                    break;
                case 'set':
                    $selected = isset($input[$name]) && is_array($input[$name]) ? $input[$name] : array();
                    $values[$key] = implode(',', array_intersect($selected, $definition['choices']));
                    break;
                case 'nullable_int':
                    $values[$key] = isset($input[$name]) && $input[$name] !== '' ? (string) (int) $input[$name] : '';
                    break;
                case 'int':
                    $values[$key] = isset($input[$name]) ? (string) (int) $input[$name] : (string) $definition['default'];
                    break;
                case 'enum':
                    $values[$key] = isset($input[$name]) && in_array($input[$name], $definition['choices'], true)
                        ? $input[$name]
                        : $definition['default'];
                    break;
                default:
                    $values[$key] = isset($input[$name]) ? (string) $input[$name] : '';
            }
        }

        return $values;
    }

    /**
     * Build the object handed to the Frappe Gantt constructor.
     *
     * Only options the library understands are emitted; everything the JS
     * bridge needs on top of that is namespaced under "kanboard".
     *
     * @param  array $values    resolved option values
     * @param  bool  $editable  whether the current user may write
     * @return array
     */
    public function buildLibraryOptions(array $values, $editable)
    {
        $options = array();

        foreach (self::getDefinition() as $name => $definition) {
            if ($definition['scope'] !== 'library') {
                continue;
            }

            $options[$name] = $values[$name];
        }

        // An empty string means "use the view mode's own snapping".
        if ($options['snap_at'] === '') {
            $options['snap_at'] = null;
        }

        // container_height is either the string "auto" or a pixel count.
        if ($options['container_height'] !== 'auto' && is_numeric($options['container_height'])) {
            $options['container_height'] = (int) $options['container_height'];
        }

        if (! $editable) {
            $options['readonly'] = true;
        }

        $options['language'] = $this->getLibraryLanguage();

        return $options;
    }

    /**
     * Settings the JS bridge consumes to build the option values that cannot
     * be expressed as JSON (the is_weekend, holidays and ignore callbacks) as
     * well as its own Kanboard-specific behaviour.
     *
     * @param  array $values
     * @return array
     */
    public function buildBridgeOptions(array $values)
    {
        return array(
            'weekend_days' => array_map('intval', $values['weekend_days']),
            'highlight_weekends' => $values['highlight_weekends'],
            'holiday_color' => $values['holiday_color'] !== '' ? $values['holiday_color'] : 'var(--g-weekend-highlight-color)',
            'holidays' => $this->parseDateList($values['holidays']),
            'ignore_weekends' => $values['ignore_weekends'],
            'ignore_dates' => array_column($this->parseDateList($values['ignore_dates']), 'date'),
            'enabled_view_modes' => $values['enabled_view_modes'],
            'open_task_on' => $values['open_task_on'],
        );
    }

    /**
     * Parse a textarea of "YYYY-MM-DD" or "YYYY-MM-DD: Label" lines.
     *
     * @param  string $text
     * @return array  list of array('date' => ..., 'label' => ...)
     */
    public function parseDateList($text)
    {
        $entries = array();

        foreach (preg_split('/[\r\n]+/', (string) $text) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = explode(':', $line, 2);
            $date = trim($parts[0]);

            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }

            $entries[] = array(
                'date' => $date,
                'label' => isset($parts[1]) ? trim($parts[1]) : '',
            );
        }

        return $entries;
    }

    /**
     * Map the Kanboard locale onto the ISO 639-1 code the library expects.
     *
     * @return string
     */
    public function getLibraryLanguage()
    {
        $language = $this->languageModel->getCurrentLanguage();

        return strtolower(substr($language, 0, 2));
    }
}
