<?php

/*
 * @copyright   2018 Konstantin Scheumann. All rights reserved
 * @author      Konstantin Scheumann
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticRecaptchaBundle\Service;

use GuzzleHttp\Client as GuzzleClient;
use Mautic\CoreBundle\Helper\ArrayHelper;
use Mautic\FormBundle\Entity\Field;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use Mautic\PluginBundle\Integration\AbstractIntegration;
use MauticPlugin\MauticRecaptchaBundle\Integration\RecaptchaIntegration;

class RecaptchaClient
{
    public const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    protected string $siteKey;

    protected string $secretKey;

    public function __construct(IntegrationHelper $integrationHelper)
    {
        $integrationObject = $integrationHelper->getIntegrationObject(
            RecaptchaIntegration::INTEGRATION_NAME
        );

        if ($integrationObject instanceof AbstractIntegration) {
            $keys = $integrationObject->getKeys();
            $this->siteKey = $keys['site_key'] ?? null;
            $this->secretKey = $keys['secret_key'] ?? null;
        }
    }

    public function verify(string $token, Field $field): bool
    {
        $client = new GuzzleClient(['timeout' => 10]);
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

        if (array_key_exists('success', $response) && $response['success'] === true) {
            $score = (float)ArrayHelper::getValue('score', $response);
            $scoreValidation = ArrayHelper::getValue(
                'scoreValidation',
                $field->getProperties()
            );
            $minScore = (float)ArrayHelper::getValue(
                'minScore',
                $field->getProperties()
            );

            return !($score && $scoreValidation && $minScore > $score);
        }

        return false;
    }
}
