<?php

namespace App\Form;

use App\Communication\Entity\Notification;
use App\IdentityAccess\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ManualNotificationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('recipients', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'username',
                'multiple' => true,
                'expanded' => false,
                'label' => 'Destinataires',
                'attr' => ['data-controller' => 'choices'],
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Priorité',
                'choices' => [
                    'Information' => Notification::PRIORITY_INFO,
                    'Avertissement' => Notification::PRIORITY_WARNING,
                    'Urgence' => Notification::PRIORITY_CRITICAL,
                ],
                'data' => Notification::PRIORITY_INFO,
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'attr' => ['rows' => 4],
            ])
            ->add('link', TextType::class, [
                'label' => 'Lien (optionnel)',
                'required' => false,
                'attr' => ['placeholder' => '/admin/dashboard'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
        ]);
    }
}
