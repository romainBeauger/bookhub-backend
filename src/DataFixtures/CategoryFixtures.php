<?php

namespace App\DataFixtures;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CategoryFixtures extends Fixture
{
    public const CATEGORY_ROMAN = 'category_roman';
    public const CATEGORY_MANGA = 'category_manga';
    public const CATEGORY_SCIFI = 'category_scifi';
    public const CATEGORY_DEV = 'category_dev';
    public const CATEGORY_FANTASTIQUE = 'category_fantastique';
    public const CATEGORY_POLICIER = 'category_policier';
    public const CATEGORY_HORREUR = 'category_horreur';
    public const CATEGORY_HISTORIQUE = 'category_historique';
    public const CATEGORY_BIOGRAPHIE = 'category_biographie';
    public const CATEGORY_ESSAI = 'category_essai';
    public const CATEGORY_POESIE = 'category_poesie';
    public const CATEGORY_THEATRE = 'category_theatre';
    public const CATEGORY_HUMOUR = 'category_humour';
    public const CATEGORY_AVENTURE = 'category_aventure';
    public const CATEGORY_CLASSIQUE = 'category_classique';
    public const CATEGORY_CONTEMPORAIN = 'category_contemporain';
    public const CATEGORY_JEUNESSE = 'category_jeunesse';
    public const CATEGORY_BANDE_DESSINEE = 'category_bande_dessinee';
    public const CATEGORY_GRAPHIC_NOVEL = 'category_graphic_novel';
    public const CATEGORY_AUTOBIOGRAPHIE = 'category_autobiographie';
    public const CATEGORY_PHILOSOPHIE = 'category_philosophie';
    public const CATEGORY_RELIGION = 'category_religion';
    public const CATEGORY_PSYCHOLOGIE = 'category_psychologie';
    public const CATEGORY_SOCIOLOGIE = 'category_sociologie';
    public const CATEGORY_POLITIQUE = 'category_politique';
    public const CATEGORY_ECONOMIE = 'category_economie';
    public const CATEGORY_SCIENCE = 'category_science';
    public const CATEGORY_TECHNOLOGIE = 'category_technologie';
    public const CATEGORY_CUISINE = 'category_cuisine';
    public const CATEGORY_VOYAGE = 'category_voyage';
    public const CATEGORY_ART = 'category_art';
    public const CATEGORY_MUSIQUE = 'category_musique';
    public const CATEGORY_CINEMA = 'category_cinema';
    public const CATEGORY_SPORT = 'category_sport';
    public const CATEGORY_SANTE = 'category_sante';
    public const CATEGORY_DEVELOPPEMENT_PERSONNEL = 'category_developpement_personnel';
    public const CATEGORY_AFFAIRES = 'category_affaires';
    public const CATEGORY_FINANCE = 'category_finance';

    public function load(ObjectManager $manager): void
    {
        $categories = [
            self::CATEGORY_ROMAN => 'Roman',
            self::CATEGORY_MANGA => 'Manga',
            self::CATEGORY_SCIFI => 'Science-fiction',
            self::CATEGORY_DEV => 'Développement web',
            self::CATEGORY_FANTASTIQUE => 'Fantastique',
            self::CATEGORY_POLICIER => 'Policier',
            self::CATEGORY_HORREUR => 'Horreur',
            self::CATEGORY_HISTORIQUE => 'Historique',
            self::CATEGORY_BIOGRAPHIE => 'Biographie',
            self::CATEGORY_ESSAI => 'Essai',
            self::CATEGORY_POESIE => 'Poésie',
            self::CATEGORY_THEATRE => 'Théâtre',
            self::CATEGORY_HUMOUR => 'Humour',
            self::CATEGORY_AVENTURE => 'Aventure',
            self::CATEGORY_CLASSIQUE => 'Classique',
            self::CATEGORY_CONTEMPORAIN => 'Contemporain',
            self::CATEGORY_JEUNESSE => 'Jeunesse',
            self::CATEGORY_BANDE_DESSINEE => 'Bande dessinée',
            self::CATEGORY_GRAPHIC_NOVEL => 'Graphic novel',
            self::CATEGORY_AUTOBIOGRAPHIE => 'Autobiographie',
            self::CATEGORY_PHILOSOPHIE => 'Philosophie',
            self::CATEGORY_RELIGION => 'Religion',
            self::CATEGORY_PSYCHOLOGIE => 'Psychologie',
            self::CATEGORY_SOCIOLOGIE => 'Sociologie',
            self::CATEGORY_POLITIQUE => 'Politique',
            self::CATEGORY_ECONOMIE => 'Économie',
            self::CATEGORY_SCIENCE => 'Science',
            self::CATEGORY_TECHNOLOGIE => 'Technologie',
            self::CATEGORY_CUISINE => 'Cuisine',
            self::CATEGORY_VOYAGE => 'Voyage',
            self::CATEGORY_ART => 'Art',
            self::CATEGORY_MUSIQUE => 'Musique',
            self::CATEGORY_CINEMA => 'Cinéma',
            self::CATEGORY_SPORT => 'Sport',
            self::CATEGORY_SANTE => 'Santé',
            self::CATEGORY_DEVELOPPEMENT_PERSONNEL => 'Développement personnel',
            self::CATEGORY_AFFAIRES => 'Affaires',
            self::CATEGORY_FINANCE => 'Finance',
        ];

        foreach ($categories as $reference => $name) {
            $category = new Category();
            $category->setName($name);

            $manager->persist($category);
            $this->addReference($reference, $category);
        }

        $manager->flush();
    }
}
