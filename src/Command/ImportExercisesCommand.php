<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Exercise;
use App\Repository\ExerciseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Importe le catalogue d'exercices depuis la Free Exercise DB (domaine public,
 * ~870 exercices avec images). Idempotent : dédup sur external_id.
 *
 *   php bin/console app:import:exercises
 *   php bin/console app:import:exercises --limit=50
 *
 * Les images sont servies via le CDN jsDelivr (contenu domaine public) — pas de
 * téléchargement local en MVP. On pourra les rapatrier dans public/ plus tard.
 */
#[AsCommand(
    name: 'app:import:exercises',
    description: 'Importe le catalogue Free Exercise DB (domaine public) dans la table exercise.',
)]
final class ImportExercisesCommand extends Command
{
    private const JSON_URL  = 'https://cdn.jsdelivr.net/gh/yuhonas/free-exercise-db@main/dist/exercises.json';
    private const IMG_BASE  = 'https://cdn.jsdelivr.net/gh/yuhonas/free-exercise-db@main/exercises/';

    /** Mapping muscles Free Exercise DB → groupes autorisés par Exercise::getMuscleGroups(). */
    private const MUSCLE_MAP = [
        'abdominals' => 'core',
        'abductors'  => 'glutes',
        'adductors'  => 'quads',
        'biceps'     => 'biceps',
        'calves'     => 'calves',
        'chest'      => 'chest',
        'forearms'   => 'arms',
        'glutes'     => 'glutes',
        'hamstrings' => 'hamstrings',
        'lats'       => 'back',
        'lower back' => 'back',
        'middle back' => 'back',
        'neck'       => 'shoulders',
        'quadriceps' => 'quads',
        'shoulders'  => 'shoulders',
        'traps'      => 'back',
        'triceps'    => 'triceps',
    ];

    public function __construct(
        private readonly HttpClientInterface    $httpClient,
        private readonly ExerciseRepository     $exerciseRepo,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limiter le nombre d\'exercices importés (test)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $limit = $input->getOption('limit') !== null ? (int) $input->getOption('limit') : null;

        $io->title('Import catalogue exercices (Free Exercise DB)');

        try {
            $rows = $this->httpClient->request('GET', self::JSON_URL, ['timeout' => 30])->toArray();
        } catch (\Throwable $e) {
            $io->error('Téléchargement du catalogue impossible : ' . $e->getMessage());
            return Command::FAILURE;
        }

        $created = 0;
        $updated = 0;
        $i       = 0;

        foreach ($rows as $row) {
            if ($limit !== null && $i >= $limit) {
                break;
            }
            $i++;

            $extId = (string) ($row['id'] ?? '');
            if ($extId === '') {
                continue;
            }

            $exercise = $this->exerciseRepo->findOneBy(['externalId' => $extId]);
            $isNew    = $exercise === null;
            if ($isNew) {
                $exercise = new Exercise();
                $exercise->setExternalId($extId);
            }

            $muscle = self::MUSCLE_MAP[$row['primaryMuscles'][0] ?? ''] ?? 'core';
            $images = $row['images'] ?? [];
            $desc   = isset($row['instructions']) ? implode("\n", $row['instructions']) : null;

            $exercise
                ->setName((string) ($row['name'] ?? 'Exercice'))
                ->setMuscleGroup($muscle)
                ->setEquipment($row['equipment'] ?? null)
                ->setDescription($desc)
                ->setImageUrl(!empty($images) ? self::IMG_BASE . $images[0] : null);
            // createdBy reste null => exercice "système" visible par tous.

            if ($isNew) {
                $this->em->persist($exercise);
                $created++;
            } else {
                $updated++;
            }

            if (($created + $updated) % 100 === 0) {
                $this->em->flush();
            }
        }

        $this->em->flush();

        $io->success(sprintf('%d exercices créés, %d mis à jour.', $created, $updated));

        return Command::SUCCESS;
    }
}
