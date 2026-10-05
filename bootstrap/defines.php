<?php

define('__ROOT_DIR__', realpath(dirname(__DIR__)));

error_reporting(E_ALL);

// Hide errors until the environment is known, they are enabled again in development by bootstrap/app.php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
