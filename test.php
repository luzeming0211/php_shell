<?php
ld('执行了1');
JobFacade::create(function($job) {
    ld('执行了2');
    $params = $job->params();
    ld('执行了3');
    ld($params);
    sleep(5);
});
ld('执行了4');