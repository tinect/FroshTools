<?php declare(strict_types=1);

/**
 * inspired by https://gist.github.com/beberlei/2bbea7a0e241a4e6ebcdd4950def6f44
 */

namespace Frosh\Tools\Messenger;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IterateEntityIndexerMessage;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Content\ImportExport\Message\DeleteFileMessage as DeleteImportExportFile;
use Shopware\Core\Content\Media\Message\DeleteFileMessage;
use Shopware\Core\Content\Media\Message\GenerateThumbnailsMessage;
use Shopware\Storefront\Framework\Cache\CacheWarmer\WarmUpMessage;

class TaskLoggingMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $taskName = $this->getTaskName($message);
        $args = $this->extractArgumentsFromMessage($message);

        $start = microtime(true);
        try {
            return $stack->next()->handle($envelope, $stack);
        } catch (HandlerFailedException $e) {
            $args = $this->addExceptionToArgs($e, $args);
            $args['errored'] = true;

            throw $e;
        } finally {
            $this->logTaskProcessing($taskName, $args, $start);
        }
    }

    private function getTaskName(object $message): string
    {
        if ($message instanceof ScheduledTask) {
            return $message->getTaskName();
        }

        $classParts = explode('\\', get_class($message));
        $taskName = end($classParts);

        if (substr($taskName, -7) === 'Message') {
            $taskName = substr($taskName, 0, -7);
        }

        return $taskName;
    }

    private function extractArgumentsFromMessage($message): array
    {
        if ($message instanceof EntityIndexingMessage) {
            $data = $message->getData();

            if (is_array($data)) {
                return ['data' => implode(',', $data)];
            }
        }

        if ($message instanceof ScheduledTask) {
            return ['taskId' => $message->getTaskId()];
        }

        if ($message instanceof DeleteImportExportFile || $message instanceof DeleteFileMessage) {
            return ['files' => implode(',', array_map(static function ($f) { return basename($f); }, $message->getFiles()))];
        }

        if ($message instanceof GenerateThumbnailsMessage) {
            return ['mediaIds' => implode(',', $message->getMediaIds())];
        }

        if ($message instanceof WarmUpMessage) {
            return ['route' => $message->getRoute(), 'domain' => $message->getDomain(), 'cache_id' => $message->getCacheId()];
        }

        if ($message instanceof IterateEntityIndexerMessage) {
            return ['indexer' => $message->getIndexer()];
        }

        return [];
    }

    private function logTaskProcessing(string $taskName, array $args, $start): void
    {
        $args = ['duration' => round(microtime(true) - $start, 3)] + $args;

        if (isset($args['errored']) && $args['errored'] === true) {
            $this->logger->error($taskName, $args);
            return;
        }

        $this->logger->info($taskName, $args);
    }

    private function addExceptionToArgs($e, array $args): array
    {
        $exceptions = $e->getNestedExceptions();
        $args['exception'] = get_class($exceptions[0]);
        $args['exception.msg'] = $exceptions[0]->getMessage();

        return $args;
    }
}
