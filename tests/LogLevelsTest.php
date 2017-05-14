<?php
namespace deploydog\Slavedriver\Tests;

use deploydog\Slavedriver\Exception\InvalidLogLevel;
use deploydog\Slavedriver\Job;
use deploydog\Slavedriver\JobLogMessage;
use deploydog\Slavedriver\LogLevels;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class LogLevelsTest extends TestCase {
    public function test_setting_starting_and_finishing() {
        $logLevels = new LogLevels();
        $logLevels->setJobStartingAndFinishing(LogLevel::DEBUG);
        $this->assertSame(
            LogLevel::DEBUG,
            $logLevels->getJobStartingAndFinishing()
        );
    }

    public function test_setting_error_exit_code() {
        $logLevels = new LogLevels();
        $logLevels->setErrorExitCode(LogLevel::DEBUG);
        $this->assertSame(
            LogLevel::DEBUG,
            $logLevels->getErrorExitCode()
        );
    }

    public function test_setting_invalid_config() {
        $logLevels = new LogLevels();
        $logLevels->setInvalidConfig(LogLevel::DEBUG);
        $this->assertSame(
            LogLevel::DEBUG,
            $logLevels->getInvalidConfig()
        );
    }

    public function test_setting_not_finished_when_expected() {
        $logLevels = new LogLevels();
        $logLevels->setNotFinishedWhenExpected(LogLevel::DEBUG);
        $this->assertSame(
            LogLevel::DEBUG,
            $logLevels->getNotFinishedWhenExpected()
        );
    }

    public function test_setting_needing_to_be_killed() {
        $logLevels = new LogLevels();
        $logLevels->setNeedingToBeKilled(LogLevel::DEBUG);
        $this->assertSame(
            LogLevel::DEBUG,
            $logLevels->getNeedingToBeKilled()
        );
    }

    public function test_setting_cannot_start_next() {
        $logLevels = new LogLevels();
        $logLevels->setCannotStartNextAsLastStillRunning(LogLevel::DEBUG);
        $this->assertSame(
            LogLevel::DEBUG,
            $logLevels->getCannotStartNextAsLastStillRunning()
        );
    }


    public function test_setting_invalid_starting_and_finishing() {
        $this->expectException(InvalidLogLevel::class);
        $logLevels = new LogLevels();
        $logLevels->setJobStartingAndFinishing('something-invalid');
    }

    public function test_setting_invalid_error_exit_code() {
        $this->expectException(InvalidLogLevel::class);
        $logLevels = new LogLevels();
        $logLevels->setErrorExitCode('something-invalid');
    }

    public function test_setting_invalid_invalid_config() {
        $this->expectException(InvalidLogLevel::class);
        $logLevels = new LogLevels();
        $logLevels->setInvalidConfig('something-invalid');
    }

    public function test_setting_invalid_not_finished_when_expected() {
        $this->expectException(InvalidLogLevel::class);
        $logLevels = new LogLevels();
        $logLevels->setNotFinishedWhenExpected('something-invalid');
    }

    public function test_setting_invalid_needing_to_be_killed() {
        $this->expectException(InvalidLogLevel::class);
        $logLevels = new LogLevels();
        $logLevels->setNeedingToBeKilled('something-invalid');
    }

    public function test_setting_invalid_cannot_start_next() {
        $this->expectException(InvalidLogLevel::class);
        $logLevels = new LogLevels();
        $logLevels->setCannotStartNextAsLastStillRunning('something-invalid');
    }
}