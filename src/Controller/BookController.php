<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\AuthorRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use JMS\Serializer\Annotation as Serializer;

#[Route('/api')]
class BookController extends AbstractController
{
    #[Route('/books', name: 'books', methods: ['GET'])]
    public function getBookList(Request $request, BookRepository $bookRepository, SerializerInterface $serializer, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        if(!$request->get('page') && !$request->get('limit')){
            $bookList = $bookRepository->findAll();
            $jsonBookList = $serializer->serialize($bookList, 'json', ['groups' => 'getBooks']);
            return new JsonResponse($jsonBookList, Response::HTTP_OK, [], true);
        }

        $idCache = "getBookList-" . $page . "-" . $limit;
        $jsonBookList = $cachePool->get($idCache, function (ItemInterface $item) use ($bookRepository, $page, $limit, $serializer)
        {
            $item->tag("booksCache");
            $bookList = $bookRepository->findAllWithPagination($page, $limit);
            return $serializer->serialize($bookList, 'json', ['groups' => 'getBooks']);
        });
       
        return new JsonResponse($jsonBookList, Response::HTTP_OK, [], true);
    }
    #[Route('/books/{id}', name: 'book', methods: ['GET'])]
    public function getBook(Book $book, SerializerInterface $serializer): JsonResponse
    {
            $jsonBook = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);
            return new JsonResponse($jsonBook, Response::HTTP_OK, [], true); 
    }
    #[Route('/books/{id}', name: 'deleteBook', methods: ['DELETE'])]
    public function deleteBook(Book $book, EntityManagerInterface $em, TagAwareCacheInterface $cachePool): JsonResponse
    {       
            $cachePool->invalidateTags(["booksCache"]);
            $em->remove($book);
            $em->flush();
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
    #[Route('/books', name: 'createBook', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas l\'autorisation de créer un livre')]
    public function createBook(Request $request, AuthorRepository $authorRepository, SerializerInterface $serializer,
     EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator): JsonResponse
    {
            $book = $serializer->deserialize($request->getContent(), Book::class, 'json');
            
            $error = $validator->validate($book);
            if($error->count() > 0){
                $violations = [];
                foreach($error as $violation){
                    $violations[] = [
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

            $content = $request->toArray();
            $idAuthor = $content['idAuthor'] ?? -1;

            $book->setAuthor($authorRepository->find($idAuthor));

            $em->persist($book);
            $em->flush();

            $jsonBook = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);

            $location = $urlGenerator->generate('book', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
            return new JsonResponse($jsonBook, Response::HTTP_CREATED, ["location" => $location], true);
    }
    #[Route('/books/{id}', name: 'updateBook', methods: ['PUT'])]
    public function updateBook(SerializerInterface $serializer, Request $request, Book $currentBook, AuthorRepository $authorRepository, EntityManagerInterface $em) : JsonResponse {
        $updateBook = $serializer->deserialize($request->getContent(),
                        Book::class,
                        'json',
                        [AbstractNormalizer::OBJECT_TO_POPULATE => $currentBook]);
        $content = $request->toArray();
        $idAuthor = $content["idAuthor"] ?? -1;
        $updateBook->setAuthor($authorRepository->find($idAuthor));   
        
        $em->persist($updateBook);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
