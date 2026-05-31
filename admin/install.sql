-- ============================================================
-- install.sql — Portal Ivan Cisneros
-- Ejecutar una sola vez en phpMyAdmin o terminal MySQL
-- ============================================================

CREATE DATABASE IF NOT EXISTS ivancisneros
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE ivancisneros;

-- Usuarios del panel admin
CREATE TABLE IF NOT EXISTS usuarios (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  nombre    VARCHAR(100)  NOT NULL,
  email     VARCHAR(150)  NOT NULL UNIQUE,
  password  VARCHAR(255)  NOT NULL,
  rol       ENUM('admin','editor') NOT NULL DEFAULT 'editor',
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO usuarios (nombre, email, password, rol)
VALUES ('Super Admin', 'superadmin@gmail.com',
        '$2y$10$RiX7Owy6hHUfg1mKiLBFeuFQR1jtbE0KHdzrQw03INTtt9DDgJ6yC', 'admin');

-- Noticias
CREATE TABLE IF NOT EXISTS noticias (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  titulo    VARCHAR(300)  NOT NULL,
  contenido TEXT,
  imagen    VARCHAR(300),
  categoria VARCHAR(100)  DEFAULT 'General',
  estado    ENUM('publicado','borrador') NOT NULL DEFAULT 'borrador',
  fecha     DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Simpatizantes (formulario "Únete")
CREATE TABLE IF NOT EXISTS simpatizantes (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  nombre           VARCHAR(150) NOT NULL,
  dni              CHAR(8)      NOT NULL UNIQUE,
  telefono         VARCHAR(20),
  distrito         VARCHAR(100),
  fecha_registro   DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Contactos / mensajes
CREATE TABLE IF NOT EXISTS contactos (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  nombre  VARCHAR(150),
  email   VARCHAR(150),
  mensaje TEXT,
  leido   TINYINT(1) DEFAULT 0,
  fecha   DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Configuración dinámica del home
CREATE TABLE IF NOT EXISTS configuracion (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  clave VARCHAR(100) NOT NULL UNIQUE,
  valor TEXT
) ENGINE=InnoDB;

INSERT IGNORE INTO configuracion (clave, valor) VALUES
  ('hero_titulo',    'ING. JOYER BASTIDAS CASAS'),
  ('hero_subtitulo', 'Candidato a la Alcaldía Provincial de Satipo'),
  ('hero_eslogan',   'Ha llegado el momento de transformar Satipo'),
  ('quien_es_texto', 'Texto biográfico del candidato...'),
  ('partido_nombre', 'ALIANZA PARA EL PROGRESO');

-- Para el modulo Partido Politico / Militantes ejecutar tambien:
-- admin/migration_militantes.sql
