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

/**
 * Form per l'aggiunta di un nuovo libro al catalogo.
 *
 * La maggior parte dei campi viene compilata automaticamente dal JavaScript
 * dopo la ricerca per ISBN (fetchIsbn). Sono presenti due campi file extra,
 * non mappati sull'entità, gestiti direttamente nel controller:
 *  - coverFile: copertina ufficiale del libro (max 5 MB)
 *  - userPhotos: foto personali dell'utente, accetta più file
 *
 * I generi sono gestiti tramite un selettore personalizzato in JavaScript
 * (genre-selector); il <select> originale viene nascosto via CSS.
 */
class BookFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Titolo obbligatorio, pre-compilato dalla ricerca ISBN
            ->add('title', TextType::class, [
                'label'       => 'Titolo',
                'constraints' => [new NotBlank(message: 'Inserisci il titolo')],
            ])

            // Autore obbligatorio, pre-compilato dalla ricerca ISBN
            ->add('bookAuthor', TextType::class, [
                'label'       => 'Autore',
                'constraints' => [new NotBlank(message: 'Inserisci l\'autore')],
            ])

            // Descrizione facoltativa, pre-compilata dalla ricerca ISBN se disponibile
            ->add('description', TextareaType::class, [
                'label'    => 'Descrizione',
                'required' => false,
            ])

            // ISBN obbligatorio: viene usato come identificatore univoco nel DB
            ->add('isbn', TextType::class, [
                'label'       => 'ISBN',
                'required'    => true,
                'constraints' => [new NotBlank(message: 'Inserisci l\'ISBN del libro')],
            ])

            // Generi: il <select> multiplo è nascosto; il JS si occupa di selezionare i valori
            ->add('genres', EntityType::class, [
                'class'        => Genre::class,
                'choice_label' => 'name',
                'multiple'     => true,
                'expanded'     => false,
                'label'        => 'Generi',
                'required'     => false,
                'attr'         => [
                    'class' => 'hidden-genre-select',
                    'style' => 'display: none;',
                ],
            ])

            // Numero di pagine facoltativo, pre-compilato dalla ricerca ISBN
            ->add('pageCount', IntegerType::class, [
                'label'      => 'Pagine',
                'required'   => false,
                'empty_data' => null,
            ])

            // Nome dell'editore facoltativo
            ->add('publisher', TextType::class, [
                'label'    => 'Editore',
                'required' => false,
            ])

            // Anno di pubblicazione facoltativo
            ->add('publicationYear', IntegerType::class, [
                'label'      => 'Anno di pubblicazione',
                'required'   => false,
                'empty_data' => null,
            ])

            // Campo file non mappato: la copertina ufficiale viene gestita dal controller
            ->add('coverFile', FileType::class, [
                'label'       => 'Copertina ufficiale',
                'mapped'      => false,
                'required'    => false,
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Carica un\'immagine valida (JPG, PNG, WebP)',
                    ),
                ],
            ])

            // Campo file multiplo non mappato: le foto personali vengono gestite dal controller
            ->add('userPhotos', FileType::class, [
                'label'    => 'Le tue foto del libro',
                'mapped'   => false,
                'required' => false,
                'multiple' => true,
                'attr'     => [
                    'accept'   => 'image/jpeg,image/png,image/webp',
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
