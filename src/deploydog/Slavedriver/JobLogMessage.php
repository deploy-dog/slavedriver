<?php

namespace deploydog\Slavedriver;

class JobLogMessage {
    /** @var Job */
    private $job = null;
    private $message = null;

    public static function init(Job $job, $message){
        return new self($job, $message);
    }

    /**
     * JobError constructor.
     * @param Job $job
     * @param null $message
     */
    private function __construct(Job $job, $message) {
        $this->job = $job;
        $this->message = $message;
    }

    public function __toString() {
        return 'Slavedriver ['.$this->job->getName().'] - ' . $this->message;
    }

    /**
     * @return Job
     */
    public function getJob() {
        return $this->job;
    }

    /**
     * @return string
     */
    public function getMessage() {
        return $this->message;
    }


}