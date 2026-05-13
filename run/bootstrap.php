<?php
require __DIR__ . '/../vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$connectionParams = [
    'dbname' => $_ENV['DB_NAME'],
    'user' => $_ENV['DB_USER'],
    'password' => $_ENV['DB_PASS'],
    'host' => $_ENV['DB_HOST'],
    'driver' => $_ENV['DB_DRIVER'],
];
$conn = DriverManager::getConnection($connectionParams);


const ROUTINE_DAILY = 1;
const ROUTINE_WEEKLY = 2;
const ROUTINE_MONTHLY = 3;
const ROUTINE_YEARLY = 4;
const ROUTINE_TYPES = [ROUTINE_DAILY, ROUTINE_WEEKLY, ROUTINE_MONTHLY, ROUTINE_YEARLY];
