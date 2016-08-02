<?php
namespace MindArc\FatZebra\Setup;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetup;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;

class UpgradeData implements UpgradeDataInterface
{

    /**
     * @var CustomerSetupFactory
     */
    private $customerSetupFactory;

    public function __construct(CustomerSetupFactory $customerSetupFactory)
    {
        $this->customerSetupFactory = $customerSetupFactory;
    }

    /**
     * Upgrades data for a module
     *
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {

        /** @var CustomerSetup $customerSetup */
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'fatzebra_token',
            [
                'label' => 'Card Token',
                'required' => 0,
                'system' => 0, // <-- important, otherwise values aren't saved.
                // @see Magento\Customer\Model\Metadata\CustomerMetadata::getCustomAttributesMetadata()
                'position' => 100
            ]
        );

        $customerSetup->addAttribute(
            Customer::ENTITY,
            'fatzebra_masked_card_number',
            [
                'label' => 'Masked Card Number',
                'required' => 0,
                'system' => 0, // <-- important, otherwise values aren't saved.
                // @see Magento\Customer\Model\Metadata\CustomerMetadata::getCustomAttributesMetadata()
                'position' => 100
            ]
        );
        $customerSetup->getEavConfig()->getAttribute('customer', 'fatzebra_masked_card_number')
            ->setData('used_in_forms', ['adminhtml_customer'])
            ->save();

        $customerSetup->addAttribute(
            Customer::ENTITY,
            'fatzebra_expiry_date',
            [
                'label' => 'Card Expiry Date',
                'required' => 0,
                'system' => 0, // <-- important, otherwise values aren't saved.
                // @see Magento\Customer\Model\Metadata\CustomerMetadata::getCustomAttributesMetadata()
                'position' => 100
            ]
        );
    }

}