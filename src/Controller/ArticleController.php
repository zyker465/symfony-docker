<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Article;
use DateTimeImmutable;
use DateTime; 
use App\Form\ArticleFormType;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Request; 

#[Route('/article')]
class ArticleController extends AbstractController
{
    #[Route('/', name: 'app_article')]
    public function index(): Response
    {
        return $this->render('article/index.html.twig', [
            'controller_name' => 'ArticleController',
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/cree', name: 'app_article_cree')]
    public function createArticle (Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->entityManager = $entityManager;

        $lastArticle = $entityManager->getRepository(Article::class)->findOneBy([], ['id' => 'DESC']);
        $lastId = $lastArticle ? $lastArticle->getId() : 0;

        $newId = $lastId + 1;

        $article = new Article();
        
        $article->setId($newId);

        $date = new DateTime(); 
        $immutableDate = DateTimeImmutable::createFromMutable($date);
        $article->setDate($immutableDate);

        $form = $this->createForm(ArticleFormType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {                    
            $imageFile = $form->get('image')->getData(); 
            if ($imageFile) {
                $newFilename = $newId.'.'.$imageFile->guessExtension();
        
                try {
                    $imageFile->move(
                        $this->getParameter('upload_directory'), 
                        $newFilename
                    );
                } catch (FileException $e) {
                }
        
                $article->setImage($newFilename);
            }

            $entityManager->persist($article);
            $entityManager->flush();

            return $this->redirectToRoute('app_article_liste');
        }

        return $this->render('article/createArticle.html.twig', [
            'form' => $form->createView(),
        ]); 
    }

    #[Route('/voir/{id}', name: 'app_article_voir')]
    public function articleVoir (EntityManagerInterface $entityManager, int $id): Response
    {
        $article = $entityManager->getRepository(Article::class)->find($id);

        if(!$article) {
            throw $this->createNotFoundException(
                'No product found for id'.$id
            );
        }

        return $this->render('article/index.html.twig', [
            'article'=>$article,
        ]);
    }

    #[Route('/liste', name: 'app_article_liste')]
    public function articlesVoirListe(EntityManagerInterface $entityManager): Response
    {
        $liste_article = $entityManager->getRepository(Article::class)->findAll();

        if (empty($liste_article)) {
            $message = 'Aucun article n\'a été trouvé.';
        } else {
            $message = null;
        }

        return $this->render('article/liste_article.html.twig', [
            'liste_article' => $liste_article,
            'message' => $message,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/edit/{id}', name: 'edit_article')]
    public function articleEdit(Request $request, EntityManagerInterface $entityManager, int $id): Response
    {
        $article = $entityManager->getRepository(Article::class)->find($id);

        if (!$article) {
            throw $this->createNotFoundException(
                'No product found for id' . $id
            );
        }
        
        $form = $this->createForm(ArticleFormType::class, $article); 

        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {

            $entityManager->flush();

            return $this->redirectToRoute('app_article_liste');
        }

        return $this->render('article/editArticle.html.twig', [
            'article' => $article,
            'form' => $form->createView(),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/supprimer/{id}', name: 'supprimer_article')]
    public function articleDelete(EntityManagerInterface $entityManager, int $id): Response
    {
        $article = $entityManager->getRepository(Article::class)->find($id);

        if (!$article) {
            throw $this->createNotFoundException('Aucun article trouvé pour l\'ID ' . $id);
        }

        $entityManager->remove($article); // Supprimez l'article de la base de données
        $entityManager->flush();

        // Redirigez l'utilisateur vers la page article liste
        return $this->redirectToRoute('app_article_liste');
    }
}
