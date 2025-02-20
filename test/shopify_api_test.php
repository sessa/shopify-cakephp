<?php
	require_once('../lib/shopify_api.php'); 
	require_once('../lib/shopify_api_config.php');
	require_once('../simpletest/autorun.php');
	
	/*
	  Creating my own mock classes because SimpleTest mocking doesn't
	  work with PHP5 -- something about the way PHP classes are made in
	  PHP5
	*/
	class MockMiniCurl extends miniCurl{
	  private $fixture_no;
	  
	  public function __construct($fixture_no){
	    $this->fixture_no = $fixture_no;
	  }
	  
	  public function send(){
	    return file_get_contents(__DIR__ . '/fixtures/mini_curl_test_data_'.$this->fixture_no.'.xml');
	  }
	}
	
  function mockSendToAPI($fixture_no){
    $ch = new MockMiniCurl($fixture_no);
    $data = $ch->send();
    return $ch->loadString($data);
  }
	
	class MockProdct extends ShopifyProduct{
	  
	  public function get(){
	    return mockSendToAPI(1);
	  }
	}
	
	class TestingFunctions extends UnitTestCase{		
		function testConfiguration(){				
			$this->assertTrue(defined('SHOPIFY_API_KEY'));
			$this->assertTrue(defined('SHOPIFY_SECRET'));
			$this->assertTrue(defined('SHOPIFY_GZIP_ENABLED'));
			$this->assertTrue(defined('SHOPIFY_GZIP_PATH'));
			$this->assertTrue(defined('SHOPIFY_FORMAT'));
		}
		
		function testIsEmpty(){
			$this->assertTrue(shopify_isEmpty(''));
			$this->assertTrue(shopify_isEmpty('   '));
			$this->assertTrue(!shopify_isEmpty('This is not empty.'));
		}
		
		function testURLEncodeArray(){
			$array = array('one' => 1, 'two' => 'to o', 'three' => array('blah'));
			$this->assertEqual("one=1&two=to%20o", shopify_url_encode_array($array));
		}
		
		function testOrganizeArray(){
			$array1 = array('something' => array(array('id' => 4), array('id' => 5)));
			$array2 = array('something' => array(4 => array('id' => 4), 5 => array('id' => 5)));
			$this->assertEqual($array2, shopify_organizeArray($array1, 'something'));
		}
		
		function testArrayToXML(){
			$array = array('something' => array('one' => 1, 'two' => array('too' => 2)));
			$xml = '<?xml version="1.0" encoding="UTF-8"?><something><one>1</one><two><too>2</too></two></something>';
			$this->assertEqual($xml, shopify_arrayToXML($array));
		}
				
		function testGZDecode(){
			$this->assertEqual("this is some text", shopify_gzdecode(gzencode("this is some text")));
		}
		
	}
	
	class TestSessionClass extends UnitTestCase{
		private $session;
		
		function testIsValid(){
			$this->session = new ShopifySession('', '', '', '');
			$this->assertFalse($this->session->valid());
			$this->session = new ShopifySession('schneider-and-sons2771.myshopify.com', '31191cf000f9d1ee2bc97ddcdd5a76fd', SHOPIFY_API_KEY, SHOPIFY_SECRET);
			$this->assertTrue($this->session->valid());
		}
		
		function testPrivateApp(){
			$this->session = new ShopifySession('schneider-and-sons2771.myshopify.com', '31191cf000f9d1ee2bc97ddcdd5a76fd', SHOPIFY_API_KEY, 'this is a secret', true);
			$this->assertTrue(substr_count($this->session->site(), 'this is a secret') > 0);
		}
	}

	class TestMiniCurl extends UnitTestCase{
	  public function testGet(){
      $products = shopify_organizeArray(mockSendToAPI(1), 'product');
      $products = $products['product'];
      
      $this->assertEqual(6, sizeof($products));
      $this->assertTrue(is_array($products[16133622]));
      $this->assertEqual('Face Persistent 24 hour artificial intelligence', $products[16133622]['title']);
      $this->assertEqual(40253192, $products[16133622]['variants']['variant']['id']);
	  }
	}

?>