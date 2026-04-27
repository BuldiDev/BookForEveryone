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

/**
 * Controller per la gestione del ciclo di vita delle richieste di prestito.
 *
 * Ogni richiesta passa attraverso i seguenti stati:
 *   pending → accepted → completed
 *            ↘ declined
 *   accepted → cancelled
 *
 * Tutte le transizioni pubblicano un aggiornamento via Mercure,
 * in modo che entrambi gli utenti vedano i cambiamenti in tempo reale
 * senza ricaricare la pagina.
 */
#[Route('/loan-request')]
class LoanRequestController extends AbstractController
{
    /**
     * Crea una nuova richiesta di prestito (stile offerta Vinted).
     * Verifica che:
     *  - il richiedente non sia lo stesso proprietario
     *  - il proprietario possieda ancora il libro fisicamente
     *  - il richiedente abbia almeno 1 Culture Point disponibile
     *  - non esista già una richiesta attiva tra gli stessi utenti per lo stesso libro
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
        $userBook  = $userBookRepo->findByUserAndBook($ownerUser, $book);

        // Verifica che il proprietario abbia ancora il libro
        if (!$userBook || !$userBook->ownsPhysically()) {
            $this->addFlash('error', 'Il proprietario non ha più questo libro disponibile.');
            return $this->redirectToRoute('app_borrow');
        }

        // Verifica saldo Culture Points sufficiente
        if ($me->getCulturePoints() < 1) {
            $this->addFlash('error', 'Non hai abbastanza Culture Points per fare un\'offerta. Ti serve almeno 1 punto.');
            return $this->redirectToRoute('app_borrow');
        }

        // Evita richieste duplicate: se esiste già, porta direttamente alla chat
        $existing = $loanRepo->findActiveBetweenForBook($me, $ownerUser, $bookId);
        if ($existing) {
            return $this->redirectToRoute('app_chat_show', ['id' => $existing->getConversation()->getId()]);
        }

        // Crea o recupera la conversazione tra i due utenti
        $conversation = $convRepo->findBetween($me, $ownerUser);
        if (!$conversation) {
            $conversation = new Conversation();
            $conversation->addParticipant($me);
            $conversation->addParticipant($ownerUser);
            $em->persist($conversation);
        }

        // Scala 1 Culture Point al richiedente come deposito dell'offerta
        $me->addCulturePoints(-1);

        $offeredPoints = max(1, $request->request->getInt('offered_points', 3));

        // Crea la richiesta di prestito
        $loanRequest = new LoanRequest();
        $loanRequest->setRequester($me);
        $loanRequest->setOwner($ownerUser);
        $loanRequest->setBook($book);
        $loanRequest->setConversation($conversation);
        $loanRequest->setOfferedPoints($offeredPoints);
        $loanRequest->setLastOfferBy('requester');

        $em->persist($loanRequest);

        // Crea il messaggio di sistema "Offerta" nella chat
        $msg = $this->createLoanMessage($em, $conversation, $me, $loanRequest, 'loan_offer', 'Offerta: ' . $offeredPoints . ' CP', [
            'points'    => $offeredPoints,
            'bookTitle' => $book->getTitle(),
            'bookCover' => $book->getCoverUrl(),
        ]);

        $em->flush();
        $this->publishLoanUpdate($hub, $conversation, $msg, $loanRequest);

        return $this->redirectToRoute('app_chat_show', ['id' => $conversation->getId()]);
    }

    /**
     * Gestisce una controproposta: una delle due parti modifica il numero di Culture Points offerti.
     * Solo chi non ha fatto l'ultima offerta può proporre una controproposta.
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

        // Solo i partecipanti alla richiesta possono fare controproposte
        if (!$loanRequest->isRequester($me) && !$loanRequest->isOwner($me)) {
            throw $this->createAccessDeniedException();
        }

        if ($loanRequest->getStatus() !== LoanRequest::STATUS_PENDING) {
            $this->addFlash('error', 'Non puoi fare una controproposta in questo momento.');
            return $this->redirectToRoute('app_chat_show', ['id' => $loanRequest->getConversation()->getId()]);
        }

        // Impedisce di rispondere alla propria offerta
        if ($loanRequest->isLastOfferByUser($me)) {
            $this->addFlash('error', 'Devi aspettare la risposta dell\'altra parte.');
            return $this->redirectToRoute('app_chat_show', ['id' => $loanRequest->getConversation()->getId()]);
        }

        $points = max(1, $request->request->getInt('offered_points', 1));
        $loanRequest->setOfferedPoints($points);
        $loanRequest->setLastOfferBy($loanRequest->isRequester($me) ? 'requester' : 'owner');

        $conv = $loanRequest->getConversation();
        $msg  = $this->createLoanMessage($em, $conv, $me, $loanRequest, 'loan_counter', 'Controproposta: ' . $points . ' CP', [
            'points'    => $points,
            'bookTitle' => $loanRequest->getBook()->getTitle(),
            'bookCover' => $loanRequest->getBook()->getCoverUrl(),
        ]);

        $em->flush();
        $this->publishLoanUpdate($hub, $conv, $msg, $loanRequest);

        return $this->redirectToRoute('app_chat_show', ['id' => $conv->getId()]);
    }

    /**
     * Accetta l'offerta corrente: lo stato passa da pending ad accepted.
     * Solo chi non ha fatto l'ultima offerta può accettare.
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
        $msg  = $this->createLoanMessage($em, $conv, $me, $loanRequest, 'loan_accepted', 'Offerta accettata', [
            'points'    => $loanRequest->getOfferedPoints(),
            'bookTitle' => $loanRequest->getBook()->getTitle(),
        ]);

        $em->flush();
        $this->publishLoanUpdate($hub, $conv, $msg, $loanRequest);

        return $this->redirectToRoute('app_chat_show', ['id' => $conv->getId()]);
    }

    /**
     * Registra la conferma dello scambio fisico del libro.
     * Lo scambio si completa solo quando entrambi gli utenti hanno confermato.
     * Al completamento i Culture Points vengono trasferiti dal richiedente al proprietario
     * e il libro viene rimosso dall'inventario del proprietario.
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

        // Segna la conferma per il partecipante corrente
        if ($loanRequest->isRequester($me)) {
            $loanRequest->setRequesterConfirmed(true);
        } elseif ($loanRequest->isOwner($me)) {
            $loanRequest->setOwnerConfirmed(true);
        } else {
            throw $this->createAccessDeniedException();
        }

        // Messaggio di conferma parziale (un solo partecipante ha confermato finora)
        $msg = $this->createLoanMessage($em, $conv, $me, $loanRequest, 'loan_confirm', 'Ha confermato lo scambio', [
            'bookTitle' => $loanRequest->getBook()->getTitle(),
        ]);

        // Se entrambi hanno confermato, completa lo scambio e trasferisci i punti
        if ($loanRequest->isRequesterConfirmed() && $loanRequest->isOwnerConfirmed()) {
            $loanRequest->setStatus(LoanRequest::STATUS_COMPLETED);

            $points    = $loanRequest->getOfferedPoints() ?? 0;
            $requester = $loanRequest->getRequester();
            $owner     = $loanRequest->getOwner();

            // Trasferimento Culture Points dal richiedente al proprietario
            $requester->addCulturePoints(-$points);
            $owner->addCulturePoints($points);

            // Il libro non è più fisicamente disponibile presso il proprietario
            $userBook = $userBookRepo->findByUserAndBook($owner, $loanRequest->getBook());
            if ($userBook) {
                $userBook->setOwnsPhysically(false);
            }

            // Messaggio di sistema che sancisce il completamento
            $msg = $this->createLoanMessage($em, $conv, $me, $loanRequest, 'loan_completed', 'Scambio completato!', [
                'points'    => $points,
                'bookTitle' => $loanRequest->getBook()->getTitle(),
            ]);
        }

        $em->flush();
        $this->publishLoanUpdate($hub, $conv, $msg, $loanRequest);

        return $this->redirectToRoute('app_chat_show', ['id' => $conv->getId()]);
    }

    /**
     * Rifiuta l'offerta corrente (stato pending → declined).
     * Il Culture Point scalato all'invio dell'offerta viene restituito al richiedente.
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

        // Rimborso del punto speso per l'offerta
        $loanRequest->getRequester()->addCulturePoints(1);
        $loanRequest->setStatus(LoanRequest::STATUS_DECLINED);

        $conv = $loanRequest->getConversation();
        $msg  = $this->createLoanMessage($em, $conv, $me, $loanRequest, 'loan_declined', 'Offerta rifiutata', [
            'bookTitle' => $loanRequest->getBook()->getTitle(),
        ]);

        $em->flush();
        $this->publishLoanUpdate($hub, $conv, $msg, $loanRequest);

        return $this->redirectToRoute('app_chat_show', ['id' => $conv->getId()]);
    }

    /**
     * Annulla lo scambio dopo che era stato accettato (stato accepted → cancelled).
     * Il Culture Point depositato viene restituito al richiedente.
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

        // Rimborso del punto depositato al momento dell'offerta
        $loanRequest->getRequester()->addCulturePoints(1);
        $loanRequest->setStatus(LoanRequest::STATUS_CANCELLED);

        $conv = $loanRequest->getConversation();
        $msg  = $this->createLoanMessage($em, $conv, $me, $loanRequest, 'loan_cancelled', 'Scambio annullato', [
            'bookTitle' => $loanRequest->getBook()->getTitle(),
        ]);

        $em->flush();
        $this->publishLoanUpdate($hub, $conv, $msg, $loanRequest);

        return $this->redirectToRoute('app_chat_show', ['id' => $conv->getId()]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Metodi privati di supporto
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Crea un messaggio di sistema associato a un evento della richiesta di prestito
     * e lo persiste nell'EntityManager (senza fare flush).
     */
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

    /**
     * Pubblica due aggiornamenti Mercure sulla conversazione:
     *  1. Il messaggio di sistema (appare nella timeline della chat)
     *  2. Un segnale per ricaricare la barra delle azioni del prestito
     */
    private function publishLoanUpdate(HubInterface $hub, Conversation $conversation, Message $message, LoanRequest $loanRequest): void
    {
        // Pubblica il messaggio nella timeline della chat
        $messageHtml = $this->renderView('chat/message_stream.html.twig', [
            'message' => $message,
        ]);
        $hub->publish(new Update(
            'chat/conversation/' . $conversation->getId(),
            $messageHtml,
        ));

        // Pubblica il segnale per aggiornare la barra delle azioni (offerta/controproposta/ecc.)
        $signalHtml = $this->renderView('chat/_loan_action_signal.html.twig', [
            'loanRequest' => $loanRequest,
        ]);
        $hub->publish(new Update(
            'chat/conversation/' . $conversation->getId() . '/loan',
            $signalHtml,
        ));
    }
}
