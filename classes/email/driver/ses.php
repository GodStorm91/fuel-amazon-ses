<?php

/**
 * Amazon Email Delivery library for FuelPHP (Support AWS Signature V4)
 *
 * @package		FuelPHP SES Driver ( base on Rob McCann's package)
 * @version		1.1
 * @author		Khanh Nguyen (khanh@in-g.jp)
 * @link		https://github.com/GodStorm91/fuel-amazon-ses
 * 
 */

class Email_Driver_Ses extends \Email_Driver {
	public $region = null;
	public $service = 'ses';
	public $credentialScope;

	protected $debug = true;
    const ISO8601_BASIC = 'Ymd\THis\Z';
    const UNSIGNED_PAYLOAD = 'UNSIGNED-PAYLOAD';
    const AMZ_CONTENT_SHA256_HEADER = 'X-Amz-Content-Sha256';
	
	public function __construct($config) 
	{
		parent::__construct($config);
		\Config::load('ses', true);
		
		$this->region = \Config::get('ses.region','us-east-1');

	}
	/**
	 * Sends the email using the Amazon SES email delivery system
	 * 
	 * @return boolean	True if successful, false if not.
	 */	
	protected function _send()
	{
		$params = array(
			'Action' => 'SendEmail',
			'Version' => '2010-12-01',
			'Source' => static::format_addresses(array($this->config['from'])),
			'Message.Subject.Data' => $this->subject,
			'Message.Body.Text.Data' => $this->body,
			'Message.Body.Text.Charset' => $this->config['charset'],
		);
		
		$i = 0;
		foreach($this->to as $value)
		{
			$params['Destination.ToAddresses.member.'.($i+1)] = static::format_addresses(array($value));
			++$i;
		}
		
		$i = 0;
		foreach($this->cc as $value)
		{
			$params['Destination.CcAddresses.member.'.($i+1)] = static::format_addresses(array($value));
			++$i;
		}
		
		$i = 0;
		foreach($this->bcc as $value)
		{
			$params['Destination.BccAddresses.member.'.($i+1)] = static::format_addresses(array($value));
			++$i;
		}
		
		$i = 0;
		foreach($this->reply_to as $value)
		{
			$params['ReplyToAddresses.member.'.($i+1)] = static::format_addresses(array($value));
			++$i;
		}	
		$date = gmdate(self::ISO8601_BASIC);
		$dateRss = gmdate(DATE_RSS);
		
		$curl = \Request::forge('https://email.' . $this->region . '.amazonaws.com/', array(
			'driver' => 'curl',
			'method' => 'post'
			))
			->set_header('Content-Type','application/x-www-form-urlencoded')
			->set_header('date', $dateRss)
			->set_header('host', 'email.' . $this->region . '.amazonaws.com')
			->set_header('x-amz-date', $date);
		$signature = $this->_sign_signature_v4($params);
		$curl->set_header('Authorization', $signature);
		$response = $curl->execute($params);
		
		
		if (intval($response-> response()->status / 100) != 2) 
		{
			\Log::debug("Send mail errors " . json_encode($response->response()));
			return false;
		}
		
		\Log::debug("Send mail ok " . json_encode($response->response()));
		return true;
	}

	/**
	 * Sets the from address and name
	 *
	 * @param   string      $email  The from email address
	 * @param   bool|string $name   The optional from name
	 *
	 * @return  $this
	 */
	public function from($email, $name = false)
	{
		$this->config['from']['email'] = (string) $email;
		$this->config['from']['name']  = (is_string($name)) ? $name : false;

		if ($this->config['from']['name'])
		{
			$this->config['from']['name'] = $this->encode_mimeheader((string) $this->config['from']['name']);
		}

		return $this;
	}

	/**
	 * Sign a curl request V4
	 *
	 * @param   object      $curl
	 * @param   object		$curl object
	 *
	 * @return  $this
	 */
	private function _sign_signature_v4($bodyParams)
	{
        $ldt = gmdate(self::ISO8601_BASIC);
        $sdt = substr($ldt, 0, 8);
        $parsed = [
            'method'  => 'POST',
            'path'    => '/',
            'query'   => [],
			'uri'     => 'https://email.' . $this->region . '.amazonaws.com/',
            'headers' => [
							'host' => ['email.' . $this->region . '.amazonaws.com'],
							'x-amz-date' => [date(DATE_RSS)],
							],
            'body'    => $bodyParams,
            'version' => 'HTTP/1.1'
        ];
		$cs = $this->createScope($sdt, $this->region, $this->service);
		$this->credentialScope = $cs;
		$payload = http_build_query($bodyParams);   

        $context = $this->createContext($parsed, hash('sha256', $payload));
		$toSign = $this->createStringToSign($ldt, $cs, $context['creq']);

        $signingKey = $this->getSigningKey(
            $sdt,
            \Config::get('ses.region'),
            'ses',
            \Config::get('ses.secret_key')
		);
		
		$signature = hash_hmac('sha256', $toSign, $signingKey);
		$accessKey = \Config::get('ses.access_key');
        $parsed['headers']['Authorization'] = [
            "AWS4-HMAC-SHA256 "
            . "Credential={$accessKey}/{$cs}, "
            . "SignedHeaders=host;x-amz-date, Signature={$signature}"
		];
		
		return implode("", $parsed['headers']['Authorization']);
	}

