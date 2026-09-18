<?php

namespace Kanboard\Plugin\FrappeGantt\Formatter;

use Kanboard\Core\Filter\FormatterInterface;
use Kanboard\Formatter\BaseFormatter;
use Kanboard\Model\LinkModel;
use Kanboard\Model\SubtaskModel;
use Kanboard\Model\TaskLinkModel;
use Kanboard\Model\TaskModel;

/**
 * Turn Kanboard tasks into Frappe Gantt bar definitions.
 */
class TaskGanttFormatter extends BaseFormatter implements FormatterInterface
{
    /**
     * How each link label positions the two tasks relative to each other.
     *
     * Kanboard stores task links in both directions, so reading a single
     * label already yields every arrow; the opposite label is mapped too so
     * that enabling either one on its own behaves identically.
     *
     * "forward"  => the opposite task depends on this task
     * "backward" => this task depends on the opposite task
     */
    const LINK_DIRECTIONS = array(
        'blocks' => 'forward',
        'is blocked by' => 'backward',
        'targets milestone' => 'forward',
        'is a milestone of' => 'backward',
        'is a parent of' => 'forward',
        'is a child of' => 'backward',
        'fixes' => 'forward',
        'is fixed by' => 'backward',
        'duplicates' => 'forward',
        'is duplicated by' => 'backward',
        'relates to' => 'forward',
    );

    /**
     * @var array Resolved plugin options.
     */
    private $options = array();

    /**
     * @var array Per-project column lists, cached across tasks.
     */
    private $columns = array();

    /**
     * @param  array $options
     * @return $this
     */
    public function withOptions(array $options)
    {
        $this->options = $options;
        return $this;
    }

    /**
     * @return array
     */
    public function format()
    {
        $tasks = $this->query->findAll();

        if (empty($tasks)) {
            return array();
        }

        $task_ids = array_map('intval', array_column($tasks, 'id'));
        $stored_progress = $this->ganttProgressModel->getMultiple($task_ids);
        $dependencies = $this->getDependencies($task_ids);
        $subtasks = empty($this->options['show_subtasks']) ? array() : $this->getSubtasks($task_ids);

        $bars = array();

        foreach ($tasks as $task) {
            $id = (int) $task['id'];
            $stored = isset($stored_progress[$id]) ? $stored_progress[$id] : null;
            $bar = $this->formatTask($task, $stored, isset($dependencies[$id]) ? $dependencies[$id] : array());
            $bars[] = $bar;

            if (isset($subtasks[$id])) {
                foreach ($subtasks[$id] as $subtask) {
                    $bars[] = $this->formatSubtask($subtask, $bar);
                }
            }
        }

        return $bars;
    }

