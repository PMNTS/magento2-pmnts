PMNTS Magento 2 Module
======================

Overview
--------
The PMNTS Magento 2 module provides a simple integration method for Magento 2.0 with the Fat Zebra, Cloud Payments and PMNTS gateway services. This module includes support for the following functionality:

•	Standard payments through the Gateway API
•	IFRAME card details capture for de-scoping of PCI requirements
•	Refunds of orders through Magento
•	Fraud screening of transactions

The module can be configured to use either direct integration (where credit card details are transmitted through the webserver onto the gateway) or by using an iframe to capture the customers credit card and using a token in lieu of the customers card details to process the transaction. While both of these methods do not completely absolve the merchant of their PCI responsibilities the IFRAME method significantly reduces the requirements under PCI DSS1

Fraud Screening of transactions is performed through the payment gateway inline with the payment request and has four possible outcomes:
•	Accept – the fraud screening considers the transaction legitimate and the transaction is attempted with the bank.
•	Challenge – the fraud screening considers the transaction to be moderate risk and the merchant should review the transaction and the fraud messages to determine whether to cancel/refund the order or fulfil it.
•	Deny – the fraud screening considers the transaction to be high risk (or a predefined DENY rule has been triggered) and the order has been prevented form processing.


Installation
------------
Installation of the module can be performed using composer, or manually.

Installation using Composer
---------------------------

1.	Under the Magento root folder run the following command to setup the repository:
```
composer config repositories.pmnts git https://github.com/PMNTS/magento2-pmnts.git
```
2.	Then run the following command to add the module:
```
composer require pmnts/magento2-pmnts:dev-master
```
3.	Following this the dependencies will be fetched or updated where required – this may take a few minutes.
4.	Once all dependencies have installed the module needs to be enabled using the following commands:
```
php bin/magento module:enable PMNTS_Gateway --clear-static-content && php bin/magento setup:upgrade
```
5.	Once the setup:upgrade command completes the module will be available within the Store Admin to configure.


Manual Installation
-------------------

1.	Download the latest archive of the module from https://github.com/PMNTS/magento2-pmnts/archive/master.zip
2.	Copy the archive to the server and extract – the files should be extracted into the Magento root folder, or copied over ensuring directories are included/preserved.
3.	Run enable the module by running the following commands:
```
php bin/magento module:enable PMNTS_Gateway --clear-static-content && php bin/magento setup:upgrade
```
4.	Once the setup:upgrade command completed the module will be available within the Store Admin to configure


Configuration
-------------
To configure the module the following steps should be taken:

1.	Login to the Magento Admin area (this is commonly at https://www.yoursite.com/admin, however it may be different)
2.	From the menu on the left hand side select Stores and then Configuration
3.  Under the Configuration menu select Sales and then Payment Methods
4.	Under the PMNTS payment method set the configuration details as required
5.	Once the configuration has been entered click Save Config – this will commit the changes to the database. The payment method can now be tested.

Notes on Fraud Shipping Maps
----------------------------
The Fraud Screening has a set of shipping type codes which need to be matched against the shipping methods used by the store – these codes are:

* low_cost
* same_day
* overnight
* express
* international
* pickup
* other

If nothing matches when mapping these values to the shipping methods used by your store we recommend using the closest available mapping (e.g. Flat Rate/Fixed would be mapped to low_cost, Click&Collect would be pickup), or choose other and inform our support team so that the appropriate rules, where applicable, can be updated.
