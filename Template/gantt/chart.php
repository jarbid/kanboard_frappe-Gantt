<?php
/**
 * Shared chart container.
 *
 * The whole configuration travels in a data attribute: Kanboard's default
 * Content-Security-Policy is "default-src 'self'", which forbids inline
 * scripts, so there is nowhere else to put it.
 *
 * @var array  $chart
 * @var string $scope  identifies the chart for the stored view-mode preference
 */

// htmlspecialchars is called directly rather than through $this->text->e()
// because that helper passes double_encode = false. The payload legitimately
// contains HTML entities (task labels are pre-escaped for the library, which
// renders them with innerHTML), and leaving those un-encoded would let a
// "&quot;" inside the JSON close the attribute early.
$config = htmlspecialchars(
    json_encode($chart, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
?>
<div class="kb-gantt-chart"
     data-gantt-scope="<?= htmlspecialchars($scope, ENT_QUOTES, 'UTF-8') ?>"
     data-gantt-config="<?= $config ?>">
    <div class="kb-gantt-target"></div>
</div>

<?= $this->asset->js('plugins/FrappeGantt/Assets/vendor/frappe-gantt.umd.js') ?>
<?= $this->asset->js('plugins/FrappeGantt/Assets/kanboard-gantt.js') ?>
