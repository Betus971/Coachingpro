<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\DailyActivityLog;
use App\Entity\Exercise;
use App\Entity\NutritionLog;
use App\Entity\Program;
use App\Entity\User;
use App\Entity\WeightLog;
use App\Entity\WorkoutSession;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Assigns API-created resources to the authenticated user when ownership is implicit.
 */
final readonly class CurrentUserOwnershipProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $processor,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $this->assignOwner($data, $user);
        }

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }

    private function assignOwner(mixed $data, User $user): void
    {
        match (true) {
            $data instanceof WeightLog,
            $data instanceof NutritionLog,
            $data instanceof WorkoutSession,
            $data instanceof DailyActivityLog => $data->setUser($user),
            $data instanceof Program => $data->setCreatedBy($user),
            $data instanceof Exercise => $data->setCreatedBy($user),
            default => null,
        };
    }
}
