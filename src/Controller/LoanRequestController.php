<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\LoanRequest;
use App\Entity\Message;
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
     * Invia un'offerta per un libro (stile Vinted).
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

        $ownerUser = $em->getReference(\App\Entity\User::class, $ownerId);
        $userBook = $userBookRepo->findByUserAndBook($ownerUser, $book);
        if (!$userBook || !$userBook->ownsPhysically()) {
            $this->addFlash('error', 'Il proprietario non ha più questo libro disponibile.');
            return $this->redirectToRoute('app_borrow');
        }

        if ($me->getCulturePoints() < 1) {
            $this->addFlash('error', 'Non hai abbastanza Culture Points per fare un\'offerta. Ti serve almeno 1 punto.');
            return $this->redirectToRoute('app_borrow');
        }

        $existing = $loanRepo->findActiveBetweenForBook($me, $ownerUser, $bookId);
        if ($existing) {
            return $this->redirectToRoute('app_chat_show', ['id' => $existing->getConversation()->getId()]);
        }

        $conversation = $convRepo->findBetween($me, $ownerUser);
        if (!$conversation) {
            $conversation = new Conversation();
            $conversation->addParticipant($me);
            $conversation->addParticipant($ownerUser);
            $em->persist($conversation);
        }

        $me->addCulturePoints(-1);

        $offeredPoints = max(1, $request->request->getInt('offered_points', 3));

        $loanRequest = new LoanRequest();
        $loanRequest->setRequester($me);
        $loanRequest->setOwner($ownerUser);
        $loanRequest->setBook($book);
        $loanRequest->setConversation($conversation);
        $loanRequest->setOfferedPoints($offeredPoints);
        $loanRequest->setLastOfferBy('requester');

        $em->persist($loanRequest);

        $msg = $this->createLoanMessage($em, $conversation, $me, $loanRequest, 'loan_offer', 'Offerta: ' . $offeredPoints . ' CP', [
            'points' => $offeredPoints,
            'bookTitle' => $book->getTitle(),
            'bookCover' => $book->getCoverUrl(),
        ]);

        $em->flush();
        $this->publishLoanUpdate($hub, $conversation, $msg, $loanRequest);

        return $this->redirectToRoute('app_chat_show', ['id' => $conversation->getId()]);
    }

    /**
     * Controproposta.
     */
    #[Route('/{id}/counter', name: 'app_loan_request_counter', methods: ['POST'])]
    public function counter(
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

        if ($loanRequest->getStatus() !== LoanRequest::STATUS_PENDING) {
            $this->addFlash('error', 'Non puoi fare una controproposta in questo momento.');
            return $this->redirectToRoute('app_chat_show', ['id' => $loanRequest->getConversation()->getId()]);
        }

        if ($loanRequest->isLastOfferByUser($me)) {
            $this->addFlash('error', 'Devi aspettare la risposta dell\'altra parte.');
            return $this->redirectToRoute('app_chat_show', ['id' => $loanRequest->getConversation()->getId()]);
        }

        $points = max(1, $request->request->getInt('offered_points', 1));
        $loanRequest->setOfferedPoints($points);
        $loanRequest->setLastOfferBy($loanRequest->isRequester($me) ? 'requester' : 'owner');

        $conv = $loanRequest->getConversation();
        $msg = $this->createLoanMessage($em, $conv, $me, $loanRequest, 'loan_counter', 'Controproposta: ' . $points . ' CP', [
            'points' => $points,
            'bookTitle' => $loanRequest->getBook()->getTitle(),
            'bookCover' => $loanRequest->getBook()->getCoverUrl(),
        ]);

        $em->flush();
        $this->publishLoanUpdate($hub, $conv, $msg, $loanRequest);

        return $this->redirectToRoute('app_chat_show', ['id' => $conv->getId()]);
    }

    /**
     * Accetta l'offerta corrente.
     */
    #[Route('/{id}/accept', name: 'app_loan_request_accept', methods: ['POST'])]
    public function accept(
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

        if ($loanRequest->getStatus() !== LoanRequest::STATUS_PENDING) {
            $this->addFlash('error', 'Non puoi accettare in questo momento.');
            return $this->redirectToRoute('app_chat_show', ['id' => $loanRequest->getConversation()->getId()]);
        }

        if ($loanRequest->isLastOfferByUser($me)) {
            $this->addFlash('error', 'Devi aspettare la risposta dell\'altra parte.');
            return $this->redirectToRoute('app_chat_show', ['id' => $loanRequest->getConversation()->getId()]);
        }

        $loanRequest->setStatus(LoanRequest::STATUS_ACCEPTED);

        $conv = $loanRequest->getConversation();
        $msg = $this->createLoanMessage($em, $conv, $me, $loanRequest, 'loan_accepted', 'Offerta accettata', [
            'points' => $loanRequest->getOfferedPoints(),
            'bookTitle' => $loanRequest->getBook()->getTitle(),
        ]);

        $em->flush();
        $this->publishLoanUpdate($hub, $conv, $msg, $loanRequest);

        return $this->redirectToRoute('app_chat_show', ['id' => $conv->getId()]);
    }

    /**
     * Conferma scambio fisico avvenuto.
     */
    #[Route('/{id}/confirm-exchange', name: 'app_loan_request_confirm_exchange', methods: ['POST'])]
    public function confirmExchange(
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

        if ($loanRequest->getStatus() !== LoanRequest::STATUS_ACCEPTED) {
            return $this->redirectToRoute('app_chat_show', ['id' => $loanRequest->getConversation()->getId()]);
        }

        $conv = $loanRequest->getConversation();

        if ($loanRequest->isRequester($me)) {
            $loanRequest->setRequesterConfirmed(true);
        } elseif ($loanRequest->isOwner($me)) {
            $loanRequest->setOwnerConfirmed(true);
        } else {
            throw $this->createAccessDeniedException();
        }

        // Conferma parziale
        $msg = $this->createLoanMessage($em, $conv, $me, $loanRequest, 'loan_confirm', 'Ha confermato lo scambio', [
            'bookTitle' => $loanRequest->getBook()->getTitle(),
        ]);

        // Entrambi hanno confermato?
        if ($loanRequest->isRequesterConfirmed() && $loanRequest->isOwnerConfirmed()) {
            $loanRequest->setStatus(LoanRequest::STATUS_COMPLETED);

            $points = $loanRequest->getOfferedPoints() ?? 0;
            $requester = $loanRequest->getRequester();
            $owner = $loanRequest->getOwner();

            $requester->addCulturePoints(-$points);
            $owner->addCulturePoints($points);

            $userBook = $userBookRepo->findByUserAndBook($owner, $loanRequest->getBook());
            if ($userBook) {
                $userBook->setOwnsPhysically(false);
            }

            // Messaggio completamento
            $msg = $this->createLoanMessage($em, $conv, $me, $loanRequest, 'loan_completed', 'Scambio completato!', [
                'points' => $points,
                'bookTitle' => $loanRequest->getBook()->getTitle(),
            ]);
        }

        $em->flush();
        $this->publishLoanUpdate($hub, $conv, $msg, $loanRequest);

        return $this->redirectToRoute('app_chat_show', ['id' => $conv->getId()]);
    }

    /**
     * Rifiuta l'offerta.
     */
    #[Route('/{id}/decline', name: 'app_loan_request_decline', methods: ['POST'])]
    public function decline(
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

        if ($loanRequest->getStatus() !== LoanRequest::STATUS_PENDING) {
            return $this->redirectToRoute('app_chat_show', ['id' => $loanRequest->getConversation()->getId()]);
        }

        $loanRequest->getRequester()->addCulturePoints(1);
        $loanRequest->setStatus(LoanRequest::STATUS_DECLINED);

        $conv = $loanRequest->getConversation();
        $msg = $this->createLoanMessage($em, $conv, $me, $loanRequest, 'loan_declined', 'Offerta rifiutata', [
            'bookTitle' => $loanRequest->getBook()->getTitle(),
        ]);

        $em->flush();
        $this->publishLoanUpdate($hub, $conv, $msg, $loanRequest);

        return $this->redirectToRoute('app_chat_show', ['id' => $conv->getId()]);
    }

    /**
     * Annulla lo scambio dopo l'accettazione.
     */
    #[Route('/{id}/cancel', name: 'app_loan_request_cancel', methods: ['POST'])]
    public function cancel(
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

        if ($loanRequest->getStatus() !== LoanRequest::STATUS_ACCEPTED) {
            return $this->redirectToRoute('app_chat_show', ['id' => $loanRequest->getConversation()->getId()]);
        }

        $loanRequest->getRequester()->addCulturePoints(1);
        $loanRequest->setStatus(LoanRequest::STATUS_CANCELLED);

        $conv = $loanRequest->getConversation();
        $msg = $this->createLoanMessage($em, $conv, $me, $loanRequest, 'loan_cancelled', 'Scambio annullato', [
            'bookTitle' => $loanRequest->getBook()->getTitle(),
        ]);

        $em->flush();
        $this->publishLoanUpdate($hub, $conv, $msg, $loanRequest);

        return $this->redirectToRoute('app_chat_show', ['id' => $conv->getId()]);
    }

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    private function createLoanMessage(
        EntityManagerInterface $em,
        Conversation $conv,
        \App\Entity\User $sender,
        LoanRequest $lr,
        string $type,
        string $content,
        array $metadata,
    ): Message {
        $msg = new Message();
        $msg->setConversation($conv);
        $msg->setSender($sender);
        $msg->setType($type);
        $msg->setLoanRequest($lr);
        $msg->setContent($content);
        $msg->setMetadata($metadata);
        $em->persist($msg);
        return $msg;
    }

    private function publishLoanUpdate(HubInterface $hub, Conversation $conversation, Message $message, LoanRequest $loanRequest): void
    {
        // 1. Pubblica il messaggio nel flusso chat (appare come evento inline)
        $messageHtml = $this->renderView('chat/message_stream.html.twig', [
            'message' => $message,
        ]);
        $hub->publish(new Update(
            'chat/conversation/' . $conversation->getId(),
            $messageHtml,
        ));

        // 2. Segnala ricarica della action bar
        $signalHtml = $this->renderView('chat/_loan_action_signal.html.twig', [
            'loanRequest' => $loanRequest,
        ]);
        $hub->publish(new Update(
            'chat/conversation/' . $conversation->getId() . '/loan',
            $signalHtml,
        ));
    }
}
