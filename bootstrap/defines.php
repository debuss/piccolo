<?php

define('__ROOT_DIR__', realpath(dirname(__DIR__)));

// Reasonable PHP error_reporting setting that includes warnings and fatal errors
error_reporting(E_ALL & ~E_USER_DEPRECATED & ~E_DEPRECATED & ~E_NOTICE);
