<?php

namespace App\Command;

use App\Model\Commit;
use App\Model\Cursor;
use App\Model\SessionAppend;
use App\Model\UploadSessionEnd;
use Clue\React\Block;
use Clue\React\Buzz\Browser;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Factory as LoopFactory;
use React\EventLoop\LoopInterface;
use React\Stream\ReadableResourceStream;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

final class UploadToDropboxBuzzCommand extends Command
{
    private const STOPWATCH_NAME = 'react-upload';

    private string $accessToken;
    private Browser $browser;
    private LoopInterface $loop;
    private SerializerInterface $serializer;
    private array $uploadCalls;

    private string $file;
    private array $promises = [];

    public function __construct(string $accessToken, SerializerInterface $serializer)
    {
        $this->accessToken = $accessToken;
        $this->loop = LoopFactory::create();
        $this->browser = (new Browser($this->loop))->withBase('https://content.dropboxapi.com/2/');
        $this->serializer = $serializer;
        $this->uploadCalls = [];

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('buzz:upload-file')
            ->addArgument('path', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stopWatch = new Stopwatch();
        $stopWatch->start(self::STOPWATCH_NAME);
        $this->file = $input->getArgument('path');
        if (!file_exists($this->file)) {
            throw new \RuntimeException('Invalid path given for the file to upload');
        }

        $headers = [
            'Content-Type' => 'application/octet-stream',
            'Authorization' => sprintf('Bearer %s', $this->accessToken),
            'Dropbox-API-Arg' => ['close' => false],
        ];

        $startSessionPromise = $this->browser->post('files/upload_session/start', $headers, '');
        try {
            /** @var ResponseInterface $startSessionResponse */
            $startSessionResponse = Block\await($startSessionPromise, $this->loop);
        } catch (\Exception $e) {
            return $e->getCode();
        }

        $streamLoop = LoopFactory::create();
        $offset = 0;
        if (200 === $startSessionResponse->getStatusCode()) {
            dump('session started');
            $sessionId = json_decode((string) $startSessionResponse->getBody(), true)['session_id'];
            $file = new ReadableResourceStream(fopen($this->file, 'r+'), $streamLoop, 1024*1024*20);
            $file->on('data', function ($chunk) use ($sessionId, &$offset) {
                $headers = [
                    'Content-Type' => 'application/octet-stream',
                    'Authorization' => sprintf('Bearer %s', $this->accessToken),
                    'Dropbox-API-Arg' => $this->serializer->serialize(new SessionAppend(new Cursor($sessionId, $offset)), 'json'),
                ];

                /** @var ResponseInterface $chunkResponse */
                try {
                    $chunkResponse = Block\await($this->browser->post('files/upload_session/append_v2', $headers, $chunk), $this->loop);
                } catch (\Exception $e) {
                    dump($e->getTraceAsString());

                    throw $e;
                }

                if (200 === $chunkResponse->getStatusCode()) {
                    $offset += strlen($chunk);

                    return;
                }

                throw new \RuntimeException(sprintf('Failed to upload a chunck at offset %s', $offset));
            });

            $streamLoop->run();

            $commit = new Commit(sprintf('/MyDropboxPath/react.%s', pathinfo($this->file, PATHINFO_EXTENSION)), Commit::MODE_ADD, true, true);
            $sessionEnd = new UploadSessionEnd(new Cursor($sessionId, $offset), $commit);
            $headers = [
                'Content-Type' => 'application/octet-stream',
                'Authorization' => sprintf('Bearer %s', $this->accessToken),
                'Dropbox-API-Arg' => $this->serializer->serialize($sessionEnd, 'json'),
            ];

            try {
                /** @var ResponseInterface $sessionEndResponse */
                $sessionEndResponse = Block\await($this->browser->post('files/upload_session/finish', $headers, ''), $this->loop);
            } catch (\Exception $e) {
                return $e->getCode();
            }

            if (200 !== $sessionEndResponse->getStatusCode()) {
                throw new \RuntimeException('Failed to finish upload session');
            }

            dump('session ended');
        }

        $stopWatch->stop(self::STOPWATCH_NAME);
        $output->writeln(sprintf('Duration: %d', ($stopWatch->getEvent(self::STOPWATCH_NAME)->getDuration() / 1000)));
        $output->writeln(sprintf('Memory usage: %d', $stopWatch->getEvent(self::STOPWATCH_NAME)->getMemory()));

        return 0;
    }
}