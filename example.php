<?php

require_once __DIR__ . '/vendor/autoload.php';

// Note: This script MUST be run every 1 minute by something else (hint: your real crontab)
// This is an assumption by Slavedriver so if this is not the case, it will NOT work as expected.

// We recommend scrapbook for your PSR-16 compatible cache, but use whatever you like!
$cache = new \MatthiasMullie\Scrapbook\Adapters\MemoryStore();
$simpleCache = new \MatthiasMullie\Scrapbook\Psr16\SimpleCache($cache);

// We'll use the default log levels, the defaults are sensible for most projects.
// You can use this to set your own defaults and can even set alternative log levels for individual jobs (e.g. if you has an ultra important job)
$logLevels = new \deploydog\Slavedriver\LogLevels();

// Build logger
$logger = new \Monolog\Logger('Slavedriver');
$logger->pushHandler(new \Monolog\Handler\StreamHandler(__DIR__.'/test.log'));

// Events use phossa2/event which is a PSR-14 event manager. PSR-14 is only "proposed" at this stage. Once PRS-14 is "accepted" we'll probably move to this requirement implementing PRS-14 instead of phossa2/event specifically
$eventsDispatcher = new \Phossa2\Event\EventDispatcher();
$eventsDispatcher->attach('slavedriver.*', function(\Phossa2\Event\Event $event) {
    /** @var \deploydog\Slavedriver\Job $job */
    $job = $event->getTarget();
    echo 'Got event "' . $event->getName().'" on job "'.$job->getName().'"'."\n";

    if ($event->getName() == \deploydog\Slavedriver\Slavedriver::EVENT_JOB_OUTPUT_STDOUT){
        echo 'stdOut > ' . $event->getParam('stdOut')."\n";
    } else if ($event->getName() == \deploydog\Slavedriver\Slavedriver::EVENT_JOB_OUTPUT_STDERR){
        echo 'stdErr > ' . $event->getParam('stdErr')."\n";
    }
});

// Instantiate Slavedriver
$slavedriver = new \deploydog\Slavedriver\Slavedriver($simpleCache, $logger, $logLevels, $eventsDispatcher);

// You can set the Slave Name that the machine running this job has (so it know which jobs to do). We'll look at the following (in this order)
// Manually set using $slavedriver->setSlaveName()
// Environment var "SLAVEDRIVER_SLAVE_NAME"
// Node hostname (from php's gethostname() function)
$slavedriver->setSlaveName('test1');


//
// Job 1
//

// Create a job
$job = new \deploydog\Slavedriver\Job('Sleep for a bit');
$job->setCommand('sleep 10');
$job->setTimeout(12);
$job->setWarnIfNotFinishedAfterSeconds(8);
$job->setSchedule('* * * * *');
$job->setSlaves(['test1']); // Optional, default to all slaves

// Add the job to Slavedriver
$slavedriver->addJob($job);

//
// Job 2
//

// Create a job
$job = new \deploydog\Slavedriver\Job('List directory contents');
$job->setCommand('ls -alh');
$job->setTimeout(2);
$job->setSchedule('* * * * *');

// Add the job to Slavedriver
$slavedriver->addJob($job);



// Optionally override the sleep internal between monitoring. A lower time gives more real-time monitoring but more disk I/O etc. Tweak as required.
$slavedriver->setMonitoringSleepInterval(3);

// Run the required jobs
$slavedriver->run();