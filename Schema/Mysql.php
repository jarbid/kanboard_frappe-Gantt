<?php

namespace Kanboard\Plugin\FrappeGantt\Schema;

use PDO;

const VERSION = 1;

function version_1(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE frappegantt_task_progress (
            task_id INT NOT NULL,
            progress INT NOT NULL DEFAULT 0,
            date_modification INT NOT NULL DEFAULT 0,
            PRIMARY KEY(task_id),
            FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE
        ) ENGINE=InnoDB CHARSET=utf8
    ");
}
