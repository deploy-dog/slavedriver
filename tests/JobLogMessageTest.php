<?php
namespace deploydog\Slavedriver\Tests;

use deploydog\Slavedriver\Job;
use deploydog\Slavedriver\JobLogMessage;
use PHPUnit\Framework\TestCase;

class JobLogMessageTest extends TestCase {
    /** @var JobLogMessage */
    private $jobLogMessage = null;

    /** @var Job */
    private $job = null;

    private $jobName = 'Test Job';
    private $message = 'This is the message';

    public function setUp(){
        $this->job = (new Job($this->jobName));
        $this->jobLogMessage = JobLogMessage::init($this->job, $this->message);
    }

    public function test_get_job() {
        $this->assertSame($this->job, $this->jobLogMessage->getJob());
    }

    public function test_get_message() {
        $this->assertSame($this->message, $this->jobLogMessage->getMessage());
    }

    public function test_to_string() {
        $this->assertSame(
            'Slavedriver ['.$this->jobName.'] - ' . $this->message,
            (string)$this->jobLogMessage
        );
    }
}