<?php

/*
 * @copyright   2018 Konstantin Scheumann. All rights reserved
 * @author      Konstantin Scheumann
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticRecaptchaBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\FormButtonsType;
use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Class RecaptchaType.
 */
class RecaptchaType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'scoreValidation',
            YesNoButtonGroupType::class,
            [
                'label'      => 'mautic.recaptcha.score.validation',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'tooltip' => 'mautic.recaptcha.min.score.tooltip',
                ],
                'data'       => $options['data']['scoreValidation'] ?? false,
            ]
        );
        $builder->add(
            'minScore',
            NumberType::class,
            [
                'label'      => 'mautic.recaptcha.min.score',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'        => 'form-control',
                    'data-show-on' => '{"formfield_properties_scoreValidation_1":"checked"}',
                ],
                'data'       => $options['data']['minScore'] ?? 0.8,
            ]
        );

        $builder->add(
            'buttons',
            FormButtonsType::class,
            [
                'apply_text'     => false,
                'save_text'      => 'mautic.core.form.submit',
                'cancel_onclick' => 'javascript:void(0);',
                'cancel_attr'    => [
                    'data-dismiss' => 'modal',
                ],
            ]
        );

        if (!empty($options['action'])) {
            $builder->setAction($options['action']);
        }
    }

    public function getBlockPrefix(): string
    {
        return 'recaptcha';
    }

}
