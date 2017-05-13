<?php

namespace deploydog\Slavedriver;

use deploydog\Slavedriver\Exception\CannotGetLock;
use deploydog\Slavedriver\Exception\InvalidJob;
use deploydog\Slavedriver\Exception\InvalidSlavedriverConfig;
use deploydog\Slavedriver\Exception\UnsupportedOS;
use deploydog\Slavedriver\Exception\VarDirUnusable;
use malkusch\lock\exception\LockAcquireException;
use malkusch\lock\mutex\FlockMutex;
use Phossa2\Event\EventDispatcher;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\SimpleCache\CacheInterface;

class Slavedriver {
    /** @var Job[] */
    private $jobs = [];
    private $slaveName = null;
    private $varDir = null;

    /** @var null|CacheInterface */
    private $cacheInterface = null;

    /** @var null|LoggerInterface */
    private $logger = null;

    /** @var LogLevels */
    private $logLevels = null;

    /** @var EventDispatcher */
    private $eventsDispatcher = null;

    /** @var RunningJob[] */
    private $runningJobs = [];

    /** @var float */
    private $startTime = null;

    const EVENT_SLAVEDRIVER_STARTED = 'slavedriver.started';
    const EVENT_JOB_STARTED = 'slavedriver.job.started';
    const EVENT_JOB_FINISHED_SUCCESS = 'slavedriver.job.finished.success';
    const EVENT_JOB_FINISHED_ERROR = 'slavedriver.job.finished.error';
    const EVENT_JOB_OUTPUT_STDOUT = 'slavedriver.job.output.stdout';
    const EVENT_JOB_OUTPUT_STDERR = 'slavedriver.job.output.stderr';
    const EVENT_JOB_TIMEOUT = 'slavedriver.job.timeout';
    const EVENT_JOB_LAST_INSTANCE_STILL_RUNNING = 'slavedriver.job.last_instance_still_running';
    const EVENT_JOB_EXPECTED_RUNTIME_ELAPSED = 'slavedriver.job.expected_runtime_elapsed';

    const RUNTIME_SECONDS = 60; // This should not be changed without considering that jobs have to-the-minute runtimes.
    const END_LEEWAY_SECONDS = 3;

    const FILENAME_EXIT_CODE = 'exit';
    const FILENAME_STDOUT = 'stdout';
    const FILENAME_STDERR = 'stderr';

    private $monitoringSleepInterval = 3;

