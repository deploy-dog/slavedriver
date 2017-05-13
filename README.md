# Slavedriver
A PHP based cron job runner. Manage, monitor, log and react to problems with your scheduled tasks.
Created by the team at [deploy.dog](http://deploy.dog) initially for internal use, but modified and released for other to enjoy too.

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

You can listen to the follow events to monitor your Slavedriver jobs and act accordingly.

*Hint: Use the Job's `CustomData` if you want to pass through additional data, such as whether to wake people up in the night if the job fails!*

### Slavedriver itself started (should be roughly every 1 minute)
* Event: `slavedriver.started`
* Event constant: `Slavedriver::EVENT_SLAVEDRIVER_STARTED`
* Event target: *None*
* Event params: *None*

### Job started
* Event: `slavedriver.job.started`
* Event constant: `Slavedriver::EVENT_JOB_STARTED`
* Event target: The `Job` object
* Event params: *None*

### Job finished successfully
* Event: `slavedriver.job.finished.success`
* Event constant: `Slavedriver::EVENT_JOB_FINISHED_SUCCESS`
* Event target: The `Job` object
* Event params: *None*

### Job finished with error (based on exit code)
* Event: `slavedriver.job.finished.error`
* Event constant: `Slavedriver::EVENT_JOB_FINISHED_ERROR`
* Event target: The `Job` object
* Event params:
  * `exitCode` = The exit code of the command
  
### Job stdOut data (this is called every few seconds with additional data)
* Event: `slavedriver.job.output.stdout`
* Event constant: `Slavedriver::EVENT_JOB_OUTPUT_STDOUT`
* Event target: The `Job` object
* Event params:
  * `stdOut` = The stdOut data since the last trigger of this event
  
### Job stdErr data (this is called every few seconds with additional data)
* Event: `slavedriver.job.output.stderr`
* Event constant: `Slavedriver::EVENT_JOB_OUTPUT_STDERR`
* Event target: The `Job` object
* Event params:
  * `stdErr` = The stdErr data since the last trigger of this event
  
### Job was still running when the timeout value was hit (and it will have been killed)
* Event: `slavedriver.job.timeout`
* Event constant: `Slavedriver::EVENT_JOB_TIMEOUT`
* Event target: The `Job` object
* Event params: *None*

### Job should have been started but the last instance was still running  and they cannot overlap
* Event: `slavedriver.job.last_instance_still_running`
* Event constant: `Slavedriver::EVENT_JOB_LAST_INSTANCE_STILL_RUNNING`
* Event target: The `Job` object
* Event params: *None*

### Job still running but expected runtime has elapsed (job not killed)
* Event: `slavedriver.job.expected_runtime_elapsed`
* Event constant: `Slavedriver::EVENT_JOB_EXPECTED_RUNTIME_ELAPSED`
* Event target: The `Job` object
* Event params: *None*


## Logging
Logging is provided by any [PSR-3](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md) compatible logger.