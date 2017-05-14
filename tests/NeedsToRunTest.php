<?php
namespace deploydog\Slavedriver\Tests;

use deploydog\Slavedriver\Job;
use PHPUnit\Framework\TestCase;

class NeedsToRunTest extends TestCase
{
    public function test_cron_expression_every_minute_all_slaves()
    {
        $job = (new Job('Test Job'))->setSchedule('* * * * *');
        $this->assertTrue($job->needsToRun('Aesop'));
    }

    public function test_cron_expression_every_minute_included_slave()
    {
        $job = (new Job('Test Job'))->setSchedule('* * * * *')->setSlaves(['Spartacus', 'Aesop']);
        $this->assertTrue($job->needsToRun('Spartacus'));
    }

    public function test_cron_expression_every_minute_wrong_slave()
    {
        $job = (new Job('Test Job'))->setSchedule('* * * * *')->setSlaves(['Aesop']);
        $this->assertFalse($job->needsToRun('Spartacus'));
    }

    public function test_cron_expression_different_time()
    {
        $job = (new Job('Test Job'))->setSchedule('61 * * * *');
        $this->assertFalse($job->needsToRun('Spartacus'));
    }

    public function test_callable_true()
    {
        $job = (new Job('Test Job'))->setSchedule(function(){
            return true;
        });
        $this->assertTrue($job->needsToRun('Spartacus'));
    }

    public function test_callable_false()
    {
        $job = (new Job('Test Job'))->setSchedule(function(){
            return false;
        });
        $this->assertFalse($job->needsToRun('Spartacus'));
    }

}