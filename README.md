# magento-2.3.5-paynamics
Magento 2.3.5 Paynamics payment module

Package name: paynamics/module-magento2-gateway

Instructions:

Option 1: 
Install git via composer.
1. Add composer repository for the plugin.
   composer config repositories.paynamics git "https://github.com/jstuvwxyz/magento-2.3.5-paynamics.git"

2. Install git via composer.
   composer require paynamics/module-magento2-gateway

Option 2:
1. Create 'code' folder under app and install the module in your directory.
   Ex. app/code/Paynamics/Gateway

2. Run CMD or terminal and go to your Magento directory. Then run "php bin/magento setup:upgrade".
   Ex. C:\wamp64\www\magento>php bin/magento setup:upgrade

3. Clear cache to make sure updates will reflect.

4. Configure your merchant credentials in the admin configuration page.
   Merchant credentials will be provided by your contact person.

Happy coding! :)
