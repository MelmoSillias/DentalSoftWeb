<?php

// src/Form/EmployeType.php
namespace App\Form;

use App\Entity\Employe;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class EmployeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom')
            ->add('prenom')
            ->add('telephone')
            ->add('fonction')
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Médecin' => 'medecin',
                    'Administratif' => 'administratif',
                    'Technicien' => 'technicien',
                ]
            ])
            ->add('salaireFixe')
            ->add('pourcentage');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Employe::class,
        ]);
    }
}