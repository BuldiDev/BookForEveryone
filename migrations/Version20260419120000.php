<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migrazione: sistema prestito da meccanico a stile Vinted con offerte e controproposte.
 *
 * Cambiamenti:
 *   - Rinomina proposed_points → offered_points
 *   - Aggiunge last_offer_by (chi ha fatto l'ultima offerta)
 *   - Rinomina requester_accepted_end → requester_confirmed
 *   - Rinomina owner_accepted_end → owner_confirmed
 *   - Rimuove requester_accepted_start, owner_accepted_start
 *   - Aggiorna i valori di status: rejected → declined, proposed/awaiting_start/in_progress/awaiting_end → mapping appropriato
 */
final class Version20260419120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Refactor loan_request: Vinted-style offer/counter-offer system';
    }

    public function up(Schema $schema): void
    {
        // Rinomina colonne
        $this->addSql('ALTER TABLE loan_request RENAME COLUMN proposed_points TO offered_points');
        $this->addSql('ALTER TABLE loan_request RENAME COLUMN requester_accepted_end TO requester_confirmed');
        $this->addSql('ALTER TABLE loan_request RENAME COLUMN owner_accepted_end TO owner_confirmed');

        // Aggiungi colonna last_offer_by
        $this->addSql('ALTER TABLE loan_request ADD last_offer_by VARCHAR(20) DEFAULT NULL');

        // Rimuovi colonne non più necessarie
        $this->addSql('ALTER TABLE loan_request DROP COLUMN requester_accepted_start');
        $this->addSql('ALTER TABLE loan_request DROP COLUMN owner_accepted_start');

        // Migra i valori di status
        $this->addSql("UPDATE loan_request SET status = 'declined' WHERE status = 'rejected'");
        $this->addSql("UPDATE loan_request SET status = 'pending', last_offer_by = 'owner' WHERE status = 'proposed'");
        $this->addSql("UPDATE loan_request SET status = 'accepted' WHERE status IN ('awaiting_start', 'in_progress', 'awaiting_end')");

        // Per le richieste pending originali, il requester ha fatto l'offerta
        $this->addSql("UPDATE loan_request SET last_offer_by = 'requester' WHERE status = 'pending' AND last_offer_by IS NULL");
    }

    public function down(Schema $schema): void
    {
        // Ripristina colonne
        $this->addSql('ALTER TABLE loan_request RENAME COLUMN offered_points TO proposed_points');
        $this->addSql('ALTER TABLE loan_request RENAME COLUMN requester_confirmed TO requester_accepted_end');
        $this->addSql('ALTER TABLE loan_request RENAME COLUMN owner_confirmed TO owner_accepted_end');

        $this->addSql('ALTER TABLE loan_request ADD requester_accepted_start BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE loan_request ADD owner_accepted_start BOOLEAN NOT NULL DEFAULT false');

        $this->addSql('ALTER TABLE loan_request DROP COLUMN last_offer_by');

        // Ripristina stati
        $this->addSql("UPDATE loan_request SET status = 'rejected' WHERE status IN ('declined', 'cancelled')");
    }
}
