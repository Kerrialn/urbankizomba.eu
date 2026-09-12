<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\City;
use App\Repository\CityRepository;
use App\Service\Slug\SlugGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Seeds the cities the submission form offers on day one.
 *
 * Idempotent: a city already present under the same name and country is left
 * alone, so this can run on every deploy. The list is the cities with a known
 * urban kiz scene plus the capitals, so most submitters find theirs in the
 * select and the "new city" fields stay the exception.
 */
#[AsCommand(
    name: 'app:cities:seed',
    description: 'Create the initial set of European cities.',
)]
final class SeedCitiesCommand extends Command
{
    /**
     * @var array<string, list<string>> country code => city names
     */
    private const array CITIES = [
        'AT' => ['Vienna'],
        'BE' => ['Brussels', 'Antwerp'],
        'BG' => ['Sofia'],
        'CH' => ['Zürich', 'Geneva', 'Lausanne'],
        'CZ' => ['Prague', 'Brno'],
        'DE' => ['Berlin', 'Hamburg', 'Munich', 'Cologne', 'Frankfurt', 'Düsseldorf', 'Stuttgart'],
        'DK' => ['Copenhagen'],
        'ES' => ['Madrid', 'Barcelona', 'Valencia', 'Seville', 'Málaga'],
        'FI' => ['Helsinki'],
        'FR' => ['Paris', 'Lyon', 'Marseille', 'Toulouse', 'Lille', 'Bordeaux', 'Nantes', 'Strasbourg'],
        'GB' => ['London', 'Manchester', 'Birmingham'],
        'GR' => ['Athens'],
        'HR' => ['Zagreb'],
        'HU' => ['Budapest'],
        'IE' => ['Dublin'],
        'IT' => ['Milan', 'Rome', 'Turin', 'Bologna', 'Naples'],
        'LU' => ['Luxembourg'],
        'NL' => ['Amsterdam', 'Rotterdam', 'Utrecht', 'The Hague'],
        'NO' => ['Oslo'],
        'PL' => ['Warsaw', 'Kraków', 'Wrocław', 'Poznań'],
        'PT' => ['Lisbon', 'Porto'],
        'RO' => ['Bucharest'],
        'RS' => ['Belgrade'],
        'SE' => ['Stockholm', 'Gothenburg', 'Malmö'],
        'SK' => ['Bratislava'],
    ];

    public function __construct(
        private readonly CityRepository $cityRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SlugGenerator $slugGenerator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $created = 0;

        foreach (self::CITIES as $country => $names) {
            foreach ($names as $name) {
                if ($this->cityRepository->findOneByNameAndCountry($name, $country) instanceof City) {
                    continue;
                }

                $this->entityManager->persist(new City($name, $country, $this->slugGenerator->forCity($name, $country)));
                // Flushed one at a time so the slug uniqueness check sees the
                // cities created earlier in this run.
                $this->entityManager->flush();
                ++$created;
            }
        }

        (new SymfonyStyle($input, $output))->success(sprintf('%d city/cities created.', $created));

        return Command::SUCCESS;
    }
}
