<?php

namespace App\Controller;

use App\Entity\Genre;
use App\Entity\Series;
use App\Entity\Rating;
use App\Form\SeriesType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @Route("/series")
 */
class SeriesController extends AbstractController
{
    /**
     * @Route("/", name="series_index", methods={"GET", "POST"})
     */
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $page = $request->query->get('page');
        $page = $page == NULL ? 1 : $page;
        
        $repository = $this->getDoctrine()
        ->getRepository(Series::class);

        $query = $repository->createQueryBuilder('s')
        ->orderBy('s.title');

        /* Variable de session pour garder une recherche active lors d'un changement de page */
        if(session_status() !== PHP_SESSION_ACTIVE) {  
            session_start();
        }

        if(!isset($_POST['reset']) || $_POST['reset'] !== 'Yes') {
            /* Nouvelle recherche */
            $searchedTitle = $request->request->get('title');
            if($searchedTitle != '') {
                $_SESSION['searchedTitle'] = $searchedTitle;

                $query = $query->where('s.title LIKE :title')
                ->setParameter('title', '%'.$searchedTitle.'%');
            /* Ou garder l'ancienne recherche */
            } else if(isset($_SESSION['searchedTitle'])) {
                $searchedTitle = $_SESSION['searchedTitle'];

                $query = $query->where('s.title LIKE :title')
                ->setParameter('title', '%'.$searchedTitle.'%');
            }
        } else {
            unset($_POST['searchedTitle']);
        }

        $series = $query->getQuery()->execute();
  
        if(!isset($_POST['reset']) || $_POST['reset'] !== 'Yes') {
            $searchedGenre = $request->request->get('genre'); // Nouvelle recherche

            // Garder l'ancienne recherche
            if($searchedGenre === NULL and isset($_SESSION['searchedGenre'])) {
                $searchedGenre = $_SESSION['searchedGenre'];
            }

            /* Enlever les séries qui n'ont pas le genre recherché */
            if($searchedGenre !== NULL and $searchedGenre != '') {
                $_SESSION['searchedGenre'] = $searchedGenre;
                $genre = $this->getDoctrine()->getRepository(Genre::class)->findOneBy(['name' => $searchedGenre], NULL);
                $genre_series = $genre->getSeries();
                $series = array_intersect($series, $genre_series->slice(0));
            }
        } else {
            unset($_POST['searchedGenre']);
        }

        $series = $paginator->paginate($series, $page, 10);
        
        $genres = $this->getDoctrine()
            ->getRepository(Genre::class)
            ->findAll();

        return $this->render('series/index.html.twig', [
            'series' => $series,
            'genres' => $genres,
            'page' => $page,
        ]);
    }

    /**
     * @Route("/new", name="series_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $series = new Series();
        $form = $this->createForm(SeriesType::class, $series);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($series);
            $entityManager->flush();

            return $this->redirectToRoute('series_index');
        }

        return $this->render('series/new.html.twig', [
            'series' => $series,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/your_series", name="user_series")
     */
    public function userSeries(Request $request): Response
    {
        if($this->getUser() === NULL) {
            return $this->redirectToRoute('app_login');
        }

        $series = $this->getUser()->getSeries();

        return $this->render('series/user_index.html.twig', [
            'series' => $series,
        ]);
    }

    /**
     * @Route("/{id}", name="series_show", methods={"GET"})
     */
    public function show(Series $series): Response
    {
        /* youtube video id */
        $step1 = explode('v=', $series->getYoutubeTrailer());
        $step2 =explode('&',$step1[1]);
        $youtube_id = $step2[0];

        $user = $this->getUser();
        $isFollowing = $user == NULL ? false : $user->getSeries()->contains($series);

        return $this->render('series/show.html.twig', [
            'series' => $series,
            'youtube_id' => $youtube_id,
            'following' => $isFollowing,
        ]);
    }

    /**
     * @Route("/rating/{series}", name="series_rating", methods={"GET"})
     */
    public function rating(Series $series) : Response
    {
        $repository = $this->getDoctrine()
        ->getRepository(Rating::class);

        $query = $repository->createQueryBuilder('r')
        ->select('r')
        ->innerJoin('r.series', 's', 'WITH', 'r.series=s.id')
        ->where('s.id = :id')
            ->setParameter('id', $series->getId())
        ->orderBy('r.value');

        $rating = $query->getQuery()->execute();
        dump($rating);

        return  $this->render('series/rating.html.twig', [
            'series' => $series,
            'rating' => $rating,
        ]);


    }

    /**
     * @Route("/{id}/edit", name="series_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Series $series): Response
    {
        $form = $this->createForm(SeriesType::class, $series);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('series_index');
        }

        return $this->render('series/edit.html.twig', [
            'series' => $series,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="series_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Series $series): Response
    {
        if ($this->isCsrfTokenValid('delete'.$series->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($series);
            $entityManager->flush();
        }

        return $this->redirectToRoute('series_index');
    }

    /**
     * @Route("/poster/{id}" , name="poster")
     */
    public function poster(Request $request, $id): Response
    {
        $poster = $this->getDoctrine()->getRepository(Series::class)
                ->findOneBy(['id' => $id])->getPoster();
        $response = new Response(stream_get_contents($poster), 200, ['Content-type' => 'img/jpg']);
        return $response;
    }

    /**
     * @Route("/follow/{id}", name="series_follow")
     */
    public function follow(Request $request, $id): Response
    {
        $serie = $this->getDoctrine()->getRepository(Series::class)
                ->findOneBy(['id' => $id]);

        $serie->addUser($this->getUser());
        $this->getDoctrine()->getManager()->flush();
        return $this->redirectToRoute('series_show', ['id' => $id]);
    }

    /**
     * @Route("/unfollow/{id}", name="series_unfollow")
     */
    public function unfollow(Request $request, $id): Response
    {
        $serie = $this->getDoctrine()->getRepository(Series::class)
                ->findOneBy(['id' => $id]);
        
        $serie->removeUser($this->getUser());
        $this->getDoctrine()->getManager()->flush();
        return $this->redirectToRoute('series_show', ['id' => $id]);
    }
}
