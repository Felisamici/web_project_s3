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
        
        if( ($searchedTitle = $request->request->get('title')) != '') {
            $query = $query->where('s.title LIKE :title')
            ->setParameter('title', '%'.$searchedTitle.'%');
        }
        
        $series = $query->getQuery()->execute();
  
        $searchedGenre = $request->request->get('genre'); // Nouvelle recherche

        /* Variable de session pour garder une recherche active lors d'un changement de page */
        if(session_status() !== PHP_SESSION_ACTIVE) {  
            session_start();
        }
        
        // Garder l'ancienne recherche
        if($searchedGenre ===NULL and isset($_SESSION['searchedGenre'])) {
            $searchedGenre = $_SESSION['searchedGenre'];
        }

        /* Enlever les séries qui n'ont pas le genre recherché */
        if($searchedGenre !== NULL and $searchedGenre != 'Nothing') {
            $_SESSION['searchedGenre'] = $searchedGenre;
            $genre = $this->getDoctrine()->getRepository(Genre::class)->findOneBy(['name' => $searchedGenre], NULL);
            $genre_series = $genre->getSeries();
            $series = array_intersect($series, $genre_series->slice(0));
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
     * @Route("/{id}", name="series_show", methods={"GET"})
     */
    public function show(Series $series): Response
    {
        return $this->render('series/show.html.twig', [
            'series' => $series,
        ]);
    }

    /**
     * @Route("/{series}", name="series_rating", methods={"GET"})
     */
    public function rating(Series $series) : Response
    {
        
        $repository = $this->getDoctrine()
        ->getRepository(Rating::class);

        $query = $repository->createQueryBuilder('r')
        ->select('r.value')
        ->from('Rating', 'r')
        ->innerJoin('r.series_id', 's', 'WITH', 'r.series_id=s.id')
        ->where('s.id = :id')
            ->setParameter('id', $series->getId())
        ->orderBy('r.value');

        $rating = $query->getQuery()->execute();

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
}
