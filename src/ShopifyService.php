<?php

namespace Cian\Shopify;

use Cian\Shopify\Request;
use Cian\Shopify\Response;
use Illuminate\Support\Str;
use Cian\Shopify\Exceptions\UnsetWebsiteException;
use Cian\Shopify\Exceptions\UnknownWebsiteException;

abstract class ShopifyService
{
    /**
     * @var \GuzzleHttp\Client $http
     */
    protected $http;

    /**
     * @var array $config
     */
    protected $config;

    /**
     * @var array $options
     */
    protected $options = [];

    /**
     * @var string $website
     */
    protected $website = null;

    /**
     * @var int $retries
     */
    protected $retries;

    public function __construct($http, $config)
    {
        $this->http = $http;

        $this->config = $config;

        $this->setVersion($config['defaults']['api_version']);

        $this->setRetries($config['defaults']['api_retries']);
    }

    /**
     * Set shopify api version.
     *
     * @param string $version
     * @return $this
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Set default retry times of request.
     *
     * @param int $retries
     * @return $this
     */
    public function setRetries(int $retries)
    {
        $this->retries = $retries;

        return $this->retries;
    }

    /**
     * Set target website.
     *
     * @param $website
     * @return $this
     * @throws UnknownWebsiteException
     */
    public function setWebsite($website)
    {
        if (!isset($this->config['websites'][$website])) {
            throw new UnknownWebsiteException($website);
        }

        $this->website = $website;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getWebsite()
    {
        $this->website;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    public function getConfig()
    {
        if (is_null($this->config)) {
            $this->config = config("shopify.websites.{$this->website}");
        }

        return $this->config;
    }

    public function getCredential()
    {
        $credential = $this->getConfig()['websites'][$this->website]['credential'];

        return [$credential['key'], $credential['password']];
    }

    public function getStoreURL()
    {
        return $this->getConfig()['websites'][$this->website]['url'];
    }

    protected function makeURL($source)
    {
        return "https://{$this->getStoreURL()}/admin/api/{$this->version}/{$source}";
    }

    /**
     * @return array
     */
    protected function getRequestOptions()
    {
        $options = [];

        $options['auth'] = $this->getCredential();

        return $options;
    }

    public function request($method, $source, $data = [])
    {
        if (is_null($this->website)) {
            throw new UnsetWebsiteException;
        }

        $options = $this->getRequestOptions();

        $method = strtoupper($method);

        if ($method === 'GET') {
            $options['query'] = $data;
        } else {
            $options['json'] = $data;
        }

        $request = new Request($this->http);

        $url = Str::startsWith($source, 'http')
            ? $source
            : $this->makeURL($source);

        $response = $request->call($method, $url, $options, $this->retries);

        return new Response($response);
    }
}
