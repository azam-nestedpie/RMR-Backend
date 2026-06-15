<?php

namespace App\Console\Commands;

use App\Services\Migration\ConnectionsMigrationService;
use App\Services\Migration\ExternalUsersMigrationService;
use App\Services\Migration\FirestoreDocumentSource;
use App\Services\Migration\NotificationsMigrationService;
use App\Services\Migration\RatingsMigrationService;
use App\Services\Migration\RequestsMigrationService;
use App\Services\Migration\UsersMigrationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:migrate-firestore-to-mysql
    {--collections= : Comma-separated Firestore collections to migrate}
    {--page-size=200 : Firestore documents fetched per request}
    {--discover : List top-level Firestore collection IDs and exit}
    {--dry-run : Fetch and count documents without writing to MySQL}')]
#[Description('Migrate configured Firestore collections into MySQL.')]
class MigrateFirestoreToMysql extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(
        FirestoreDocumentSource $source,
        UsersMigrationService $users,
        ExternalUsersMigrationService $externalUsers,
        RequestsMigrationService $requests,
        ConnectionsMigrationService $connections,
        RatingsMigrationService $ratings,
        NotificationsMigrationService $notifications,
    ): int {
        $services = [
            'users' => $users,
            'external_users' => $externalUsers,
            'requests' => $requests,
            'connections' => $connections,
            'ratings' => $ratings,
            'notifications' => $notifications,
        ];

        $collections = $this->collections();
        $sourceCollectionMap = $this->sourceCollectionMap();
        $pageSize = max(1, (int) $this->option('page-size'));
        $dryRun = (bool) $this->option('dry-run');
        $hasFailures = false;

        if ((bool) $this->option('discover')) {
            $this->info('Top-level Firestore collections:');

            foreach ($source->collectionIds($pageSize) as $collectionId) {
                $this->line('- '.$collectionId);
            }

            return self::SUCCESS;
        }

        foreach ($collections as $collection) {
            if (! array_key_exists($collection, $sourceCollectionMap)) {
                $this->error("Unsupported collection [{$collection}].");

                return self::FAILURE;
            }
        }

        if ($dryRun) {
            $this->warn('Dry run enabled. No MySQL rows will be written.');
        }

        foreach ($collections as $collection) {
            $target = $sourceCollectionMap[$collection];

            try {
                $this->info("Fetching Firestore collection [{$collection}]...");
                $documents = $source->documents($collection, $pageSize);
                $this->line('Fetched '.count($documents).' documents.');

                if ($dryRun) {
                    continue;
                }

                $result = $services[$target]->migrate($documents);
                $this->line("Migrated [{$collection} -> {$target}]: success={$result['success']} failed={$result['failed']} skipped={$result['skipped']}");

                if ($result['failed'] > 0) {
                    $hasFailures = true;
                }
            } catch (Throwable $throwable) {
                $hasFailures = true;
                $this->error("Failed migrating [{$collection}]: ".$throwable->getMessage());

                if (config('migration.stop_on_error')) {
                    return self::FAILURE;
                }
            }
        }

        return $hasFailures ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function collections(): array
    {
        $option = $this->option('collections');
        $configuredSourceCollections = $this->configuredSourceCollections();

        if (is_string($option) && trim($option) !== '') {
            return collect(explode(',', $option))
                ->map(fn (string $collection): string => trim($collection))
                ->filter()
                ->map(fn (string $collection): string => $configuredSourceCollections[$collection] ?? $collection)
                ->values()
                ->all();
        }

        return collect(config('migration.collections', []))
            ->map(fn (string $collection): string => $configuredSourceCollections[$collection] ?? $collection)
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function sourceCollectionMap(): array
    {
        $map = [
            'new_ratings' => 'ratings',
            'new ratings' => 'ratings',
            'newRatings' => 'ratings',
            'New Ratings' => 'ratings',
        ];

        foreach ($this->configuredSourceCollections() as $target => $source) {
            $map[$target] = $target;
            $map[$source] = $target;
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private function configuredSourceCollections(): array
    {
        return collect(config('migration.firestore_collections', []))
            ->filter(fn (mixed $collection): bool => is_string($collection) && trim($collection) !== '')
            ->map(fn (string $collection): string => trim($collection))
            ->all();
    }
}
