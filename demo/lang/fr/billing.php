<?php

return [
    'plan' => [
        'title' => 'Votre offre',
        'name' => 'Offre :plan',
        'price' => ':price par mois, facturé :interval',
        'usage' => ':used factures sur :limit ce mois-ci',
        'renews' => 'Se renouvelle le :date',
    ],
    'payment_method' => [
        'title' => 'Moyen de paiement',
        'card' => ':brand se terminant par :last4, expire le :expiry',
    ],
    'history' => [
        'title' => 'Historique de facturation',
        'invoice' => 'Facture',
        'date' => 'Date',
        'status' => 'Statut',
        'amount' => 'Montant',
        'empty' => 'Aucune facture pour l’instant. La première arrive à la fin du mois.',
    ],
    'status' => [
        'paid' => 'Payée',
        'open' => 'Ouverte',
        'overdue' => 'En retard',
        'void' => 'Annulée',
    ],
    'seats' => '{0} Aucun siège|{1} :count siège|[2,*] :count sièges',
    'trial_ends' => 'Votre essai se termine dans :days jours. <a href=":url">Choisissez une offre</a> pour garder vos factures.',
    'cancel' => 'Résilier l’abonnement',
];
