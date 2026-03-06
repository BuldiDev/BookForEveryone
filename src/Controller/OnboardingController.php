<?php

namespace App\Controller;

use App\Repository\GenreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OnboardingController extends AbstractController
{
    #[Route('/onboarding', name: 'app_onboarding')]
    public function genres(GenreRepository $genreRepo): Response
    {
        $user = $this->getUser();

        if ($user->isOnboardingCompleted()) {
            return $this->redirectToRoute('app_feed');
        }

        return $this->render('onboarding/genres.html.twig', [
            'genres' => $genreRepo->findAllOrdered(),
        ]);
    }

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

        $user = $this->getUser();
        $genreIds = $request->request->all('genres');

        // Rimuovi generi vecchi
        foreach ($user->getPreferredGenres() as $genre) {
            $user->removePreferredGenre($genre);
        }

        // Aggiungi nuovi
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
