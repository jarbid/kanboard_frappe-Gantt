<?php

namespace Kanboard\Plugin\FrappeGantt\Schema;

use PDO;

const VERSION = 1;

function version_1(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE dbo.frappegantt_task_progress (
            task_id int NOT NULL
          , progress int DEFAULT 0 NOT NULL
          , date_modification int DEFAULT 0 NOT NULL
          , PRIMARY KEY(task_id)
          , FOREIGN KEY(task_id) REFERENCES dbo.tasks(id) ON DELETE CASCADE
        );
    ");
}
