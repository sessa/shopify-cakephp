<?php
/*
	Shopify API in PHP
	Created: May 4th, 2010
	Modified: October 8th, 2010
	Version: 1.20101008.2
*/

  include('shopify_api_config.php');

	//this function is just to make the code a little cleaner
	function shopify_isEmpty($string){
		return (strlen(trim($string)) == 0);
	}
	
	/* Special XML Attributes for posting to Shopify */
	/* Created function for these special cases because constant arrays cannot be defined */
	function shopify_specialCases($key = ''){
	  return array(
  	  'variants' => 'variant',
  	  'images' => 'image',
  	  'options' => 'option',
  	  'line-items' => 'line-item'
  	);
	}
	
	//this function will url encode paramaters assigned to API calls
	function shopify_url_encode_array($params){
		$string = '';
		if (is_array($params)){
			foreach($params as $k => $v) if (!is_array($v)) $string .= $k.'='.str_replace(' ', '%20', $v).'&';
			$string = substr($string, 0, strlen($string) - 1);
		}
		return $string;
	}
	
	/*
		shopify_organizeArray applies some changes to the array that is generated from returned XML
		This is done so that traversing the result is easier to manipulate by setting the index
		of returned data to the actual ID of the record
	*/
	function shopify_organizeArray($array, $type){
		if (!is_array($array)) return array($type => array());
		if (isset($array['error'])) return array($type => $array);
			
		if (SHOPIFY_FORMAT == "json"){
			if (isset($array[$type . 's'])){
				$array[$type] = $array[$type . 's'];
				unset($array[$type . 's']);
			}
		}
		
		/* no organizing needed */
		if (!isset($array[$type][0])){
			$temp = $array[$type];
			$id = $temp['id'];
			$array[$type] = array();
			$array[$type][$id] = $temp;
		}else{
			foreach($array[$type] as $k => $v){
		    $id = (isset($v['key'])) ? $v['key'] : $v['id'];
				$array[$type][$id] = $v;
				unset($array[$type][$k]);
			}
		}
		
		return $array;
	}
	
	function shopify_arrayToXML($array, $xml = '', $specialCaseTag = ''){
	  if ($xml == "") $xml = '<?xml version="1.0" encoding="UTF-8"?>';
	  $shopify_specialCases = shopify_specialCases();
		foreach($array as $k => $v){
		  if (is_numeric($k) && !shopify_isEmpty($specialCaseTag)) $k = $specialCaseTag;
			if (is_array($v)){
			  if (array_key_exists($k, $shopify_specialCases)){
				  $xml .= '<' . $k . ' type="array">';
				  $xml = shopify_arrayToXML($v, $xml, $shopify_specialCases[$k]);
			  }else{
			    $xml .= '<' . $k . '>';
				  $xml = shopify_arrayToXML($v, $xml);
			  }
				$xml .= '</' . $k . '>';
			}else{
				$xml .= '<' . $k . '>' . $v . '</' . $k . '>';
			}
		}	
		return $xml;
	}
	
	function shopify_sendToAPI($url, $request = 'GET', $xml = array()){
		if ($request != "GET"){
			if (substr_count($url, '?') > 0){
				$url = str_replace('?', '.' . SHOPIFY_FORMAT . '?', $url);
			}else{
				$url .= '.' . SHOPIFY_FORMAT;
			}
		}else{
			if (substr_count($url, '?') > 0){
				$url = str_replace('?', '.xml?', $url);
			}else{
				$url .= '.xml';
			}
		}

    $xml = (sizeof($xml) > 0) ? shopify_arrayToXML($xml) : false;
		$ch = new ShopifyminiCURL();
		$data = $ch->send($url, $request, $xml);
	  return $ch->loadString($data);
	}
	
	function shopify_gzdecode($data){
		$g = tempnam(SHOPIFY_GZIP_PATH, 'ff');
		@file_put_contents($g, $data);
		ob_start();
		readgzfile($g);
		$d = ob_get_clean();
		unlink($g);
		return $d;
	}

	class ShopifyApplicationCharge{
		private $prefix = "/application_charges";
		private $array = array();
		
		public function __construct($site){
			$this->prefix = $site .  $this->prefix;
		}
		
		public function get($id = 0, $cache = false){
		  if ($id == 0){
		    if (!$cache || !isset($this->array['application-charge'])) $this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix), 'application-charge');
		    return $this->array['application-charge'];
		  }else{
		    if (!$cache || !isset($this->array['application-charge'][$id])){
		      $temp = shopify_sendToAPI($this->prefix . "/" . $id);
		      $this->array['application-charge'][$id] = $temp;
		    }
		    return $this->array['application-charge'][$id];
		  }
		}
		
		public function create($fields){
			$fields = array('application-charge' => $fields);
			return shopify_sendToAPI($this->prefix, 'POST', $fields);
		}
		
		public function activate($id){
		  if (!isset($this->array['application-charge'][$id])) $this->get($id);
			return shopify_sendToAPI($this->prefix . "/" . $id . "/activate", 'POST', array('application-charge' => $this->array['application-charge'][$id]));
		}
		
		public function __destruct(){
			unset($this->prefix);
			unset($this->array);
		}
	}
	
	class ShopifyRecurringApplicationCharge{
		private $prefix = "/recurring_application_charges";
		private $array = array();
		
		public function __construct($site){
			$this->prefix = $site .  $this->prefix;
		}
		
		public function get($id = 0, $cache = false){
		  if ($id == 0){
		    if (!$cache || !isset($this->array['recurring-application-charge'])) $this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix), 'recurring-application-charge');
		    return $this->array['recurring-application-charge'];
		  }else{
		    if (!$cache || !isset($this->array['recurring-application-charge'][$id])){
		      $temp = shopify_sendToAPI($this->prefix . "/" . $id);
		      $this->array['recurring-application-charge'][$id] = $temp;
		    }
		    return $this->array['recurring-application-charge'][$id];
		  }
		}
		
		public function create($fields){
			$fields = array('recurring-application-charge' => $fields);
			return shopify_sendToAPI($this->prefix, 'POST', $fields);
		}
		
		public function activate($id){
		  if (!isset($this->array['recurring-application-charge'][$id])) $this->get($id);
			return shopify_sendToAPI($this->prefix . "/" . $id . "/activate", 'POST', array('recurring-application-charge' => $this->array['recurring-application-charge'][$id]));
		}
		
		public function cancel($id){
			return shopify_sendToAPI($this->prefix . "/" . $id, 'DELETE');
		}
		
		public function __destruct(){
			unset($this->prefix);
			unset($this->array);
		}
	}	

	class ShopifyArticle{
		private $prefix = "/blogs/";
		private $array = array();
		public $metafield;
		
		public function __construct($site){
			$this->prefix = $site . $this->prefix;
			$this->metafield = new ShopifyMetafield($site, "articles");
		}
		
		public function get($blog_id, $article_id = 0, $cache = false, $params = array()){
			if ($article_id == 0){
				if (!$cache || !isset($this->array['article'])){
					$params = shopify_url_encode_array($params);
					$this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix . $blog_id . "/articles?" . $params), 'article');
				}			
				return $this->array['article'];
			}else{
				if (!$cache || !isset($this->array['article'][$article_id])){
					$temp = shopify_sendToAPI($this->prefix . $blog_id . "/articles/" . $article_id);
					$this->array['article'][$article_id] = $temp;
				}
				return $this->array['article'][$article_id];
			}
		}
		
		public function count($blog_id, $params = array()){
			$params = shopify_url_encode_array($params);
			return shopify_sendToAPI($this->prefix . $blog_id . "/articles/count?" . $params);
		}
		
		public function create($blog_id, $fields){
			$fields = array('article' => $fields);
			return shopify_sendToAPI($this->prefix . $blog_id . "/articles", 'POST', $fields);
		}
		
		public function modify($blog_id, $id, $fields){
			$fields = array('article' => $fields);
			return shopify_sendToAPI($this->prefix . $blog_id . "/articles/" . $id, 'PUT', $fields);
		}
		
		public function remove($id){
			return shopify_sendToAPI($this->prefix . $blog_id . "/articles/" . $id, 'DELETE');
		}
		
		public function __destruct(){
			unset($this->prefix);
			unset($this->array);
			unset($this->metafield);
		}
	}
	
	class ShopifyAsset{
		private $prefix = "/assets";
		private $array = array();
		
		public function __construct($site){
			$this->prefix = $site . $this->prefix;
		}
			
		public function get($key = '', $cache = false){
			if (shopify_isEmpty($key)){
				if (!$cache || !isset($this->array['asset'])) $this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix), 'asset');		
				return $this->array['asset'];
			}else{
				if (!$cache || !isset($this->array['asset'][$key])){
					$temp = shopify_sendToAPI($this->prefix . '?asset[key]=' . $key);
					$this->array['asset'][$key] = $temp;
				}
				return $this->array['asset'][$key];
			}
		}
			
		public function modify($fields){
			$fields = array('asset' => $fields);
			return shopify_sendToAPI($this->prefix, 'PUT', $fields);
		}
		
		public function copy($fields){
			$fields = array('asset' => $fields);
			return shopify_sendToAPI($this->prefix, 'PUT', $fields);			
		}
		
		public function remove($key){
			return shopify_sendToAPI($this->prefix . "?asset[key]=" . $key, 'DELETE');
		}
		
		public function __destruct(){
			unset($this->prefix);
			unset($this->array);
		}		
	}
	
	class ShopifyBlog{
		private $prefix = "/blogs";
		private $array = array();
		public $metafield;
		
		public function __construct($site){
			$this->prefix = $site .  $this->prefix;
			$this->metafield = new ShopifyMetafield($site, "blogs");
		}
		
		public function get($id = 0, $cache = false){
			if ($id == 0){
				if (!$cache || !isset($this->array['blog'])) $this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix), 'blog');
				return $this->array['blog'];
			}else{
				if (!$cache || !isset($this->array['blog'][$id])){
					$temp = shopify_sendToAPI($this->prefix . "/" . $id);
					$this->array['blog'][$id] = $temp;
				}
				return $this->array['blog'][$id];
			}
		}
		
		public function count(){
			return shopify_sendToAPI($this->prefix . "/count" . "?");
		}

		public function create($fields){
			$fields = array('blog' => $fields);
			return shopify_sendToAPI($this->prefix, 'POST', $fields);
		}
		
		public function modify($id, $fields){
			$fields = array('blog' => $fields);
			return shopify_sendToAPI($this->prefix . "/" . $id, 'PUT', $fields);
		}
		
		public function remove($blog_id, $id){
			return shopify_sendToAPI($this->prefix . "/" . $id, 'DELETE');
		}
		
		public function __destruct(){
			unset($this->prefix);
			unset($this->array);
			unset($this->metafield);
		}
	}
	
	class ShopifyCustomCollection{
		private $prefix = "/";	
		private $array = array();
		public $metafield;
		
		public function __construct($site){
			$this->prefix = $site . $this->prefix;
			$this->metafield = new ShopifyMetafield($site, "custom_collections");
		}
		
		public function get($id = 0, $params = array(), $cache = false){
			if ($id == 0){
				if (!$cache || !isset($this->array['custom-collection'])){
					$params = shopify_url_encode_array($params);
					$this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix . "custom_collections?" . $params), 'custom-collection');
				}			
				return $this->array['custom-collection'];
			}else{
				if (!$cache || !isset($this->array['custom-collection'][$id])){
					$temp = shopify_sendToAPI($this->prefix . "/custom_collections/" . $id);
					$this->array['custom-collection'][$id] = $temp;				
				}
				return $this->array['custom-collection'][$id];
			}
		}
		
		public function count($params = array()){
			$params = shopify_url_encode_array($params);			
			return shopify_sendToAPI($this->prefix . "custom_collections/count?" . $params);
		}
		
		public function create($fields){
			$fields = array('custom-collection' => $fields);
			return shopify_sendToAPI($this->prefix . "custom_collections", 'POST', $fields);
		}
		
		public function modify($id, $fields){
			$fields = array('custom-collection' => $fields);
			return shopify_sendToAPI($this->prefix . "custom_collections/" . $id, 'PUT', $fields);
		}
		
		public function remove($id){
			return shopify_sendToAPI($this->prefix . "custom_collections/" . $id, 'DELETE');
		}
		
		public function __destruct(){
			unset($this->prefix);
			unset($this->array);
			unset($this->metafield);
		}
	}
	
	class ShopifyCollect{
		private $prefix = "/";
		private $array = array();
		
		public function __construct($site){
			$this->prefix = $site . $this->prefix;
		}
		
		public function get($id = 0, $params = array(), $cache = false){
			if ($id == 0){
				if (!$cache || !isset($this->array['collect'])){
					$params = shopify_url_encode_array($params);
					$this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix . "collects?" . $params), 'collect');
				}			
				return $this->array['collect'];
			}else{
				$collect = array();

				if (!$cache || !isset($this->array['collect'][$id])){
					$params = shopify_url_encode_array($params);
					if ($id > 0){
						$temp = shopify_sendToAPI($this->prefix . "collects/" . $id . "?" . $params);
						$this->array['collect'][$id] = $temp;
						$collect = $temp;
					}else{
						if (isset($params['product_id']) && isset($params['collection_id'])){
							$temp = shopify_sendToAPI($this->prefix . "/collects?" . $params);

							if (isset($temp['collect'][0])){
								$id = $temp['collect'][0]['id'];
								$this->array['collect'][$id] = $temp['collect'][0];
								$collect = $temp['collect'][0];
							}
						}else{
							throw new Exception("Must specify a collect id or product_id and collection_id in the params array if trying to fetch a specific ShopifyCollect.");										
						}
					}
				}

				return $collect;
			}
		}
		
		public function count($params = array()){
			$params = shopify_url_encode_array($params);
			return shopify_sendToAPI($this->prefix . "collects?" . $params);
		}
		
		public function create($fields){
			$fields = array('collect' => $fields);
			return shopify_sendToAPI($this->prefix . "collects", 'POST', $fields);
		}
		
		public function remove($id){
			return shopify_sendToAPI($this->prefix . "custom_collections", 'DELETE');
		}
					
		public function __destruct(){
			unset($this->prefix);
			unset($this->array);
		}
	}
	
	class ShopifyComment{
		private $prefix = "/";
		private $array = array();
		
		public function __construct($site){
			$this->prefix = $site . $this->prefix;
		}
		
		public function get($id = 0, $params = array(), $cache = false){
			if ($id == 0){
				if (!$cache || !isset($this->array['comment'])){
					$params = shopify_url_encode_array($params);
					$this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix . "comments?" . $params), 'comment');
				}			
				return $this->array['comment'];
			}else{
				if (!$cache || !isset($this->array['comment'][$id])){
					$temp = shopify_sendToAPI($this->prefix . "comments/" . $id);
					$this->array['comment'][$id] = $temp;
				}
				return $this->array['comment'][$id];
			}
		}
		
		public function count($params = array()){
			$params = shopify_url_encode_array($params);
			return shopify_sendToAPI($this->prefix . "comments/count?" . $params);
		}
		
		public function create($fields){
			$fields = array('comment' => $fields);
			return shopify_sendToAPI($this->prefix . "comments", 'POST', $fields);
		}
		
		public function modify($id, $fields){
			$fields = array('comment' => $fields);
			return shopify_sendToAPI($this->prefix . "comments/" . $id, 'POST', $fields);
		}
		
		public function markAsSpam($id){
			return shopify_sendToAPI($this->prefix . "comments/" . $id . "/spam", 'POST');
		}

		public function approve($id){
			return shopify_sendToAPI($this->prefix . "comments/" . $id . "/approve", 'POST');
		}
		
		public function __destruct(){
			unset($this->prefix);
			unset($this->array);
		}
	}
	
	class ShopifyCountry{
		private $prefix = "/";
		private $array = array();
		
		public function __construct($site){
			$this->prefix = $site . $this->prefix;
		}
		
		public function get($id = 0, $cache = false){
			if ($id == 0){
				if (!$cache && !isset($this->array['country'])) $this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix . "countries"), 'country');
				return $this->array['country'];
			}else{
				if (!$cache || !isset($this->array['country'][$id])){
					$temp = shopify_sendToAPI($this->prefix . "countries/" . $id);
					$this->array['country'][$id] = $temp;
				}
				return $this->array['country'][$id];
			}
		}
		
		public function count(){
			return shopify_sendToAPI($this->prefix . "countries/count");
		}
		
		public function create($fields){
			$fields = array('country' => $fields);
			return shopify_sendToAPI($this->prefix . "countries", 'POST', $fields);
		}
		
		public function modify($id, $fields){
			$fields = array('country' => $fields);
			return shopify_sendToAPI($this->prefix . "countries/" . $id, 'PUT', $fields);
		}
		
		public function remove($id){
			return shopify_sendToAPI($this->prefix . "countries/" . $id, 'DELETE');
		}
		
		public function __destruct(){
			unset($this->prefix);
			unset($this->array);
		}
	}
	
	class ShopifyEvent{
		private $prefix = "/";
		private $array;
		
		public function __construct($site){
			$this->prefix = $site . $this->prefix;
		}
		
		public function get($id = 0, $order = 0, $product = 0, $params = array()){
			if ($product == 0 && $order == 0){
				if ($event_id == 0){
					$params = shopify_url_encode_array($params);
					$this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix . "events?" . $params), 'event');			
					return $this->array['event'];
				}else{
					if (!$cache){
						$temp = shopify_sendToAPI($this->prefix . "events/" . $id);
						$this->array['event'][$id] = $temp;
					}			
					if (!isset($this->array['event'][$id])) throw new Exception("ShopifyEvent not found in the cache. Set cache to false.");
					return $this->array['event'][$id];
				}
			}
			else if ($product > 0 && $order == 0){
				$params = shopify_url_encode_array($params);			
				$this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix . "products/" . $id . "/events?" . $params), 'event');			
				return $this->array['event'];
			}
			else if ($product == 0 && $order > 0){
				$params = shopify_url_encode_array($params);
				$this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix . "orders/" . $id . "/events?" . $params), 'event');			
				return $this->array['event'];
			}
		}
		
		public function __destruct(){
			unset($this->prefix);
			unset($this->array);
		}
	}
	
	class ShopifyFulfillment{
		private $prefix = "/orders/";
		private $array = array();
		
		public function __construct($site){
			$this->prefix = $site . $this->prefix;
		}
	
		public function get($order_id, $id = 0, $params = array(), $cache = false){
			if ($id == 0){
				if (!$cache || !isset($this->array['fulfillment'])){
					$params = shopify_url_encode_array($params);
					$this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix . $order_id . "/fulfillments?" . $params), 'fulfillment');
				}			
				return $this->array['fulfillment'];
			}else{
				if (!$cache || !isset($this->array['fulfillment'][$id])){
					$temp = shopify_sendToAPI($this->prefix . $order_id . "/fulfillments/" . $id);
					$this->array['fulfillment'][$id] = $temp;
				}
				return $this->array['fulfillment'][$id];
			}
		}
		
		public function count($order_id, $params = array()){
			$params = shopify_url_encode_array($params);
			return shopify_sendToAPI($this->prefix . $order_id . "/fulfillments/count?" . $params);
		}
		
		public function create($order_id, $fields){
			$fields = array('fulfillment' => $fields);
			return shopify_sendToAPI($this->prefix . $order_id . "/fulfillments", 'POST', $fields);
		}
		
		public function fulfill($order_id, $id, $fields){
			$fields = array('fulfillment' => $fields);
			return shopify_sendToAPI($this->prefix . $order_id . "/fulfillments", 'POST', $fields);
		}
		
		public function modify($order_id, $id, $fields){
			$fields = array('article' => $fields);
			return shopify_sendToAPI($this->prefix . $order_id . "/fulfillments/" . $id, 'PUT', $fields);
		}
		
		public function __destruct(){
			unset($this->prefix);
			unset($this->array);
		}
	}
	
	class ShopifyMetafield{
		private $prefix = "/";
		private $object = "";
		private $array;
		
		public function __construct($site, $object){
			$this->prefix = $site . $this->prefix;
			if (!shopify_isEmpty($object)) $this->prefix .= $object . "/";
			$this->object = $object;
		}
		
		public function get($metafield_id = 0, $id = 0, $params = array(), $cache = false){
		  $params = shopify_url_encode_array($params);
		  
		  if ($id == 0){
		    if ($metafield_id == 0){
		      $this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix . "metafields?" . $params, 'GET'), 'metafield');
		    }else{
		      $temp = shopify_sendToAPI($this->prefix . "metafields/" . $id . ".xml?" . $params);
		      $this->array['metafield'][$metafield_id] = $temp;
		      return $this->array['metafield'][$metafield_id];
		    }
		  }else{
		    if (shopify_isEmpty($this->object)){
		      throw new Exception("Must provide an object id");		      
		    }else{
		      if ($metafield_id == 0){
					  $this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix . $id . "/metafields?" . $params, 'GET'), 'metafield');
		      }else{
            $temp = shopify_sendToAPI($this->prefix . $id . "/metafields/" . $metafield_id . ".xml?" . $params, 'GET');
				  }
		    }
		  }
		  
			return $this->array['metafield'];
		}
				
		public function create($object_id, $fields){
			$fields = array('metafield' => $fields);
			return ($object_id > 0) ? shopify_sendToAPI($this->prefix . $object_id . "/metafields", 'POST', $fields) : shopify_sendToAPI($this->prefix . "metafields", 'POST', $fields);
		}
		
		public function modify($object_id, $id, $fields){
			$fields = array('metafield' => $fields);
			return ($object_id > 0) ? shopify_sendToAPI($this->prefix . $object_id . "/metafields/" . $id, 'PUT', $fields) : shopify_sendToAPI($this->prefix . "metafields/" . $id, 'PUT', $fields);
		}
		
		public function remove($object_id, $id){
			return ($object_id > 0) ? shopify_sendToAPI($this->prefix . $object_id . "/metafields/" . $id, 'DELETE') : shopify_sendToAPI($this->prefix . "metafields/" . $id, 'DELETE');
		}
		
		public function __destruct(){
			unset($this->prefix);
			unset($this->array);
			unset($this->object);
		}
	}
	
	class ShopifyOrder{
		private $prefix = "/";
		private $array = array();
		public $metafield;
		
		public function __construct($site){
			$this->prefix = $site . $this->prefix;
		}
		
		public function get($id = 0, $params = array(), $cache = false){
			if ($id == 0){
				if (!$cache || !isset($this->array['order'])){
					$params = shopify_url_encode_array($params);
					$this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix . "orders?" . $params), 'order');
				}			
				return $this->array['order'];
			}else{
				if (!$cache || !isset($this->array['order'][$id])){
					$temp = shopify_sendToAPI($this->prefix . "orders/" . $id);
					$this->array['order'][$id] = $temp;
				}
				return $this->array['order'][$id];
			}
		}		
		
		public function count($params = array()){
			$params = shopify_url_encode_array($params);
			return shopify_sendToAPI($this->prefix . "orders/count?" . $params);
		}
		
		public function open($id){
			return shopify_sendToAPI($this->prefix . "orders/" . $id . "/open", 'POST');
		}
		
		public function close($id){
			return shopify_sendToAPI($this->prefix . "orders/" . $id . "/close", 'POST');
		}
		
		public function modify($id, $fields){
			$fields = array('order' => $fields);
			return shopify_sendToAPI($this->prefix . "orders/" . $id, 'PUT', $fields);
		}
		
		public function setNoteAttributes($id, $fields){
			$fields = array('order' => array('id' => $id, 'note-attributes' => array('note-attribute' => $fields)));
			return shopify_sendToAPI($this->prefix . "orders/" . $id, 'PUT', $fields);
		}
		
		public function remove($id){
		  return shopify_sendToAPI($this->prefix . "orders/" . $id, 'DELETE');
		}
			
		public function __destruct(){
			unset($this->prefix);
			unset($this->array);
		}
	}
	
	class ShopifyPage{
		private $prefix = "/";
		private $array = array();
		public $metafield;
		
		public function __construct($site){
			$this->prefix = $site . $this->prefix;
			$this->metafield = new ShopifyMetafield($site, "pages");
		}
		
		public function get($id = 0, $params = array(), $cache = false){
			if ($id == 0){
				$params = shopify_url_encode_array($params);
				if (!$cache || !isset($this->array['page'])) $this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix . "pages?" . $params), 'page');
				return $this->array['page'];
			}else{
				if (!$cache || !isset($this->array['page'][$id])){
					$temp = shopify_sendToAPI($this->prefix . "pages/" . $id);
					$this->array['page'][$id] = $temp;
				}
				return $this->array['page'][$id];
			}
		}
		
		public function count($params = array()){
			return shopify_sendToAPI($this->prefix . "pages/count?" . $params);
		}
		
		public function create($fields){
			$fields = array('page' => $fields);
			return shopify_sendToAPI($this->prefix . "pages", 'POST', $fields);
		}
		
		public function modify($id, $fields){
			$fields = array('page' => $fields);
			return shopify_sendToAPI($this->prefix . "pages/" . $id .SHOPIFY_FORMAT, 'PUT', $fields);
		}
		
		public function remove($id){
			return shopify_sendToAPI($this->prefix . "pages/" . $id, 'DELETE');
		}
		
		public function __destruct(){
			unset($this->prefix);
			unset($this->array);
			unset($this->metafields);
		}
	}
	
	class ShopifyProduct{
		private $prefix = "/";
		private $array = array();
		public $metafield;
		
		public function __construct($site){
			$this->prefix = $site . $this->prefix;
			$this->metafield = new ShopifyMetafield($site, "products");
		}
		
		public function get($id = 0, $collection_id = 0, $params = array(), $cache = false){
			if ($id == 0){
				if (!$cache || !isset($this->array['product'])){
					$params = shopify_url_encode_array($params);
					$xmlObj = ($collection_id > 0) ? shopify_sendToAPI($this->prefix . "products?collection_id=" . $collection_id . "&" . $params) : shopify_sendToAPI($this->prefix . "products?" . $params);
					$this->array = shopify_organizeArray($xmlObj, 'product');
				}			
				return $this->array['product'];
			}else{
				if (!$cache || !isset($this->array['product'][$id])){
					$temp = shopify_sendToAPI($this->prefix . "products/" . $id);
					$this->array['product'][$id] = $temp;
				}
				return $this->array['product'][$id];
			}
		}
		
		public function count($collection_id = 0, $params = array()){
			$params = shopify_url_encode_array($params);
			return ($collection_id > 0) ? shopify_sendToAPI($this->prefix . "products/count?collection_id=" . $collection_id . "&" . $params) : shopify_sendToAPI($this->prefix . "products/count?" . $params);
		}
				
		public function create($fields){
			$fields = array('product' => $fields);
			return shopify_sendToAPI($this->prefix . "products", 'POST', $fields);
		}
		
		public function modify($id, $fields){
			$fields = array('product' => $fields);
			return shopify_sendToAPI($this->prefix . "products/" . $id, 'PUT', $fields);
		}
		
		public function remove($id){
			return shopify_sendToAPI($this->prefix . "products/". $id, 'DELETE');
		}
		
		public function __destruct(){
			unset($this->prefix);
			unset($this->array);
			unset($this->metafield);
		}
	}
	
	class ShopifyProductImage{
		private $prefix = "/products/";
		private $array;
		
		public function __construct($site){
			$this->prefix = $site . $this->prefix;
		}
		
		public function get($product_id, $cache = false){
			if (!$cache || !isset($this->array['image'])) $this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix . $product_id . "/images"), 'image');
			return $this->array['image'];
		}
		
		public function create($product_id, $fields){
			$fields = array('image' => $fields);
			return shopify_sendToAPI($this->prefix . $product_id . "/images", 'POST', $fields);
		}
		
		public function remove($product_id, $id){
			return shopify_sendToAPI($this->prefix . $product_id . "/images/". $id, 'DELETE');
		}
		
		public function __destruct(){
			unset($this->prefix);
			unset($this->array);
		}
	}
	
	class ShopifyProductVariant{
		private $prefix = "/products/";
		private $array = array();
		public $metafield;
		
		public function __construct($site){
			$this->prefix = $site . $this->prefix;
			$this->metafield = new ShopifyMetafield($site, "variants");
		}
		
		public function get($product_id, $id = 0, $cache = false){
			if ($id == 0){
				if (!$cache || !isset($this->array['variant'])) $this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix . $product_id . "/variants?"), 'variant');
				return $this->array['variant'];
			}else{
				if (!$cache || !isset($this->array['variant'][$id])){
					$temp = shopify_sendToAPI($this->prefix . $product_id . "/variants/" . $id);
					$this->array['variant'][$id] = $temp;
				}
				return $this->array['variant'][$id];	
			}
		}
		
		public function count($product_id){
			return shopify_sendToAPI($this->prefix . $product_id . "/variants/count");
		}
		
		public function create($product_id, $fields){
			$fields = array('variant' => $fields);
			return shopify_sendToAPI($this->prefix . $product_id . "/variants", 'POST', $fields);
		}
		
		public function modify($product_id, $id, $fields){
			$fields = array('variant' => $fields);
			return shopify_sendToAPI($this->prefix . $product_id . "/variants/" . $id, 'PUT', $fields);
		}
		
		public function remove($product_id, $id){
			return shopify_sendToAPI($this->prefix . $product_id . "/variants/" . $id, 'DELETE');
		}
		
		public function __destruct(){
			unset($this->prefix);
			unset($this->array);
		}
	}
	
	class ShopifyProvince{
		private $prefix = "/countries/";
		private $array = array();
		
		public function __construct($site){
			$this->prefix = $site . $this->prefix;
		}
		
		public function get($country_id, $id = 0, $cache = false){
			if ($id == 0){
				if (!$cache || !isset($this->array['province'])) $this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix . $country_id . "/provinces"), 'province');
				return $this->array['province'];
			}else{
				if (!$cache || !isset($this->array['province'][$id])){
					$temp = shopify_sendToAPI($this->prefix . $country_id . "/provinces/" . $id);
					$this->array['province'][$id] = $temp;
				}
				return $this->array['province'][$id];
			}
		}
		
		public function count($country_id){
			return shopify_sendToAPI($this->prefix . $country_id . "/provinces/count");
		}
		
		public function modify($country_id, $id, $fields){
			$fields = array('province' => $fields);
			return shopify_sendToAPI($this->prefix . $country_id . "/provinces/" . $id, 'PUT', $fields);
		}
		
		public function __destruct(){
			unset($this->prefix);
			unset($this->array);
		}
	}
	
	class ShopifyRedirect{
		private $prefix = "/";
		private $array = array();
		
		public function __construct($site){
			$this->prefix = $site . $this->prefix;
		}
		
		public function get($id = 0, $params = array(), $cache = false){
			if ($id == 0){
				if (!$cache || !isset($this->array['redirect'])){
					$params = shopify_url_encode_array($params);
					$this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix . "redirects?" . $params), 'redirect');
				}		
				return $this->array['redirect'];
			}else{
				if (!$cache || !isset($this->array['redirect'][$id])){
					$temp = shopify_sendToAPI($this->prefix . "redirects/" . $id .SHOPIFY_FORMAT);
					$this->array['redirect'][$id] = $temp;
				}
				return $this->array['redirect'][$id];
			}
		}
		
		public function count($params = array()){
			$params = shopify_url_encode_array($params);
			return shopify_sendToAPI($this->prefix . "redirects/count?" . $params);
		}
		
		public function create($fields){
			$fields = array('redirect' => $fields);
			return shopify_sendToAPI($this->prefix . "redirects", 'POST', $fields);
		}
		
		public function modify($id, $fields){
			$fields = array('redirect' => $fields);
			return shopify_sendToAPI($this->prefix . "redirects/" . $id, 'PUT', $fields);
		}
		
		public function remove($id){
			return shopify_sendToAPI($this->prefix . "redirects/" . $id, 'DELETE');
		}
		
		public function __destruct(){
			unset($this->prefix);
			unset($this->array);
		}
	}
	
	class ShopifyShop{
		private $prefix = "/";
		
		public function __construct($site){
			$this->prefix = $site . $this->prefix;
		}
		
		public function get(){
			return shopify_sendToAPI($this->prefix . "shop");
		}
		
		public function __destruct(){
			unset($this->prefix);
		}
	}
	
	class ShopifySmartCollection{
		private $prefix = "/";	
		private $array = array();
		public $metafield;
		
		public function __construct($site){
			$this->prefix = $site . $this->prefix;
			$this->metafield = new ShopifyMetafield($site, "smart_collections");
		}
		
		public function get($id = 0, $params =  array(), $cache = false){
			if ($id == 0){
				if (!$cache || !isset($this->array['smart-collection'])){
					$params = shopify_url_encode_array($params);
					$this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix . "smart_collections?" . $params), 'smart-collection');
				}
				return $this->array['smart-collection'];
			}else{
				if (!$cache || !isset($this->array['smart-collection'][$id])){
					$temp = shopify_sendToAPI($this->prefix . "/smart_collections/" . $id);
					$this->array['smart-collection'][$id] = $temp;				
				}
				return $this->array['smart-collection'][$id];
			}
		}
		
		public function count($params = array()){
			$params = shopify_url_encode_array($params);			
			return shopify_sendToAPI($this->prefix . "smart_collections/count?" . $params);			
		}
		
		public function create($fields){
			$fields = array('smart-collection' => $fields);
			return shopify_sendToAPI($this->prefix . "smart_collections", 'POST', $fields);
		}
		
		public function modify($id, $fields){
			$fields = array('smart-collection' => $fields);
			return shopify_sendToAPI($this->prefix . "smart_collections/" . $id, 'PUT', $fields);	
		}
		
		public function delete($id){
			return shopify_sendToAPI($this->prefix . "smart_collections/" . $id, 'DELETE');
		}
		
		public function __destruct(){
			unset($this->prefix);
			unset($this->array);
			unset($this->metafield);
		}		
	}
	
	class ShopifyTransaction{
		private $prefix = "/orders/";
		private $array = array();
		
		public function __construct($site){
			$this->prefix = $site . $this->prefix;
		}
		
		public function get($order_id, $id = 0, $cache = false){
			if ($id == 0){
				if (!$cache || !isset($this->array['transaction'])) $this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix . $order_id . "/transactions"), 'transaction');			
				return $this->array['transaction'];
			}else{
				if (!$cache || !isset($this->array['transaction'][$id])){
					$temp = shopify_sendToAPI($this->prefix . $order_id . "/transactions/" . $id);
					$this->array['transaction'][$id] = $temp;
				}
				return $this->array['transaction'][$id];
			}
		}
		
		public function count($order_id){
			return shopify_sendToAPI($this->prefix . $order_id . "/transactions/count");
		}

		public function create($order_id, $fields){
			$fields = array('transaction' => $fields);
			return shopify_sendToAPI($this->prefix . $order_id . "/transactions", 'POST', $fields);
		}
		
		public function __destruct(){
			unset($this->prefix);
			unset($this->array);
		}
	}
	
	class ShopifyWebhook{
		private $prefix = "/";
		private $array = array();
		
		public function __construct($site){
			$this->prefix = $site . $this->prefix;
		}
		
		public function get($id = 0, $params = array(), $cache = false){
			if ($id == 0){
				if (!$cache || !isset($this->array['webhook'])) $this->array = shopify_organizeArray(shopify_sendToAPI($this->prefix . "webhooks?" . $params), 'webhook');
				return $this->array['webhook'];
			}else{
				if (!$cache || !isset($this->array['webhook'][$id])){
					$temp = shopify_sendToAPI($this->prefix . "webhooks/" . $id);
					$this->array['webhook'][$id] = $temp;
				}
				return $this->array['webhook'][$id];
			}
		}
		
		public function count($params = array()){
		  $params = shopify_url_encode_array($params);
		  return shopify_sendToAPI($this->prefix . "webhooks/count?" . $params);
		}
		
		public function create($fields){
			$fields = array('webhook' => $fields);
			return shopify_sendToAPI($this->prefix . "webhooks", 'POST', $fields);
		}
		
		public function modify($id, $fields){
			$fields = array('webhook' => $fields);
			return shopify_sendToAPI($this->prefix . "webhooks/" . $id, 'PUT', $fields);
		}
		
		public function remove($id){
			return shopify_sendToAPI($this->prefix . "webhooks/". $id, 'DELETE');
		}
		
		public function __destruct(){
			unset($this->prefix);
			unset($this->array);
		}
	}
	
	class ShopifySession{
		private $api_key;
		private $secret;
		private $protocol = 'https';
		private $private = false;
		
		private $url;
		private $token;
		private $name;
		
		public $application_charge;
		public $recurring_application_charge;
		public $article;
		public $asset;
		public $blog;
		public $collect;
		public $comment;
		public $country;
		public $custom_collection;
		public $event;
		public $fulfillment;
		public $metafield;
		public $order;
		public $page;
		public $product;
		public $product_image;
		public $product_variant;
		public $province;
		public $redirect;
		public $shop;
		public $smart_collection;
		public $transaction;
		public $webhook;
						
		/*
			BEGIN PUBLIC
		*/
		
		public function __construct($url, $token = '', $api_key, $secret, $private = false, $params = array()){
			$this->url = $url;
			$this->token = (shopify_isEmpty($token)) ? $url : $token;
			$this->api_key = $api_key;
			$this->secret = $secret;
			$this->private = $private;
			if (isset($params['signature'])){
				$timestamp = $params['timestamp'];
				$expireTime = time() - (24 * 86400);
				if (!$this->validate_signature($params) || $expireTime > $timestamp){
					throw new Exception('Invalid signature: Possible malicious login.');
				}
			}
			$this->url = $this->prepare_url($this->url);
			
			if ($this->valid()){
				$this->application_charge 			= new ShopifyApplicationCharge($this->site());
				$this->recurring_application_charge = new ShopifyRecurringApplicationCharge($this->site());;
				$this->article 						= new ShopifyArticle($this->site());
				$this->asset 						= new ShopifyAsset($this->site());
				$this->blog 						= new ShopifyBlog($this->site());
				$this->collect 						= new ShopifyCollect($this->site());
				$this->comment 						= new ShopifyComment($this->site());
				$this->country 						= new ShopifyCountry($this->site());
				$this->custom_collection 			= new ShopifyCustomCollection($this->site());
				$this->event 						= new ShopifyEvent($this->site());
				$this->fulfillment					= new ShopifyFulfillment($this->site());
				$this->metafield 					= new ShopifyMetafield($this->site(), "");
				$this->order 						= new ShopifyOrder($this->site());
				$this->page 						= new ShopifyPage($this->site());
				$this->product 						= new ShopifyProduct($this->site());
				$this->product_image 				= new ShopifyProductImage($this->site());
				$this->product_variant 				= new ShopifyProductVariant($this->site());
				$this->province 					= new ShopifyProvince($this->site());
				$this->redirect 					= new ShopifyRedirect($this->site());
				$this->shop							= new ShopifyShop($this->site());
				$this->smart_collection 			= new ShopifySmartCollection($this->site());
				$this->transaction 					= new ShopifyTransaction($this->site());
				$this->webhook 						= new ShopifyWebhook($this->site());
			}
		}
			
		public function create_permission_url(){
			return (shopify_isEmpty($this->url) || shopify_isEmpty($this->api_key)) ? '' : 'http://' . $this->url . '/admin/api/auth?api_key=' . $this->api_key;
		}
		
		/* Used to make all non-authetication calls */
		public function site(){
			return $this->protocol . '://' . $this->api_key . ':' . $this->computed_password() . '@' . $this->url . '/admin';
		}
		
		public function valid(){
			return (!shopify_isEmpty($this->url) && !shopify_isEmpty($this->token));
		}
			
		public function __destruct(){
			unset($this->api_key);
			unset($this->secret);
			unset($this->protocol);
			unset($this->format);
			unset($this->url);
			unset($this->token);
			unset($this->name);
			unset($this->application_charge);
			unset($this->recurring_application_charge);
			unset($this->article);
			unset($this->asset);
			unset($this->blog);
			unset($this->collect);
			unset($this->comment);
			unset($this->country);
			unset($this->custom_collection);
			unset($this->event);
			unset($this->fulfillment);
			unset($this->metafield);
			unset($this->order);
			unset($this->page);
			unset($this->product);
			unset($this->product_image);
			unset($this->product_variant);
			unset($this->province);
			unset($this->redirect);
			unset($this->shop);
			unset($this->smart_collection);
			unset($this->transaction);
			unset($this->webhook);
		}
		
		/*
			END PUBLIC
			BEGIN PRIVATE
		*/
		
		private function computed_password(){
			return ($this->private) ? $this->secret : md5($this->secret . $this->token);
		}
		
		private function prepare_url($url){
			if (shopify_isEmpty($url)) return '';
			$url = preg_replace('/https?:\/\//', '', $url);
			if (substr_count($url, '.myshopify.com') == 0 && substr_count($url, '.com') == 0){
				$url .= '.myshopify.com';
			}
			return $url;
		}
		
		private function validate_signature($params){	
			$this->signature = $params['signature'];
			$genSig = $this->secret;
			ksort($params);
			foreach($params as $k => $v){
				if ($k != "signature" && $k != "action" && $k != "controller" && !is_numeric($k)){
					$genSig .= $k . '=' . $v;
				}
			}
			return (md5($genSig) == $this->signature);
		}		

		/*
			END PRIVATE
		*/	
	}
	
	class ShopifyminiCURL{
		
		private $ch;
		
		public function __construct(){
			if (!function_exists('curl_init')) die("Error: cURL does not exist! Please install cURL.");
		}
		
		public function send($url, $request = 'GET', $xml_payload = '', $headers = array()){
			$this->ch = curl_init($url);
			
			if (SHOPIFY_GZIP_ENABLED) $headers[] = 'Accept-Encoding: gzip';
			
			$options = array(
				CURLOPT_HEADER => 0,
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_CUSTOMREQUEST => $request,
				CURLOPT_SSL_VERIFYPEER => SHOPIFY_USE_SSL,
				CURLOPT_HTTPHEADER => $headers
			);
			
			if (SHOPIFY_USE_SSL_PEM) $options[CURLOPT_CAINFO] = SHOPIFY_CA_FILE;
			if ($request != "GET") $options[CURLOPT_HTTPHEADER] = array('Content-Type: application/xml; charset=utf-8');
			if ($xml_payload !== false) $options[CURLOPT_POSTFIELDS] = $xml_payload;
			
			curl_setopt_array($this->ch, $options);
			if (!curl_exec($this->ch)) die(curl_error($this->ch));
			$data = (!SHOPIFY_GZIP_ENABLED) ? curl_multi_getcontent($this->ch) : shopify_gzdecode(curl_multi_getcontent($this->ch));
			$code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
			curl_close($this->ch);
			
			return $data;
		}
		
		public function loadString($data){
			$array = array();
				
			if (SHOPIFY_FORMAT == "xml"){
        if (substr_count($data, '<html xmlns') == 0 && preg_match("/\<(.*?)\>/", $data) > 0){
          if (!function_exists('simplexml_load_string')) die("SimpleXML library not installed. Either change format to .json or upgrade your version of PHP");
				  $xml = simplexml_load_string($data);
				  $this->recurseXML($xml, $array);
			  }else{
			    $array = array('error' => $data);
		    }
      }
			else if (SHOPIFY_FORMAT == "json"){
				if (!function_exists('json_decode')) die("json library not installed. Either change format to .xml or upgrade your version of PHP");
				$array = json_decode($data, true);
				if (isset($array['count'])) $array = $array['count'];				
			}
			return $array;
		}
		
    public function recurseXML($xml, &$array){ 
      $children = $xml->children(); 
      $executed = false;

      foreach ($children as $k => $v){ 
        if (is_array($array)){
          if (array_key_exists($k, $array)){
            if (is_array($array[$k]) && array_key_exists(0, $array[$k])){ 
              $i = count($array[$k]); 
              $this->recurseXML($v, $array[$k][$i]);     
            }else{ 
              $tmp = $array[$k]; 
              $array[$k] = array(); 
              $array[$k][0] = $tmp; 
              $i = count($array[$k]); 
              $this->recurseXML($v, $array[$k][$i]); 
            } 
          }else{ 
            $array[$k] = array(); 
            $this->recurseXML($v, $array[$k]);    
          }
        }else{
          $array[$k] = array(); 
          $this->recurseXML($v, $array[$k]);
        } 

        $executed = true; 
      }

      if (!$executed && shopify_isEmpty($children->getName())){ 
        $array = (string)$xml; 
      } 
    }
		
		public function __destruct(){
			empty($this->ch);			
		}
	}
?>