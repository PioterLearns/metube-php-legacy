<?php

use JetBrains\PhpStorm\NoReturn;

function printLn(string $message = ""): void
{
    echo $message . PHP_EOL;
}

#[NoReturn] function dd(string $message): void
{
    die($message . PHP_EOL);
}

#[NoReturn] function vd($var): void
{
    var_dump($var);
    die();
}