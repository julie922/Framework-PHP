<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création des tables initiales pour la marketplace (SQLite)';
    }

    public function up(Schema $schema): void
    {
        // Ordre respecté pour les contraintes FK : role et user avant les tables dépendantes
        $this->addSql('CREATE TABLE role (id VARCHAR(36) NOT NULL, name VARCHAR(50) NOT NULL, permissions CLOB NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_57698A6A5E237E06 ON role (name)');

        $this->addSql('CREATE TABLE "user" (id VARCHAR(36) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, refresh_token VARCHAR(512) DEFAULT NULL, refresh_token_expires_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');

        $this->addSql('CREATE TABLE user_roles (user_id VARCHAR(36) NOT NULL, role_id VARCHAR(36) NOT NULL, PRIMARY KEY (user_id, role_id), CONSTRAINT FK_54FCD59FA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54FCD59FD60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_54FCD59FA76ED395 ON user_roles (user_id)');
        $this->addSql('CREATE INDEX IDX_54FCD59FD60322AC ON user_roles (role_id)');

        $this->addSql('CREATE TABLE service (id VARCHAR(36) NOT NULL, title VARCHAR(255) NOT NULL, description CLOB NOT NULL, category VARCHAR(100) NOT NULL, price DOUBLE PRECISION NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, prestataire_id VARCHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_E19D9AD2BE3DB2B7 FOREIGN KEY (prestataire_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_E19D9AD2BE3DB2B7 ON service (prestataire_id)');

        $this->addSql('CREATE TABLE demande (id VARCHAR(36) NOT NULL, title VARCHAR(255) NOT NULL, description CLOB NOT NULL, category VARCHAR(100) NOT NULL, budget DOUBLE PRECISION NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, demandeur_id VARCHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_2694D7A595A6EE59 FOREIGN KEY (demandeur_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_2694D7A595A6EE59 ON demande (demandeur_id)');

        $this->addSql('CREATE TABLE proposition (id VARCHAR(36) NOT NULL, price DOUBLE PRECISION NOT NULL, message CLOB NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, demande_id VARCHAR(36) NOT NULL, prestataire_id VARCHAR(36) NOT NULL, service_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY (id), CONSTRAINT FK_C7CDC35380E95E18 FOREIGN KEY (demande_id) REFERENCES demande (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C7CDC353BE3DB2B7 FOREIGN KEY (prestataire_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C7CDC353ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C7CDC35380E95E18 ON proposition (demande_id)');
        $this->addSql('CREATE INDEX IDX_C7CDC353BE3DB2B7 ON proposition (prestataire_id)');
        $this->addSql('CREATE INDEX IDX_C7CDC353ED5CA9E6 ON proposition (service_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE proposition');
        $this->addSql('DROP TABLE demande');
        $this->addSql('DROP TABLE service');
        $this->addSql('DROP TABLE user_roles');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('DROP TABLE role');
    }
}
