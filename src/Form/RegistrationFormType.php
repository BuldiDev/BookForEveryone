<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Form di registrazione di un nuovo utente.
 *
 * La password è definita come campo "non mappato" (plainPassword):
 * viene validata qui ma viene hashata e scritta nel controller tramite
 * UserPasswordHasherInterface prima della persistenza.
 *
 * Latitudine e longitudine sono campi nascosti compilati in automatico
 * dal browser tramite la Geolocation API durante la registrazione.
 */
class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Username: solo lettere, numeri e underscore, da 3 a 50 caratteri
            ->add('username', TextType::class, [
                'label'       => 'Username',
                'constraints' => [
                    new NotBlank(message: 'Inserisci un username'),
                    new Length(min: 3, max: 50),
                    new Regex(
                        pattern: '/^[a-zA-Z0-9_]+$/',
                        message: 'Solo lettere, numeri e underscore',
                    ),
                ],
            ])

            // Email: usata come identificatore univoco per il login
            ->add('email', EmailType::class, [
                'label'       => 'Email',
                'constraints' => [
                    new NotBlank(message: 'Inserisci la tua email'),
                ],
            ])

            // Password in chiaro: NON mappata sull'entità, hashata nel controller
            ->add('plainPassword', PasswordType::class, [
                'mapped'      => false,
                'label'       => 'Password',
                'constraints' => [
                    new NotBlank(message: 'Inserisci una password'),
                    new Length(
                        min: 6,
                        minMessage: 'La password deve avere almeno {{ limit }} caratteri',
                        max: 4096,
                    ),
                ],
            ])

            // Latitudine GPS: compilata in automatico dalla Geolocation API del browser
            ->add('latitude', HiddenType::class, [
                'required' => false,
            ])

            // Longitudine GPS: compilata in automatico dalla Geolocation API del browser
            ->add('longitude', HiddenType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
