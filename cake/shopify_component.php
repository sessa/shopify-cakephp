<?php

/**
 * ShopifyComponent
 *
 * Copyright 2010 Isaac Bowen <ikebowen@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 *
 * This component requires the Shopify PHP API (currently found at
 * http://github.com/Shopify/shopify_php_api) but does not make any claim to
 * copyright on it.
 *
 */

require_once(dirname(__FILE__) . '/../lib/shopify_api.php');

if(!class_exists('Object')) {
    die('Cake doesn\'t appear to be loaded.');
}

class ShopifyComponent extends Object {
    
    var $name       = 'Shopify';
    var $components = array('Session');
    
    var $session_key    = '__Shopify';
    var $api_key        = null;
    var $api_secret     = null;
    var $api_gzip       = true;
    var $api_gzip_path  = '/tmp';
    var $api_format     = 'xml';
    var $api_ssl        = false;
    var $api_ssl_pem    = false;
    var $api_ca_file    = '';
    var $autoredirect   = false;
    var $autologin      = false;
    
    private $state  = array();
    public $api    = null;
    private $apiReflection  = null;
    
    var $Controller;
    var $Session;
    
    function initialize(&$controller, $settings=array()) {
        $this->_set($settings);
        $this->Controller =& $controller;
        
        // apply definitions, if they're not already set
        if(!defined('SHOPIFY_API_KEY'))
            define('SHOPIFY_API_KEY', $this->api_key);
        if(!defined('SHOPIFY_SECRET'))
            define('SHOPIFY_SECRET', $this->api_secret);
        if(!defined('SHOPIFY_FORMAT'))
            define('SHOPIFY_FORMAT', $this->api_format);
        if(!defined('SHOPIFY_GZIP_ENABLED'))
            define('SHOPIFY_GZIP_ENABLED', $this->api_gzip);
        if(!defined('SHOPIFY_GZIP_PATH'))
            define('SHOPIFY_GZIP_PATH', $this->api_gzip_path);
        if(!defined('SHOPIFY_USE_SSL'))
            define('SHOPIFY_USE_SSL', $this->api_ssl);
        if(!defined('SHOPIFY_USE_SSL_PEM'))
            define('SHOPIFY_USE_SSL_PEM', $this->api_ssl_pem);
        if(!defined('SHOPIFY_CA_FILE'))
            define('SHOPIFY_CA_FILE', $this->api_ssl_pem ? $this->api_ssl_pem : ROOT . '/vendors/shopify/lib/cacert.pem');
        
        // pull settings from Shopify constants, if needed
        if(empty($this->api_key)) $this->api_key = SHOPIFY_API_KEY;
        if(empty($this->api_secret)) $this->api_secret = SHOPIFY_SECRET;
        
        $this->state    = $this->Session->read($this->session_key);
        
        // if we have enough data, automatically open a Shopify session
        if($this->autologin) {
            if(!empty($this->Controller->params['url']['shop']) && !empty($this->Controller->params['url']['t'])) {
                // log in with GET vars
                $this->login($this->Controller->params['url']['shop']);
            }
        }
        if(!$this->auth() && !empty($this->state['domain']) && !empty($this->state['password'])) {
            // log in with session vars
            $this->login($this->state['effective_domain'], $this->state['password']);
        }
    }
    
    function state($key = null) {
        if(!$this->auth()) {
            return false;
        }

        if($key) {
            if(array_key_exists($key, $this->state)) {
                return $this->state[$key];
            } else {
                return null;
            }
        } else {
            return $this->state;
        }
        return false;
    }

    function auth($key = null) {
        $data   = $this->Session->read($this->session_key);
        if($this->api && $this->api->valid() && !empty($data['Shop'])) {
            if($key) {
                if(array_key_exists($key, $data['Shop'])) {
                    return $data['Shop'][$key];
                } else {
                    return true;
                }
            } else {
                return $data['Shop'];
            }
        } else {
            return false;
        }
    }

    function api() {
        return $this->api;
    }
    
