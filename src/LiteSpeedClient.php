<?php
/**
 * LiteSpeed reseller API.
 * 
 * @package Ganesh\LiteSpeed
 * @category Licensing
 * @author Ganesh R
 * @copyright 2020
 * 
 */

namespace Ganesh\Litespeed;

class LiteSpeedClient
{
	//API Endpoint
	private $endPoint;

	//Litespeed Store Login username
	private $username;

	//Litespeed Login Password
	private $password;

	//API version
	private $version = '2.0';

	private $params = [];

	/**
	 * Class constructor
	 * 
	 * @param string $username	// Store username
	 * @param string $password	// Store password
	 * @param bool	 $isLive	// Set true for live, false for sandbox
	 */
	public function __construct(string $username, string $password, bool $isLive = true)
	{
		$this->username = $username;
		$this->password = $password;
		$this->endPoint = $this->setUrl($isLive);
		$this->setParams();
	}

	/**
	 * Test API Connection
	 * 
	 * @return array $response
	 */
	public function ping()
	{
		$this->params['eService_action'] = 'Ping';
		return $this->call();
	}

	/**
	 * Place a New Order
	 * 
	 * @param string $product	Product type Default free starter license
	 * @param string $period	Billing frequency 'monthly' or 'yearly'
	 * @param string $paymentType allowed values 'credit' or 'creditcard'
	 * @param string $cvv		Required when $paymentType is credit
	 * @param string $ip		IpAddress 
	 * 
	 * @return array $response
	 */
	public function order($product, $period = 'monthly', $paymentType = 'credit', $cvv = false, $ip = false)
	{
		$this->params['eService_action'] = 'Order';
		$this->params['order_product'] = $this->validateProduct($product);
		$this->params['order_period'] = $this->validatePeriod($period);
		$this->params['order_payment'] = $paymentType;
		if ($paymentType == 'creditcard') {
			$this->params['order_cvv'] = $cvv ? $cvv : false;
		}
		if ($ip) {
			$this->params['server_ip'] = $ip ? $ip : false;
		}
		return $this->call();
	}

	/**
	 * Cancel Leased License
	 * 
	 * @param string $licenseSerial License serial number
	 * @param string $cancelNow 	Cancel 'Y' or 'N' ("Y": Immediately, "N": End of Billing Cycle)
	 * @param string $cancelReason	Reason for cancelling (optional)
	 * 
	 * @return array $response
	 */
	public function cancel($licenseSerial, $cancelNow = 'Y', $cancelReason = false)
	{
		$this->params['eService_action'] = 'Cancel';
		$this->params['license_serial'] = $licenseSerial;
		$this->params['cancel_now'] = $cancelNow;
		$this->params['cancel_reason'] = $cancelReason;
		return $this->call();
	}

	/**
	 * Release Registered License
	 * 
	 * @param string $licenseSerial License serial number
	 * @param string $serverIP		VPS/Server IP the license to be released from
	 * @param string $newIP			New IP that this license to be locked in
	 * 
	 * @return array $response
	 */
	public function release($licenseSerial, $serverIP, $newIP = null)
	{
		$this->params['eService_action'] = 'ReleaseLicense';
		$this->param['license_serial '] = $licenseSerial;
		$this->param['server_ip'] = $serverIP;
		$this->param['new_ip'] = $newIP;
		return $this->call();
	}

	/**
	 * Suspend/Unsuspend Leased License
	 * 
	 * @param string $licenseSerial License serial number
	 * @param string $action		Action is suspend / unsuspend license
	 * @param string $reason		Reason for action to perform
	 * 
	 * @return array $response
	 */
	public function licenseAction($licenseSerial, $action, $reason = false)
	{
		if (in_array($action, ['suspend', 'unsuspend'])) {
			$this->params['eService_action'] = $action;
			$this->params['license_serial'] = $licenseSerial;
			if ($reason) {
				$this->params['reason'] = $reason;
			}
			return $this->call();
		}
		return false;
	}

	/**
	 * Upgrade or Downgrade a License
	 * 
	 * @param string $licenseSerial License serial number
	 * @param string $setProduct	Set Product type
	 * @param string $paymentType 	Allowed values 'credit' or 'creditcard'
	 * @param string $cvv			Required when $paymentType is credit
	 * 
	 * @return array $response	
	 */
	public function changeProductType($licenseSerial, $setProduct, $paymentType='credit', $cvv = false)
	{
		$this->params['eService_action'] = 'Upgrade';
		$this->params['license_serial'] = $licenseSerial;
		$this->params['set_product'] = $this->validateProduct($setProduct);
		$this->params['order_payment'] = $paymentType;
		if ($cvv) {
			$this->params['order_cvv'] = $cvv;
		}
		return $this->call();
	}

