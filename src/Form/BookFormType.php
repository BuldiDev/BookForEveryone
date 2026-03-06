<?php

namespace App\Form;

use App\Entity\Book;
use App\Entity\Genre;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class BookFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titolo',
                'constraints' => [new NotBlank(message: 'Inserisci il titolo')],
            ])
            ->add('bookAuthor', TextType::class, [
                'label' => 'Autore',
                'constraints' => [new NotBlank(message: 'Inserisci l\'autore')],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Descrizione',
                'required' => false,
            ])
            ->add('isbn', TextType::class, [
                'label' => 'ISBN',
                'required' => true,
                'constraints' => [new NotBlank(message: 'Inserisci l\'ISBN del libro')],
            ])
            ->add('genres', EntityType::class, [
                'class' => Genre::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => false,
                'label' => 'Generi',
                'required' => false,
                'attr' => [
                    'class' => 'hidden-genre-select',
                    'style' => 'display: none;',
                ],
            ])
            ->add('pageCount', IntegerType::class, [
                'label' => 'Pagine',
                'required' => false,
            ])
            ->add('publisher', TextType::class, [
                'label' => 'Editore',
                'required' => false,
            ])
            ->add('publicationYear', IntegerType::class, [
                'label' => 'Anno di pubblicazione',
                'required' => false,
            ])
            ->add('coverFile', FileType::class, [
                'label' => 'Copertina ufficiale',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Carica un\'immagine valida (JPG, PNG, WebP)',
                    ),
                ],
            ])
            ->add('userPhotos', FileType::class, [
                'label' => 'Le tue foto del libro',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'constraints' => [
                ],
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp',
                    'multiple' => 'multiple',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Book::class,
        ]);
    }
}
