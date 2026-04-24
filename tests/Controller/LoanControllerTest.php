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
        $this->loanService = $this->createMock(LoanService::class);
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

    public function testBorrowReturns403IfNotLoggedIn(): void
    {
        $reflection = new \ReflectionMethod(LoanController::class, 'create');
        $attributes = $reflection->getAttributes(IsGranted::class);

        $this->assertNotEmpty($attributes, 'La methode create() doit etre protegee par #[IsGranted]');

        $isGranted = $attributes[0]->newInstance();
        $this->assertSame('ROLE_USER', $isGranted->attribute);
    }

    public function testGetMyLoansReturnsOnlyMineNotOthers(): void
    {
        $user = (new User())
            ->setFirstName('Jean')
            ->setLastName('Dupont');
        $book = (new Book())->setTitle('Dune')->setAvailableCopies(1);
        $loan = (new Loan())
            ->setBook($book)
            ->setUser($user)
            ->setDueDate(new \DateTimeImmutable('+14 days'));

        $this->controller->setTestUser($user);

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
        $this->assertSame('Jean', $payload[0]['user']['firstName']);
        $this->assertSame('Dupont', $payload[0]['user']['lastName']);
    }

    public function testOwnerCanRequestOwnReturn(): void
    {
        $user = new User();
        $book = (new Book())->setTitle('Dune');
        $loan = (new Loan())
            ->setBook($book)
            ->setUser($user)
            ->setDueDate(new \DateTimeImmutable('+14 days'));

        $this->controller->setTestUser($user);
        $this->loanRepository->method('find')->with(12)->willReturn($loan);
        $this->loanService->expects($this->once())->method('requestReturn')->with($loan)->willReturn($loan);

        $response = $this->controller->returnLoan(12);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testUserCannotRequestAnotherUsersReturn(): void
    {
        $owner = new User();
        $otherUser = new User();
        $book = (new Book())->setTitle('Dune');
        $loan = (new Loan())
            ->setBook($book)
            ->setUser($owner)
            ->setDueDate(new \DateTimeImmutable('+14 days'));

        $this->controller->setTestUser($otherUser);
        $this->loanRepository->method('find')->with(12)->willReturn($loan);
        $this->loanService->expects($this->never())->method('requestReturn');

        $response = $this->controller->returnLoan(12);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testLibrarianCanValidateReturn(): void
    {
        $librarian = (new User())->setRoles(['ROLE_LIBRARIAN']);
        $user = new User();
        $book = (new Book())->setTitle('Dune');
        $loan = (new Loan())
            ->setBook($book)
            ->setUser($user)
            ->setDueDate(new \DateTimeImmutable('+14 days'))
            ->setStatus(Loan::STATUS_RETURN_REQUESTED);

        $this->controller->setTestUser($librarian);
        $this->loanRepository->method('find')->with(21)->willReturn($loan);
        $this->loanService->expects($this->once())->method('validateReturn')->with($loan)->willReturn($loan);

        $response = $this->controller->validateReturn(21);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testReturnRequestRequiresUserRole(): void
    {
        $reflection = new \ReflectionMethod(LoanController::class, 'returnLoan');
        $attributes = $reflection->getAttributes(IsGranted::class);

        $this->assertNotEmpty($attributes, 'La methode returnLoan() doit etre protegee par #[IsGranted]');

        $isGranted = $attributes[0]->newInstance();
        $this->assertSame('ROLE_USER', $isGranted->attribute);
    }

    public function testValidateReturnRequiresLibrarianRole(): void
    {
        $reflection = new \ReflectionMethod(LoanController::class, 'validateReturn');
        $attributes = $reflection->getAttributes(IsGranted::class);

        $this->assertNotEmpty($attributes, 'La methode validateReturn() doit etre protegee par #[IsGranted]');

        $isGranted = $attributes[0]->newInstance();
        $this->assertSame('ROLE_LIBRARIAN', $isGranted->attribute);
    }
}
