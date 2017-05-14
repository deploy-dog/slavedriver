# Slavedriver
A PHP based cron job runner. Manage, monitor, log and react to problems with your scheduled tasks.
Created by the team at [deploy.dog](http://deploy.dog) initially for internal use, but modified and released for other to enjoy too.

## Installation
*Composer based install coming soon. This is not yet ready to be released or used. Hang in there...*

## Usage

### Create your file which describes your jobs. We suggest using a file named `slavedriver.php` in the project root, and that's what we'll be using in this example

**A more detailed example of this file can be seen [here](https://github.com/deploy-dog/slavedriver/blob/master/slavedriver.php), but the basics are below**

```php
// You'll need autoloading
require_once __DIR__ . '/vendor/autoload.php';

// We recommend scrapbook for your PSR-16 compatible cache, but use whatever you like!
// This example is using Scrapbook and Flysystem to write a cache file to the disk, but you'll probably want to use Redis or something better like that
$adapter = new \League\Flysystem\Adapter\Local('/tmp/dd.slavedriver.cache', LOCK_EX);
$filesystem = new \League\Flysystem\Filesystem($adapter);
$cache = new \MatthiasMullie\Scrapbook\Adapters\Flysystem($filesystem);
$simpleCache = new \MatthiasMullie\Scrapbook\Psr16\SimpleCache($cache);

// Events use phossa2/event which is a PSR-14 event manager. PSR-14 is only "proposed" at this stage. Once PRS-14 is "accepted" we'll probably move to this requirement implementing PRS-14 instead of phossa2/event specifically
$eventsDispatcher = new \Phossa2\Event\EventDispatcher();
$eventsDispatcher->attach('slavedriver.*', function(\Phossa2\Event\Event $event) {
    $job = $event->getTarget();
    // See Events in the readme below for details on events
});

// Instantiate Slavedriver
$slavedriver = new \deploydog\Slavedriver\Slavedriver($simpleCache, $eventsDispatcher);

// Want logging (in addition to events)? See the Logging section of the readme below

// You can set the Slave Name that the machine running this job has (so it know which jobs to do). We'll look at the following (in this order)
// Manually set using $slavedriver->setSlaveName()
// Environment var "SLAVEDRIVER_SLAVE_NAME"
// Node hostname (from php's gethostname() function)
$slavedriver->setSlaveName('test1');


// Create a job
$job = new \deploydog\Slavedriver\Job('Sleep for a bit');
$job->setCommand('sleep 10');
$job->setTimeout(14);
$job->setWarnIfNotFinishedAfterSeconds(11);
$job->setSchedule('* * * * *');
$job->setSlaves(['test1']); // Optional, default to all slaves

// Add the job to Slavedriver
$slavedriver->addJob($job);

// Add more jobs like with the example above


// Alternatively (or in addition) if you have lots of jobs you might want to include one per file and get Slavedriver to recursively look in directory for jobs
// Your included files should return an instance of the Job object.
$slavedriver->addAllJobsInDirectory(__DIR__.'/DirWithJobs');

// Run the required jobs
$slavedriver->run();
```

### Running Slavedriver
You need to get something (e.g. the real system crontab) to run your Slavedriver file every 1 minute (this time internal is important, don't change it).

```
* * * * * cd /path/to/project && php slavedriver.php 1>> /dev/null 2>&1
```


## Events
Events are dispatched throughout the process which you can listen to.
We're currently using [phossa2/event](https://github.com/phossa2/event) for the events as it is [PSR-14 (Event Manager)](https://github.com/php-fig/fig-standards/blob/master/proposed/event-manager.md) compatible.
PSR-14 is currently at the "proposed" stage so we should expect it to change. Once it is accepted we intend to swap Slavedriver to require any PSR-14 compatible library rather than phossa2/event specifically. 

You can listen to the follow events to monitor your Slavedriver jobs and act accordingly.

*Hint: Use the Job's `CustomData` if you want to pass through additional data, such as whether to wake people up in the night if the job fails!*

### Catching events
#### Handling specific events

```php
$eventsDispatcher = new \Phossa2\Event\EventDispatcher(); // This is the same one you passed into Slavedriver on construct
$eventsDispatcher->attach('slavedriver.job.output.stdout', function(\Phossa2\Event\Event $event) {
     $job = $event->getTarget();
     $stdOut = $event->getParam('stdOut');
    
     // Log $stdOut somewhere, this can be called multiple times for each job and will be given any
     // new stdOut since the last call. You can then append this to the previous stdOut of the job
     // Likewise the event "slavedriver.job.output.stderr" will give you the stdErr in $event->getParam('stdErr')
});
```


### Handling all Slavedriver events

```php
$eventsDispatcher = new \Phossa2\Event\EventDispatcher(); // This is the same one you passed into Slavedriver on construct
$eventsDispatcher->attach('slavedriver.*', function(\Phossa2\Event\Event $event) {
    $job = $event->getTarget();

    if ($job instanceof \deploydog\Slavedriver\Job) {
        echo 'Got event "'.$event->getName().'" on job "'.$job->getName().'"'."\n";
    } else {
        echo 'Got event "'.$event->getName().'"'."\n";
    }

    if ($event->getName() == \deploydog\Slavedriver\Slavedriver::EVENT_JOB_OUTPUT_STDOUT){
        echo 'stdOut > ' . $event->getParam('stdOut')."\n";
    } else if ($event->getName() == \deploydog\Slavedriver\Slavedriver::EVENT_JOB_OUTPUT_STDERR){
        echo 'stdErr > ' . $event->getParam('stdErr')."\n";
    }
});
```


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
You can provider custom log levels for different messages if you wish, or leave the leave the log levels at the defaults which are sensible.
To setup logging, call the `setLogger()` method on the Slavedriver object, passing in the PSR-3 logger and optionally the log levels.

```php
$logger = new \Monolog\Logger('Slavedriver');
$logger->pushHandler(new \Monolog\Handler\StreamHandler(__DIR__.'/slavedriver.log'));

$slavedriver->setLogger($logger);
```

Or with custom log levels (e.g. override the `ErrorExitCode` log level to `alert`)

```php
$logLevels = new \deploydog\Slavedriver\LogLevels();
$logLevels->setErrorExitCode(\Psr\Log\LogLevel::ALERT);

$slavedriver->setLogger($logger, $logLevels);
```