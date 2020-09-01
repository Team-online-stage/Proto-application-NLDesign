<?php

// src/Command/CreateUserCommand.php

namespace App\Command;

use App\Service\FactuurService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FactuurCommand extends Command
{
    private $em;
    private $factuurService;

    public function __construct(EntityManagerInterface $em, FactuurService $factuurService)
    {
        $this->em = $em;
        $this->factuurService = $factuurService;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('app:factuur:creation')
            // the short description shown while running "php bin/console list"
            ->setDescription('Creates a monthly factuur for all subscriptions.')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command creates a monthly factuur for all the subscriptions')
            ->setDescription('creates a monthly factuur for all the subscriptions')
            ->addOption('houseNumber', null, InputOption::VALUE_OPTIONAL, 'the house number of the objects to cache')
            ->addOption('postcode', null, InputOption::VALUE_OPTIONAL, 'the postcode of the objects to cache');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('houseNumber') && $input->getOption('postcode')) {
            $houseNumber = $input->getOption('houseNumber');
            $postcode = $input->getOption('postcode');
        } else {
            $postcode = '5382JX';
            $houseNumber = 1;
        }

        /** @var string $version */
        $io->text("Warming up cache for postcode $postcode with house number $houseNumber");
        $this->factuurService->getAdresOnHuisnummerPostcode($houseNumber, $postcode);
        $io->text("Cache warmed up with postcode $postcode and house number $houseNumber");
    }
}
