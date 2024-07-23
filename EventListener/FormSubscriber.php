<?php

/*
 * @copyright   2018 Konstantin Scheumann. All rights reserved
 * @author      Konstantin Scheumann
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticRecaptchaBundle\EventListener;

use Mautic\CoreBundle\Exception\BadConfigurationException;
use Mautic\CoreBundle\Helper\PathsHelper;
use Mautic\FormBundle\Event\FormBuilderEvent;
use Mautic\FormBundle\Event\ValidationEvent;
use Mautic\FormBundle\FormEvents;
use Mautic\LeadBundle\Event\LeadEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use Mautic\PluginBundle\Integration\AbstractIntegration;
use MauticPlugin\MauticRecaptchaBundle\Form\Type\RecaptchaType;
use MauticPlugin\MauticRecaptchaBundle\Helper\RecaptchaHelper;
use MauticPlugin\MauticRecaptchaBundle\Integration\RecaptchaIntegration;
use MauticPlugin\MauticRecaptchaBundle\RecaptchaEvents;
use MauticPlugin\MauticRecaptchaBundle\Service\RecaptchaClient;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FormSubscriber implements EventSubscriberInterface
{

    private string $siteKey;

    private string $version;

    private bool $recaptchaIsConfigured;

    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
        protected IntegrationHelper $integrationHelper,
        protected RecaptchaClient $recaptchaClient,
        protected LeadModel $leadModel,
        protected TranslatorInterface $translator,
        protected PathsHelper $pathsHelper
    ) {
        $integrationObject = $integrationHelper->getIntegrationObject(
            RecaptchaIntegration::INTEGRATION_NAME
        );

        if ($integrationObject instanceof AbstractIntegration) {
            $keys = $integrationObject->getKeys();
            $this->version = $keys['version'] ?? null;
            $this->siteKey = $keys['site_key'] ?? null;
            $secretKey = $keys['secret_key'] ?? null;

            if ($this->siteKey && $secretKey) {
                $this->recaptchaIsConfigured = true;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::FORM_ON_BUILD => ['onFormBuild', 0],
            RecaptchaEvents::ON_FORM_VALIDATE => ['onFormValidate', 0],
        ];
    }

    /**
     * @param FormBuilderEvent $event
     *
     * @throws BadConfigurationException
     */
    public function onFormBuild(FormBuilderEvent $event): void
    {
        if (!$this->recaptchaIsConfigured) {
            return;
        }

        $event->addFormField('plugin.recaptcha', [
            'label' => 'mautic.plugin.actions.recaptcha',
            'formType' => RecaptchaType::class,
            'template' => '@MauticRecaptcha/Form/recaptcha.html.twig',
            'builderOptions' => [
                'addLeadFieldList' => false,
                'addIsRequired' => false,
                'addDefaultValue' => false,
                'addSaveResult' => true,
            ],
        ]);

        $event->addValidator('plugin.recaptcha.validation', [
            'eventName' => RecaptchaEvents::ON_FORM_VALIDATE,
            'fieldType' => 'plugin.recaptcha',
        ]);
    }

    /**
     * @param ValidationEvent $event
     */
    public function onFormValidate(ValidationEvent $event): void
    {
        if (!$this->recaptchaIsConfigured) {
            return;
        }

        if ($this->recaptchaClient->verify(
            $event->getValue(),
            $event->getField()
        )) {
            return;
        }

        $event->failedValidation(
            $this->translator === null
                ? 'reCAPTCHA was not successful.'
                : $this->translator->trans(
                'mautic.integration.recaptcha.failure_message'
            )
        );

        $this->eventDispatcher->addListener(
            LeadEvents::LEAD_POST_SAVE,
            function (LeadEvent $event) {
                if ($event->isNew()) {
                    $this->leadModel->deleteEntity($event->getLead());
                }
            },
            -255
        );
    }
}
