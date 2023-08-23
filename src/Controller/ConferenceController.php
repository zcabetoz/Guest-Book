<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ConferenceController extends AbstractController
{
    /**
     * @Route("/{name}", name="app_homepage")
     */
    public function index($name): Response
    {
        return $this->render('conference/index.html.twig', [
            'controller_name' => $name,
        ]);
    }
}
