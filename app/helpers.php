<?php

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

function runConsoleCommand($cmd)
{
    $process = new Process($cmd, null, null, null, 3600);
    try {
        $process->mustRun();
    } catch (ProcessFailedException $e) {
        Log::debug('Console command error: ' . $e->getMessage());
    }
}