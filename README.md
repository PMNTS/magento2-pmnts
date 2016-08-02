magento2-PMNTS_Gateway
======================

PMNTS payment gateway Magento2 extension

Install
=======

1. Go to Magento2 root folder

2. Enter following commands to install module:

    ```bash
    composer config repositories.pmnts git https://github.com/PMNTS/magento2-pmnts.git
    composer require pmnts/magento2-pmnts:dev-master
    ```
   Wait while dependencies are updated.

3. Enter following commands to enable module:

    ```bash
    php bin/magento module:enable PMNTS_Gateway --clear-static-content
    php bin/magento setup:upgrade
    ```
4. Enable and configure FatZebra in Magento Admin under Stores/Configuration/Payment Methods/PMNTS


