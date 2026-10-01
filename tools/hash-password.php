<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("This script can only be run from the command line.\n");
}

fwrite(STDOUT, "Password: ");
$password = fgets(STDIN);
$password = $password === false ? '' : rtrim($password, "\r\n");

if ($password === '') {
    fwrite(STDERR, "Password cannot be empty.\n");
    exit(1);
}

fwrite(STDOUT, password_hash($password, PASSWORD_DEFAULT) . PHP_EOL);