<?php

namespace App\Command;

use App\Model\Commit;
use App\Model\Cursor;
use App\Model\SessionAppend;
use App\Model\UploadSessionEnd;
use Psr\Log\LoggerInterface;
use React\EventLoop\Factory as EventLoopFactory;
use React\Stream\ReadableResourceStream;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class UploadToDropbox extends Command
{
    private HttpClientInterface $client;
    private string $dropboxBasePath;
    private LoggerInterface $logger;
    private SerializerInterface $serializer;

    public function __construct(string $accessToken, string $dropboxBasePath, LoggerInterface $logger, SerializerInterface $serializer)
    {
        $this->client = HttpClient::createForBaseUri('https://content.dropboxapi.com/2/', ['auth_bearer' => $accessToken]);
        $this->dropboxBasePath = $dropboxBasePath;
        $this->logger = $logger;
        $this->serializer = $serializer;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('app:upload-file')
            ->addArgument('path', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stopWatch = new Stopwatch();
        $stopWatch->start('symfony-upload');
        $filePath = $input->getArgument('path');
        if (!file_exists($filePath)) {
            throw new \RuntimeException('Invalid path given for the file to upload');
        }

        $startSessionRequest = $this->client->request('POST', 'files/upload_session/start', [
            'headers' => [
                'Content-Type' => 'application/octet-stream',
                'Dropbox-Api-Arg' => json_encode(['close' => false])
            ]
        ]);

        if (Response::HTTP_OK !== $startSessionRequest->getStatusCode()) {
            throw new \RuntimeException('Failed to start an upload session');
        }

        $sessionId = $startSessionRequest->toArray(false)['session_id'];
        dump('session started');
        $loop = EventLoopFactory::create();

        $offset = 0;
        //20MB chunk size
        $fileStream = new ReadableResourceStream(fopen($filePath, 'r+'), $loop, 1024*1024*20);
        $fileStream->on('data', function ($data) use ($sessionId, &$offset) {
            $headers = [
                'Content-Type' => 'application/octet-stream',
                'Dropbox-API-Arg' => $this->serializer->serialize(new SessionAppend(new Cursor($sessionId, $offset)), 'json'),
            ];

            $chunkResponse = $this->client->request('POST', 'files/upload_session/append_v2', [
                'headers' => $headers,
                'body' => $data,
            ]);

            if (Response::HTTP_OK !== $chunkResponse->getStatusCode()) {
                $this->logger->critical($chunkResponse->getContent(false));
            }

            dump('chunk uploaded');
            $offset += strlen($data);
        });

        $loop->run();

        $commit = new Commit(sprintf('/MyDropboxPath/symfony.%s', pathinfo($filePath, PATHINFO_EXTENSION)), Commit::MODE_ADD, true, true);
        $sessionEnd = new UploadSessionEnd(new Cursor($sessionId, $offset), $commit);
        $headers = [
            'Content-Type' => 'application/octet-stream',
            'Dropbox-API-Arg' => $this->serializer->serialize($sessionEnd, 'json'),
        ];

        $sessionEndRequest = $this->client->request('POST', 'files/upload_session/finish', ['headers' => $headers]);

        if (Response::HTTP_OK !== $sessionEndRequest->getStatusCode()) {
            $this->logger->critical($sessionEndRequest->getContent(false), ['status_code' => $sessionEndRequest->getStatusCode()]);
        }

        dump('session ended');

        $stopWatch->stop('symfony-upload');
        $output->writeln(sprintf('Duration: %d seconds', ($stopWatch->getEvent('symfony-upload')->getDuration()) / 1000));
        $output->writeln(sprintf('Memory usage: %d', $stopWatch->getEvent('symfony-upload')->getMemory()));

        return 0;
    }
}
