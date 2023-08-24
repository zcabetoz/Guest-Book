<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Conference;
use App\Form\CommentFormType;
use App\Repository\CommentRepository;
use App\Repository\ConferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ConferenceController extends AbstractController
{
    private $em;
    private $slugger;

    public function __construct(EntityManagerInterface $em, SluggerInterface $slugger)
    {
        $this->em = $em;
        $this->slugger = $slugger;
    }

    /**
     * @Route("/", name="app_homepage")
     */
    public function index(ConferenceRepository $conferenceRepository): Response
    {
        return $this->render('conference/index.html.twig');
    }

    /**
     * @Route("/conference/{slug}", name="app_conference")
     * //     * @throws ORMException
     */
    public function showIndex(Request $request, CommentRepository $commentRepository, Conference $conference, string $photoDir): Response
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
