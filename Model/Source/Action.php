<?php
/**
 * Payment CC Types Source Model
 *
 * @category    MindArc
 * @package     MindArc_FatZebra
 * @author      John Vella
 * @copyright   MindArc (http://mindarc.com.au)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace MindArc\FatZebra\Model\Source;

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
