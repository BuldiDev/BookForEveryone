<?php

namespace App\Controller;

use App\Repository\UserBookRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProfileController extends AbstractController
{
    #[Route('/profile/{username}', name: 'app_profile')]
    public function show(
        string $username,
        UserRepository $userRepo,
        UserBookRepository $userBookRepo,
    ): Response {
        $user = $userRepo->findByUsername($username);

        if (!$user) {
            throw $this->createNotFoundException('Utente non trovato');
        }

        return $this->render('profile/show.html.twig', [
            'user' => $user,
            'readBooks' => $userBookRepo->findReadByUser($user),
        ]);
    }

    #[Route('/user/{id}/follow', name: 'app_follow_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleFollow(
        int $id,
        Request $request,
        UserRepository $userRepo,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $targetUser = $userRepo->find($id);
        if (!$targetUser) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('follow' . $id, $request->request->get('_token'))) {
            return $this->redirectToRoute('app_profile', ['username' => $targetUser->getUsername()]);
        }

        $currentUser = $this->getUser();

        if ($currentUser->isFollowing($targetUser)) {
            $currentUser->unfollow($targetUser);
        } else {
            $currentUser->follow($targetUser);
        }

        $em->flush();

        return $this->redirectToRoute('app_profile', ['username' => $targetUser->getUsername()]);
    }
}
