<?php

class JobClientFacade extends BaseFacade
{
    public static function doJob($name, $params = array())
    {
        if (TANGO_ENV == 'production') {
            $jobFileRoot = '/var/www/web_api/current/jobs';
        } else {
            $jobFileRoot = '/var/www/web_api/jobs';
        }

        $jobFile = $jobFileRoot . '/' . $name . '.php';

        if (!file_exists($jobFile)) {
            ld('找不到任务对应的执行脚本: ' . $jobFile);
            return false;
        }

        $jobId = JobFacade::init($name, $params);
        $jobFile = $jobFileRoot . '/' . $name . '.php';
        $cmd = join(array('php', $jobFile, '--job ' . $jobId), ' ');
        $cmd = $cmd . ' ' . '> /dev/null 2>/dev/null &';
        shell_exec($cmd);
    }
}
