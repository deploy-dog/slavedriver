<?php

if (count($argv) != 3){
	exit(-2);
}

system(escapeshellcmd($argv[1]), $exitCode);

file_put_contents($argv[2], $exitCode);