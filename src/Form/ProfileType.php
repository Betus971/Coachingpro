<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['class' => 'input input-bordered w-full bg-base-100']
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => ['class' => 'input input-bordered w-full bg-base-100']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'attr' => ['class' => 'input input-bordered w-full bg-base-100']
            ])
            ->add('heightCm', IntegerType::class, [
                'label' => 'Taille (cm)',
                'required' => false,
                'attr' => ['class' => 'input input-bordered w-full bg-base-100', 'min' => 100, 'max' => 250, 'placeholder' => '182'],
            ])
            ->add('sex', ChoiceType::class, [
                'label' => 'Sexe',
                'required' => false,
                'placeholder' => '— Non précisé —',
                'choices' => ['Homme' => 'M', 'Femme' => 'F'],
                'attr' => ['class' => 'select select-bordered w-full bg-base-100'],
            ])
            ->add('birthDate', DateType::class, [
                'label' => 'Date de naissance',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'input input-bordered w-full bg-base-100'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
