<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\HealthConnect\HealthConnectImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Backfill des séances depuis un export Health Connect (.db SQLite).
 *
 *   php bin/console app:import:health-connect /chemin/health_connect_export.db user@mail.com
 *   php bin/console app:import:health-connect export.db user@mail.com --dry-run
 */
#[AsCommand(
    name: 'app:import:health-connect',
    description: 'Importe les séances d\'un export Health Connect vers WorkoutSession (idempotent).',
)]
final class ImportHealthConnectCommand extends Command
{
    public function __construct(
        private readonly HealthConnectImporter $importer,
        private readonly UserRepository        $userRepo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('db', InputArgument::REQUIRED, 'Chemin du fichier health_connect_export.db')
            ->addArgument('email', InputArgument::REQUIRED, 'Email du user cible')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule sans rien écrire en base');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dbPath = (string) $input->getArgument('db');
        $email  = (string) $input->getArgument('email');
        $dryRun = (bool) $input->getOption('dry-run');

        $user = $this->userRepo->findOneBy(['email' => $email]);
        if ($user === null) {
            $io->error("Aucun utilisateur avec l'email : $email");
            return Command::FAILURE;
        }

        $io->title('Import Health Connect → WorkoutSession');
        $io->text([
            'Fichier : ' . $dbPath,
            'User    : ' . $email,
            'Mode    : ' . ($dryRun ? 'DRY-RUN (aucune écriture)' : 'écriture en base'),
        ]);

        $res = $this->importer->import($dbPath, $user, $dryRun);

        foreach ($res['errors'] as $err) {
            $io->warning($err);
        }

        $io->success(sprintf(
            '%s%d séance(s) importée(s), %d ignorée(s) (déjà présentes), %d erreur(s).',
            $dryRun ? '[DRY-RUN] ' : '',
            $res['imported'],
            $res['skipped'],
            count($res['errors']),
        ));

        return $res['errors'] !== [] && $res['imported'] === 0
            ? Command::FAILURE
            : Command::SUCCESS;
    }
}
