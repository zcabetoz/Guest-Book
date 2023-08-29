<?php

namespace App\Command;

use App\Repository\CommentRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CommentCleanupCommand extends Command
{
    private $commentRepository;
    protected static $defaultName = 'app:comment:cleanup';
    protected static $defaultDescription = 'Deletes rejected and spam comments from the database';

    public function __construct(CommentRepository $commentRepository, string $name = null)
    {
        $this->commentRepository = $commentRepository;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry-run')
        ;
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('dry-run')) {
            $io->note('Dry mode enabled');
            $count = $this->commentRepository->countOldRejected();
        }else{
            $count = $this->commentRepository->deleteOldRejected();
        }

        $io->success(sprintf('Delete "%d" old rejected/spam comments', $count ));

        return Command::SUCCESS;
    }
}