    /**
     * @param  array $task
     * @param  int|null $stored_progress
     * @param  array $dependencies
     * @return array
     */
    private function formatTask(array $task, $stored_progress, array $dependencies)
    {
        $project_id = (int) $task['project_id'];

        if (! isset($this->columns[$project_id])) {
            $this->columns[$project_id] = $this->columnModel->getList($project_id);
        }

        // Frappe Gantt always needs a range. Tasks missing one or both dates
        // are given a one-day bar around the known date (or today) and are
        // flagged so the chart can render them differently and so that the
        // bridge knows both endpoints are still unset.
        $has_start = ! empty($task['date_started']);
        $has_due = ! empty($task['date_due']);

        if ($has_start && $has_due) {
            $start = (int) $task['date_started'];
            $end = (int) $task['date_due'];
        } elseif ($has_start) {
            $start = (int) $task['date_started'];
            $end = $start;
        } elseif ($has_due) {
            $end = (int) $task['date_due'];
            $start = $end;
        } else {
            $start = time();
            $end = $start;
        }

        if ($end < $start) {
            $end = $start;
        }

        $color = $this->getColor($task);

        // The library feeds custom_class straight into classList.add(), which
        // rejects anything containing a space, so it carries the bar's type
        // alone. State modifiers travel separately and are applied by the
        // JavaScript bridge once the bars are in the DOM.
        $classes = array();

        if (! $has_start || ! $has_due) {
            $classes[] = 'kb-gantt-undated';
        }

        if ((int) $task['is_active'] === TaskModel::STATUS_CLOSED) {
            $classes[] = 'kb-gantt-closed';
        }

        if (! empty($task['is_milestone'])) {
            $classes[] = 'kb-gantt-milestone';
        }

        return array(
            'id' => 'task-'.$task['id'],
            'name' => $this->escapeLabel('#'.$task['id'].' '.$task['title']),
            'start' => date('Y-m-d', $start),
            'end' => date('Y-m-d', $end),
            'progress' => $this->ganttProgressModel->resolve($task, $this->columns[$project_id], $stored_progress),
            'dependencies' => array_values($dependencies),
            'custom_class' => 'kb-gantt-task',
            'color' => $color['background'],
            'color_progress' => $color['border'],
            'description' => $this->getDescription($task),
            'kb' => array(
                'type' => 'task',
                'classes' => $classes,
                'task_id' => (int) $task['id'],
                'project_id' => $project_id,
                'title' => $task['title'],
                'url' => $this->helper->url->href('TaskViewController', 'show', array(
                    'project_id' => $project_id,
                    'task_id' => $task['id'],
                )),
                'column' => $task['column_name'],
                'swimlane' => isset($task['swimlane_name']) ? $task['swimlane_name'] : '',
                'category' => isset($task['category_name']) ? $task['category_name'] : '',
                'project' => isset($task['project_name']) ? $task['project_name'] : '',
                'assignee' => $task['assignee_name'] ?: $task['assignee_username'],
                'is_closed' => (int) $task['is_active'] === TaskModel::STATUS_CLOSED,
                'has_start' => $has_start,
                'has_due' => $has_due,
                'time_estimated' => (float) $task['time_estimated'],
                'time_spent' => (float) $task['time_spent'],
                'priority' => (int) $task['priority'],
                'editable' => true,
            ),
        );
    }

    /**
     * Subtasks carry no dates in Kanboard, so they are drawn inside their
     * parent's range as read-only child rows.
     *
     * @param  array $subtask
     * @param  array $parent_bar
     * @return array
     */
    private function formatSubtask(array $subtask, array $parent_bar)
    {
        $progress = 0;

        if ((int) $subtask['status'] === SubtaskModel::STATUS_DONE) {
            $progress = 100;
        } elseif ((int) $subtask['status'] === SubtaskModel::STATUS_INPROGRESS) {
            $progress = 50;
        }

        return array(
            'id' => 'subtask-'.$subtask['id'],
            'name' => $this->escapeLabel('↳ '.$subtask['title']),
            'start' => $parent_bar['start'],
            'end' => $parent_bar['end'],
            'progress' => $progress,
            'dependencies' => array(),
            'custom_class' => 'kb-gantt-subtask',
            'color' => $parent_bar['color'],
            'color_progress' => $parent_bar['color_progress'],
            'description' => '',
            'kb' => array(
                'type' => 'subtask',
                'classes' => array(),
                'subtask_id' => (int) $subtask['id'],
                'task_id' => $parent_bar['kb']['task_id'],
                'project_id' => $parent_bar['kb']['project_id'],
                'title' => $subtask['title'],
                'url' => $parent_bar['kb']['url'],
                'assignee' => isset($subtask['name']) && $subtask['name'] ? $subtask['name'] : (isset($subtask['username']) ? $subtask['username'] : ''),
                'time_estimated' => (float) $subtask['time_estimated'],
                'time_spent' => (float) $subtask['time_spent'],
                'status' => (int) $subtask['status'],
                // Subtasks have no dates of their own to write back to.
                'editable' => false,
            ),
        );
    }

