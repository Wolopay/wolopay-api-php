<?php

namespace Wolopay;

/**
 * This file is part of wolopay.com (c)
 *
 * Class to help merchants to use Wolopay API
 */
class WolopayApi
{
    /** @var String */
    private $clientId;

    /** @var String */
    private $secret;

    /** @var String */
    protected $environmentUrl;

    /** @var Boolean */
    private $debug;

    /** @var String */
    private $language;

    const PRODUCTION_URL = 'https://wolopay.com/api/v1';
    const SANDBOX_URL    = 'https://sandbox.wolopay.com/api/v1';

    /**
     * @param $clientId
     * @param $secret
     * @param bool $sandbox
     * @param bool $debug var_dump if fail show response from server
     * @param string $language
     */
    function __construct($clientId, $secret, $sandbox=false, $debug=false, $language='en')
    {
        $this->clientId = $clientId;
        $this->secret   = $secret;
        $this->debug    = $debug;
        $this->language = $language;

        if ($sandbox)
            $this->environmentUrl = static::SANDBOX_URL;
        else
            $this->environmentUrl = static::PRODUCTION_URL;
    }

    /**
     * @param string $language languages available (look your admin (configurator -> wizard ) and check your languages)
     */
    public function setLanguage($language)
    {
        $this->language = $language;
    }

    /**
     * @param $gamerId
     * @param $gamerLevel
     * @param array $extraPostValues
     * @param bool $autoRedirect
     * @return bool
     */
    public function createTransaction($gamerId, $gamerLevel, array $extraPostValues = array(), $autoRedirect = false)
    {
        $postValues = array_merge($extraPostValues, array('gamer_id'=> $gamerId, 'gamer_level' => $gamerLevel));
        $result = $this->makeRequest('/transaction.json', 'POST', $postValues);

        if ($autoRedirect && $result && $result->url){
            header("Location: ".$result->url);
            exit;
        }

        return $result;
    }

    /**
     * @param $gamerId
     * @param $amount
     * @param $currencyISO String ISO 4217 3 digits
     * @param $countryISO String ISO 3166 2 digits
     * @param $payMethodId integer See the docs to set your favourite payment method
     * @param string $articleTitle
     * @param string $articleDescription
     * @param array $extraPostValues
     * @param bool $autoRedirect
     * @return bool
     */
    public function directPayment($gamerId, $amount, $currencyISO, $countryISO, $payMethodId, $articleTitle,
        $articleDescription = '', array $extraPostValues = array(), $autoRedirect = false)
    {
        $params = array(
            'gamer_id'            => $gamerId,
            'amount'              => $amount,
            'currency'            => $currencyISO,
            'country'             => $countryISO,
            'pay_method_id'       => $payMethodId,
            'article_title'       => $articleTitle,
            'article_description' => $articleDescription
        );

        $postValues = array_merge($extraPostValues, $params);
        $result = $this->makeRequest('/transaction_simple.json', 'POST', $postValues);

        if ($autoRedirect && $result && $result->url){
            header("Location: ".$result->url);
            exit;
        }

        return $result;
    }
    
     /**
     * @param $gamerId
     * @param $gamerLevel
     * @param $countryISO String ISO 3166 2 digits
     * @param $payMethodId integer See the docs to set your favourite payment method
     * @param string $articles Comma separated list of articles (ids)
     * @param string $gamerEmail Email of the gamer
     * @param array $extraPostValues
     * @param bool $autoRedirect
     * @return bool
     */
    public function directPaymentArticles($gamerId, $gamerLevel, $countryId, $payMethodId, $articlesIdsCSV,
                                          $gamer_email ='', array $extraPostValues = array(), $autoRedirect = false)
    {
        $params = array(
            'gamer_id'      => $gamerId,
            'gamer_level'    => $gamerLevel,
            'country'    => $countryId,
            'pay_method_id'    => $payMethodId,
            'articles'    => $articlesIdsCSV,
            'gamer_email'    => $gamer_email
        );

        $postValues = array_merge($extraPostValues, $params);
        $result = $this->makeRequest('/transaction_articles.json', 'POST', $postValues);

        if ($autoRedirect && $result && $result->url){
            header("Location: ".$result->url);
            exit;
        }

        return $result;
    }

