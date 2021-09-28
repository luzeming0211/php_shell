<?php

$name = 'test';
$params = array(
    'key1' => 1,
    'key2' => 2,
);
JobClientFacade::doJob($name, $params);