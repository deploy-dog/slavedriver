<?php

namespace deploydog\Slavedriver;

use deploydog\Slavedriver\Exception\InvalidCommand;

class Process {
    private $command;
    private $stdoutFile;
    private $stdErrFile;
    private $exitCodeFile;
    private $pid;

    /**
     * @param string $command The command to execute
     * @param $stdoutFile
     * @param $stdErrFile
     */
    public function __construct($command, $stdoutFile, $stdErrFile, $exitCodeFile) {
        $this->command = $command;
        $this->stdoutFile = $stdoutFile;
        $this->stdErrFile = $stdErrFile;
        $this->exitCodeFile = $exitCodeFile;
    }

    /**
     * Runs a command in the background
     */
    public function run() {
        $this->pid = (int)shell_exec($this->getFullCommand());
    }

    /**
     * Builds and returns the full command that will be run
     *
     * @return string
     * @throws InvalidCommand
     */
    public function getFullCommand(){
        if (!is_string($this->command)){
            throw new InvalidCommand('The command provided is not a string.');
        }

        return 'php '.escapeshellarg(__DIR__.'/../../../bin/slavedriverRun.php').' '. escapeshellarg($this->command).' '.escapeshellarg($this->exitCodeFile).' 1> '.escapeshellarg($this->stdoutFile).' 2> '.escapeshellarg($this->stdErrFile) . ' & echo $!';
    }

    /**
     * Returns true if the process is running and false if it isn't
     *
     * @return bool
     */
    public function isRunning() {
        try {
            $result = shell_exec(sprintf('ps %d 2>&1', $this->pid));
            if (count(explode("\n", $result)) > 2 && !preg_match('/ERROR: Process ID out of range/', $result)) {
                return true;
            }
        } catch (\Exception $e) {
            // Eat exceptions
        }

        return false;
    }

    /**
     * Stops the process
     */
    public function stop() {
        try {
            $result = shell_exec(sprintf('kill %d 2>&1', $this->pid));
            if (!preg_match('/No such process/', $result)) {
                return true;
            }
        } catch (\Exception $e) {
            // Eat exceptions
        }

        return false;
    }

    /**
     * Returns the process ID
     *
     * @return int The ID of the process
     */
    public function getPid() {
        return $this->pid;
    }

    /**
     * Set the process ID
     *
     * @param $pid
     */
    protected function setPid($pid) {
        $this->pid = $pid;
    }
}
