<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Repository\ConversationRepository;
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

#[Route('/chat')]
class ChatController extends AbstractController
{
    #[Route('', name: 'app_chat')]
    public function index(ConversationRepository $conversationRepo): Response
    {
        $user = $this->getUser();
        $conversations = $conversationRepo->findByUser($user);

        return $this->render('chat/index.html.twig', [
            'conversations' => $conversations,
        ]);
    }

    #[Route('/new/{id}', name: 'app_chat_new')]
    public function newConversation(
        int $id,
        UserRepository $userRepo,
        ConversationRepository $conversationRepo,
        EntityManagerInterface $em,
    ): Response {
        $me = $this->getUser();
        $other = $userRepo->find($id);

        if (!$other || $other->getId() === $me->getId()) {
            throw $this->createNotFoundException();
        }

        // Cerca conversazione esistente
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

    #[Route('/{id}', name: 'app_chat_show', requirements: ['id' => '\d+'])]
    public function show(
        Conversation $conversation,
        MessageRepository $messageRepo,
    ): Response {
        $me = $this->getUser();

        if (!$conversation->hasParticipant($me)) {
            throw $this->createAccessDeniedException();
        }

        $messages = $messageRepo->findByConversation($conversation);
        $other = $conversation->getOtherParticipant($me);

        return $this->render('chat/show.html.twig', [
            'conversation' => $conversation,
            'messages' => $messages,
            'other' => $other,
        ]);
    }

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

        if (!$this->isCsrfTokenValid('chat_send', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF non valido.');
        }

        $content = trim($request->request->getString('content'));
        if ($content === '') {
            return $this->redirectToRoute('app_chat_show', ['id' => $conversation->getId()]);
        }

        $message = new Message();
        $message->setConversation($conversation);
        $message->setSender($me);
        $message->setContent($content);

        $em->persist($message);
        $em->flush();

        // Pubblica aggiornamento via Mercure (Turbo Stream)
        $html = $this->renderView('chat/message_stream.html.twig', [
            'message' => $message,
        ]);

        $update = new Update(
            'chat/conversation/' . $conversation->getId(),
            $html,
        );
        $hub->publish($update);

        // Se la richiesta arriva da Turbo, restituiamo vuoto (Mercure ha già inviato l'update)
        if ($request->headers->has('Turbo-Frame') || $request->getPreferredFormat() === 'text/vnd.turbo-stream.html') {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return $this->redirectToRoute('app_chat_show', ['id' => $conversation->getId()]);
    }
}
