<?php

namespace App\Tests\Controller;

use App\Controller\LoanController;
use App\Entity\Book;
use App\Entity\Loan;
use App\Entity\User;
use App\Repository\BookRepository;
use App\Repository\LoanRepository;
use App\Services\LoanService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// Juste avant "class LoanControllerTest extends TestCase"
class LoanControllerDouble extends LoanController
{
    private ?\Symfony\Component\Security\Core\User\UserInterface $testUser = null;

    public function setTestUser(?\Symfony\Component\Security\Core\User\UserInterface $user): void
    {
        $this->testUser = $user;
    }

    protected function getUser(): ?\Symfony\Component\Security\Core\User\UserInterface
    {
        return $this->testUser;
    }
}

class LoanControllerTest extends TestCase
{
    /** @var LoanService&MockObject */
    private LoanService $loanService;

    /** @var BookRepository&MockObject */
    private BookRepository $bookRepository;

    /** @var LoanRepository&MockObject */
    private LoanRepository $loanRepository;

    private LoanControllerDouble $controller;

    protected function setUp(): void
    {
        $this->loanService    = $this->createMock(LoanService::class);
        $this->bookRepository = $this->createMock(BookRepository::class);
        $this->loanRepository = $this->createMock(LoanRepository::class);

        $this->controller = new LoanControllerDouble(
            $this->loanService,
            $this->bookRepository,
            $this->loanRepository,
        );

        $this->controller->setContainer(new class implements ContainerInterface {
            public function get(string $id) { throw new \RuntimeException("Service inattendu : $id"); }
            public function has(string $id): bool { return false; }
        });
    }

    // Vérifie qu'un emprunt valide retourne un statut 201 avec les données du prêt
    public function testBorrowReturns201(): void
    {
        $user = new User();
        $book = (new Book())->setTitle('Dune')->setAvailableCopies(2);
        $loan = (new Loan())->setBook($book)->setUser($user)->setDueDate(new \DateTimeImmutable('+14 days'));

        $this->controller->setTestUser($user);
        $this->bookRepository->method('find')->willReturn($book);
        $this->loanService->method('createLoan')->willReturn($loan);

        $request = new Request([], [], [], [], [], [], json_encode(['bookId' => 1]));
        $request->headers->set('Content-Type', 'application/json');

        $response = $this->controller->create($request);

        $this->assertSame(201, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertSame('Dune', $payload['bookTitle']);
    }

    // Vérifie que la méthode create() est protégée par #[IsGranted('ROLE_USER')] via Reflection
    public function testBorrowReturns403IfNotLoggedIn(): void
    {
        $reflection = new \ReflectionMethod(LoanController::class, 'create');
        $attributes = $reflection->getAttributes(IsGranted::class);

        $this->assertNotEmpty($attributes, 'La méthode create() doit être protégée par #[IsGranted]');

        $isGranted = $attributes[0]->newInstance();
        $this->assertSame('ROLE_USER', $isGranted->attribute);
    }

    // Vérifie que myLoans() appelle getLoansByUser() avec le bon utilisateur et retourne uniquement ses emprunts
    public function testGetMyLoansReturnsOnlyMineNotOthers(): void
    {
        $user = new User();
        $book = (new Book())->setTitle('Dune')->setAvailableCopies(1);

        $loan = (new Loan())
            ->setBook($book)
            ->setUser($user)
            ->setDueDate(new \DateTimeImmutable('+14 days'));

        $this->controller->setTestUser($user);

        // On vérifie que getLoansByUser est appelé avec le bon user
        $this->loanService
            ->expects($this->once())
            ->method('getLoansByUser')
            ->with($user)
            ->willReturn([$loan]);

        $response = $this->controller->myLoans();

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertCount(1, $payload);
        $this->assertSame('Dune', $payload[0]['bookTitle']);
    }

    // Vérifie que la méthode returnLoan() est protégée par #[IsGranted('ROLE_LIBRARIAN')] via Reflection
    public function testReturnRequiresLibrarianRole(): void
    {
        $reflection = new \ReflectionMethod(LoanController::class, 'returnLoan');
        $attributes = $reflection->getAttributes(IsGranted::class);

        $this->assertNotEmpty($attributes, 'La méthode returnLoan() doit être protégée par #[IsGranted]');

        $isGranted = $attributes[0]->newInstance();
        $this->assertSame('ROLE_LIBRARIAN', $isGranted->attribute);
    }
}
