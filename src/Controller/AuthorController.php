<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api')]
class AuthorController extends AbstractController
{
    #[Route('/authors', name: 'authors', methods: ['GET'])]
    public function getAllAuthors(Request $request, AuthorRepository $authorRepository, SerializerInterface $serializer, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        if(!$request->get('page') && !$request->get('limit')){

            $authorsList = $authorRepository->findAll();
            $jsonAuthorsList = $serializer->serialize($authorsList, 'json', ['groups' => 'getAuthors']);
            return new JsonResponse($jsonAuthorsList, Response::HTTP_OK, [], true);
        }

        $idCache = "getAuthorsList-" . $page . "-" . $limit;
        $jsonAuthorList = $cachePool->get($idCache, function (ItemInterface $item) use ($authorRepository, $page, $limit, $serializer)
        {
            $item->tag("authorsCache");
            $authorsList = $authorRepository->findAllWithPagination($page, $limit);
            return $serializer->serialize($authorsList, 'json', ['groups' => 'getAuthors']);
        });
        return new JsonResponse($jsonAuthorList, Response::HTTP_OK, [], true);
    }
    
    #[Route('/authors/{id}', name: 'author', methods: ['GET'])]
    public function getAuthorById(Author $author, SerializerInterface $serializer): JsonResponse
    {
        $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getAuthors']);
        return new JsonResponse($jsonAuthor, Response::HTTP_OK, [], true);
    }
    #[Route('/authors/{id}', name: 'deleteAuthor', methods: ['DELETE'])]
    public function deleteAuthor(Author $author, EntityManagerInterface $em){

        $em->remove($author);
        $em->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
    #[Route('/authors', name: 'createAuthor', methods: ['POST'])]
    public function createAuthor(Request $request, EntityManagerInterface $em, SerializerInterface $serializer,
     UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator) : JsonResponse {
        $author = $serializer->deserialize($request->getContent(), Author::class, 'json');

        $error = $validator->validate($author);
        if($error->count() > 0){
            $violations = [];
            foreach ($error as $violation){
                $violations[]= [
                    'field' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage()
                ];
            }
            return new JsonResponse([
                'status' => JsonResponse::HTTP_BAD_REQUEST,
                'error' => 'Validation failed',
                'violations' => $violations
            ], JsonResponse::HTTP_BAD_REQUEST);  
        }

        $em->persist($author);
        $em->flush();

        $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getAuthors']);
        $location = $urlGenerator->generate('author', ['id' => $author->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonAuthor, Response::HTTP_CREATED, ["location" => $location], true);
    }

    #[Route('/authors/{id}', name: 'updateAuthor', methods: ['PUT'])]
    public function updateAuthor(Request $request, Author $currentAuthor, EntityManagerInterface $em, SerializerInterface $serializer) : JsonResponse {
        $updateAuthor = $serializer->deserialize($request->getContent(),
                        Author::class,
                        'json',
                        [AbstractNormalizer::OBJECT_TO_POPULATE => $currentAuthor]);
        $em->persist($updateAuthor);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);

    }
}
