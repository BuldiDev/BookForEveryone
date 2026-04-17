<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\BookRepository;
use App\Repository\UserBookRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        BookRepository $bookRepo,
    ): Response {
        $user = $userRepo->findByUsername($username);

        if (!$user) {
            throw $this->createNotFoundException('Utente non trovato');
        }

        $readBooks = $userBookRepo->findReadByUser($user);
        $ownedBooks = $userBookRepo->findOwnedByUser($user);
        $counts = $userRepo->getProfileCounts($user);

        return $this->render('profile/show.html.twig', [
            'user' => $user,
            'readBooks' => $readBooks,
            'ownedBooks' => $ownedBooks,
            'readCount' => count($readBooks),
            'ownedCount' => count($ownedBooks),
            'followersCount' => $counts['followers'],
            'followingCount' => $counts['following'],
            'recommendedBooks' => $bookRepo->findBy(['addedBy' => $user], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/profile/{username}/followers', name: 'app_profile_followers')]
    public function followers(
        string $username,
        UserRepository $userRepo,
    ): Response {
        $user = $userRepo->findByUsername($username);

        if (!$user) {
            throw $this->createNotFoundException('Utente non trovato');
        }

        return $this->render('profile/followers.html.twig', [
            'user' => $user,
            'followers' => $user->getFollowers(),
        ]);
    }

    #[Route('/profile/{username}/following', name: 'app_profile_following')]
    public function following(
        string $username,
        UserRepository $userRepo,
    ): Response {
        $user = $userRepo->findByUsername($username);

        if (!$user) {
            throw $this->createNotFoundException('Utente non trovato');
        }

        return $this->render('profile/following.html.twig', [
            'user' => $user,
            'following' => $user->getFollowing(),
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
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Invalid CSRF token'], 400);
            }
            return $this->redirectToRoute('app_profile', ['username' => $targetUser->getUsername()]);
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $isFollowing = $currentUser->isFollowing($targetUser);
        
        if ($isFollowing) {
            $currentUser->unfollow($targetUser);
        } else {
            $currentUser->follow($targetUser);
        }

        $em->flush();

        // Invalida cache Redis per follow counts e following IDs
        $userRepo->invalidateFollowingIds($currentUser);
        $userRepo->invalidateProfileCounts($currentUser);
        $userRepo->invalidateProfileCounts($targetUser);

        // Return JSON for AJAX requests
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'following' => !$isFollowing,
            ]);
        }

        return $this->redirectToRoute('app_profile', ['username' => $targetUser->getUsername()]);
    }

    #[Route('/settings', name: 'app_settings')]
    public function settings(
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('settings', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token CSRF non valido.');
                return $this->redirectToRoute('app_settings');
            }

            $displayName = trim($request->request->getString('displayName'));
            $bio = trim($request->request->getString('bio'));
            $lat = $request->request->get('latitude');
            $lng = $request->request->get('longitude');

            $user->setDisplayName($displayName ?: null);
            $user->setBio($bio ?: null);
            $user->setLatitude($lat !== '' && $lat !== null ? (float) $lat : null);
            $user->setLongitude($lng !== '' && $lng !== null ? (float) $lng : null);

            $em->flush();
            $this->addFlash('success', 'Impostazioni salvate.');

            return $this->redirectToRoute('app_settings');
        }

        return $this->render('profile/settings.html.twig', [
            'user' => $user,
        ]);
    }
}
