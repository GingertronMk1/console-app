<?php

namespace App\Command;

use Stringable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * * read model
 * * write model
 * * repository and finder interfaces and concrete implementations (using Doctrine DBAL)
 * * create and update commands and handlers
 * * Symfony forms
 * * Controller
 */
#[AsCommand(
    name: 'app:create-entity',
    description: 'Create an entity with associated models'
)]
class CreateEntityCommand extends Command
{
    private const ENTITY_NAME_ARG = 'entityName';

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly Filesystem      $filesystem
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ENTITY_NAME_ARG, InputArgument::REQUIRED, 'Entity name')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $entityName = $input->getArgument(self::ENTITY_NAME_ARG);

        if ($entityName) {
            $io->note(sprintf('You passed an argument: %s', $entityName));
        }

        $io->note($this->kernel->getProjectDir() . DIRECTORY_SEPARATOR . 'src');

        $table = $io->createTable();
        $table->setHeaders(['path', 'class']);
        foreach ($this->getFilesToCreate($entityName) as $path => $class) {
            $table->addRow([$path, $class]);
        }

        $table->render();

        return Command::SUCCESS;
    }

    private function getFilesToCreate(string $entityName): array
    {
        $appPrefix = "\\App";
        $entity = "Domain\\{$entityName}\\{$entityName}Entity";
        $repositoryInterface = "Domain\\{$entityName}\\{$entityName}RepositoryInterface";
        $model = "Application\\{$entityName}\\{$entityName}Model";
        $finderInterface = "Application\\{$entityName}\\{$entityName}FinderInterface";
        $createCommand = "Application\\{$entityName}\\Command\\Create{$entityName}Command";
        $updateCommand = "Application\\{$entityName}\\Command\\Update{$entityName}Command";
        $createCommandHandler = "Application\\{$entityName}\\CommandHandler\\Create{$entityName}CommandHandler";
        $updateCommandHandler = "Application\\{$entityName}\\CommandHandler\\Update{$entityName}CommandHandler";
        $dbalFinder = "Infrastructure\\{$entityName}\\Dbal{$entityName}Finder";
        $dbalRepository = "Infrastructure\\{$entityName}\\Dbal{$entityName}Repository";
        $controller = "Framework\\{$entityName}\\Controller\\{$entityName}Controller";
        $createForm = "Framework\\{$entityName}\\Form\\Create{$entityName}Form";
        $updateForm = "Framework\\{$entityName}\\Form\\Update{$entityName}Form";

        return [
            $entity => $this->getEntityClass(
                $entity
            ),
            $repositoryInterface => $this->getEntityClass(
                $repositoryInterface
            ),
            $model => $this->getEntityClass(
                $model
            ),
            $finderInterface => $this->getEntityClass(
                $finderInterface
            ),
            $createCommand => $this->getEntityClass(
                $createCommand
            ),
            $updateCommand => $this->getEntityClass(
                $updateCommand
            ),
            $createCommandHandler => $this->getEntityClass(
                $createCommandHandler
            ),
            $updateCommandHandler => $this->getEntityClass(
                $updateCommandHandler
            ),
            $dbalFinder => $this->getEntityClass(
                $dbalFinder,
                implements: [$finderInterface]
            ),
            $dbalRepository => $this->getEntityClass(
                $dbalRepository,
                implements: [$repositoryInterface]
            ),
            $controller => $this->getEntityClass(
                $controller,
                extends: [AbstractController::class]
            ),
            $createForm => $this->getEntityClass(
                $createForm
            ),
            $updateForm => $this->getEntityClass(
                $updateForm
            ),
        ];
    }

    private function getEntityClass(
        string $fqn,
        array  $implements = [],
        array  $extends = [],
        array  $attributes = [],
        array  $methods = [],
    ): object
    {
        return new class(
            fqn: $fqn,
            implements: $implements,
            extends: $extends,
            attributes: $attributes,
            methods: $methods
        ) implements Stringable {
            public function __construct(
                public readonly string $fqn,
                public readonly array  $implements,
                public readonly array  $extends,
                public readonly array  $attributes,
                public readonly array  $methods,
            )
            {
            }

            private function getLastBackslash(): int
            {
                return strrpos($this->fqn, '\\');
            }

            private function getNamespace(): string
            {
                return substr($this->fqn, 0, $this->getLastBackslash());
            }

            private function getClass(): string
            {
                return substr($this->fqn, $this->getLastBackslash() + 1);
            }

            private function getExtends(): string
            {

                return match (count($this->extends)) {
                    0 => '',
                    default => ' extends ' . implode(', ', $this->addLeadingBackslashes($this->extends))
                };
            }

            private function getImplements(): string
            {
                return match (count($this->implements)) {
                    0 => '',
                    default => ' implements ' . implode(', ', $this->addLeadingBackslashes($this->implements))
                };
            }

            private function addLeadingBackslashes(array $strings): array
            {
                return array_map(
                    fn (string $fqn) => str_starts_with($fqn, '\\') ? $fqn : "\\{$fqn}",
                    $strings
                );
            }

            public function __toString(): string
            {
                return <<<PHP
<?php

declare(strict_types=1);

namespace App\\{$this->getNamespace()};

class {$this->getClass()}{$this->getImplements()}{$this->getExtends()} {

}

PHP;

            }
        };
    }
}
