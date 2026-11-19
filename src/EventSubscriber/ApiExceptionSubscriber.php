<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Gestion centralisée des erreurs de l'API :
 * le client reçoit un JSON clair avec le bon code HTTP,
 * le détail technique des erreurs inattendues part uniquement dans les journaux.
 */
class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ?LoggerInterface $logger = null)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // Priorité basse : la couche sécurité transforme d'abord ses exceptions (401/403)
        return [KernelEvents::EXCEPTION => ['onKernelException', -10]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof HttpExceptionInterface) {
            $statut = $exception->getStatusCode();
            $message = $exception->getMessage() ?: JsonResponse::$statusTexts[$statut] ?? 'Erreur';
            $entetes = $exception->getHeaders();
        } else {
            $statut = 500;
            $message = 'Une erreur interne est survenue.';
            $entetes = [];
            $this->logger?->error('Erreur API inattendue', ['exception' => $exception]);
        }

        $reponse = new JsonResponse(['erreur' => $message], $statut, $entetes);
        $reponse->setEncodingOptions(JsonResponse::DEFAULT_ENCODING_OPTIONS | JSON_UNESCAPED_UNICODE);
        $event->setResponse($reponse);
    }
}
