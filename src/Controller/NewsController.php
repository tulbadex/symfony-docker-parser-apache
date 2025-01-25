<?php
namespace App\Controller;

use App\Repository\NewsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

class NewsController extends AbstractController
{
    /**
     * @Route("/news", name="news_list")
     * @IsGranted("ROLE_USER")
     */
    public function list(NewsRepository $newsRepository, Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 10;

        $paginatedNews = $newsRepository->findPaginatedNews($page, $limit);

        return $this->render('news/list.html.twig', [
            'newsItems' => $paginatedNews['data'],
            'pagination' => $paginatedNews,
            'previousPage' => $page > 1 ? $page - 1 : null,
            'nextPage' => $page < $paginatedNews['totalPages'] ? $page + 1 : null,
        ]);
    }
    /* public function list(NewsRepository $newsRepository, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $page = $request->query->getInt('page', 1);
        $limit = 10;

        $newsItems = $newsRepository->findBy([], ['createdAt' => 'DESC'], $limit, ($page - 1) * $limit);

        $previousPage = $page > 1 ? $page - 1 : null;
        $nextPage = count($newsItems) === $limit ? $page + 1 : null;

        return $this->render('news/list.html.twig', [
            'newsItems' => $newsItems,
            'previousPage' => $previousPage,
            'nextPage' => $nextPage,
        ]);
    } */

    /**
     * @Route("/news/{id}/delete", name="news_delete", methods={"POST"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function delete(int $id, NewsRepository $newsRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $news = $newsRepository->find($id);
        if ($news) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($news);
            $entityManager->flush();
        }

        return $this->redirectToRoute('news_list');
    }
}