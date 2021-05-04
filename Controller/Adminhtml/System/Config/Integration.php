<?php

namespace ID\ActiveCampaign\Controller\Adminhtml\System\Config;

use GuzzleHttp\Client;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ResponseFactory;
use ID\ActiveCampaign\Helper\Data;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;

class Integration extends Action
{
    protected $_logger;
    protected $_clientFactory;
    protected $_responseFactory;
    protected $_client;
    protected $_helper;
    protected $_path = '/api/3/connections';
    protected $_configWriter;

    public function __construct(
        Context $context,
        \Psr\Log\LoggerInterface $logger,
        Data $helper,
        ClientFactory $clientFactory,
        ResponseFactory $responseFactory,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter
    ) {
        $this->_logger = $logger;
        $this->_clientFactory = $clientFactory;
        $this->_responseFactory = $responseFactory;
        $this->_helper = $helper;
        $this->_configWriter = $configWriter;

        $this->_client = $this->_clientFactory->create(['config' => [
            'base_uri' => $this->_helper->getLoginConfig('api_url')
        ]]);
        parent::__construct($context);
    }

    public function execute()
    {
        if( !$this->_helper->getLoginConfig('integration_id') ) {
            $params = array(
                'headers' => array('Api-Token' => $this->_helper->getLoginConfig('key')),
                'json' => array( 'connection' => array(
                    'service' => 'Magento 2',
                    'externalid' => 'default',
                    'name' => $this->_helper->getGeneralConfig('name'),
                    'logoUrl' => $this->_helper->getGeneralConfig('logo'),
                    'linkUrl' => $this->_helper->getGeneralConfig('link')
                )),
            );

            try {
                $response = $this->_client->request('POST', $this->_path, $params);

                if( $response->getStatusCode() == 201 ) {
                    $response_data = json_decode($response->getBody());

                    $this->_configWriter->save('activecampaign/login/integration_id', $response_data->connection->id, 'default', 0);
                }
            } catch (GuzzleException $exception) {
                $this->_logger->info('exception');
                $this->_logger->info($exception->getMessage());
            }
        }
    }

}