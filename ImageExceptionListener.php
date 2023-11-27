<?php

declare(strict_types=1);

namespace Mosky\ShopwareStageFileProxyBundle\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ImageExceptionListener
{
    public function __construct(
        private readonly string $remoteUrl,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * TODO:
     * - Make plugin
     * - Cleaner isImage check
     * -
     * - Try to retry the request so a refresh isn't needed
     */

    public function __invoke(ExceptionEvent $event): void
    {
        try {
            if ($this->remoteUrl === '') {
                return;
            }

            $throwable = $event->getThrowable();

            if ($throwable instanceof NotFoundHttpException) {
                $request = $event->getRequest();
                $uri = $request->getRequestUri();

                // Detect if the request is for an image
                if ($this->isImage($uri)) {
                    // Fetch the image from the remote server
                    $image = file_get_contents($this->remoteUrl . $uri);

                    if ($image === false) {
                        // The image could not be fetched from the remote server
                        return;
                    }
                    // Get the directory of the uri and created it if it doesn't exist
                    $directory = ltrim(dirname($uri), '/');

                    if (!file_exists($directory)) {
                        // The third parameter specifies that directories should be created recursively
                        mkdir($directory, 0775, true);
                    }

                    // Store the image in the uri location.
                    file_put_contents(ltrim(urldecode($uri), '/'), $image);
                }
            }
        } catch (Throwable $e) {
            $this->logger->error(
                "Error while fetching image from remote server",
                [
                'exception' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ],
                'remote_url' => $this->remoteUrl,
                'uri' => $uri ?? 'unknown',
                ]
            );

            return;
        }
    }

    public function isImage(string $filePath): bool
    {
        // Get the file extension
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // List of common image extensions
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'];

        // Check if the extension is in the list of image extensions
        if (in_array($extension, $imageExtensions, true)) {
            return true; // It's an image
        }

        return false;
    }
}