    /**
     * Slavedriver constructor.
     * @param CacheInterface $cacheInterface
     * @param EventDispatcher $eventsDispatcher
     * @param string $varDir A directory that Slavedriver can use for write files it needs for it's normal function
     * @throws UnsupportedOS
     * @throws VarDirUnusable
     */
    public function __construct(CacheInterface $cacheInterface, EventDispatcher $eventsDispatcher, $varDir = '/tmp/dd.slavedriver') {
        $this->cacheInterface = $cacheInterface;
        $this->eventsDispatcher = $eventsDispatcher;
        $this->startTime = microtime(true);
        $this->varDir = rtrim($varDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->logLevels = new LogLevels(); // This is a default

        // Throw exception if not on unix based OS
        if (!in_array(strtoupper(PHP_OS), ['LINUX', 'FREEBSD', 'DARWIN'])){
            throw new UnsupportedOS('Your OS ('.PHP_OS.') is not supported by Slavedriver. You need to be on a unix based OS.');
        }

        // Ensure we can use the varDir
        if (!file_exists($this->varDir)){
            if (!mkdir($this->varDir, 0777, true)){
                throw new VarDirUnusable('Cannot create var dir.');
            }
        } else {
            if (!is_dir($this->varDir) || !is_writeable($this->varDir)){
                throw new VarDirUnusable('Var dir is not a writeable directory.');
            }
        }
    }

    /**
     * @param LoggerInterface $logger
     * @param LogLevels|null $logLevels
     * @return $this
     * @throws InvalidSlavedriverConfig
     */
    public function setLogger(LoggerInterface $logger, $logLevels = null){
        $this->logger = $logger;

        if ($logLevels instanceof LogLevels) {
            $this->logLevels = $logLevels;
        } elseif (!is_null($logLevels)){
            throw new InvalidSlavedriverConfig('You can use the default logLevels but, if you specifiy something custom, it must be an instance of Slavedriver/LogLevels.');
        }

        return $this;
    }

    /**
     * @param $slaveName
     * @return $this
     * @throws InvalidJob
     */
    public function setSlaveName($slaveName){
        if (is_callable($slaveName)){
            $this->slaveName = $slaveName();
            return $this;
        }

        if (is_string($slaveName)){
            $this->slaveName = $slaveName;
            return $this;
        }

        throw new InvalidJob('The slave name must be either a string or a callable.');
    }

    /**
     * @param Job $job
     * @return $this
     * @throws InvalidJob
     */
    public function addJob(Job $job){
        // Ensure job name is unique
        foreach ($this->jobs as $existingJob){
            if ($existingJob->getName() == $job->getName()){
                throw new InvalidJob('A job with the name "'.$existingJob->getName().'" already exists. All jobs must have unique names.');
            }
        }

        $this->jobs[] = $job;
        return $this;
    }

    /**
     * Looks in the directory given recursively for files  ending with ".job.php" and adds their jobs
     *
     * @param $directory
     */
    public function addAllJobsInDirectory($directory){
        // TODO: Implement adding all jobs in a directory
    }

    public function run(){
        // Get lock
        $mutex = new FlockMutex(fopen(__FILE__, 'r'));
        try {
            $mutex->synchronized(
                function () {
                    // Ensure we have a slave name
                    $this->ensureSlaveNameSet();

                    // Event that Slavedriver itself is running
                    $this->eventsDispatcher->trigger(self::EVENT_SLAVEDRIVER_STARTED);

                    // Load in running jobs
                    $this->loadRunningJobs();

                    // Start required jobs
                    $this->startAllRequiredJobs();

                    // Monitor progress of jobs
                    $this->monitorProgressOfJobs();
                }
            );
        } catch (LockAcquireException $e){
            throw new CannotGetLock($e->getMessage());
        }
    }

    private function startAllRequiredJobs(){
        $this->log(LogLevel::DEBUG, 'Slavedriver has been given ' . count($this->jobs) . ' job(s) to manage.');

        foreach ($this->jobs as $job) {
            // Determine if job needs to run at this time on this slave
            if ($job->needsToRun($this->slaveName)) {
                // Ensure that we can run this
                if ($job->allowRunningIfPreviousStillRunning()) {
                    $this->startProcess($job);
                } else {
                    if ($this->isJobStillRunningFromBefore($job)) {
                        $this->log(
                            $this->logLevels->getCannotStartNextAsLastStillRunning(),
                            JobLogMessage::init($job, 'Cannot start next instance as the last is still running.')
                        );

                        // Trigger event
                        $this->eventsDispatcher->trigger(self::EVENT_JOB_LAST_INSTANCE_STILL_RUNNING, $job);
                    } else {
                        $this->startProcess($job);
                    }
                }
            } else {
                $this->log(LogLevel::DEBUG, 'Job '.$job->getName().' does not need to run now on slave '.$this->slaveName.'.');
            }
        }
    }

    private function monitorProgressOfJobs(){
        $endTime = $this->startTime + self::RUNTIME_SECONDS - self::END_LEEWAY_SECONDS;
        while (microtime(true) < $endTime){
            // Check on outputs of jobs, which jobs are still running, exit codes, etc
            foreach ($this->runningJobs as $runningJobKey => $runningJob){
                // Debug Logging
                $this->log(LogLevel::DEBUG, 'Checking status of running job ' . $runningJob->getJob()->getName());

                // Read stdout
                $stdOut = $this->readRemainderOfFile($runningJob->getProcessFilesDir().self::FILENAME_STDOUT, $runningJob->getStdOutReadSoFar());
                $bytesRead = mb_strlen($stdOut, '8bit');
                if ($bytesRead > 0) {
                    $runningJob->addToStdoutReadSoFar($bytesRead);
                    $this->eventsDispatcher->trigger(self::EVENT_JOB_OUTPUT_STDOUT, $runningJob->getJob(), ['stdOut' => $stdOut]);
                }
                unset($stdOut); // Free up memory

                // Read stderr
                $stdErr = $this->readRemainderOfFile($runningJob->getProcessFilesDir().self::FILENAME_STDERR, $runningJob->getStdErrReadSoFar());
                $bytesRead = mb_strlen($stdErr, '8bit');
                if ($bytesRead > 0) {
                    $runningJob->addToStderrReadSoFar($bytesRead);
                    $this->eventsDispatcher->trigger(self::EVENT_JOB_OUTPUT_STDERR, $runningJob->getJob(), ['stdErr' => $stdErr]);
                }
                unset($stdErr); // Free up memory

                // Check if still running
                if (!$runningJob->getProcess()->isRunning()){
                    // Get things about job
                    $jobRunTime = microtime(true) - $runningJob->getStartTs();
//                    $jobExitCode = (int)shell_exec('wait '.$runningJob->getProcess()->getPid() .' && echo $?');

                    if (file_exists($runningJob->getProcessFilesDir().self::FILENAME_EXIT_CODE)){
                        $jobExitCode = @file_get_contents($runningJob->getProcessFilesDir().self::FILENAME_EXIT_CODE);
                        if ($jobExitCode === false){
                            $jobExitCode = null;
                        } else {
                            $jobExitCode = (int)$jobExitCode;
                        }
                    } else {
                        $jobExitCode = null;
                    }

                    // Log finished
                    $this->log(
                        $this->logLevels->getJobStartingAndFinishing(),
                        JobLogMessage::init($runningJob->getJob(), 'Job finished with exit code '.(is_null($jobExitCode) ? '?' : $jobExitCode).'. It ran for approximately ' . $this->secondsToTime($jobRunTime).'.')
                    );

                    // If error exit code, log that too
                    if ($jobExitCode !== 0){
                        if (is_null($jobExitCode)){
                            $exitMessage = JobLogMessage::init($runningJob->getJob(), 'Slavedriver was unable to determine exit code, sorry. The job ran for approximately ' . $this->secondsToTime($jobRunTime).'.');
                        } else {
                            $exitMessage = JobLogMessage::init($runningJob->getJob(), 'Job finished with exit code '.$jobExitCode.'. It ran for approximately ' . $this->secondsToTime($jobRunTime).'.');
                        }

                        $this->log(
                            $this->logLevels->getErrorExitCode(),
                            $exitMessage
                        );
                        unset($exitMessage);

                        // Trigger finished event
                        $this->eventsDispatcher->trigger(self::EVENT_JOB_FINISHED_ERROR, $runningJob->getJob(), ['exitCode' => $jobExitCode]);
                    } else {
                        // Trigger finished event
                        $this->eventsDispatcher->trigger(self::EVENT_JOB_FINISHED_SUCCESS, $runningJob->getJob());
                    }

                    // Remove from the runningJobs as it is finished now
                    unset($this->runningJobs[$runningJobKey]);
                    $this->saveRunningJobs();

                    // Cleanup directory
                    $files_to_delete = [
                        $runningJob->getProcessFilesDir().self::FILENAME_EXIT_CODE,
                        $runningJob->getProcessFilesDir().self::FILENAME_STDOUT,
                        $runningJob->getProcessFilesDir().self::FILENAME_STDERR,
                    ];
                    foreach ($files_to_delete as $f){
                        if (file_exists($f)){
                            @unlink($f);
                        }
                    }
                    if (file_exists($runningJob->getProcessFilesDir()) && is_dir($runningJob->getProcessFilesDir())) {
                        rmdir($runningJob->getProcessFilesDir());
                    }

                } else {
                    // Job is still running

                    // Check if it has hit it's timeout
                    if (!is_null($runningJob->getJob()->getTimeout()) && ($runningJob->getStartTs() + $runningJob->getJob()->getTimeout()) < time()){
                        $runningJob->getProcess()->stop();

                        // Log
                        $this->log(
                            $this->logLevels->getNeedingToBeKilled(),
                            JobLogMessage::init($runningJob->getJob(), 'Job hit timeout and was killed.')
                        );

                        // Trigger event
                        $this->eventsDispatcher->trigger(self::EVENT_JOB_TIMEOUT, $runningJob->getJob());

                    } else if (!$runningJob->haveAlreadyWarnedNotFinishedWhenExpected() && !is_null($runningJob->getJob()->getWarnIfNotFinishedAfterSeconds()) && ($runningJob->getStartTs() + $runningJob->getJob()->getWarnIfNotFinishedAfterSeconds()) < time()){
                        // Not hit timeout yet, but is taking too long
                        $runningJob->setAlreadyWarnedNotFinishedWhenExpected();

                        // Log
                        $this->log(
                            $this->logLevels->getNotFinishedWhenExpected(),
                            JobLogMessage::init($runningJob->getJob(), 'Job is still running but was expected to have finished by now.')
                        );

                        // Trigger event
                        $this->eventsDispatcher->trigger(self::EVENT_JOB_EXPECTED_RUNTIME_ELAPSED, $runningJob->getJob());
                    }
                }
            }

            // If no more running jobs, exit now
            if (count($this->runningJobs) == 0){
                break;
            }

            // Sleep for the monitoring interval
            if (!is_null($this->monitoringSleepInterval)){
                if (microtime(true) + $this->monitoringSleepInterval < $endTime) {
                    sleep($this->monitoringSleepInterval);
                }
            }
        }
    }

    private function readRemainderOfFile($file, $offset){
        return file_get_contents($file, null, null, $offset);
    }

    private function ensureSlaveNameSet(){
        // Ensure we have a slave name
        if (!is_null($this->slaveName)){
            return;
        }

        // Look for environment var
        $slaveNameFromEnv = getenv('SLAVEDRIVER_SLAVE_NAME', true) ?: getenv('SLAVEDRIVER_SLAVE_NAME');
        if ($slaveNameFromEnv !== false){
            $this->slaveName = $slaveNameFromEnv;
            return;
        }

        // Look for hostname
        $slaveNameFromHostname = gethostname();
        if ($slaveNameFromHostname !== false){
            $this->slaveName = $slaveNameFromHostname;
            return;
        }

        // Still not got one, log and throw exception!
        $message = 'Unable to determine the slaveName for this host.';
        $this->log($this->logLevels->getInvalidConfig(), $message);
        throw new InvalidSlavedriverConfig($message);
    }

    /**
     * Checks if we have this job in our list of currently running jobs already
     *
     * @param Job $job
     * @return bool
     */
    private function isJobStillRunningFromBefore(Job $job){
        foreach ($this->runningJobs as $runningJob){
            if ($job->getName() == $runningJob->getJob()->getName() && $runningJob->getProcess()->isRunning()){
                return true;
            }
        }

        return false;
    }

    private function determineJobProcessFilesDir(Job $job){
        return $this->varDir . $job->getNameSlug() . DIRECTORY_SEPARATOR . time() . DIRECTORY_SEPARATOR;
    }

    private function startProcess(Job $job){
        // Log
        $this->log(
            $this->logLevels->getJobStartingAndFinishing(),
            JobLogMessage::init($job, 'Starting job')
        );

        // Get a directory for this job's process files and ensure it exists
        $processFilesDir = $this->determineJobProcessFilesDir($job);
        if (!file_exists($processFilesDir)){
            if (!mkdir($processFilesDir, 0777, true)){
                throw new VarDirUnusable('Cannot create required directory ' . $processFilesDir);
            }
        }

        // Start the job
        $process = new Process(
            $job->getCommand(),
            $processFilesDir.self::FILENAME_STDOUT,
            $processFilesDir.self::FILENAME_STDERR,
            $processFilesDir.self::FILENAME_EXIT_CODE
        );
        $process->run();

        // Log
        $this->log(
            $this->logLevels->getJobStartingAndFinishing(),
            JobLogMessage::init($job, 'Job started with command: ' . $process->getFullCommand())
        );

        // Trigger event
        $this->eventsDispatcher->trigger(self::EVENT_JOB_STARTED, $job);

        // Record this job is running
        $this->runningJobs[] = new RunningJob($job, $process, microtime(true), $processFilesDir);
        $this->saveRunningJobs();
    }

    /**
     * Loads the currently running jobs to the cache provider so that if this main process dies, we don't lose track of what is going on
     */
    private function loadRunningJobs(){
        $cached = $this->cacheInterface->get('slavedriver.runningJobs');
        if (!is_null($cached)) {
            $this->runningJobs = unserialize($cached);
            $this->log(LogLevel::DEBUG, 'Loaded '.count($this->runningJobs).' running job(s) from the cache.');
        }
    }

    /**
     * Saves the currently running jobs to the cache provider so that if this main process dies, we don't lose track of what is going on
     * @return bool
     */
    private function saveRunningJobs(){
        return $this->cacheInterface->set('slavedriver.runningJobs', serialize($this->runningJobs));
    }

    /**
     * Allow overriding the default sleep interval between checking on jobs
     *
     * @param int $monitoringSleepInterval
     */
    public function setMonitoringSleepInterval($monitoringSleepInterval) {
        $this->monitoringSleepInterval = $monitoringSleepInterval;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    private function log($level, $message, $context = []){
        if ($this->logger instanceof LoggerInterface){
            $this->logger->log($level, $message, $context);
        }
    }

    // Based on code from http://stackoverflow.com/a/8273826/1178671, thanks
    private function secondsToTime($inputSeconds) {
        $secondsInAMinute = 60;
        $secondsInAnHour = 60 * $secondsInAMinute;
        $secondsInADay = 24 * $secondsInAnHour;

        // Extract days
        $days = floor($inputSeconds / $secondsInADay);

        // Extract hours
        $hourSeconds = $inputSeconds % $secondsInADay;
        $hours = floor($hourSeconds / $secondsInAnHour);

        // Extract minutes
        $minuteSeconds = $hourSeconds % $secondsInAnHour;
        $minutes = floor($minuteSeconds / $secondsInAMinute);

        // Extract the remaining seconds
        $remainingSeconds = $minuteSeconds % $secondsInAMinute;
        $seconds = ceil($remainingSeconds);

        // Format and return
        $timeParts = [];
        $sections = [
            'day' => (int)$days,
            'hour' => (int)$hours,
            'minute' => (int)$minutes,
            'second' => (int)$seconds,
        ];

        foreach ($sections as $name => $value){
            if ($value > 0){
                $timeParts[] = $value. ' '.$name.($value == 1 ? '' : 's');
            }
        }

        return implode(', ', $timeParts);
    }
}