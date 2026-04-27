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

/**
 * Controller per la gestione dei profili utente.
 * Include visualizzazione del profilo pubblico, lista follower/seguiti,
 * toggle del follow e modifica delle impostazioni personali.
 */
class ProfileController extends AbstractController
{
    /**
     * Mostra il profilo pubblico di un utente con le sue raccolte di libri:
     * libri consigliati (aggiunti), libri letti e libri posseduti.
     */
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

        $readBooks  = $userBookRepo->findReadByUser($user);
        $ownedBooks = $userBookRepo->findOwnedByUser($user);
        $counts     = $userRepo->getProfileCounts($user);

        return $this->render('profile/show.html.twig', [
            'user'             => $user,
            'readBooks'        => $readBooks,
            'ownedBooks'       => $ownedBooks,
            'readCount'        => count($readBooks),
            'ownedCount'       => count($ownedBooks),
            'followersCount'   => $counts['followers'],
            'followingCount'   => $counts['following'],
            'recommendedBooks' => $bookRepo->findBy(['addedBy' => $user], ['createdAt' => 'DESC']),
        ]);
    }

    /**
     * Mostra la lista degli utenti che seguono il profilo indicato.
     */
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
            'user'      => $user,
            'followers' => $user->getFollowers(),
        ]);
    }

    /**
     * Mostra la lista degli utenti che il profilo indicato segue.
     */
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
            'user'      => $user,
            'following' => $user->getFollowing(),
        ]);
    }

    /**
     * Aggiunge o rimuove il follow verso un altro utente (toggle).
     * Supporta sia richieste AJAX (restituisce JSON) sia form POST tradizionali.
     * Invalida la cache Redis dei contatori follow dopo ogni modifica.
     */
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

        // Valida il token CSRF incluso nel form o nella richiesta AJAX
        if (!$this->isCsrfTokenValid('follow' . $id, $request->request->get('_token'))) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Token CSRF non valido'], 400);
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

        // Invalida la cache Redis dei contatori e degli ID following per entrambi gli utenti
        $userRepo->invalidateFollowingIds($currentUser);
        $userRepo->invalidateProfileCounts($currentUser);
        $userRepo->invalidateProfileCounts($targetUser);

        // Risposta JSON per le richieste AJAX (pulsante follow nel feed e nelle liste utenti)
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'following' => !$isFollowing,
            ]);
        }

        return $this->redirectToRoute('app_profile', ['username' => $targetUser->getUsername()]);
    }

    /**
     * Mostra e gestisce la pagina delle impostazioni personali dell'utente:
     * nome visualizzato, biografia e posizione geografica (latitudine/longitudine).
     */
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
            $bio         = trim($request->request->getString('bio'));
            $lat         = $request->request->get('latitude');
            $lng         = $request->request->get('longitude');

            // I campi opzionali vengono impostati a null se vuoti
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
