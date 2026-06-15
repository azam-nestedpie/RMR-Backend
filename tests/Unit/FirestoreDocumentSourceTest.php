<?php

use App\Services\Migration\FirestoreDocumentSource;

test('it converts firestore rest documents to migration arrays', function () {
    $document = FirestoreDocumentSource::documentFromRest([
        'name' => 'projects/demo/databases/(default)/documents/users/firebase-uid-1',
        'fields' => [
            'email' => ['stringValue' => 'USER@example.com'],
            'isRepresentative' => ['booleanValue' => true],
            'noOfRatings' => ['integerValue' => '12'],
            'avgRating' => ['doubleValue' => 4.75],
            'createdAt' => ['timestampValue' => '2026-05-16T10:20:30.000000Z'],
            'myFavourites' => [
                'arrayValue' => [
                    'values' => [
                        ['stringValue' => 'fav-1'],
                        ['referenceValue' => 'projects/demo/databases/(default)/documents/users/fav-2'],
                    ],
                ],
            ],
            'address' => [
                'mapValue' => [
                    'fields' => [
                        'city' => ['stringValue' => 'Ashgabat'],
                        'country' => ['stringValue' => 'TM'],
                    ],
                ],
            ],
        ],
    ]);

    expect($document)->toMatchArray([
        '_id' => 'firebase-uid-1',
        'email' => 'USER@example.com',
        'isRepresentative' => true,
        'noOfRatings' => 12,
        'avgRating' => 4.75,
        'createdAt' => '2026-05-16T10:20:30.000000Z',
        'myFavourites' => ['fav-1', 'fav-2'],
        'address' => [
            'city' => 'Ashgabat',
            'country' => 'TM',
        ],
    ]);
});
