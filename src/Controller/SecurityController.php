<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Controller per l'autenticazione: login e logout.
 * Il logout è gestito interamente dal firewall di Symfony;
 * questo controller definisce solo la rotta necessaria alla configurazione.
 */
class SecurityController extends AbstractController
{
    /**
     * Mostra il modulo di login.
     * In caso di errore (credenziali errate), Symfony lo passa automaticamente
     * tramite AuthenticationUtils. Se l'utente è già autenticato viene reindirizzato al feed.
     */
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_feed');
        }

        return $this->render('auth/login.html.twig', [
            'last_username' => $authUtils->getLastUsername(),
            'error'         => $authUtils->getLastAuthenticationError(),
        ]);
    }

    /**
     * Rotta di logout: il corpo del metodo non viene mai eseguito.
     * Symfony intercetta la richiesta prima che arrivi qui e invalida la sessione.
     */
    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Intercettato dal firewall di Symfony prima dell'esecuzione
    }
}
