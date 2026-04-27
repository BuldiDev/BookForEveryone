<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Repository\ConversationRepository;
use App\Repository\LoanRequestRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller per la messaggistica in tempo reale.
 * Gestisce la lista conversazioni, l'apertura di nuove chat, l'invio dei messaggi
 * e la pubblicazione degli aggiornamenti via Mercure (Server-Sent Events).
 */
#[Route('/chat')]
class ChatController extends AbstractController
{
    /**
     * Mostra la lista di tutte le conversazioni dell'utente autenticato.
     */
    #[Route('', name: 'app_chat')]
    public function index(ConversationRepository $conversationRepo): Response
    {
        $user          = $this->getUser();
        $conversations = $conversationRepo->findByUser($user);

        return $this->render('chat/index.html.twig', [
            'conversations' => $conversations,
        ]);
    }

    /**
     * Avvia una nuova conversazione con un altro utente.
     * Se esiste già una conversazione tra i due, reindirizza direttamente a essa.
     */
    #[Route('/new/{id}', name: 'app_chat_new')]
    public function newConversation(
        int $id,
        UserRepository $userRepo,
        ConversationRepository $conversationRepo,
        EntityManagerInterface $em,
    ): Response {
        $me    = $this->getUser();
        $other = $userRepo->find($id);

        // Impedisce di creare una conversazione con se stessi
        if (!$other || $other->getId() === $me->getId()) {
            throw $this->createNotFoundException();
        }

        // Riutilizza la conversazione esistente se già presente
        $conversation = $conversationRepo->findBetween($me, $other);

        if (!$conversation) {
            $conversation = new Conversation();
            $conversation->addParticipant($me);
            $conversation->addParticipant($other);
            $em->persist($conversation);
            $em->flush();
        }

        return $this->redirectToRoute('app_chat_show', ['id' => $conversation->getId()]);
    }

    /**
     * Mostra la schermata di una conversazione con tutti i messaggi e l'eventuale
     * richiesta di prestito attiva associata alla conversazione.
     */
    #[Route('/{id}', name: 'app_chat_show', requirements: ['id' => '\d+'])]
    public function show(
        Conversation $conversation,
        MessageRepository $messageRepo,
        LoanRequestRepository $loanRepo,
    ): Response {
        $me = $this->getUser();

        // Solo i partecipanti possono visualizzare la conversazione
        if (!$conversation->hasParticipant($me)) {
            throw $this->createAccessDeniedException();
        }

        $messages          = $messageRepo->findByConversation($conversation);
        $other             = $conversation->getOtherParticipant($me);
        $activeLoanRequest = $loanRepo->findActiveByConversation($conversation);

        return $this->render('chat/show.html.twig', [
            'conversation'      => $conversation,
            'messages'          => $messages,
            'other'             => $other,
            'activeLoanRequest' => $activeLoanRequest,
        ]);
    }

    /**
     * Renderizza (parziale) la barra di azioni della richiesta di prestito attiva.
     * Viene richiamato via Mercure per aggiornare l'interfaccia senza ricaricare la pagina.
     */
    #[Route('/{id}/loan-action-bar', name: 'app_chat_loan_action_bar', requirements: ['id' => '\d+'])]
    public function loanActionBar(
        Conversation $conversation,
        LoanRequestRepository $loanRepo,
    ): Response {
        $me = $this->getUser();

        if (!$conversation->hasParticipant($me)) {
            throw $this->createAccessDeniedException();
        }

        $lr = $loanRepo->findActiveByConversation($conversation);
        if (!$lr) {
            // Nessuna richiesta attiva: restituisce una risposta vuota
            return new Response('');
        }

        return $this->render('chat/_loan_action_bar.html.twig', [
            'loanRequest' => $lr,
            'me'          => $me,
        ]);
    }

    /**
     * Riceve un messaggio di testo, lo salva e lo pubblica in tempo reale
     * tramite il protocollo Mercure (Server-Sent Events).
     * Se la richiesta proviene da Turbo, risponde con 204 No Content perché
     * l'aggiornamento dell'interfaccia è già gestito dall'evento Mercure.
     */
    #[Route('/{id}/send', name: 'app_chat_send', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function send(
        Conversation $conversation,
        Request $request,
        EntityManagerInterface $em,
        HubInterface $hub,
    ): Response {
        $me = $this->getUser();

        if (!$conversation->hasParticipant($me)) {
            throw $this->createAccessDeniedException();
        }

        // Verifica il token CSRF per proteggere da richieste falsificate
        if (!$this->isCsrfTokenValid('chat_send', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF non valido.');
        }

        $content = trim($request->request->getString('content'));
        if ($content === '') {
            return $this->redirectToRoute('app_chat_show', ['id' => $conversation->getId()]);
        }

        // Crea e persiste il nuovo messaggio
        $message = new Message();
        $message->setConversation($conversation);
        $message->setSender($me);
        $message->setContent($content);

        $em->persist($message);
        $em->flush();

        // Renderizza il frammento HTML del messaggio e lo pubblica sul canale Mercure
        $html = $this->renderView('chat/message_stream.html.twig', [
            'message' => $message,
        ]);

        $hub->publish(new Update(
            'chat/conversation/' . $conversation->getId(),
            $html,
        ));

        // Se la richiesta arriva da Turbo Frame, l'aggiornamento è già avvenuto via Mercure
        if ($request->headers->has('Turbo-Frame') || $request->getPreferredFormat() === 'text/vnd.turbo-stream.html') {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return $this->redirectToRoute('app_chat_show', ['id' => $conversation->getId()]);
    }
}
