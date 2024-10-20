<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class ExceptionSubscriber implements EventSubscriberInterface
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $statusCode = 500;
        $message = 'An unexpected error has occured.';
        $resource = null;

        if($exception instanceof NotFoundHttpException){
            $statusCode = 404;
            $resource = $event->getRequest()->getPathInfo();
            $method = $event->getRequest()->getMethod();
            $message = 'The requested resource '.$resource .' with method '. $method .' was not found';
        }elseif($exception instanceof AccessDeniedHttpException){
            $statusCode = 403;
            $message = 'Access denied';
        }elseif($exception instanceof BadRequestHttpException){
            $statusCode = 400;
            $message = 'Bad request, check your input';
        
        }elseif($exception instanceof HttpExceptionInterface){
            $statusCode = $exception->getStatusCode();
            $message = $exception->getMessage();
        }
        $data = [
            'status' => $statusCode,
            'error' => [
                'message' => $message
            ]
            ];

        $event->setResponse(new JsonResponse($data, $statusCode));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }
}
