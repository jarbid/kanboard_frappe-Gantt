<?php

/*
 * The "dblib" driver connects to SQL Server, so it shares the Mssql schema.
 * Kanboard picks the schema file from ucfirst(DB_DRIVER), which is why this
 * alias has to exist as its own file.
 */

require_once __DIR__.'/Mssql.php';
