<?php

namespace App\Jobs;

use Kreait\Firebase\Factory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PlannifierTransfertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Exécuter le job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            Log::info("Début de l'exécution de PlannifierTransfertJob");
    
            // Initialiser Firestore
            $firebase = (new Factory)
                ->withServiceAccount(env('FIREBASE_CREDENTIALS'))
                ->createFirestore();
    
            $firestore = $firebase->database();
            $transactions = $firestore->collection('transactions');
            $plannifications = $firestore->collection('plannifications');
    
            // Obtenir les transactions "scheduled"
            Log::info("Récupération des transactions plannifiées...");
            $scheduledTransactions = $plannifications->where('status', '==', 'scheduled')->documents();
    
            foreach ($scheduledTransactions as $transaction) {
                $data = $transaction->data();
                $transactionId = $transaction->id();
                Log::info("Transaction plannifiée trouvée : {$transactionId}", $data);
    
                // Vérifier si la date est arrivée à terme
                $currentDate = Carbon::now();
                $nextDate = Carbon::parse($data['nextDate']);
                Log::info("Date actuelle : {$currentDate}, Date de la transaction : {$nextDate}");
    
                if ($currentDate->greaterThanOrEqualTo($nextDate)) {
                    Log::info("La date de la transaction est arrivée à terme.");
    
                    // Mettre à jour les soldes des utilisateurs
                    $users = $firestore->collection('users');
                    $sender = $users->document($data['senderId'])->snapshot();
                    $receiver = $users->document($data['receiverId'])->snapshot();
    
                    if ($sender->exists() && $receiver->exists()) {
                        Log::info("Utilisateurs trouvés : Sender - {$data['senderId']}, Receiver - {$data['receiverId']}");
    
                        $senderBalance = $sender->data()['solde'];
                        $receiverBalance = $receiver->data()['solde'];
                        Log::info("Solde initial : Sender - {$senderBalance}, Receiver - {$receiverBalance}");
    
                        // Vérifier si l'expéditeur a assez de solde
                        if ($senderBalance >= $data['montant']) {
                            Log::info("Solde suffisant pour effectuer la transaction. Débit et crédit en cours...");
    
                            // Débiter l'expéditeur et créditer le bénéficiaire
                            $users->document($data['senderId'])->update([
                                ['path' => 'solde', 'value' => $senderBalance - $data['montant']],
                            ]);
                            Log::info("Solde de l'expéditeur mis à jour.");
    
                            $users->document($data['receiverId'])->update([
                                ['path' => 'solde', 'value' => $receiverBalance + $data['montant']],
                            ]);
                            Log::info("Solde du bénéficiaire mis à jour.");
    
                            // Mettre à jour le statut de la transaction planifiée
                            $plannifications->document($transactionId)->update([
                                ['path' => 'status', 'value' => 'completed'],
                                ['path' => 'updatedAt', 'value' => now()],
                            ]);
                            Log::info("Statut de la transaction planifiée mis à jour : {$transactionId}");
    
                            // Enregistrer la transaction
                            $transactions->add([
                                'senderId' => $data['senderId'],
                                'receiverId' => $data['receiverId'],
                                'recipientName' => $data['recipientName'],
                                'montant' => $data['montant'],
                                'status' => 'completed',
                                'date' => now(),
                                'createdAt' => now(),
                                'updatedAt' => now(),
                            ]);
                            Log::info("Nouvelle transaction enregistrée dans la collection 'transactions'.");
    
                            // Vérifier l'existence de plannifications similaires en double
                            $existingPlannifications = $plannifications
                                ->where('senderId', '==', $data['senderId'])
                                ->where('receiverId', '==', $data['receiverId'])
                                ->where('montant', '==', $data['montant'])
                                ->where('status', '==', 'scheduled')
                                ->documents();
    
                            if (count(iterator_to_array($existingPlannifications)) == 0) {
                                $newNextDate = $this->calculateNextDate($nextDate, $data['frequency']);
                                $plannifications->add([
                                    'date' => now(),
                                    'senderId' => $data['senderId'],
                                    'receiverId' => $data['receiverId'],
                                    'recipientName' => $data['recipientName'],
                                    'montant' => $data['montant'],
                                    'status' => 'scheduled',
                                    'frequency' => $data['frequency'],
                                    'nextDate' => $newNextDate,
                                    'createdAt' => now(),
                                    'updatedAt' => now(),
                                ]);
                                Log::info("Prochaine transaction planifiée pour : {$newNextDate}");
                            }
                        } else {
                            Log::warning("Solde insuffisant pour l'utilisateur {$data['senderId']}.");
                        }
                    } else {
                        Log::error("Utilisateur(s) introuvable(s) pour la transaction {$transactionId}.");
                    }
                } else {
                    Log::info("La date de la transaction {$transactionId} n'est pas encore arrivée.");
                }
            }
    
            Log::info("Fin de l'exécution de PlannifierTransfertJob");
        } catch (\Kreait\Firebase\Exception\FirebaseException $e) {
            Log::error("Erreur Firebase : " . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'exécution de PlannifierTransfertJob : " . $e->getMessage());
        }
    }
    

    /**
     * Calculer la prochaine date en fonction de la fréquence.
     *
     * @param Carbon $currentDate
     * @param string $frequency
     * @return string
     */
    private function calculateNextDate(Carbon $currentDate, string $frequency): string
    {
        $validFrequencies = ['Journalier', 'Mensuel', 'Annuel','Hebdomadaire','Ponctuel'];
        
        if (!in_array($frequency, $validFrequencies)) {
            Log::warning("Fréquence invalide : {$frequency}. Utilisation de la fréquence par défaut.");
            $frequency = 'mensuel'; // Fréquence par défaut
        }

        switch ($frequency) {
            case 'Journalier':
                return $currentDate->addDay()->toDateTimeString();
            case 'Mensuel':
                return $currentDate->addMonth()->toDateTimeString();
            case 'Annuel':
                return $currentDate->addYear()->toDateTimeString();
        }
    }
}