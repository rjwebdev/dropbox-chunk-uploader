<?php

namespace App\Command;

use Clue\React\Buzz\Browser;
use Clue\React\Block;
use React\EventLoop\Factory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class BlockingCommand extends Command
{
    protected function configure()
    {
        $this->setName('blocking-example');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $loop = Factory::create();

        // this example uses an HTTP client
        // this could be pretty much everything that binds to an event loop
        $browser = new Browser($loop);

        // set up two parallel requests
        $request1 = $browser->get('http://www.google.com/');
        $request2 = $browser->get('http://www.google.co.uk/');

        // keep the loop running (i.e. block) until the first response arrives
        $fasterResponse = Block\awaitAny(array($request1, $request2), $loop);

        dump((string) $fasterResponse->getBody());

        return 0;
    }
}