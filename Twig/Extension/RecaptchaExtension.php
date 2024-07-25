<?php

namespace MauticPlugin\MauticRecaptchaBundle\Twig\Extension;

use Mautic\PluginBundle\Helper\IntegrationHelper;
use Mautic\PluginBundle\Integration\AbstractIntegration;
use MauticPlugin\MauticRecaptchaBundle\Integration\RecaptchaIntegration;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class RecaptchaExtension extends AbstractExtension
{

    private ?string $siteKey;

    private ?string $version;

    private ?string $secretKey;

    private bool $isEnabled = false;

    public function __construct(IntegrationHelper $integrationHelper)
    {
        $integrationObject = $integrationHelper->getIntegrationObject(
            RecaptchaIntegration::INTEGRATION_NAME
        );

        if ($integrationObject instanceof AbstractIntegration) {
            $keys = $integrationObject->getKeys();
            $integrationSettings = $integrationObject->getIntegrationSettings();
            $this->isEnabled = $integrationSettings->isPublished();

            $this->version = $keys['version'] ?? null;
            $this->siteKey = $keys['site_key'] ?? null;
            $this->secretKey = $keys['secret_key'] ?? null;
        }
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('hash', [$this, 'hash'], ['is_safe' => ['all']]),
            new TwigFunction('version', [$this, 'version'], ['is_safe' => ['all']]),
            new TwigFunction('siteKey', [$this, 'siteKey'], ['is_safe' => ['all']]),
            new TwigFunction('recaptchaJs', [$this, 'recaptchaJs'], ['is_safe' => ['all']]),
            new TwigFunction('isConfigured', [$this, 'isConfigured'], ['is_safe' => ['all']]),
            new TwigFunction('isEnabled', [$this, 'isEnabled'], ['is_safe' => ['all']]),
        ];
    }

    public function hash(string $formName): string
    {
        return md5($formName);
    }

    public function version(): ?string
    {
        return $this->version;
    }

    public function siteKey(): ?string
    {
        return $this->siteKey;
    }

    public function recaptchaJs(string $hashedFormName): string
    {
        if ($this->version === 'v2') {
            $jsUrl = 'https://www.google.com/recaptcha/api.js';
        } else {
            $jsUrl = "https://www.google.com/recaptcha/api.js?onload=onLoad_{$hashedFormName}&render={$this->siteKey}";
        }

        return $jsUrl;
    }

    public function isConfigured(): bool
    {
        return !empty($this->siteKey) && !empty($this->secretKey) && !empty($this->version);
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

}
