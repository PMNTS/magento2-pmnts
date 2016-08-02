<?php
/**
 * Payment CC Types Source Model
 *
 * @category    PMNTS
 * @package     PMNTS_Gateway
 * @copyright   PMNTS (http://PMNTS.io)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PMNTS\Gateway\Model\Source;

class Action
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'auth_capture',
                'label' => 'Authorize and Capture'
            ),
            array(
                'value' => 'auth',
                'label' => 'Authorize Only'
            )
        );
    }
}
