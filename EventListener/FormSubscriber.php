<?php

/*
 * @copyright   2018 Konstantin Scheumann. All rights reserved
 * @author      Konstantin Scheumann
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticRecaptchaBundle\EventListener;

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
use MauticPlugin\MauticRecaptchaBundle\Integration\RecaptchaIntegration;
use MauticPlugin\MauticRecaptchaBundle\RecaptchaEvents;
use MauticPlugin\MauticRecaptchaBundle\Service\RecaptchaClient;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Translation\TranslatorInterface;

class FormSubscriber implements EventSubscriberInterface
{

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var RecaptchaClient
     */
    protected $recaptchaClient;

    /**
     * @var string
     */
    protected $siteKey;

    /**
     * @var string
     */
    protected $secretKey;

    /**
     * @var boolean
     */
    private $recaptchaIsConfigured = false;

    /**
     * @var LeadModel
     */
    private $leadModel;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var string|null
     */
    private $version;

    /**
     * @var string
     */
    protected $bundlesRoot;

    /**
     * @param EventDispatcherInterface $eventDispatcher
     * @param IntegrationHelper        $integrationHelper
     * @param RecaptchaClient          $recaptchaClient
     * @param LeadModel                $leadModel
     * @param TranslatorInterface      $translator
     * @param PathsHelper              $pathsHelper
     */
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        IntegrationHelper $integrationHelper,
        RecaptchaClient $recaptchaClient,
        LeadModel $leadModel,
        TranslatorInterface $translator,
        PathsHelper $pathsHelper
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->recaptchaClient = $recaptchaClient;
        $this->bundlesRoot = $pathsHelper->getSystemPath('bundles', true);

        $integrationObject = $integrationHelper->getIntegrationObject(
            RecaptchaIntegration::INTEGRATION_NAME
        );

        if ($integrationObject instanceof AbstractIntegration) {
            $keys = $integrationObject->getKeys();
            $this->siteKey = $keys['site_key'] ?? null;
            $this->secretKey = $keys['secret_key'] ?? null;
            $this->version = $keys['version'] ?? null;

            if ($this->siteKey && $this->secretKey) {
                $this->recaptchaIsConfigured = true;
            }
        }
        $this->leadModel = $leadModel;
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::FORM_ON_BUILD         => ['onFormBuild', 0],
            RecaptchaEvents::ON_FORM_VALIDATE => ['onFormValidate', 0],
        ];
    }

    /**
     * @param FormBuilderEvent $event
     *
     */
    public function onFormBuild(FormBuilderEvent $event): void
    {
        if (!$this->recaptchaIsConfigured) {
            return;
        }

        $event->addFormField('plugin.recaptcha', [
            'label'          => 'mautic.plugin.actions.recaptcha',
            'formType'       => RecaptchaType::class,
            'template'       => 'MauticRecaptchaBundle:Integration:recaptcha.html.php',
            'builderOptions' => [
                'addLeadFieldList' => false,
                'addIsRequired'    => false,
                'addDefaultValue'  => false,
                'addSaveResult'    => true,
            ],
            'site_key'       => $this->siteKey,
            'version'        => $this->version,
            'bundlesRoot'    => $this->bundlesRoot,
        ]);

        $event->addValidator('plugin.recaptcha.validator', [
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
        )
        ) {
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
