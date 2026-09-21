<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:user:create',
    description: "Cree le compte de l'artisan qui utilisera l'application.",
)]
final class CreerUtilisateurCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, "Email de connexion")
            ->addArgument('motDePasse', InputArgument::OPTIONAL, 'Mot de passe (demande de maniere masquee si absent)')
            ->addOption('nom-complet', null, InputOption::VALUE_REQUIRED, "Nom affiche dans l'interface")
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Attribue le role ROLE_ADMIN');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        if (null !== $this->userRepository->findOneBy(['email' => $email])) {
            $style->error(\sprintf('Un compte existe deja pour "%s".', $email));

            return Command::FAILURE;
        }

        $motDePasse = $input->getArgument('motDePasse');

        if (null === $motDePasse) {
            $question = (new Question('Mot de passe : '))->setHidden(true)->setHiddenFallback(false);
            $motDePasse = $style->askQuestion($question);
        }

        if (!\is_string($motDePasse) || \strlen($motDePasse) < 8) {
            $style->error('Le mot de passe doit comporter au moins 8 caracteres.');

            return Command::FAILURE;
        }

        $utilisateur = (new User())
            ->setEmail($email)
            ->setNomComplet($input->getOption('nom-complet'))
            ->setRoles($input->getOption('admin') ? ['ROLE_ADMIN'] : []);

        $utilisateur->setPassword($this->passwordHasher->hashPassword($utilisateur, $motDePasse));

        $violations = $this->validator->validate($utilisateur);

        if (\count($violations) > 0) {
            foreach ($violations as $violation) {
                $style->error(\sprintf('%s : %s', $violation->getPropertyPath(), (string) $violation->getMessage()));
            }

            return Command::FAILURE;
        }

        $this->entityManager->persist($utilisateur);
        $this->entityManager->flush();

        $style->success(\sprintf('Compte "%s" cree.', $email));

        return Command::SUCCESS;
    }
}
