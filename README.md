# Slavedriver
A PHP based cron job runner. Manage, monitor, log and react to problems with your scheduled tasks. Created by the team at [deploy.dog](http://deploy.dog).

## Installation
*Composer based install coming soon. This is not yet ready to be released or used. Hang in there...*

## Usage
You need to get something (e.g. the real system crontab) to run your Slavedriver file every 1 minute (this time internal is important).

```
* * * * * cd /path/to/project && php slavedriver.php 1>> /dev/null 2>&1
```

More details coming soon, but for now see [slavedriver.php](https://github.com/deploy-dog/slavedriver/blob/master/slavedriver.php) for an example.

## Events
Events are dispatched throughout the process which you can listen to.
We're currently using [phossa2/event](https://github.com/phossa2/event) for the events as it is [PSR-14 (Event Manager)](https://github.com/php-fig/fig-standards/blob/master/proposed/event-manager.md) compatible.
PSR-14 is currently at the "proposed" stage so we should expect it to change. Once it is accepted we intend to swap Slavedriver to require any PSR-14 compatible library rather than phossa2/event specifically. 

You can listen to the follow events to monitor your Slavedriver jobs and act accordingly. All events are given the target of the `Job` object.

*Hint: Use the Job's `CustomData` if you want to pass through additional data, such as whether to wake people up in the night if the job fails!*

| Event | Event Constant | Argv array |
| ----- | -------------- | ---------- |
| `slavedriver.job.started` | `Slavedriver::EVENT_JOB_STARTED` | `[]` |
| `slavedriver.job.finished.success` | `Slavedriver::EVENT_JOB_FINISHED_SUCCESS` | `[]` |
| `slavedriver.job.finished.error` | `Slavedriver::EVENT_JOB_FINISHED_ERROR` | `['exitCode' => 1]` |
| `slavedriver.job.output.stdout` | `Slavedriver::EVENT_JOB_OUTPUT_STDOUT` | `['stdOut' => 'Full command stdOut']` |
| `slavedriver.job.output.stderr` | `Slavedriver::EVENT_JOB_OUTPUT_STDERR` | `['stdErr' => 'Full command stdErr']` |
| `slavedriver.job.timeout` | `Slavedriver::EVENT_JOB_TIMEOUT` | `[]` |
| `slavedriver.job.last_instance_still_running` | `Slavedriver::EVENT_JOB_LAST_INSTANCE_STILL_RUNNING` | `[]` |
| `slavedriver.job.expected_runtime_elapsed` | `Slavedriver::EVENT_JOB_EXPECTED_RUNTIME_ELAPSED` | `[]` |
