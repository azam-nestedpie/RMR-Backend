<?php

namespace App\Services\Migration;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class FirestoreDocumentSource
{
    private const DatastoreScope = 'https://www.googleapis.com/auth/datastore';

    /**
     * @return array<int, string>
     */
    public function collectionIds(int $pageSize = 200): array
    {
        $collectionIds = [];
        $pageToken = null;
        $baseUrl = $this->firestoreBaseUrl();
        $accessToken = $this->accessToken();

        do {
            $body = ['pageSize' => $pageSize];

            if ($pageToken !== null) {
                $body['pageToken'] = $pageToken;
            }

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(30)
                ->retry(3, 300)
                ->post($baseUrl.':listCollectionIds', $body)
                ->throw();

            $payload = $response->json();
            $collectionIds = array_merge($collectionIds, $payload['collectionIds'] ?? []);
            $pageToken = $payload['nextPageToken'] ?? null;
        } while ($pageToken !== null);

        sort($collectionIds);

        return $collectionIds;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function documents(string $collection, int $pageSize = 200): array
    {
        $documents = [];
        $pageToken = null;
        $baseUrl = $this->firestoreBaseUrl();
        $accessToken = $this->accessToken();

        do {
            $query = ['pageSize' => $pageSize];

            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(30)
                ->retry(3, 300)
                ->get($baseUrl.'/'.$this->encodeDocumentPath($collection), $query)
                ->throw();

            $payload = $response->json();

            foreach ($payload['documents'] ?? [] as $document) {
                $documents[] = self::documentFromRest($document);
            }

            $pageToken = $payload['nextPageToken'] ?? null;
        } while ($pageToken !== null);

        return $documents;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public static function documentFromRest(array $document): array
    {
        $data = [
            '_id' => Str::afterLast((string) ($document['name'] ?? ''), '/'),
        ];

        foreach (($document['fields'] ?? []) as $field => $value) {
            $data[$field] = self::valueFromRest($value);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private static function valueFromRest(array $value): mixed
    {
        if (array_key_exists('nullValue', $value)) {
            return null;
        }

        if (array_key_exists('booleanValue', $value)) {
            return (bool) $value['booleanValue'];
        }

        if (array_key_exists('integerValue', $value)) {
            return (int) $value['integerValue'];
        }

        if (array_key_exists('doubleValue', $value)) {
            return (float) $value['doubleValue'];
        }

        if (array_key_exists('timestampValue', $value)) {
            return (string) $value['timestampValue'];
        }

        if (array_key_exists('stringValue', $value)) {
            return (string) $value['stringValue'];
        }

        if (array_key_exists('bytesValue', $value)) {
            return (string) $value['bytesValue'];
        }

        if (array_key_exists('referenceValue', $value)) {
            return Str::afterLast((string) $value['referenceValue'], '/');
        }

        if (array_key_exists('geoPointValue', $value)) {
            return [
                'latitude' => $value['geoPointValue']['latitude'] ?? null,
                'longitude' => $value['geoPointValue']['longitude'] ?? null,
            ];
        }

        if (array_key_exists('arrayValue', $value)) {
            return array_map(
                fn (array $item): mixed => self::valueFromRest($item),
                $value['arrayValue']['values'] ?? []
            );
        }

        if (array_key_exists('mapValue', $value)) {
            $fields = [];

            foreach (($value['mapValue']['fields'] ?? []) as $field => $fieldValue) {
                $fields[$field] = self::valueFromRest($fieldValue);
            }

            return $fields;
        }

        return null;
    }

    private function accessToken(): string
    {
        $token = (new ServiceAccountCredentials(self::DatastoreScope, $this->credentials()))
            ->fetchAuthToken();

        if (empty($token['access_token'])) {
            throw new RuntimeException('Unable to fetch a Google access token for Firestore.');
        }

        return $token['access_token'];
    }

    /**
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        $credentialsJson = config('firebase.credentials_json');

        if (is_string($credentialsJson) && $credentialsJson !== '') {
            $decoded = base64_decode($credentialsJson, true);
            $json = $decoded !== false && str_starts_with(trim($decoded), '{')
                ? $decoded
                : $credentialsJson;

            return $this->decodeCredentials($json);
        }

        $path = $this->credentialsPath();

        if (! is_file($path)) {
            throw new RuntimeException("Firebase service account file not found at [{$path}].");
        }

        return $this->decodeCredentials((string) file_get_contents($path));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeCredentials(string $json): array
    {
        $credentials = json_decode($json, true);

        if (! is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new RuntimeException('Firebase service account credentials are invalid.');
        }

        return $credentials;
    }

    private function credentialsPath(): string
    {
        $configured = (string) config('firebase.credentials');
        $candidates = [
            $configured,
            'storage/serviceAccountKeyFile.json',
            'storage/servicesAccountKeyFile.json',
            'storage/firebase/service-account.json',
        ];

        foreach ($candidates as $candidate) {
            $path = str_starts_with($candidate, DIRECTORY_SEPARATOR)
                ? $candidate
                : base_path($candidate);

            if (is_file($path)) {
                return $path;
            }
        }

        return str_starts_with($configured, DIRECTORY_SEPARATOR)
            ? $configured
            : base_path($configured);
    }

    private function firestoreBaseUrl(): string
    {
        $credentials = $this->credentials();
        $projectId = config('firebase.project_id') ?: ($credentials['project_id'] ?? null);

        if (! is_string($projectId) || $projectId === '') {
            throw new RuntimeException('Firebase project_id is missing from config and service account credentials.');
        }

        return 'https://firestore.googleapis.com/v1/projects/'.$projectId.'/databases/(default)/documents';
    }

    private function encodeDocumentPath(string $path): string
    {
        return collect(explode('/', $path))
            ->map(fn (string $segment): string => rawurlencode($segment))
            ->implode('/');
    }
}
