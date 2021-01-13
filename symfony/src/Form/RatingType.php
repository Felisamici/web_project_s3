<?php

namespace App\Form;

use App\Entity\Rating;
//use Doctrine\DBAL\Types\TextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RatingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('value', NumberType::class, [
                'required' => true,
                'html5' => true,
                'attr'     => array(
                    'min'  => 0,
                    'max'  => 10,
                    'step' => 0.5,
                ),
                'label' => "Note ",
            ])
            ->add('comment', TextareaType::class, [
                'label' => "Commentaire ",
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Rating::class,
        ]);
    }
}
