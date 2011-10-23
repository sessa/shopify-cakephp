shopify-sandbox
================

This project integrates the Shopify PHP API with CakePHP.


Usage
-----

### Installation

Copy this directory to your environment's vendors folder, then add this line to
config/bootstrap.php:

    App::import('Vendor', 'shopify-cakephp/cake/shopify_component');

Then, add 'Shopify' as a component in yourcontroller class.

API configuration may be done as part of loading the component (shown below), or
by setting the constants found in lib/shopify_api_config.php.

    class TestController {
        var $components = array(
            'Shopify'   => array(
                'api_key'       => '99999999999999999999999999999999',
                'api_secret'    => '11111111111111111111111111111111',
            ),
        );
    }


### Authentication

To authenticate a user, call `$this->Shopify->login($domain)` from your controller,
where `$domain` is the domain of the shop in question. ShopifyComponent will attempt
to automatically maintain the Shopify session across pageloads. To logout, use
`$this->Shopify->logout()`.

### API calls

ShopifyComponent acts transparently - you may use any and all calls documented
in the official PHP API, as if you were using the API itself. For example, to
fetch two products, use the following in your controller:

    $products = $this->Shopify->product->get(0, 0, array('limit' => 2));

Complete documentation for API functions can be found in PHP_Shopify_API_Documentation.rtf
(see API_README.md).


License
-------

The following files are released under [the Apache License, Version 2.0](http://www.apache.org/licenses/LICENSE-2.0):

* cake/shopify_component.php

No claim to copyright is made on any other files, including CakePHP and the
Shopify PHP API (which are licensed independently).
