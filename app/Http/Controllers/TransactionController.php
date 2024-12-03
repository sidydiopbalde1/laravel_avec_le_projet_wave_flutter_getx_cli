<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FirebaseService;
use Carbon\Carbon;

class TransactionController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebase = $firebaseService;
    }

    public function createTransaction(Request $request)
    {
        $firestore = $this->firebase->getFirestore();

        // Validate input data
        $validatedData = $request->validate([
            'amount' => 'required|numeric|min:0',
            'sender' => 'required',
            'receiver' => 'required',
            'scheduled_at' => 'nullable|date|after:now'
        ]);

        // Transaction to be recorded
        $transaction = [
            'amount' => $validatedData['amount'],
            'sender' => $validatedData['sender'],
            'receiver' => $validatedData['receiver'],
            'scheduled_at' => Carbon::parse($validatedData['scheduled_at'] ?? now()->addMinutes(30)),
            'created_at' => now(),
            'status' => 'pending'
        ];

        // Add transaction to Firestore
        $firestore->database()->collection('transactions')->add($transaction);

        return response()->json([
            'message' => 'Transaction scheduled successfully',
            'transaction' => $transaction
        ], 201);
    }
}