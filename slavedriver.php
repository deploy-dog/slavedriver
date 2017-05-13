<?php

require_once __DIR__ . '/vendor/autoload.php';

// Note: This script MUST be run every 1 minute by something else (hint: your real crontab)
// This is an assumption by Slavedriver so if this is not the case, it will NOT work as expected.

// We recommend scrapbook for your PSR-16 compatible cache, but use whatever you like!
$cache = new \MatthiasMullie\Scrapbook\Adapters\MemoryStore();
$simpleCache = new \MatthiasMullie\Scrapbook\Psr16\SimpleCache($cache);

// Events use phossa2/event which is a PSR-14 event manager. PSR-14 is only "proposed" at this stage. Once PRS-14 is "accepted" we'll probably move to this requirement implementing PRS-14 instead of phossa2/event specifically
$eventsDispatcher = new \Phossa2\Event\EventDispatcher();
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

// Instantiate Slavedriver
$slavedriver = new \deploydog\Slavedriver\Slavedriver($simpleCache, $eventsDispatcher);

// We want a logger in this example and we're showing how we could set customer log levels
// We'll use the default log levels, the defaults are sensible for most projects.
//If using the defaults, you don't actually need to pass this into $slavedriver->setLogger() but we're showing it here for the example
$logLevels = new \deploydog\Slavedriver\LogLevels();

// Build logger
$logger = new \Monolog\Logger('Slavedriver');
$logger->pushHandler(new \Monolog\Handler\StreamHandler(__DIR__.'/test.log'));

// Give logger to Slavedriver
$slavedriver->setLogger($logger, $logLevels);

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

// Create a job (you can also chain the setup if you prefer)
$job = new \deploydog\Slavedriver\Job('List directory contents');
$job->setCommand('ls -alh')
->setTimeout(2)
->setSchedule('* * * * *')
->setCustomData([
    'critical' => true, // You could listen to the "slavedriver.job.finished.error" event and if the job has this set, trigger an alert to PagerDuty or something!
]);

// Add the job to Slavedriver
$slavedriver->addJob($job);



// Optionally override the sleep internal between monitoring. A lower time gives more real-time monitoring but more disk I/O etc. Tweak as required.
$slavedriver->setMonitoringSleepInterval(3);

// Run the required jobs
$slavedriver->run();