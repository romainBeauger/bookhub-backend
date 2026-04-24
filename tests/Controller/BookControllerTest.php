<?php

namespace App\Tests\Controller;

use App\Controller\BookController;
use App\Entity\Book;
use App\Entity\Category;
use App\Repository\BookRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class BookControllerTest extends TestCase
{
    /** @var BookRepository&MockObject */
    private BookRepository $bookRepository;

    /** @var CategoryRepository&MockObject */
    private CategoryRepository $categoryRepository;

    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $entityManager;

    /** @var TokenStorageInterface&MockObject */
    private TokenStorageInterface $tokenStorage;

    /** @var AuthorizationCheckerInterface&MockObject */
    private AuthorizationCheckerInterface $authorizationChecker;

    private BookController $controller;

    protected function setUp(): void
    {
        $this->bookRepository = $this->createMock(BookRepository::class);
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);

        $this->controller = new BookController(
            $this->bookRepository,
            $this->categoryRepository,
            $this->entityManager,
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

    public function testIndexAppliqueRechercheEtFiltres(): void
    {
        $category = (new Category())->setName('Science-fiction');
        $this->forceEntityId($category, 3);

        $book = (new Book())
            ->setTitle('Dune')
            ->setAuthor('Frank Herbert')
            ->setDescription('Une epopee de science-fiction.')
            ->setIsbn('9780000000149')
            ->setTotalCopies(6)
            ->setAvailableCopies(6)
            ->setPublishedAt(new \DateTimeImmutable('1965-08-01'))
            ->setImage('https://example.test/dune.jpg')
            ->setCategory($category);
        $this->forceEntityId($book, 14);

        $expectedFilters = [
            'q' => 'dune',
            'author' => 'frank',
            'categoryId' => 3,
            'available' => true,
            'publishedFrom' => new \DateTimeImmutable('1960-01-01'),
            'publishedTo' => new \DateTimeImmutable('1970-12-31'),
            'sort' => 'desc',
        ];

        $this->bookRepository
            ->expects($this->once())
            ->method('findPaginatedWithFilters')
            ->with(
                2,
                5,
                $this->callback(function (array $filters) use ($expectedFilters): bool {
                    return ($filters['q'] ?? null) === $expectedFilters['q']
                        && ($filters['author'] ?? null) === $expectedFilters['author']
                        && ($filters['categoryId'] ?? null) === $expectedFilters['categoryId']
                        && ($filters['available'] ?? null) === $expectedFilters['available']
                        && ($filters['publishedFrom'] ?? null)?->format('Y-m-d') === $expectedFilters['publishedFrom']->format('Y-m-d')
                        && ($filters['publishedTo'] ?? null)?->format('Y-m-d') === $expectedFilters['publishedTo']->format('Y-m-d')
                        && ($filters['sort'] ?? null) === $expectedFilters['sort'];
                })
            )
            ->willReturn([$book]);

        $this->bookRepository
            ->expects($this->once())
            ->method('countFiltered')
            ->with($this->isType('array'))
            ->willReturn(1);

        $request = new Request([
            'page' => 2,
            'limit' => 5,
            'q' => 'dune',
            'author' => 'frank',
            'categoryId' => '3',
            'available' => 'true',
            'publishedFrom' => '1960-01-01',
            'publishedTo' => '1970-12-31',
            'sort' => 'desc',
        ]);

        $response = $this->controller->index($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = json_decode($response->getContent() ?: '', true);
        $this->assertSame('Dune', $payload['data'][0]['title']);
        $this->assertSame(3, $payload['filters']['categoryId']);
        $this->assertTrue($payload['filters']['available']);
        $this->assertSame('1960-01-01', $payload['filters']['publishedFrom']);
        $this->assertSame('desc', $payload['filters']['sort']);
        $this->assertSame(2, $payload['pagination']['page']);
    }

    public function testIndexRetourneUneErreurSiLeFiltreAvailableEstInvalide(): void
    {
        $response = $this->controller->index(new Request([
            'available' => 'maybe',
        ]));

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $payload = json_decode($response->getContent() ?: '', true);
        $this->assertSame('Le filtre "available" doit etre true, false, 1 ou 0', $payload['message']);
    }

    public function testIndexUtiliseDouzeLivresParPageParDefaut(): void
    {
        $this->bookRepository
            ->expects($this->once())
            ->method('findPaginatedWithFilters')
            ->with(1, 12, $this->isType('array'))
            ->willReturn([]);

        $this->bookRepository
            ->expects($this->once())
            ->method('countFiltered')
            ->with($this->isType('array'))
            ->willReturn(0);

        $response = $this->controller->index(new Request());

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = json_decode($response->getContent() ?: '', true);
        $this->assertSame(12, $payload['pagination']['limit']);
        $this->assertSame('random', $payload['filters']['sort']);
    }

    public function testIndexRetourneUneErreurSiLeTriEstInvalide(): void
    {
        $response = $this->controller->index(new Request([
            'sort' => 'alpha',
        ]));

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $payload = json_decode($response->getContent() ?: '', true);
        $this->assertSame('Le filtre "sort" doit etre asc, desc ou random', $payload['message']);
    }

    public function testIndexAccepteLeTriRandom(): void
    {
        $this->bookRepository
            ->expects($this->once())
            ->method('findPaginatedWithFilters')
            ->with(
                1,
                12,
                $this->callback(fn (array $filters): bool => ($filters['sort'] ?? null) === 'random')
            )
            ->willReturn([]);

        $this->bookRepository
            ->expects($this->once())
            ->method('countFiltered')
            ->with($this->isType('array'))
            ->willReturn(0);

        $response = $this->controller->index(new Request([
            'sort' => 'random',
        ]));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = json_decode($response->getContent() ?: '', true);
        $this->assertSame('random', $payload['filters']['sort']);
    }

    public function testUserCannotCreateBook(): void
    {
        $this->mockAuthenticatedUser(new \App\Entity\User());
        $this->authorizationChecker->method('isGranted')->willReturn(false);

        $request = new Request([], [], [], [], [], [], json_encode([
            'title' => 'Dune',
            'author' => 'Frank Herbert',
            'isbn' => '9780000000001',
            'totalCopies' => 4,
            'availableCopies' => 4,
            'categoryId' => 1,
        ]));

        $response = $this->controller->create($request);

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testLibrarianCanCreateBook(): void
    {
        $this->mockAuthenticatedUser((new \App\Entity\User())->setRoles(['ROLE_LIBRARIAN']));
        $this->authorizationChecker
            ->method('isGranted')
            ->willReturnCallback(fn (mixed $attribute): bool => $attribute === 'ROLE_LIBRARIAN');

        $category = (new Category())->setName('Science-fiction');
        $this->categoryRepository->method('find')->with(1)->willReturn($category);
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $request = new Request([], [], [], [], [], [], json_encode([
            'title' => 'Dune',
            'author' => 'Frank Herbert',
            'isbn' => '9780000000002',
            'totalCopies' => 4,
            'availableCopies' => 4,
            'categoryId' => 1,
        ]));

        $response = $this->controller->create($request);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    }

    public function testAdminCanUpdateBook(): void
    {
        $this->mockAuthenticatedUser((new \App\Entity\User())->setRoles(['ROLE_ADMIN']));
        $this->authorizationChecker
            ->method('isGranted')
            ->willReturnCallback(fn (mixed $attribute): bool => $attribute === 'ROLE_ADMIN');

        $category = (new Category())->setName('Science-fiction');
        $this->forceEntityId($category, 1);

        $book = (new Book())
            ->setTitle('Dune')
            ->setAuthor('Frank Herbert')
            ->setIsbn('9780000000003')
            ->setTotalCopies(4)
            ->setAvailableCopies(4)
            ->setCategory($category);
        $this->forceEntityId($book, 5);

        $this->categoryRepository->method('find')->with(1)->willReturn($category);
        $this->entityManager->expects($this->once())->method('flush');

        $request = new Request([], [], [], [], [], [], json_encode([
            'title' => 'Dune Messiah',
        ]));

        $response = $this->controller->update($book, $request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = json_decode($response->getContent() ?: '', true);
        $this->assertSame('Dune Messiah', $payload['title']);
    }

    public function testUserCannotDeleteBook(): void
    {
        $this->mockAuthenticatedUser(new \App\Entity\User());
        $this->authorizationChecker->method('isGranted')->willReturn(false);

        $book = new Book();

        $this->entityManager->expects($this->never())->method('remove');
        $this->entityManager->expects($this->never())->method('flush');

        $response = $this->controller->delete($book);

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    private function mockAuthenticatedUser(\App\Entity\User $user): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->tokenStorage
            ->method('getToken')
            ->willReturn($token);
    }

    private function forceEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionProperty($entity, 'id');
        $reflection->setValue($entity, $id);
    }
}
