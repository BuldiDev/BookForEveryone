<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\LoanRequest;
use App\Repository\BookRepository;
use App\Repository\ConversationRepository;
use App\Repository\LoanRequestRepository;
use App\Repository\UserBookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/loan-request')]
class LoanRequestController extends AbstractController
{
    /**
     * Crea una nuova richiesta di prestito da un libro sulla mappa.
     * Apre (o riusa) una conversazione con il proprietario e deduce 1 culture point.
     */
    #[Route('/new/{bookId}/{ownerId}', name: 'app_loan_request_new', methods: ['POST'])]
    public function new(
        int $bookId,
        int $ownerId,
        Request $request,
        BookRepository $bookRepo,
        UserBookRepository $userBookRepo,
        ConversationRepository $convRepo,
        LoanRequestRepository $loanRepo,
        EntityManagerInterface $em,
        HubInterface $hub,
    ): Response {
        $me = $this->getUser();

        if ($me->getId() === $ownerId) {
            throw $this->createAccessDeniedException('Non puoi richiedere il tuo stesso libro.');
        }

        if (!$this->isCsrfTokenValid('loan_request_new', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF non valido.');
        }

        $book = $bookRepo->find($bookId);
        if (!$book) {
            throw $this->createNotFoundException('Libro non trovato.');
        }

        // Verifica che il proprietario possieda fisicamente il libro
        $ownerUser = $em->getReference(\App\Entity\User::class, $ownerId);
        $userBook = $userBookRepo->findByUserAndBook($ownerUser, $book);
        if (!$userBook || !$userBook->ownsPhysically()) {
            $this->addFlash('error', 'Il proprietario non ha più questo libro disponibile.');
            return $this->redirectToRoute('app_borrow');
        }

        // Non puoi chiedere il tuo stesso libro
        if ($ownerUser->getId() === $me->getId()) {
            $this->addFlash('error', 'Non puoi richiedere il tuo stesso libro.');
            return $this->redirectToRoute('app_borrow');
        }

        // Verifica punti cultura sufficienti (minimo 1)
        if ($me->getCulturePoints() < 1) {
            $this->addFlash('error', 'Non hai abbastanza Culture Points per fare una richiesta. Ti serve almeno 1 punto.');
            return $this->redirectToRoute('app_borrow');
        }

        // Richiesta attiva già esistente?
        $existing = $loanRepo->findActiveBetweenForBook($me, $ownerUser, $bookId);
        if ($existing) {
            return $this->redirectToRoute('app_chat_show', ['id' => $existing->getConversation()->getId()]);
        }

        // Crea o recupera conversazione
        $conversation = $convRepo->findBetween($me, $ownerUser);
        if (!$conversation) {
            $conversation = new Conversation();
            $conversation->addParticipant($me);
            $conversation->addParticipant($ownerUser);
            $em->persist($conversation);
        }

        // Deduce 1 culture point al richiedente
        $me->addCulturePoints(-1);

        // Crea la richiesta
        $loanRequest = new LoanRequest();
        $loanRequest->setRequester($me);
        $loanRequest->setOwner($ownerUser);
        $loanRequest->setBook($book);
        $loanRequest->setConversation($conversation);

        $em->persist($loanRequest);
        $em->flush();

        // Pubblica aggiornamento banner via Mercure
        $this->publishBannerUpdate($hub, $conversation, $loanRequest);

        return $this->redirectToRoute('app_chat_show', ['id' => $conversation->getId()]);
    }

    /**
     * Il proprietario propone N culture points per il prestito.
     */
    #[Route('/{id}/propose', name: 'app_loan_request_propose', methods: ['POST'])]
    public function propose(
        LoanRequest $loanRequest,
        Request $request,
        EntityManagerInterface $em,
        HubInterface $hub,
    ): Response {
        $me = $this->getUser();

        if (!$this->isCsrfTokenValid('loan_action_' . $loanRequest->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if (!$loanRequest->isOwner($me)) {
            throw $this->createAccessDeniedException();
        }

        if ($loanRequest->getStatus() !== LoanRequest::STATUS_PENDING) {
            $this->addFlash('error', 'Non puoi proporre punti in questo momento.');
            return $this->redirectToRoute('app_chat_show', ['id' => $loanRequest->getConversation()->getId()]);
        }

        $points = max(1, $request->request->getInt('proposed_points', 1));
        $loanRequest->setProposedPoints($points);
        $loanRequest->setStatus(LoanRequest::STATUS_PROPOSED);

        $em->flush();
        $this->publishBannerUpdate($hub, $loanRequest->getConversation(), $loanRequest);

        return $this->redirectToRoute('app_chat_show', ['id' => $loanRequest->getConversation()->getId()]);
    }

    /**
     * Il richiedente accetta la proposta di punti → si passa ad awaiting_start.
     */
    #[Route('/{id}/accept-proposal', name: 'app_loan_request_accept_proposal', methods: ['POST'])]
    public function acceptProposal(
        LoanRequest $loanRequest,
        Request $request,
        EntityManagerInterface $em,
        HubInterface $hub,
    ): Response {
        $me = $this->getUser();

        if (!$this->isCsrfTokenValid('loan_action_' . $loanRequest->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if (!$loanRequest->isRequester($me)) {
            throw $this->createAccessDeniedException();
        }

        if ($loanRequest->getStatus() !== LoanRequest::STATUS_PROPOSED) {
            $this->addFlash('error', 'Non puoi accettare in questo momento.');
            return $this->redirectToRoute('app_chat_show', ['id' => $loanRequest->getConversation()->getId()]);
        }

        $loanRequest->setStatus(LoanRequest::STATUS_AWAITING_START);

        $em->flush();
        $this->publishBannerUpdate($hub, $loanRequest->getConversation(), $loanRequest);

        return $this->redirectToRoute('app_chat_show', ['id' => $loanRequest->getConversation()->getId()]);
    }

    /**
     * Conferma "Inizio Scambio" (entrambi devono premere).
     * Quando entrambi confermano: libro rimosso dalla mappa, stato → in_progress.
     */
    #[Route('/{id}/confirm-start', name: 'app_loan_request_confirm_start', methods: ['POST'])]
    public function confirmStart(
        LoanRequest $loanRequest,
        Request $request,
        UserBookRepository $userBookRepo,
        EntityManagerInterface $em,
        HubInterface $hub,
    ): Response {
        $me = $this->getUser();

        if (!$this->isCsrfTokenValid('loan_action_' . $loanRequest->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($loanRequest->getStatus() !== LoanRequest::STATUS_AWAITING_START) {
            return $this->redirectToRoute('app_chat_show', ['id' => $loanRequest->getConversation()->getId()]);
        }

        if ($loanRequest->isRequester($me)) {
            $loanRequest->setRequesterAcceptedStart(true);
        } elseif ($loanRequest->isOwner($me)) {
            $loanRequest->setOwnerAcceptedStart(true);
        } else {
            throw $this->createAccessDeniedException();
        }

        // Entrambi hanno confermato?
        if ($loanRequest->isRequesterAcceptedStart() && $loanRequest->isOwnerAcceptedStart()) {
            $loanRequest->setStatus(LoanRequest::STATUS_IN_PROGRESS);

            // Rimuovi il libro dalla mappa (ownsPhysically = false)
            $owner = $loanRequest->getOwner();
            $userBook = $userBookRepo->findByUserAndBook($owner, $loanRequest->getBook());
            if ($userBook) {
                $userBook->setOwnsPhysically(false);
            }
        }

        $em->flush();
        $this->publishBannerUpdate($hub, $loanRequest->getConversation(), $loanRequest);

        return $this->redirectToRoute('app_chat_show', ['id' => $loanRequest->getConversation()->getId()]);
    }

    /**
     * Conferma "Fine Scambio" (entrambi devono premere).
     * Quando entrambi confermano: punti trasferiti, stato → completed.
     */
    #[Route('/{id}/confirm-end', name: 'app_loan_request_confirm_end', methods: ['POST'])]
    public function confirmEnd(
        LoanRequest $loanRequest,
        Request $request,
        UserBookRepository $userBookRepo,
        EntityManagerInterface $em,
        HubInterface $hub,
    ): Response {
        $me = $this->getUser();

        if (!$this->isCsrfTokenValid('loan_action_' . $loanRequest->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($loanRequest->getStatus() !== LoanRequest::STATUS_IN_PROGRESS) {
            return $this->redirectToRoute('app_chat_show', ['id' => $loanRequest->getConversation()->getId()]);
        }

        if ($loanRequest->isRequester($me)) {
            $loanRequest->setRequesterAcceptedEnd(true);
        } elseif ($loanRequest->isOwner($me)) {
            $loanRequest->setOwnerAcceptedEnd(true);
        } else {
            throw $this->createAccessDeniedException();
        }

        // Entrambi hanno confermato la fine?
        if ($loanRequest->isRequesterAcceptedEnd() && $loanRequest->isOwnerAcceptedEnd()) {
            $loanRequest->setStatus(LoanRequest::STATUS_COMPLETED);

            $points = $loanRequest->getProposedPoints() ?? 0;
            $requester = $loanRequest->getRequester();
            $owner = $loanRequest->getOwner();

            // Trasferimento punti
            $requester->addCulturePoints(-$points);
            $owner->addCulturePoints($points);
        }

        $em->flush();
        $this->publishBannerUpdate($hub, $loanRequest->getConversation(), $loanRequest);

        return $this->redirectToRoute('app_chat_show', ['id' => $loanRequest->getConversation()->getId()]);
    }

    /**
     * Rifiuta la richiesta (qualsiasi partecipante può rifiutare).
     */
    #[Route('/{id}/reject', name: 'app_loan_request_reject', methods: ['POST'])]
    public function reject(
        LoanRequest $loanRequest,
        Request $request,
        EntityManagerInterface $em,
        HubInterface $hub,
    ): Response {
        $me = $this->getUser();

        if (!$this->isCsrfTokenValid('loan_action_' . $loanRequest->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if (!$loanRequest->isRequester($me) && !$loanRequest->isOwner($me)) {
            throw $this->createAccessDeniedException();
        }

        if (!$loanRequest->isActive()) {
            return $this->redirectToRoute('app_chat_show', ['id' => $loanRequest->getConversation()->getId()]);
        }

        // Se rifiutato durante pending, restituisci il punto al richiedente
        if ($loanRequest->getStatus() === LoanRequest::STATUS_PENDING) {
            $loanRequest->getRequester()->addCulturePoints(1);
        }

        $loanRequest->setStatus(LoanRequest::STATUS_REJECTED);
        $em->flush();

        $this->publishBannerUpdate($hub, $loanRequest->getConversation(), $loanRequest);

        return $this->redirectToRoute('app_chat_show', ['id' => $loanRequest->getConversation()->getId()]);
    }

    private function publishBannerUpdate(HubInterface $hub, Conversation $conversation, LoanRequest $loanRequest): void
    {
        $html = $this->renderView('chat/_loan_banner_stream.html.twig', [
            'loanRequest' => $loanRequest,
        ]);

        $hub->publish(new Update(
            'chat/conversation/' . $conversation->getId() . '/loan',
            $html,
        ));
    }
}
