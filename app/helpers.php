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

function replaceABNData($message, $receiverAbn, $senderAbn)
{
    $message['Invoice']['accountingSupplierParty']['party']['partyLegalEntity'][0]['companyID']['ABN'] = $senderAbn;
    $message['Invoice']['accountingCustomerParty']['party']['partyIdentification'][0]['ABN'] = $receiverAbn;
    $message['Invoice']['accountingCustomerParty']['party']['partyLegalEntity'][0]['companyID']['ABN'] = $senderAbn;
    $message['Invoice']['issueDate'] = \Carbon\Carbon::now()->startOfMonth()->toDateString();
    $message['Invoice']['dueDate'] = \Carbon\Carbon::now()->addMonth()->startOfMonth()->toDateString();

    return $message;
}