<?php
	/*
		ShopifyAPI Config File
		You can find your API Key, and Secret in your Shopify partners account (http://www.shopify.com/partners/)
	*/

	define('SHOPIFY_API_KEY', '');
	define('SHOPIFY_SECRET', '');
	define('SHOPIFY_FORMAT', 'xml');
	define('SHOPIFY_GZIP_ENABLED', true); // set to false if you do not want gzip encoding. If false SHOPIFY_GZIP_PATH is not needed to be set
	define('SHOPIFY_GZIP_PATH', '/tmp'); // path for gzip decoding (this file will need write permissions)
	define('SHOPIFY_USE_SSL', true);

	/* These values only need to be set if SHOPIFY_USE_SSL is true and the API cannot verify the certificate */
	define('SHOPIFY_USE_SSL_PEM', false); //set to true if pem file is needed
	define('SHOPIFY_CA_FILE', '/full/path/to/cacert.pem');
		
	/*
		Note that all XML tags with an - in the tag name are returned with a _ (underscore) in JSON	
	*/
?>