<?php

namespace App\DataFixtures;

use App\Entity\Book;
use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class BookFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $books = [
            [
                'title' => "L'Étranger",
                'author' => 'Albert Camus',
                'isbn' => '9782070360024',
                'description' => "Un roman bref et marquant autour de Meursault, de l'absurde et du jugement social.",
                'publishedAt' => '1942-01-01',
                'totalCopies' => 5,
                'availableCopies' => 5,
                'category' => CategoryFixtures::CATEGORY_ROMAN,
            ],
            [
                'title' => 'Le Petit Prince',
                'author' => 'Antoine de Saint-Exupéry',
                'isbn' => '9782070612758',
                'description' => "Un conte poétique sur l'enfance, l'amitié et le regard que l'on porte sur le monde.",
                'publishedAt' => '1943-01-01',
                'totalCopies' => 6,
                'availableCopies' => 6,
                'category' => CategoryFixtures::CATEGORY_JEUNESSE,
            ],
            [
                'title' => 'Germinal',
                'author' => 'Émile Zola',
                'isbn' => '9782253004226',
                'description' => "Une fresque sociale sur la condition ouvrière et la révolte des mineurs.",
                'publishedAt' => '1885-01-01',
                'totalCopies' => 4,
                'availableCopies' => 4,
                'category' => CategoryFixtures::CATEGORY_CLASSIQUE,
            ],
            [
                'title' => 'Les Misérables',
                'author' => 'Victor Hugo',
                'isbn' => '9782253096337',
                'description' => "Le parcours de Jean Valjean dans une France traversée par la misère, la justice et la rédemption.",
                'publishedAt' => '1862-01-01',
                'totalCopies' => 3,
                'availableCopies' => 3,
                'category' => CategoryFixtures::CATEGORY_CLASSIQUE,
            ],
            [
                'title' => 'La Peste',
                'author' => 'Albert Camus',
                'isbn' => '9782070360420',
                'description' => "Le récit d'une ville frappée par une épidémie et des choix humains qu'elle révèle.",
                'publishedAt' => '1947-01-01',
                'totalCopies' => 4,
                'availableCopies' => 4,
                'category' => CategoryFixtures::CATEGORY_ROMAN,
            ],
            [
                'title' => 'Madame Bovary',
                'author' => 'Gustave Flaubert',
                'isbn' => '9782253004868',
                'description' => "Le portrait d'Emma Bovary, prise entre ses rêves romanesques et la réalité provinciale.",
                'publishedAt' => '1857-01-01',
                'totalCopies' => 4,
                'availableCopies' => 4,
                'category' => CategoryFixtures::CATEGORY_CLASSIQUE,
            ],
            [
                'title' => 'Notre-Dame de Paris',
                'author' => 'Victor Hugo',
                'isbn' => '9782253009689',
                'description' => "Un grand roman historique autour de la cathédrale, de Quasimodo, d'Esmeralda et du Paris médiéval.",
                'publishedAt' => '1831-01-01',
                'totalCopies' => 3,
                'availableCopies' => 3,
                'category' => CategoryFixtures::CATEGORY_HISTORIQUE,
            ],
            [
                'title' => 'Bel-Ami',
                'author' => 'Guy de Maupassant',
                'isbn' => '9782253009009',
                'description' => "L'ascension de Georges Duroy dans le Paris mondain et journalistique du XIXe siècle.",
                'publishedAt' => '1885-01-01',
                'totalCopies' => 4,
                'availableCopies' => 4,
                'category' => CategoryFixtures::CATEGORY_CLASSIQUE,
            ],
            [
                'title' => 'Le Père Goriot',
                'author' => 'Honoré de Balzac',
                'isbn' => '9782253085799',
                'description' => "Un roman central de La Comédie humaine, entre ambition sociale, argent et amour paternel.",
                'publishedAt' => '1835-01-01',
                'totalCopies' => 4,
                'availableCopies' => 4,
                'category' => CategoryFixtures::CATEGORY_CLASSIQUE,
            ],
            [
                'title' => 'Vingt mille lieues sous les mers',
                'author' => 'Jules Verne',
                'isbn' => '9782253006329',
                'description' => "Une aventure sous-marine avec le professeur Aronnax et le capitaine Nemo à bord du Nautilus.",
                'publishedAt' => '1870-01-01',
                'totalCopies' => 4,
                'availableCopies' => 4,
                'category' => CategoryFixtures::CATEGORY_AVENTURE,
            ],
        ];

        foreach ($books as $bookData) {
            $book = new Book();
            $book->setTitle($bookData['title']);
            $book->setAuthor($bookData['author']);
            $book->setIsbn($bookData['isbn']);
            $book->setDescription($bookData['description']);
            $book->setPublishedAt(new \DateTimeImmutable($bookData['publishedAt']));
            $book->setTotalCopies($bookData['totalCopies']);
            $book->setAvailableCopies($bookData['availableCopies']);
            $book->setImage($this->getCoverUrl($bookData['isbn']));

            /** @var Category $category */
            $category = $this->getReference($bookData['category'], Category::class);
            $book->setCategory($category);

            $manager->persist($book);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CategoryFixtures::class,
        ];
    }

    private function getCoverUrl(string $isbn): string
    {
        return 'https://covers.openlibrary.org/b/isbn/' . $isbn . '-L.jpg?default=false';
    }
}
