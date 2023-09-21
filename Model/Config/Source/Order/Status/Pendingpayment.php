<?php
declare(strict_types=1);

namespace PMNTS\Gateway\Model\Config\Source\Order\Status;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Config\Source\Order\Status;

class Pendingpayment extends Status
{
    /**
     * @var array
     */
    protected $_stateStatuses = [Order::STATE_PENDING_PAYMENT, Order::STATE_PROCESSING];
}
