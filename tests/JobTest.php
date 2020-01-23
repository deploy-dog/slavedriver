<?php
namespace deploydog\Slavedriver\Tests;

use deploydog\Slavedriver\Exception\InvalidJob;
use deploydog\Slavedriver\Job;
use PHPUnit\Framework\TestCase;

class JobTest extends TestCase
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

    public function test_cron_expression_every_minute_disabled()
    {
        $job = (new Job('Test Job'))->setSchedule('* * * * *')->setEnabled(false);
        $this->assertFalse($job->needsToRun('Spartacus'));
    }

    public function test_cron_expression_invalid_time()
    {
        $this->expectException(InvalidJob::class);
        $job = (new Job('Test Job'))->setSchedule('61 * * * *');
        $job->needsToRun('Spartacus');
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

    public function test_setting_invalid_schedule_string()
    {
        $this->expectException(InvalidJob::class);

        $job = (new Job('Test Job'))->setSchedule('I should be a cron schedule!');
        $job->needsToRun('Spartacus');
    }

    public function test_setting_invalid_schedule_class()
    {
        $this->expectException(InvalidJob::class);

        $job = (new Job('Test Job'))->setSchedule((new \stdClass()));
    }

    public function test_name_slug()
    {
        $job = (new Job('Test Job'));
        $this->assertEquals('test-job', $job->getNameSlug());
    }
}
