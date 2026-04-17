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

class BookControllerTest extends TestCase
{
    /** @var BookRepository&MockObject */
    private BookRepository $bookRepository;

    /** @var CategoryRepository&MockObject */
    private CategoryRepository $categoryRepository;

    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $entityManager;

    private BookController $controller;

    protected function setUp(): void
    {
        $this->bookRepository = $this->createMock(BookRepository::class);
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->controller = new BookController(
            $this->bookRepository,
            $this->categoryRepository,
            $this->entityManager,
        );
        $this->controller->setContainer(new class implements ContainerInterface {
            public function get(string $id)
            {
                throw new \RuntimeException(sprintf('Unexpected service lookup: %s', $id));
            }

            public function has(string $id): bool
            {
                return false;
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

    private function forceEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionProperty($entity, 'id');
        $reflection->setValue($entity, $id);
    }
}
