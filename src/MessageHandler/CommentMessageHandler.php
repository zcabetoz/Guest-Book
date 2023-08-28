<?php

namespace App\MessageHandler;

use App\ImageOptimizer;
use App\Message\CommentMessage;
use App\Repository\CommentRepository;
use App\SpamChecker;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class CommentMessageHandler implements MessageHandlerInterface
{
    private $spamChecker;
    private $em;
    private $commentRepository;
    private $bus;
    private $logger;
    private $workflow;
    private $imageOptimizer;
    private $photoDir;


    public function __construct(
        SpamChecker            $spamChecker,
        EntityManagerInterface $em,
        CommentRepository      $commentRepository,
        MessageBusInterface    $bus,
        WorkflowInterface      $commentStateMachine,
        string                 $photoDir,
        ImageOptimizer         $imageOptimizer,
        LoggerInterface        $logger = null
    )

    {
        $this->spamChecker = $spamChecker;
        $this->em = $em;
        $this->commentRepository = $commentRepository;
        $this->bus = $bus;
        $this->logger = $logger;
        $this->workflow = $commentStateMachine;
        $this->imageOptimizer = $imageOptimizer;
        $this->photoDir = $photoDir;

    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function __invoke(CommentMessage $message)
    {
        $comment = $this->commentRepository->find($message->getId());
        if (!$comment) {
            return;
        }
        if ($this->workflow->can($comment, 'accept')) {

            $score = $this->spamChecker->getSpamScore($comment, $message->getContext());

            $transition = 'accept';

            if (2 === $score) {
                $transition = 'reject_spam';
            } elseif (1 === $score) {
                $transition = 'might_be_spam';
            }

            $this->workflow->apply($comment, $transition);
            $this->em->flush();

            $this->bus->dispatch($message);

        } elseif ($this->workflow->can($comment, 'publish') || $this->workflow->can($comment, 'publish_ham')) {
            if ($comment->getPhoto()) {
                $this->imageOptimizer->resize($this->photoDir . '/' . $comment->getPhoto());
            }
            $this->workflow->apply($comment, $this->workflow->can($comment, 'publish') ? 'publish' : 'publish_ham');
            $this->em->flush();

        } elseif ($this->logger) {
            $this->logger->debug('Drooping comment message', ['comment' => $comment->getId(), 'state' => $comment->getState()]);
        }
    }
}