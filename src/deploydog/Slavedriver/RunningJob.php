<?php

namespace deploydog\Slavedriver;

class RunningJob {
    /** @var Job */
    private $job;

    /** @var Process */
    private $process;
    private $startTs;
    private $processFilesDir;
    private $alreadyWarnedNotFinishedWhenExpected = false;

    private $stdOutReadSoFar = 0;
    private $stdErrReadSoFar = 0;

    /**
     * RunningJob constructor.
     * @param Job $job
     * @param Process $process
     * @param int $startTs
     * @param string $processFilesDir
     */
    public function __construct(Job $job, Process $process, $startTs, $processFilesDir) {
        $this->job = $job;
        $this->process = $process;
        $this->startTs = $startTs;
        $this->processFilesDir = $processFilesDir;
    }

    /**
     * @return Job
     */
    public function getJob() {
        return $this->job;
    }

    /**
     * @return Process
     */
    public function getProcess() {
        return $this->process;
    }

    /**
     * @return int
     */
    public function getStartTs() {
        return $this->startTs;
    }

    /**
     * @return string
     */
    public function getProcessFilesDir() {
        return $this->processFilesDir;
    }

    /**
     * @param $additionBytesRead
     * @return $this
     */
    public function addToStdoutReadSoFar($additionBytesRead){
        $this->stdOutReadSoFar += $additionBytesRead;
        return $this;
    }

    /**
     * @param $additionBytesRead
     * @return $this
     */
    public function addToStderrReadSoFar($additionBytesRead){
        $this->stdErrReadSoFar += $additionBytesRead;
        return $this;
    }

    /**
     * @return int
     */
    public function getStdOutReadSoFar() {
        return $this->stdOutReadSoFar;
    }

    /**
     * @return int
     */
    public function getStdErrReadSoFar() {
        return $this->stdErrReadSoFar;
    }

    /**
     * @return bool
     */
    public function haveAlreadyWarnedNotFinishedWhenExpected(){
        return $this->alreadyWarnedNotFinishedWhenExpected;
    }

    /**
     * @return $this
     */
    public function setAlreadyWarnedNotFinishedWhenExpected(){
        $this->alreadyWarnedNotFinishedWhenExpected = true;
        return $this;
    }
}