	/**
	 * Get All Licenses
	 * 
	 * @param string $filter	Filter by status, must be any one of the following ['active', 'suspended', 'pendingcancel', 'active,withmodules','suspended,withmodules']
	 * 
	 * @return array $response
	 */
	public function getAll($filter = '') {
		$this->params['eService_action'] = 'Query';
		$this->params['query_field'] = 'AllLicenses';
		if ($filter && in_array($filter, ['active', 'suspended', 'pendingcancel', 'active,withmodules','suspended,withmodules'])) {
			$this->params['query_filter'] = $filter;
		}
		return $this->call();
	}

	/**
	 * Get License Details
	 * 
	 * @param string $filterBy		String must be any one of the following ['IP', 'RIP', 'LIP', 'Serial','ID']	
	 * @param string $filterData	Filter data is actual search term
	 * 
	 * @return array $response
	 */
	public function getLicenseDetails($filterBy = '', $filterData = '')
	{
		$this->params['eService_action'] = 'Query';
		$this->params['query_field'] = 'LicenseDetail';
		if ($filterBy && $filterData && in_array($filterBy, ['IP', 'RIP', 'LIP', 'Serial','ID'])) {
			$this->params['query_filter'] = "$filterBy:$filterData";
		}
		return $this->call();
	}

	/**
	 * Get Account Credit Balance
	 * 
	 * @return array $response
	 */
	public function getBalance()
	{
		$this->params['eService_action'] = 'Query';
		$this->params['query_field'] = 'CreditBalance';
		return $this->call();
	}

	/**
	 * Get Product type details array
	 * 
	 * @return array $productTypes
	 */
	public function getProductTypes()
	{
		$productTypes = [
			'WS_F' => 'Free Starter License (1-Domain, 1-Worker, Memory < 2GB)',
			'WS_SM' => 'Site Owner License (5-Domain, 1-Worker, Memory < 8GB)',
			'WS_S' => 'Site Owner Plus License (5-Domain, 1-Worker)',
			'WS_1M' => 'Web Host Lite License (1-Worker, Memory < 8GB)',
			'WS_1' => 'Web Host Essential License (1-Worker)',
			'WS_2' => 'Web Host Professional License (2-Worker)',
			'WS_4' => 'Web Host Enterprise License (4-Worker)',
			'WS_X' => 'Web Host Elite (X-Worker configurable)'
		];
		return $productTypes;
	}

	/**
	 * API Calls made
	 * 
	 * @return array $response
	 */
	private function call()
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->endPoint);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		curl_close ($ch);
		return json_decode($response, true);
	}

	/**
	 * Set api endpoint
	 * 
	 * @return string $url
	 */
	private function setUrl(bool $live)
	{
		$url = $live == true ? 'https://store.litespeedtech.com/reseller/LiteSpeed_eService.php' : 'https://sandbox.litespeedtech.com/reseller/LiteSpeed_eService.php';
		return $url;
	}

	/**
	 * Set basic required params
	 * 
	 */
	private function setParams()
	{
		$this->params['litespeed_store_login'] = $this->username;
		$this->params['litespeed_store_pass'] = $this->password;
		$this->params['eService_version'] = $this->version;
	}

	/**
	 * Validates product type returns false if not matches
	 * 
	 * @param string $product
	 * 
	 * @return string $product
	 */
	private function validateProduct(string $product)
	{
		/*
		Product type. New values for v2.0, all included LSCache module by default:
		“WS_F” : Free Starter License (1-Domain, 1-Worker, Memory < 2GB)
		“WS_SM” : Site Owner License (5-Domain, 1-Worker, Memory < 8GB)
		“WS_S” : Site Owner Plus License (5-Domain, 1-Worker)
		“WS_1M”: Web Host Lite License (1-Worker, Memory < 8GB)
		“WS_1” : Web Host Essential License (1-Worker)
		“WS_2” : Web Host Professional License (2-Worker)
		“WS_4” : Web Host Enterprise License (4-Worker)
		“WS_X” : Web Host Elite (X-Worker configurable)
		Please note for license with type of “WS_F”, “WS_SM”, “WS_S”, “WS_1M”, “WS_X”, LiteSpeed
		Web Server Enterprise 5.3 and above is required.
		 */
		$productList = ['WS_F', 'WS_SM', 'WS_S', 'WS_1M', 'WS_1', 'WS_2', 'WS_4', 'WS_X'];
		if (in_array($product, $productList)) {
			return $product;
		}
		return false;
	}

	/**
	 * Validates product type returns false if not matches
	 * 
	 * @param string/integer $period
	 * 
	 * @return string $period
	 */
	private function validatePeriod($period)
	{
		$periodList = ['monthly', 'yearly'];
		$periodListNum = [1 => 'monthly', 12 => 'yearly'];
		if (in_array($period, $periodList)) {
			return $period;
		} elseif (in_array($period, $periodListNum)) {
			return $periodListNum[$period];
		}
		return false;
	}
}