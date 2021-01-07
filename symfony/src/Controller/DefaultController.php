<?php

namespace App\Controller;

use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    /**
     * @Route("/", name="index")
     */
    public function index(): Response
    {
        return $this->render('default/index.html.twig', [
            'controller_name' => 'DefaultController',
        ]);
    }

    /**
     * @Route("/about", name="about")
     */
    public function about(): Response
    {
        return $this->render('default/modele.html.twig', [
            'controller_name' => 'DefaultController',
        ]);
    }

    /**
     * @Route("/profile", name="profile")
     */
    public function profile(): Response
    {
        if(($user = $this->getUser()) === NULL) {
            return $this->redirectToRoute('app_login');
        }
        
        $days = date_diff(new DateTime('now'), $user->getRegisterDate())->format('%d jours, %m mois et %y années');

        return $this->render('default/profile.html.twig', [
            'days' => $days,
        ]);
    }
}
