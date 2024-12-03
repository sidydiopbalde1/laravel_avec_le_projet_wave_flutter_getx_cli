<?php
namespace App\Services;

interface FirebaseServiceInterface {
    public function getAuth();
    public function getFirestore();
} 