<?php

namespace App\Tests\Controller;

use App\Controller\StatsController;
use App\Repository\BookRepository;
use App\Repository\LoanRepository;
use App\Repository\ReservationRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class StatsControllerTest extends TestCase
{
    /** @var BookRepository&MockObject */
    private BookRepository $bookRepository;

    /** @var LoanRepository&MockObject */
    private LoanRepository $loanRepository;

    /** @var ReservationRepository&MockObject */
    private ReservationRepository $reservationRepository;

    /** @var TokenStorageInterface&MockObject */
    private TokenStorageInterface $tokenStorage;

    /** @var AuthorizationCheckerInterface&MockObject */
    private AuthorizationCheckerInterface $authorizationChecker;

    private StatsController $controller;

    protected function setUp(): void
    {
        $this->bookRepository = $this->createMock(BookRepository::class);
        $this->loanRepository = $this->createMock(LoanRepository::class);
        $this->reservationRepository = $this->createMock(ReservationRepository::class);
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);

        $this->controller = new StatsController(
            $this->bookRepository,
            $this->loanRepository,
            $this->reservationRepository,
        );

        $tokenStorage = $this->tokenStorage;
        $authorizationChecker = $this->authorizationChecker;
        $this->controller->setContainer(new class ($tokenStorage, $authorizationChecker) implements ContainerInterface {
            public function __construct(
                private TokenStorageInterface $tokenStorage,
                private AuthorizationCheckerInterface $authorizationChecker,
            ) {}

            public function get(string $id)
            {
                return match ($id) {
                    'security.token_storage' => $this->tokenStorage,
                    'security.authorization_checker' => $this->authorizationChecker,
                    default => throw new \RuntimeException(sprintf('Unexpected service lookup: %s', $id)),
                };
            }

            public function has(string $id): bool
            {
                return in_array($id, [
                    'security.token_storage',
                    'security.authorization_checker',
                ], true);
            }
        });
    }

    public function testCatalogueStatsReturnsDashboardMetricsForLibrarian(): void
    {
        $this->mockAuthenticatedUser((new \App\Entity\User())->setRoles(['ROLE_LIBRARIAN']));
        $this->authorizationChecker
            ->method('isGranted')
            ->willReturnCallback(fn (mixed $attribute): bool => $attribute === 'ROLE_LIBRARIAN');

        $this->bookRepository->method('countAll')->willReturn(120);
        $this->reservationRepository->method('countAllReservations')->willReturn(40);
        $this->reservationRepository->method('countCurrentReservations')->willReturn(12);
        $this->reservationRepository->method('countPastReservations')->willReturn(28);
        $this->loanRepository->method('findMostBorrowedBooks')->with(5)->willReturn([
            ['id' => 1, 'title' => 'Dune', 'author' => 'Frank Herbert', 'loanCount' => 14],
            ['id' => 2, 'title' => '1984', 'author' => 'George Orwell', 'loanCount' => 11],
        ]);

        $response = $this->controller->catalogueStats();

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent() ?: '', true);
        $this->assertSame(120, $payload['totalBooks']);
        $this->assertSame(40, $payload['totalReservations']);
        $this->assertSame(12, $payload['currentReservations']);
        $this->assertSame(28, $payload['pastReservations']);
        $this->assertSame('Dune', $payload['topBorrowedBooks'][0]['title']);
        $this->assertSame(14, $payload['topBorrowedBooks'][0]['loanCount']);
    }

    public function testCatalogueStatsReturns403ForSimpleUser(): void
    {
        $this->mockAuthenticatedUser(new \App\Entity\User());
        $this->authorizationChecker->method('isGranted')->willReturn(false);

        $this->bookRepository->expects($this->never())->method('countAll');
        $this->reservationRepository->expects($this->never())->method('countAllReservations');
        $this->loanRepository->expects($this->never())->method('findMostBorrowedBooks');

        $response = $this->controller->catalogueStats();

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testCatalogueStatsEndpointRequiresAuthenticatedUserRole(): void
    {
        $reflection = new \ReflectionMethod(StatsController::class, 'catalogueStats');
        $attributes = $reflection->getAttributes(IsGranted::class);

        $this->assertNotEmpty($attributes, 'La methode catalogueStats() doit etre protegee par #[IsGranted]');

        $isGranted = $attributes[0]->newInstance();
        $this->assertSame('ROLE_USER', $isGranted->attribute);
    }

    private function mockAuthenticatedUser(\App\Entity\User $user): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->tokenStorage
            ->method('getToken')
            ->willReturn($token);
    }
}
