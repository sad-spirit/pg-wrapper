<?php

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('UTC');

define(
    'TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING',
    'host=localhost user=postgres password=postgres dbname=pgwrapper_test'
);