    /**
     * @param $gamerId
     * @param $amount
     * @param $currencyISO String ISO 4217 3 digits
     * @param string $articleTitle
     * @param string $articleDescription
     * @param array $extraPostValues
     * @param bool $autoRedirect
     * @return bool
     */
    public function directPaymentWidget($gamerId, $amount, $currencyISO, $articleTitle = '',
        $articleDescription = '', array $extraPostValues = array(), $autoRedirect = false)
    {
        $params = array(
            'gamer_id'            => $gamerId,
            'amount'              => $amount,
            'currency'            => $currencyISO,
            'article_title'       => $articleTitle,
            'article_description' => $articleDescription
        );

        $postValues = array_merge($extraPostValues, $params);
        $result = $this->makeRequest('/transaction_widget.json', 'POST', $postValues);

        if ($autoRedirect && $result && $result->url){
            header("Location: ".$result->url);
            exit;
        }

        return $result;
    }

    /**
     * Return transaction with his purchases
     *
     * @param $transactionId
     * @return bool
     */
    public function getTransaction($transactionId)
    {
        $result = $this->makeRequest('/transaction/'.$transactionId.'.json');

        return $result;
    }

    /**
     * @param $transactionId
     * @return bool
     */
    public function isTransactionCompleted($transactionId)
    {
        if (!$result = $this->getTransaction($transactionId))
            return false;

        if ($result->status_category->id != 200 && $result->status_category->id != 201 )
            return false;

        return true;
    }

    /**
     * @return bool
     */
    public function isAValidRequest()
    {
        $params = '';
        foreach ($_POST as $param)
            $params .= $param;

        $signature = 'Signature '.sha1($params.$this->secret);

        $headers = apache_request_headers();
        if(!isset($headers['Authorization']))
            return false;

        if ($signature !== $headers['Authorization'])
            return false;

        return true;
    }

    /**
     * This function is used to know how long does the gamer takes to do a first purchase, OPTIONAL
     *
     * @param $gamerId
     * @param array $optionalParameters
     * @return \stdclass
     */
    public function createGamer($gamerId, $optionalParameters = array())
    {
        $optionalParameters['gamer_id'] = $gamerId;

        return $this->makeRequest('/gamer.json', 'POST', $optionalParameters);
    }

    /**
     * Create a promotional code
     *
     * @param $promo_code
     * @param $articleId
     * @param array $extraOptions
     * @return \stdclass
     */
    public function createPromotionalCode($promo_code, $articleId, $extraOptions = array())
    {
        $extraOptions['promo_code'] = $promo_code;
        $extraOptions['article_id'] = $articleId;

        return $this->makeRequest('/promo_code.json', 'POST', $extraOptions);
    }

    /**
     * Make a purchase using a promotional code and a user
     *
     * @param $promoCode
     * @param $gamerId
     * @param array $extraOptions
     * @return \stdclass
     */
    public function usePromotionalCodeByGamerId($promoCode, $gamerId, $extraOptions = array())
    {
        $extraOptions['promo_code'] = $promoCode;
        $extraOptions['gamer_id'] = $gamerId;

        return $this->makeRequest('/promo_code/use.json', 'POST', $extraOptions);
    }

    /**
     * Create a promotional code
     *
     * @param $promo_code
     * @param $articleId
     * @param array $extraOptions
     * @return \stdclass
     */
    public function updatePromotionalCode($promo_code, $articleId, $extraOptions = array())
    {
        $extraOptions['promo_code'] = $promo_code;
        $extraOptions['article_id'] = $articleId;

        return $this->makeRequest('/promo_code.json', 'PUT', $extraOptions);
    }

    /**
     * Get Articles by country
     *
     * @param $countryISO String ISO 3166 2 digits
     * @param array $extraOptions
     * @return \stdclass
     */
    public function getArticlesByCountry($countryISO, $extraOptions = array())
    {
        return $this->makeRequest('/articles/country/'.$countryISO.'.json', 'GET', $extraOptions);
    }