    /**
     * @param array  $parsedRequest
     * @param string $payload Hash of the request payload
     * @return array Returns an array of context information
     */
    private function createContext($parsedRequest, $payload)
    {
        $blacklist = $this->getHeaderBlacklist();

        // Normalize the path as required by SigV4
        $canon = $parsedRequest['method'] . "\n"
            . $this->createCanonicalizedPath($parsedRequest['path']) . "\n"
            . $this->getCanonicalizedQuery($parsedRequest['query']) . "\n";

        // Case-insensitively aggregate all of the headers.
        $aggregate = [];
        foreach ($parsedRequest['headers'] as $key => $values) {
            $key = strtolower($key);
            if (!isset($blacklist[$key])) {
                foreach ($values as $v) {
                    $aggregate[$key][] = $v;
                }
            }
        }

        ksort($aggregate);
        $canonHeaders = [];

		$canonHeaders[] = "host:" . 'email.' . $this->region . '.amazonaws.com';
		$canonHeaders[] = "x-amz-date:" . gmdate(self::ISO8601_BASIC);
        $signedHeadersString = implode(';', array_keys($aggregate));
        $canon .= implode("\n", $canonHeaders) . "\n\n"
            . $signedHeadersString . "\n"
            . $payload;

        return ['creq' => $canon, 'headers' => $signedHeadersString];
    }

    /**
     * @param array  $path
     * @return string Returns string in canocicalized
     */
	protected function createCanonicalizedPath($path)
    {
        $doubleEncoded = rawurlencode(ltrim($path, '/'));

        return '/' . str_replace('%2F', '/', $doubleEncoded);
    }

    private function createStringToSign($longDate, $credentialScope, $creq)
    {
        $hash = hash('sha256', $creq);

        return "AWS4-HMAC-SHA256\n{$longDate}\n{$credentialScope}\n{$hash}";
    }


    private function createScope($shortDate, $region, $service)
    {
        return "$shortDate/$region/$service/aws4_request";
    }

	private function getSigningKey($shortDate, $region, $service, $secretKey)
    {
        $k = $shortDate . '_' . $region . '_' . $service . '_' . $secretKey;
		$dateKey = hash_hmac(
			'sha256',
			$shortDate,
			"AWS4{$secretKey}",
			true
		);
		$regionKey = hash_hmac('sha256', $region, $dateKey, true);
		$serviceKey = hash_hmac('sha256', $service, $regionKey, true);
		$signingKey = hash_hmac(
			'sha256',
			'aws4_request',
			$serviceKey,
			true
		);

		return $signingKey;
	}
	
    /**
     * The following headers are not signed because signing these headers
     * would potentially cause a signature mismatch when sending a request
     * through a proxy or if modified at the HTTP client level.
     *
     * @return array
     */
    private function getHeaderBlacklist()
    {
        return [
            'cache-control'         => true,
            'content-type'          => true,
            'content-length'        => true,
            'expect'                => true,
            'max-forwards'          => true,
            'pragma'                => true,
            'range'                 => true,
            'te'                    => true,
            'if-match'              => true,
            'if-none-match'         => true,
            'if-modified-since'     => true,
            'if-unmodified-since'   => true,
            'if-range'              => true,
            'accept'                => true,
            'authorization'         => true,
            'proxy-authorization'   => true,
            'from'                  => true,
            'referer'               => true,
            'user-agent'            => true,
            'x-amzn-trace-id'       => true,
            'aws-sdk-invocation-id' => true,
            'aws-sdk-retry'         => true,
        ];
    }

	private function getCanonicalizedQuery(array $query)
    {
        unset($query['X-Amz-Signature']);

        if (!$query) {
            return '';
        }

        $qs = '';
        ksort($query);
        foreach ($query as $k => $v) {
            if (!is_array($v)) {
                $qs .= rawurlencode($k) . '=' . rawurlencode($v) . '&';
            } else {
                sort($v);
                foreach ($v as $value) {
                    $qs .= rawurlencode($k) . '=' . rawurlencode($value) . '&';
                }
            }
        }

        return substr($qs, 0, -1);
    }


}