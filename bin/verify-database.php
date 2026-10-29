<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = DB::connection();
$pdo = $connection->getPdo();

printf("Driver: %s\n", $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
printf("Server version: %s\n", $pdo->getAttribute(PDO::ATTR_SERVER_VERSION));
printf("Database: %s\n", $connection->getDatabaseName());
