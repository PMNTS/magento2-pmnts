magento2-MindArc_FatZebra
======================

FatZebra payment gateway Magento2 extension

Install
=======

1. Go to Magento2 root folder

2. Enter following commands to install module:

    ```bash
    composer config repositories.mindarcfatzebra git https://github.com/MindArc/magento2-MindArc_FatZebra.git
    composer require mindarc/fatzebra:dev-master
    ```
   Wait while dependencies are updated.

3. Enter following commands to enable module:

    ```bash
    php bin/magento module:enable MindArc_FatZebra --clear-static-content
    php bin/magento setup:upgrade
    ```
4. Enable and configure FatZebra in Magento Admin under Stores/Configuration/Payment Methods/FatZebra


