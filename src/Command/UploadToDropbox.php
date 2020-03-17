<?php

namespace App\Command;

use Psr\Log\LoggerInterface;
use React\EventLoop\Factory as EventLoopFactory;
use React\Stream\ReadableResourceStream;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class UploadToDropbox extends Command
{
    private HttpClientInterface $client;
    private LoggerInterface $logger;

    public function __construct(string $accessToken, LoggerInterface $logger)
    {
        $this->client = HttpClient::createForBaseUri('https://content.dropboxapi.com/2/', ['auth_bearer' => $accessToken]);
        $this->logger = $logger;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('app:upload-file')
            ->addArgument('path', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
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

        $loop = EventLoopFactory::create();

        $offset = 0;
        //20MB chunk size
        $fileStream = new ReadableResourceStream(fopen($filePath, 'r+'), $loop, 1024*1024*20);
        $fileStream->on('data', function ($data) use ($sessionId, &$offset) {
            $chunkResponse = $this->client->request('POST', 'files/upload_session/append_v2', [
                'headers' => [
                    'Content-Type' => 'application/octet-stream',
                    'Dropbox-Api-Arg' => json_encode([
                        'cursor' => [
                            'session_id' => $sessionId,
                            'offset' => $offset,
                        ],
                        'close' => false,
                    ]),
                ],
                'body' => $data,
            ]);

            if (Response::HTTP_OK !== $chunkResponse->getStatusCode()) {
                $this->logger->critical($chunkResponse->getContent(false));
            }

            $offset += strlen($data);
        });

        $loop->run();

        $sessionEndRequest = $this->client->request('POST', 'files/upload_session/finish', [
            'headers' => [
                'Content-Type' => 'application/octet-stream',
                'Dropbox-API-Arg' => json_encode([
                    'cursor' => [
                        'session_id' => $sessionId,
                        'offset' => $offset,
                    ],
                    'commit' => [
                        'path' => sprintf('/HelloFirstFolder%s', basename($filePath)),
                        'mode' => 'add',
                        'autorename' => true,
                    ]
                ]),
            ]
        ]);

        if (Response::HTTP_OK !== $sessionEndRequest->getStatusCode()) {
            $this->logger->critical($sessionEndRequest->getContent(false), ['status_code' => $sessionEndRequest->getStatusCode()]);
        }

        return 0;
    }
}