    /**
     * Get pay methods available by country and by direct payment
     *
     * @param $countryISO String ISO 3166 2 digits
     * @param array $extraOptions
     * @return \stdclass
     */
    public function getDirectPaymentPayMethodsAvailable($countryISO, $extraOptions = array())
    {
        return $this->makeRequest('/direct_payment/paymethods/country/'.$countryISO.'.json', 'GET', $extraOptions);
    }

    /**
     * Make a purchase with your virtual currency (only available in some apps)
     *
     * @param $gamerId
     * @param $gamerLevel
     * @param $articleId
     * @param $countryISO String ISO 3166 2 digits
     * @param array $extraOptions
     *
     * @return \stdclass
     */
    public function makePurchaseWithVirtualCurrencies($gamerId, $gamerLevel, $articleId, $countryISO, $extraOptions = array())
    {
        $extraOptions['gamer_id']    = $gamerId;
        $extraOptions['article_id']  = $articleId;
        $extraOptions['country']     = $countryISO;
        $extraOptions['gamer_level'] = $gamerLevel;

        return $this->makeRequest('/virtual_currency/exchange.json', 'POST', $extraOptions);
    }

    /**
     * @param $url
     * @param string $method
     * @param array $values
     * @return \stdclass
     */
    protected function makeRequest($url, $method='GET', array $values = array())
    {
        $url = $this->environmentUrl . $url;

        $ch = curl_init();

        if ($method=='POST' || $method=='PUT' )
        {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($values));
        }else{
            if (strpos($url, '?') === false)
                $url.='?';

            $url.= http_build_query($values);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-WSSE: '.$this->generateHeaderWSSE(), 'Accept-Language: '.$this->language));

        $resultJson = curl_exec ($ch);

        $result = curl_getinfo($ch);
        curl_close ($ch);

        $httpCode = $result['http_code'];

        if ($httpCode < 200 || $httpCode >= 300) {

            if ($this->debug) {

                $this->printPrettyErrorMessage($result, $resultJson);
            }

            return false;
        }

        return json_decode($resultJson);
    }

    protected function printPrettyErrorMessage($curlInfo, $resultJson)
    {
        $httpCode = $curlInfo['http_code'];

        $template = <<<'HTML'
                <div style='background: #FFB6B6; padding:20px; border: 1px solid #000; text-shadow: 1px 1px 2px #fff'>
                    HTTP_CODE: <b>%1$d</b><br><br>
                    <pre>%2$s</pre>
                    <em style="font-size: 0.6em; text-align: right; display: block;">WolopayApi.php</em>
                </div>
HTML;

        $msg = $httpCodeDesc = '';

        if ($httpCode === 401) {
            $msg .= "Acces Deniend (verify your credentials)\n<br>";
        }

        $json = @json_decode($resultJson, true);

        if ($json) {
            $msg .= print_r($json, true);
        } else {
            $msg .= $resultJson;
        }

        $msg .= "<br>\n";

        echo sprintf($template, $httpCode, $msg);
    }

    /**
     * @return string
     */
    protected function generateHeaderWSSE()
    {
        $nonce = md5(rand().uniqid(), true);
        $created = gmdate(DATE_ISO8601, time());

        $digest = base64_encode(sha1($nonce.$created.$this->secret,true));
        $b64nonce = base64_encode($nonce);

        return  sprintf('UsernameToken Username="%s", PasswordDigest="%s", Nonce="%s", Created="%s"',
            $this->clientId,
            $digest,
            $b64nonce,
            $created
        );
    }

    /**
     * @param $httpStatusCode
     * @param $httpStatusMsg
     */
    public function setHttpCode($httpStatusCode, $httpStatusMsg)
    {
        $phpSapiName    = substr(php_sapi_name(), 0, 3);
        if ($phpSapiName == 'cgi' || $phpSapiName == 'fpm') {
            header('Status: '.$httpStatusCode.' '.$httpStatusMsg);
        } else {
            $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
            header($protocol.' '.$httpStatusCode.' '.$httpStatusMsg);
        }
    }

    /**
     * @param $string
     * @param $numberOfItems
     * @return string
     */
    public static function replaceNumbersOfItems($string, $numberOfItems)
    {
        return preg_replace('/\{\[\{[ ]*number[ ]*\}\]\}/', $numberOfItems, $string);
    }

}
