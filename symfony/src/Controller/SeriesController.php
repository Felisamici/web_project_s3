<?php

namespace App\Controller;

use App\Entity\Genre;
use App\Entity\Series;
use App\Entity\Rating;
use App\Entity\Actor;
use App\Entity\Season;
use App\Entity\Episode;
use App\Form\SeriesType;
use Exception;
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
        $searchedGenre = $request->query->get('genre');
        $searchedTitle = $request->query->get('title');
        
        $repository = $this->getDoctrine()
        ->getRepository(Series::class);

        $query = $repository->createQueryBuilder('s')
        ->orderBy('s.title');

        if($searchedTitle !== NULL and $searchedTitle != '') {
            $query = $query->where('s.title LIKE :title')
                ->setParameter('title', '%'.$searchedTitle.'%');
        }
        
        $series = $query->getQuery()->execute();

        /* Enlever les séries qui n'ont pas le genre recherché */
        if($searchedGenre !== NULL and $searchedGenre != '') {
            $_SESSION['searchedGenre'] = $searchedGenre;
            $genre = $this->getDoctrine()->getRepository(Genre::class)->findOneBy(['name' => $searchedGenre], NULL);
            $genre_series = $genre->getSeries();
            $series = array_intersect($series, $genre_series->slice(0));
        }

        $series = $paginator->paginate($series, $page, 9);
        
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
        if(!($user = $this->getUser()) || !$user->getAdmin()) {
            return $this->redirectToRoute('series_index');
        }

        $series = new Series();
        $form = $this->createForm(SeriesType::class, $series);

        $form->handleRequest($request);
        
        $entityManager = $this->getDoctrine()->getManager();

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if($form->getData('imdb') !== '') {
                    $url = "http://www.omdbapi.com/?apikey=572fd4b3&i=". $form->getData('imdb')->getImdb();
                    
                    $data = ['collection' => 'test'];
                    $r = curl_init($url);
                    curl_setopt($r, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($r, CURLOPT_POST, true);
                    curl_setopt($r, CURLOPT_POSTFIELDS,  json_encode($data));

                    $response = curl_exec($r);
                    curl_close($r);
                    $response = json_decode($response);

                    $series->setTitle($response->Title);
                    $series->setPlot($response->Plot);
                    $series->setImdb($response->imdbID);
                    $series->setPoster(fopen($response->Poster, 'rb'));
                    $series->setDirector($response->Director);
                    $series->setAwards($response->Awards);

                    $years=explode('–', $response->Year);
                    $series->setYearStart(intval($years[0]));
                    if(count($years) > 1) {
                        $series->setYearEnd(intval($years[1]));
                    }

                    /* Ajout des genres */
                    $genresStr = explode(', ', $response->Genre);
                    $queryBuilder = $this->getDoctrine()->getRepository(Genre::class)->createQueryBuilder('g');
                    foreach($genresStr as $g) {
                        $queryBuilder = $queryBuilder->orWhere("g.name='".$g."'");
                    }
                    $genres = $queryBuilder->getQuery()->execute();

                    foreach($genres as $g) {
                        $series->addGenre($g);
                    }

                    /* Ajout des acteurs */
                    $actorsStr = explode(', ', $response->Actors);
                    $queryBuilder = $this->getDoctrine()->getRepository(Actor::class)->createQueryBuilder('a');
                    foreach($actorsStr as $a) {
                        $queryBuilder = $queryBuilder->orWhere("a.name='".$a."'");
                    }
                    $actors = $queryBuilder->getQuery()->execute();

                    foreach($actors as $a) {
                        $series->addActor($a);
                    }

                    $nbSeasons = intval($response->totalSeasons);
                    for($i=1; $i<=$nbSeasons; $i++) {

                        $season = new Season();
                        $season->setNumber($i);

                        $url = "http://www.omdbapi.com/?apikey=572fd4b3&i=". $form->getData('imdb')->getImdb()."&Season=".$i;

                        $data = ['collection' => 'test'];
                        $r = curl_init($url);
                        curl_setopt($r, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($r, CURLOPT_POST, true);
                        curl_setopt($r, CURLOPT_POSTFIELDS,  json_encode($data));

                        $response = curl_exec($r);
                        curl_close($r);
                        $response = json_decode($response);

                        foreach($response->Episodes as $ep) {
                            $episode = new Episode();
                            $episode->setTitle($ep->Title);
                            $episode->setDate(date_create_from_format("Y-m-d", $ep->Released));
                            $episode->setImdb($ep->imdbID);
                            $episode->setImdbrating(floatval($ep->imdbRating));
                            $episode->setNumber($ep->Episode);
                            $season->addEpisode($episode);
                            $entityManager->persist($episode);
                        }
                        $entityManager->persist($season);

                        $series->addSeason($season);
                    }

                    $entityManager->persist($series);
                    $entityManager->flush();
                }

                return $this->render('series/new.html.twig', [
                    'series' => $series,
                    'form' => $form->createView(),
                    'response' => $response->Response,
                ]);
            } catch(Exception $e) {
                return $this->render('series/new.html.twig', [
                    'series' => $series,
                    'form' => $form->createView(),
                    'response' => 'False',
                ]);
            } 

        }

        return $this->render('series/new.html.twig', [
            'series' => $series,
            'form' => $form->createView(),
            'response' => NULL,
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
        $youtube_id = NULL;
        /* youtube video id */
        if(($trailer = $series->getYoutubeTrailer()) != NULL) {
            $step1 = explode('v=', $trailer);
            $step2 =explode('&',$step1[1]);
            $youtube_id = $step2[0];
        }

        $user = $this->getUser();
        $isFollowing = $user == NULL ? false : $user->getSeries()->contains($series);

        $repositoryAVG = $this->getDoctrine()
        ->getRepository(Rating::class);

        $queryAVG = $repositoryAVG->createQueryBuilder('g')
            ->select('avg(g.value)')
            ->where('g.series = :id')
            ->setParameter('id', $series->getId());
        
        $avg = $queryAVG->getQuery()->execute();

        return $this->render('series/show.html.twig', [
            'series' => $series,
            'youtube_id' => $youtube_id,
            'following' => $isFollowing,
            'avg' => $avg[0][1],
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
        ->orderBy('r.date', 'DESC');

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
        if(!($user =$this->getUser()) || !$user->getAdmin()) {
            return $this->redirectToRoute('series_show', ['id' => $series->getId()]);
        }

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
        if(($user =$this->getUser()) && $user->getAdmin()) {
            if ($this->isCsrfTokenValid('delete'.$series->getId(), $request->request->get('_token'))) {
                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->remove($series);
                $entityManager->flush();
            }
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
        if($user = $this->getUser()) {
            $serie = $this->getDoctrine()->getRepository(Series::class)
                    ->findOneBy(['id' => $id]);

            $serie->addUser($this->getUser());
            $this->getDoctrine()->getManager()->flush();
        }
        return $this->redirectToRoute('series_show', ['id' => $id]);
    }

    /**
     * @Route("/unfollow/{id}", name="series_unfollow")
     */
    public function unfollow(Request $request, $id): Response
    {
        if($user = $this->getUser()) {
            $serie = $this->getDoctrine()->getRepository(Series::class)
                    ->findOneBy(['id' => $id]);
            
            $serie->removeUser($this->getUser());
            $this->getDoctrine()->getManager()->flush();
        }
        return $this->redirectToRoute('series_show', ['id' => $id]);
    }
}
