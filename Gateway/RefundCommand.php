<?php

namespace PMNTS\Gateway\Gateway;

use Magento\Payment\Gateway\Command;
use Magento\Payment\Gateway\Command\CommandException;

class RefundCommand implements \Magento\Payment\Gateway\CommandInterface
{

    /**
     * @param array $commandSubject
     * @return null|Command\ResultInterface
     * @throws CommandException
     */
    public function execute(array $commandSubject)
    {
        // TODO: Implement execute() method.
    }
}