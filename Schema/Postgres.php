<?php

namespace Kanboard\Plugin\FrappeGantt\Schema;

use PDO;

const VERSION = 1;

function version_1(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE frappegantt_task_progress (
            task_id INTEGER NOT NULL PRIMARY KEY,
            progress INTEGER NOT NULL DEFAULT 0,
            date_modification INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE
        )
    ");
}
