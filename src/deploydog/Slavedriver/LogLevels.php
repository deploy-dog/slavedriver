<?php

namespace deploydog\Slavedriver;

use deploydog\Slavedriver\Exception\InvalidLogLevel;
use Psr\Log\LogLevel;

class LogLevels {
    private $jobStartingAndFinishing = LogLevel::NOTICE;
    private $errorExitCode = LogLevel::ERROR;
    private $invalidConfig = LogLevel::CRITICAL;
    private $notFinishedWhenExpected = LogLevel::WARNING;
    private $needingToBeKilled = LogLevel::ERROR;
    private $cannotStartNextAsLastStillRunning = LogLevel::ERROR;

    private function validateLogLevel($logLevel){
        if (!in_array($logLevel, [
            LogLevel::DEBUG,
            LogLevel::INFO,
            LogLevel::NOTICE,
            LogLevel::WARNING,
            LogLevel::ERROR,
            LogLevel::CRITICAL,
            LogLevel::ALERT,
        ])){
            throw new InvalidLogLevel('The log level of "'.$logLevel.'" is not a valid option.');
        }
    }

    /**
     * @return string
     */
    public function getJobStartingAndFinishing() {
        return $this->jobStartingAndFinishing;
    }

    /**
     * @param string $jobStartingAndFinishing
     * @return $this
     */
    public function setJobStartingAndFinishing($jobStartingAndFinishing) {
        $this->validateLogLevel($jobStartingAndFinishing);
        $this->jobStartingAndFinishing = $jobStartingAndFinishing;
        return $this;
    }

    /**
     * @return string
     */
    public function getErrorExitCode() {
        return $this->errorExitCode;
    }

    /**
     * @param string $errorExitCode
     * @return $this
     */
    public function setErrorExitCode($errorExitCode) {
        $this->validateLogLevel($errorExitCode);
        $this->errorExitCode = $errorExitCode;
        return $this;
    }

    /**
     * @return string
     */
    public function getInvalidConfig() {
        return $this->invalidConfig;
    }

    /**
     * @param string $invalidConfig
     * @return $this
     */
    public function setInvalidConfig($invalidConfig) {
        $this->validateLogLevel($invalidConfig);
        $this->invalidConfig = $invalidConfig;
        return $this;
    }

    /**
     * @return string
     */
    public function getNotFinishedWhenExpected() {
        return $this->notFinishedWhenExpected;
    }

    /**
     * @param string $notFinishedWhenExpected
     * @return $this
     */
    public function setNotFinishedWhenExpected($notFinishedWhenExpected) {
        $this->validateLogLevel($notFinishedWhenExpected);
        $this->notFinishedWhenExpected = $notFinishedWhenExpected;
        return $this;
    }

    /**
     * @return string
     */
    public function getNeedingToBeKilled() {
        return $this->needingToBeKilled;
    }

    /**
     * @param string $needingToBeKilled
     * @return $this
     */
    public function setNeedingToBeKilled($needingToBeKilled) {
        $this->validateLogLevel($needingToBeKilled);
        $this->needingToBeKilled = $needingToBeKilled;
        return $this;
    }

    /**
     * @return string
     */
    public function getCannotStartNextAsLastStillRunning() {
        return $this->cannotStartNextAsLastStillRunning;
    }

    /**
     * @param string $cannotStartNextAsLastStillRunning
     * @return $this
     */
    public function setCannotStartNextAsLastStillRunning($cannotStartNextAsLastStillRunning) {
        $this->validateLogLevel($cannotStartNextAsLastStillRunning);
        $this->cannotStartNextAsLastStillRunning = $cannotStartNextAsLastStillRunning;
        return $this;
    }


}