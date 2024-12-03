<?php

namespace App\Services;

use Google\Cloud\Firestore\FirestoreClient;

class FirebaseService implements FirebaseServiceInterface
{
    private $firebase;
    private $auth;
    private $firestore;

    public function __construct()
    {
        $factory = (new \Kreait\Firebase\Factory)
            ->withServiceAccount(env('FIREBASE_CREDENTIALS'));

        $this->firebase = $factory;
        $this->auth = $factory->createAuth();
        $this->firestore = $factory->createFirestore(); // Utilisation correcte pour Firestore
    }

    public function getAuth()
    {
        return $this->auth;
    }

    public function getFirestore()
    {
        return $this->firestore; // Retourne Firestore
    }
}

