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
        $this->addSql('CREATE TABLE role (
            id          VARCHAR(36)  NOT NULL,
            name        VARCHAR(50)  NOT NULL,
            permissions CLOB         NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ROLE_NAME ON role (name)');

        $this->addSql('CREATE TABLE user (
            id                       VARCHAR(36)  NOT NULL,
            email                    VARCHAR(180) NOT NULL,
            password                 VARCHAR(255) NOT NULL,
            first_name               VARCHAR(100) NOT NULL,
            last_name                VARCHAR(100) NOT NULL,
            refresh_token            VARCHAR(512) DEFAULT NULL,
            refresh_token_expires_at DATETIME     DEFAULT NULL,
            created_at               DATETIME     NOT NULL,
            updated_at               DATETIME     NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_EMAIL ON user (email)');

        $this->addSql('CREATE TABLE user_roles (
            user_id VARCHAR(36) NOT NULL,
            role_id VARCHAR(36) NOT NULL,
            PRIMARY KEY (user_id, role_id),
            CONSTRAINT FK_USER_ROLES_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE,
            CONSTRAINT FK_USER_ROLES_ROLE FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE
        )');

        $this->addSql('CREATE TABLE service (
            id             VARCHAR(36)  NOT NULL,
            prestataire_id VARCHAR(36)  NOT NULL,
            title          VARCHAR(255) NOT NULL,
            description    CLOB         NOT NULL,
            category       VARCHAR(100) NOT NULL,
            price          REAL         NOT NULL,
            status         VARCHAR(20)  NOT NULL,
            created_at     DATETIME     NOT NULL,
            updated_at     DATETIME     NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT FK_SERVICE_PRESTATAIRE FOREIGN KEY (prestataire_id) REFERENCES user (id)
        )');

        $this->addSql('CREATE TABLE demande (
            id           VARCHAR(36)  NOT NULL,
            demandeur_id VARCHAR(36)  NOT NULL,
            title        VARCHAR(255) NOT NULL,
            description  CLOB         NOT NULL,
            category     VARCHAR(100) NOT NULL,
            budget       REAL         NOT NULL,
            status       VARCHAR(20)  NOT NULL,
            created_at   DATETIME     NOT NULL,
            updated_at   DATETIME     NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT FK_DEMANDE_DEMANDEUR FOREIGN KEY (demandeur_id) REFERENCES user (id)
        )');

        $this->addSql('CREATE TABLE proposition (
            id             VARCHAR(36) NOT NULL,
            demande_id     VARCHAR(36) NOT NULL,
            prestataire_id VARCHAR(36) NOT NULL,
            service_id     VARCHAR(36) DEFAULT NULL,
            price          REAL        NOT NULL,
            message        CLOB        NOT NULL,
            status         VARCHAR(20) NOT NULL,
            created_at     DATETIME    NOT NULL,
            updated_at     DATETIME    NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT FK_PROP_DEMANDE     FOREIGN KEY (demande_id)     REFERENCES demande (id) ON DELETE CASCADE,
            CONSTRAINT FK_PROP_PRESTATAIRE FOREIGN KEY (prestataire_id) REFERENCES user (id),
            CONSTRAINT FK_PROP_SERVICE     FOREIGN KEY (service_id)     REFERENCES service (id) ON DELETE SET NULL
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE proposition');
        $this->addSql('DROP TABLE demande');
        $this->addSql('DROP TABLE service');
        $this->addSql('DROP TABLE user_roles');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE role');
    }
}
