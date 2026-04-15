<?php

namespace App\DataFixtures;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CategoryFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $categories = [
            "Roman",
            "Science-fiction",
            "Fantastique",
            "Policier",
            "Horreur",
            "Historique",
            "Biographie",
            "Essai",
            "Poésie",
            "Théâtre",
            "Humour",
            "Aventure",
            "Classique",
            "Contemporain",
            "Jeunesse",
            "Manga",
            "Bande dessinée",
            "Graphic novel",
            "Autobiographie",
            "Philosophie",
            "Religion",
            "Psychologie",
            "Sociologie",
            "Politique",
            "Économie",
            "Science",
            "Technologie",
            "Cuisine",
            "Voyage",
            "Art",
            "Musique",
            "Cinéma",
            "Sport",
            "Santé",
            "Développement personnel",
            "Affaires",
            "Finance"
        ];
        
        foreach ($categories as $categoryName) {
            $category = new Category();
            $category->setName($categoryName);
            $manager->persist($category);
        }

        $manager->flush();
    }
}