    function login($domain, $password = null) {
        $domain = preg_replace('/^(https?:\/\/)?([^\/]+).*?$/', '$2', trim($domain));
        
        // use a session password, if we have one
        if($password && $this->state) {
            $this->api  = new ShopifySession(
                $this->state['effective_domain'],
                null,
                $this->api_key,
                $this->state['password'],
                true
            );
            if( !$this->api->valid() ) {
                return false;
            }
            return true;
        }
        
        // idiot check
        if( !@file_get_contents("http://$domain") ) {
            $this->Session->setFlash('Doesn\'t look like that\'s a valid shop URL. Try again?');
            return false;
        }
        
        // clear the old session, saving the redirect value
        $redirect   = $this->Session->read($this->session_key . '.redirect');
        $this->Session->delete($this->session_key);
        
        if(!$password && empty($this->Controller->params['url']['t'])) {
            // we don't have a token, so send the user off to Shopify for authentication
            $this->api  = new ShopifySession($domain, null, $this->api_key, $this->api_secret);
            
            if( $this->api->valid() ) {
                if($this->autoredirect) {
                    // going to redirect back here after a successful authentication
                    $this->Session->write($this->session_key . '.redirect', Router::url(null, true));
                }
                $this->Controller->redirect($this->api->create_permission_url());
            } else {
                return false;
            }
        } else {
            // test for effective domain
            // all requests to a shopify shop tld get redirected to the myshopify.com subdomain, which poses a problem for post requests
            $ch = curl_init('http://' . $domain . '/admin/shop.xml');
            curl_setopt_array($ch, array(
                CURLOPT_FOLLOWLOCATION  => true,
                CURLOPT_RETURNTRANSFER  => true,
            ) );
            if(curl_exec($ch)) {
                $effective  = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                if(preg_match('/^https?:\/\/(\w+\.myshopify\.com)/', $effective, $matches)) {
                    $domain = $matches[1];
                }
            }
            curl_close($ch);
            
            // set up session
            if( $password ) {
                $this->api  = new ShopifySession($domain, null, $this->api_key, $password, true);
            } elseif( !empty( $this->Controller->params[ 'url' ][ 't' ] ) ) {
                $this->api  = new ShopifySession($domain, $this->Controller->params['url']['t'], $this->api_key, $this->api_secret);
                $password   = md5($this->api_secret . $this->Controller->params['url']['t']);
            } else {
                $this->cakeError('error500');
            }
            if($this->api->valid() && ($shop = $this->api->shop->get()) && empty($shop['error'])) {
                $this->Session->write($this->session_key, array(
                    'Shop'      => $shop,
                    'domain'    => $shop['domain'],
                    'effective_domain'  => $domain,
                    'password'  => $password,
                    'token'     => !empty($this->Controller->params['url']['t']) ? $this->Controller->params['url']['t'] : '',
                ));
                $this->state    = $this->Session->read($this->session_key);
                
                // success.
                $this->Session->setFlash('Welcome, ' . $shop['name'] . '.');
                
                // head back to the origin
                if($this->autoredirect && $redirect) {
                    $this->Session->delete($this->session_key . '.redirect');
                    $this->Controller->redirect($redirect);
                }
                
                return $shop;
            } else {
                $this->Session->setFlash('Failed to log in.');
                return false;
            }
        }
    }
    
    function logout() {
        $this->Session->delete($this->session_key);
        unset($this->api);
        $this->api  = null;
    }
    
    function __get($name) {
        try {
            $obj    = $this->_getReflection();
            if($obj && $obj->hasProperty($name)) {
                return $this->api->$name;
            }
        } catch(ReflectionException $e) {
            $this->cakeError('error500');
        }
    }
    
    function __call($func, $args = array()) {
        try {
            $obj    = $this->_getReflection();
            if($obj && $obj->hasMethod($func)) {
                return $obj->getMethod($func)->invokeArgs($this->api, $args);
            }
        } catch(ReflectionException $e) {
            $this->cakeError('error500');
        }
    }
    
    function _getReflection() {
        if(!is_object($this->api)) {
            $this->cakeError('error500');
        }
        if(!is_object($this->apiReflection)) {
            try {
                $this->apiReflection    = new ReflectionObject($this->api);
            } catch(ReflectionException $e) {
                $this->cakeError('error500');
            }
        }
        return $this->apiReflection;
    }
    
}
