<?php
/**
 * Payment CC Types Source Model
 *
 * @category    PMNTS
 * @package     PMNTS_Gateway
 * @copyright   PMNTS (http://PMNTS.io)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace PMNTS\Gateway\Model\Source;

class Action
{
    /**
     * Get payment Options
     *
     * @return \string[][]
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'auth_capture',
                'label' => 'Authorize and Capture'
            ],
            [
                'value' => 'auth',
                'label' => 'Authorize Only'
            ]
        ];
    }
}
