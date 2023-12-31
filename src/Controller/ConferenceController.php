<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Conference;
use App\Form\CommentFormType;
use App\Message\CommentMessage;
use App\Repository\CommentRepository;
use App\Repository\ConferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class ConferenceController extends AbstractController
{
    private $em;
    private $bus;

    public function __construct(EntityManagerInterface $em, MessageBusInterface $bus)
    {
        $this->em = $em;
        $this->bus = $bus;
    }

    /**
     * @Route("/", name="app_homepage")
     */
    public function index(ConferenceRepository $conferenceRepository): Response
    {
        $conferences = $conferenceRepository->findAll();

        $html = $this->renderView('conference/index.html.twig', [
            'conferences' => $conferences
        ]);

        $response = new Response($html);

        $response->setSharedMaxAge(30);

        return $response;
    }

    /**
     * @Route("/conference-header", name="app_conference_header")
     */
    public function conferenceHeaderIndex(ConferenceRepository $conferenceRepository): Response
    {
        $html = $this->renderView('conference/header.html.twig', [
            'conferences' => $conferenceRepository->findAll()
        ]);

        $response = new Response($html);

        $response->setSharedMaxAge(3600);

        return $response;
    }

    /**
     * @Route("/conference/{slug}", name="app_conference")
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */

    public function showIndex(Request $request, CommentRepository $commentRepository, Conference $conference, string $photoDir, NotifierInterface $notifier): Response
    {
        $offset = max(0, $request->query->getInt('offset', 0));
        $paginator = $commentRepository->getCommentPaginator($conference, $offset);
        $comment = new Comment();
        $form = $this->createForm(CommentFormType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $comment->setConference($conference);
            if ($photoFile = $form['photoFile']->getData()) {
                $safeFileName = uniqid() . '.' . $photoFile->guessExtension();
                try {
                    $photoFile->move(
                        $photoDir,
                        $safeFileName
                    );

                } catch (FileException $e) {
                    echo 'unable to upload the photo';
                }
                $comment->setPhoto($safeFileName);
            }

            $this->em->persist($comment);
            $this->em->flush();

            $context = [
                'user_ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('user-agent'),
                'referrer' => $request->headers->get('referer'),
                'permalink' => $request->getUri()
            ];

            $notifier->send(new Notification('Thank you for the feedback;  your comment will be posted after moderation', ['browser']));
            $this->bus->dispatch(new CommentMessage($comment->getId(), $context));

            return $this->redirectToRoute('app_conference', ['slug' => $conference->getSlug()]);
        }

        return $this->render('conference/show.conference.html.twig', [
            'conference' => $conference,
            'comments' => $paginator,
            'previous' => $offset - CommentRepository::PAGINATOR_PER_PAGE,
            'next' => min(count($paginator), $offset + CommentRepository::PAGINATOR_PER_PAGE),
            'comment_form' => $form->createView()
        ]);
    }
}
