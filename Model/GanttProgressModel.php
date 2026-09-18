<?php

namespace Kanboard\Plugin\FrappeGantt\Model;

use Kanboard\Core\Base;
use Kanboard\Model\TaskModel;

/**
 * Per-task completion percentage.
 *
 * Kanboard has no percent-complete field: its notion of progress is derived
 * from the position of the task's column on the board. Frappe Gantt however
 * lets the user drag a progress handle, so the plugin keeps its own value and
 * falls back to the column-derived one for tasks that were never dragged.
 */
class GanttProgressModel extends Base
{
    const TABLE = 'frappegantt_task_progress';

    /**
     * Get the stored progress for a set of tasks, indexed by task id.
     *
     * @param  int[] $task_ids
     * @return array
     */
    public function getMultiple(array $task_ids)
    {
        if (empty($task_ids)) {
            return array();
        }

        $rows = $this->db
            ->table(self::TABLE)
            ->in('task_id', $task_ids)
            ->columns('task_id', 'progress')
            ->findAll();

        $result = array();

        foreach ($rows as $row) {
            $result[(int) $row['task_id']] = (int) $row['progress'];
        }

        return $result;
    }

    /**
     * Get the stored progress for one task, or null when never set.
     *
     * @param  int $task_id
     * @return int|null
     */
    public function get($task_id)
    {
        $value = $this->db
            ->table(self::TABLE)
            ->eq('task_id', $task_id)
            ->findOneColumn('progress');

        return $value === null || $value === false ? null : (int) $value;
    }

    /**
     * Store the progress of a task.
     *
     * @param  int $task_id
     * @param  int $progress  0-100, clamped
     * @return bool
     */
    public function save($task_id, $progress)
    {
        $progress = max(0, min(100, (int) $progress));

        $values = array(
            'task_id' => (int) $task_id,
            'progress' => $progress,
            'date_modification' => time(),
        );

        if ($this->db->table(self::TABLE)->eq('task_id', $task_id)->exists()) {
            return $this->db
                ->table(self::TABLE)
                ->eq('task_id', $task_id)
                ->update(array(
                    'progress' => $progress,
                    'date_modification' => $values['date_modification'],
                ));
        }

        return $this->db->table(self::TABLE)->insert($values);
    }

    /**
     * Forget the stored progress, which re-enables the column-derived value.
     *
     * @param  int $task_id
     * @return bool
     */
    public function remove($task_id)
    {
        return $this->db->table(self::TABLE)->eq('task_id', $task_id)->remove();
    }

    /**
     * Resolve the progress to display for a task.
     *
     * Closed tasks are always complete. Otherwise the stored value wins, and
     * the column position is used when there is none.
     *
     * @param  array    $task
     * @param  array    $columns   column_id => title, for the task's project
     * @param  int|null $stored
     * @return float
     */
    public function resolve(array $task, array $columns, $stored = null)
    {
        if (isset($task['is_active']) && (int) $task['is_active'] === TaskModel::STATUS_CLOSED) {
            return 100.0;
        }

        if ($stored !== null) {
            return (float) $stored;
        }

        return (float) $this->taskModel->getProgress($task, $columns);
    }
}
