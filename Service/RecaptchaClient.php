<?php

/*
 * @copyright   2018 Konstantin Scheumann. All rights reserved
 * @author      Konstantin Scheumann
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticRecaptchaBundle\Service;

use GuzzleHttp\Client as GuzzleClient;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use Mautic\PluginBundle\Integration\AbstractIntegration;
use MauticPlugin\MauticRecaptchaBundle\Integration\RecaptchaIntegration;

class RecaptchaClient extends CommonSubscriber
{
    public const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * @var string
     */
    protected $siteKey;

    /**
     * @var string
     */
    protected $secretKey;

    /**
     * FormSubscriber constructor.
     *
     * @param IntegrationHelper $integrationHelper
     */
    public function __construct(IntegrationHelper $integrationHelper)
    {
        $integrationObject = $integrationHelper->getIntegrationObject(RecaptchaIntegration::INTEGRATION_NAME);

        if ($integrationObject instanceof AbstractIntegration) {
            $keys = $integrationObject->getKeys();
            $this->siteKey = $keys['site_key'] ?? null;
            $this->secretKey = $keys['secret_key'] ?? null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [];
    }


    /**
     * @param string $token
     *
     * @return bool
     */
    public function verify(string $token): bool
    {
        $client = new GuzzleClient([ 'timeout' => 10 ]);

        $response = $client->post(
            self::VERIFY_URL,
            [
                'form_params' => [
                    'secret' => $this->secretKey,
                    'response' => $token,
                ],
            ]
        );

        $response = json_decode($response->getBody(), true);

        return array_key_exists('success', $response) && $response['success'] === true;
    }
}
