<?php

namespace deploydog\Slavedriver;

use Cocur\Slugify\Slugify;
use Cron\CronExpression;
use deploydog\Slavedriver\Exception\InvalidJob;

class Job {
    private $name = null;
    private $command = null;
    private $schedule = null;

    /** @var null|\DateTime */
    private $runAtDateTime = null;
    private $enabled = true;
    private $slaves = [];
    private $timeout = null;
    private $warnIfNotFinishedAfterSeconds = null;
    private $allowRunningIfPreviousStillRunning = null;
    private $customData = [];

    private $slugCache = null;

    /**
     * Job constructor.
     * @param null $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getNameSlug() {
        if (is_null($this->slugCache)){
            $this->slugCache = (new Slugify())->slugify($this->name);
        }
        return $this->slugCache;
    }

    /**
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * @param string $command
     * @return $this
     */
    public function setCommand($command)
    {
        $this->command = $command;
        return $this;
    }

    /**
     * @return array
     */
    public function getCustomData()
    {
        return $this->customData;
    }

    /**
     * @param mixed $customData
     * @return $this
     */
    public function setCustomData($customData)
    {
        $this->customData = $customData;
        return $this;
    }

    /**
     * @param string|callable $schedule
     * @return $this
     * @throws InvalidJob
     */
    public function setSchedule($schedule)
    {
        if (!is_null($this->runAtDateTime)){
            throw new InvalidJob('You cannot set both a Schedule and a RunAtDateTime');
        }

        if (!is_callable($schedule) && !is_string($schedule)){
            throw new InvalidJob('Schedule can be set as a callable to return a bool or a Crontab style schedule.');
        }

        $this->schedule = $schedule;
        return $this;
    }

    /**
     * @param \DateTime $runAtDateTime
     * @return $this
     * @throws InvalidJob
     */
    public function setRunAtDateTime(\DateTime $runAtDateTime)
    {
        if (!is_null($this->schedule)){
            throw new InvalidJob('You cannot set both a Schedule and a RunAtDateTime');
        }

        $this->runAtDateTime = $runAtDateTime;
        return $this;
    }

    /**
     * @param bool $enabled
     * @return $this
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * @param array $slaves
     * @return $this
     */
    public function setSlaves(array $slaves)
    {
        $this->slaves = $slaves;
        return $this;
    }

    /**
     * @param int $timeout
     * @return $this
     */
    public function setTimeout($timeout) {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * @param int $warnIfNotFinishedAfterSeconds
     */
    public function setWarnIfNotFinishedAfterSeconds($warnIfNotFinishedAfterSeconds)
    {
        $this->warnIfNotFinishedAfterSeconds = $warnIfNotFinishedAfterSeconds;
    }

    /**
     * @return null
     */
    public function allowRunningIfPreviousStillRunning() {
        return $this->allowRunningIfPreviousStillRunning;
    }

    /**
     * @param bool $allowRunningIfPreviousStillRunning
     * @return $this
     * @throws InvalidJob
     */
    public function setAllowRunningIfPreviousStillRunning($allowRunningIfPreviousStillRunning) {
        if (!is_bool($allowRunningIfPreviousStillRunning)){
            throw new InvalidJob('allowRunningIfPreviousStillRunning must be boolean.');
        }
        $this->allowRunningIfPreviousStillRunning = $allowRunningIfPreviousStillRunning;
        return $this;
    }

    /**
     * @param $slaveName
     * @return bool
     */
    public function needsToRun($slaveName) {
        // Check enabled
        if (!$this->enabled){
            return false;
        }

        // Check if this slaveName needs to run this job
        if (count($this->slaves) == 0 || in_array($slaveName, $this->slaves)){
            // Check if we need to run at this time
            return $this->needsToRunAtThisTime();
        }

        return false;
    }

    private function needsToRunAtThisTime(){
        // Look at schedule
        if (!is_null($this->schedule)) {
            if (is_callable($this->schedule)) {
                $callable = $this->schedule;
                return $callable();
            } else {
                try {
                    $cron = CronExpression::factory($this->schedule);
                } catch (\InvalidArgumentException $e){
                    throw new InvalidJob($e->getMessage());
                }
                return $cron->isDue();
            }
        }

        // Look at date time
        if (!is_null($this->runAtDateTime)){
            return (new \DateTime())->format('Y-m-d H:i') == $this->runAtDateTime->format('Y-m-d H:i');
        }

        // We shouldn't get here, throw an exception
        throw new InvalidJob('No schedule or runAtDateTime was set for job name "'.$this->name.'".');
    }

    /**
     * @return null|int
     */
    public function getTimeout() {
        return $this->timeout;
    }

    /**
     * @return null"int
     */
    public function getWarnIfNotFinishedAfterSeconds() {
        return $this->warnIfNotFinishedAfterSeconds;
    }
}