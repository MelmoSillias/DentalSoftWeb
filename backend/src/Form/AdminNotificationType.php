<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\NotificationPriority;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminNotificationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('recipients', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'username',
                'multiple' => true,
                'expanded' => false,
                'required' => true,
                'label' => 'Destinataires',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'required' => true,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Entrez le contenu de la notification...',
                ],
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Priorité',
                'required' => true,
                'choices' => [
                    'Info' => NotificationPriority::INFO,
                    'Avertissement' => NotificationPriority::AVERTISSEMENT,
                    'Critique' => NotificationPriority::CRITIQUE,
                ],
                'data' => NotificationPriority::INFO,
            ])
            ->add('link', TextType::class, [
                'label' => 'Lien (optionnel)',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: /patients/12'],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Envoyer',
                'attr' => ['class' => 'btn btn-primary'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}