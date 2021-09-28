<?php
class JobFacade extends BaseFacade {
    private $id;
    private $name;
    private $startAt;
    private $pid;
    private $status;
    private $params;
    private $error;
    private $process;

    const STATUS_INIT = 'init';
    const STATUS_RUNNING = 'running';
    const STATUS_ABORT = 'abort';
    const STATUS_PAUSE = 'pause';
    const STATUS_DONE = 'done';

    public static function all() {
        // $redis = App::get('redis');
        // $jobs = $redis->getAll('job');
        $jobs = RDManager::getCache('job');
        return $jobs;
    }

    public static function getInfo($jobId) {
        // $redis = App::get('redis');
        // return $redis->get('job', $jobId);
        $jobs = RDManager::getCache('job', $jobId);
        return $jobs;
    }

    public static function genId() {
        return time() . rand(1000, 9999);
    }

    public static function logPrefix($id, $name) {
        return '[id: ' . $id . '] ' . $name . ' ';
    }

    public static function init($name, $params) {
        $id = self::genId();
        $jobInfo = array(
            'id' => $id,
            'name' => $name,
            'status' => self::STATUS_INIT,
            'error' => '',
            'params' => $params
        );

        // $redis = App::get('redis');
        // $redis->set('job', $id, $jobInfo);

        RDManager::setCache('job', $id, $jobInfo);

        ld(self::logPrefix($id, $name) . '任务初始化');

        return $id;
    }

    public static function create($jobProcess) {
        $optParams = getopt(null, array("job:"));

        if (empty($optParams['job'])) {
            Log::error('参数中需要传入jobid');
            return false;
        }
        $jobId = $optParams['job'];
        new JobFacade($jobId, $jobProcess);
    }

    public static function stop($id) {
        // $redis = App::get('redis');
        // $jobInfo = $redis->get('job', $id);
        $jobInfo = RDManager::getCache('job', $id);
        if (empty($jobInfo)) {
            return false;
        }

        if (empty($jobInfo['pid'])) {
            // $redis->del('job', $id);
            RDManager::removeCache('job', $id);
            return;
        }

        $pid = $jobInfo['pid'];
        posix_kill($pid, defined('SIGTERM') ? SIGTERM : 15);
        sleep(1);

        if (!posix_getpgid($pid)) {
            // $redis->del('job', $id);
            RDManager::removeCache('job', $id);
            ld(self::logPrefix($jobInfo['id'], $jobInfo['name']) . '任务停止运行');
        }
    }    

    public function __construct($jobId, $process) {
        $this->id = $jobId;

        // $redis = App::get('redis');
        // $jobInfo = $redis->get('job', $this->id);
        $jobInfo = RDManager::getCache('job',  $this->id);
        if (empty($jobInfo)) {
            return false;
        }
        
        $this->name = $jobInfo['name'];
        $this->status = $jobInfo['status'];
        $this->error = $jobInfo['error'];
        $this->params = $jobInfo['params'];

        $this->startAt = time();
        $this->pid = getmypid();
        $this->process = $process;

        $this->run();
    }

    public function run() {
        $this->log('任务开始运行 ...');
        $this->setStatus(self::STATUS_RUNNING);
        $fn = $this->process;

        if (!is_callable($fn)) {
            return false;
        }
        
        $obj = $this;
        register_shutdown_function(function() use ($obj) {
            $error = error_get_last();
            if ($error !== NULL && !in_array($error['type'], array(E_NOTICE, E_USER_NOTICE, E_WARNING, E_USER_WARNING))) {
                $obj->abort($error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']);
            }
        });

        try {
            $fn($this);
            $this->done();
        } catch (Exception $e) {
            $this->abort($e->getMessage());
        }
    }

    public function currentInfo() {
        return array(
            'id' => $this->id,
            'pid' => $this->pid,
            'name' => $this->name,
            'status' => $this->status,
            'error' => $this->error,
            'params' => $this->params,
        );
    }

    public function sync($jobInfo) {
        // $redis = App::get('redis');
        // $redis->set('job', $this->id, $jobInfo);
        RDManager::setCache('job',  $this->id, $jobInfo);
    }

    public function setStatus($status) {
        $this->status = $status;

        $jobInfo = $this->currentInfo();
        $this->sync($jobInfo);
    }

    public function log($msg) {
        ld(self::logPrefix($this->id, $this->name) . $msg);
    }

    public function id() {
        return $this->id;
    }

    public function status() {
        return $this->status;
    }

    public function name() {
        return $this->name;
    }

    public function pid() {
        return $this->pid;
    }

    public function params() {
        return $this->params;
    }

    public function error() {
        return $this->error;
    }    

    public function abort($msg) {
        $this->log('任务异常终止: ' . $msg);
        $this->status = self::STATUS_ABORT;
        $this->pid = '';
        $this->error = $msg;

        $jobInfo = $this->currentInfo();
        $this->sync($jobInfo);
        die();
    }

    public function done() {
        // $redis = App::get('redis');
        // $redis->del('job', $this->id);
        RDManager::removeCache('job', $this->id);
        $time = time() - $this->startAt;
        $this->log('任务结束, 耗时 ' . $time . ' 秒');
    }
}