    /**
     * Build the dependency map: task id => list of bar ids it depends on.
     *
     * Only links between tasks that are both on the chart are kept, so the
     * library never points an arrow at a bar that was filtered out.
     *
     * @param  int[] $task_ids
     * @return array
     */
    private function getDependencies(array $task_ids)
    {
        $labels = isset($this->options['dependency_links']) ? $this->options['dependency_links'] : array();

        if (empty($labels) || empty($task_ids)) {
            return array();
        }

        $rows = $this->db
            ->table(TaskLinkModel::TABLE)
            ->columns(
                TaskLinkModel::TABLE.'.task_id',
                TaskLinkModel::TABLE.'.opposite_task_id',
                LinkModel::TABLE.'.label'
            )
            ->join(LinkModel::TABLE, 'id', 'link_id', TaskLinkModel::TABLE)
            ->in(TaskLinkModel::TABLE.'.task_id', $task_ids)
            ->in(TaskLinkModel::TABLE.'.opposite_task_id', $task_ids)
            ->in(LinkModel::TABLE.'.label', $labels)
            ->findAll();

        $visible = array_flip($task_ids);
        $dependencies = array();

        foreach ($rows as $row) {
            $label = $row['label'];

            if (! isset(self::LINK_DIRECTIONS[$label])) {
                continue;
            }

            $task_id = (int) $row['task_id'];
            $opposite_id = (int) $row['opposite_task_id'];

            if (! isset($visible[$task_id]) || ! isset($visible[$opposite_id]) || $task_id === $opposite_id) {
                continue;
            }

            if (self::LINK_DIRECTIONS[$label] === 'forward') {
                $dependent = $opposite_id;
                $predecessor = $task_id;
            } else {
                $dependent = $task_id;
                $predecessor = $opposite_id;
            }

            // Keyed by predecessor so the mirrored row collapses into one arrow.
            $dependencies[$dependent]['task-'.$predecessor] = 'task-'.$predecessor;
        }

        return $dependencies;
    }

    /**
     * All subtasks of the given tasks, fetched in one query and grouped by
     * their parent task id.
     *
     * @param  int[] $task_ids
     * @return array
     */
    private function getSubtasks(array $task_ids)
    {
        $grouped = array();

        foreach ($this->subtaskModel->getAllByTaskIds($task_ids) as $subtask) {
            $grouped[(int) $subtask['task_id']][] = $subtask;
        }

        return $grouped;
    }

    /**
     * @param  array $task
     * @return array background/border color pair
     */
    private function getColor(array $task)
    {
        $source = isset($this->options['color_source']) ? $this->options['color_source'] : 'task';

        if ($source === 'none') {
            return array('background' => '', 'border' => '');
        }

        $color_id = $task['color_id'];

        if ($source === 'category' && ! empty($task['category_color_id'])) {
            $color_id = $task['category_color_id'];
        }

        $properties = $this->colorModel->getColorProperties($color_id);

        return array(
            'background' => $properties['background'],
            'border' => $properties['border'],
        );
    }

    /**
     * Escape a bar label.
     *
     * Frappe Gantt writes task.name into the label element with innerHTML, so
     * an unescaped title would inject markup into the chart. Escaping here
     * makes the library render the title as the literal text it is.
     *
     * @param  string $label
     * @return string
     */
    private function escapeLabel($label)
    {
        return htmlspecialchars($label, ENT_QUOTES, 'UTF-8', false);
    }

    /**
     * Short plain-text excerpt of the task description for the popup.
     *
     * @param  array $task
     * @return string
     */
    private function getDescription(array $task)
    {
        $description = trim((string) $task['description']);

        if ($description === '') {
            return '';
        }

        $description = preg_replace('/\s+/', ' ', strip_tags($description));

        if (mb_strlen($description) > 200) {
            $description = mb_substr($description, 0, 200).'…';
        }

        return $description;
    }
}
