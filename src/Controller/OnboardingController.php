<?php

namespace App\Controller;

use App\Repository\GenreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller per il flusso di onboarding.
 * Al primo accesso, ogni utente deve selezionare almeno 3 generi letterari preferiti
 * prima di poter accedere al feed personalizzato.
 */
class OnboardingController extends AbstractController
{
    /**
     * Mostra la pagina di selezione dei generi.
     * Se l'utente ha già completato l'onboarding, reindirizza direttamente al feed.
     */
    #[Route('/onboarding', name: 'app_onboarding')]
    public function genres(GenreRepository $genreRepo): Response
    {
        $user = $this->getUser();

        // Salta l'onboarding se già completato in precedenza
        if ($user->isOnboardingCompleted()) {
            return $this->redirectToRoute('app_feed');
        }

        return $this->render('onboarding/genres.html.twig', [
            'genres' => $genreRepo->findAllOrdered(),
        ]);
    }

    /**
     * Salva i generi selezionati dall'utente e segna l'onboarding come completato.
     * Rimuove prima i generi precedenti per evitare duplicati in caso di rivisita.
     */
    #[Route('/onboarding/save', name: 'app_onboarding_save', methods: ['POST'])]
    public function save(
        Request $request,
        GenreRepository $genreRepo,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCsrfTokenValid('onboarding', $request->request->get('_token'))) {
            return $this->redirectToRoute('app_onboarding');
        }

        $user     = $this->getUser();
        $genreIds = $request->request->all('genres');

        // Rimuove i generi precedentemente selezionati
        foreach ($user->getPreferredGenres() as $genre) {
            $user->removePreferredGenre($genre);
        }

        // Associa i nuovi generi selezionati
        foreach ($genreIds as $id) {
            $genre = $genreRepo->find($id);
            if ($genre) {
                $user->addPreferredGenre($genre);
            }
        }

        $user->setOnboardingCompleted(true);
        $em->flush();

        $this->addFlash('success', 'Generi salvati! Ecco il tuo feed.');

        return $this->redirectToRoute('app_feed');
    }
}
